<?php

class SecurityGuard
{

    private const RATE_DIR = 'data/security/rate/';

    private static function rateDir(): string
    {
        $dir = ROOT_PATH . self::RATE_DIR;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    private static array $cfg = [];

    private static ?string $ip = null;

    public static function handle(): void
    {
        try {

            if (self::cfg('enabled', '0') !== '1') return;

            if (self::isWhitelisted()) return;

            $ip   = self::ip();
            $ua   = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            $path = self::path();

            if (self::cfg('check_blacklist', '1') === '1' && self::ipBlocked($ip)) {
                self::block('blacklist_ip', Language::get('guard_reason_blacklist_ip', 'IP 命中黑名单'), $ip, $ua, $path);
            }

            if (self::cfg('check_blacklist', '1') === '1' && self::uaBlocked($ua)) {
                self::block('blacklist_ua', Language::get('guard_reason_blacklist_ua', 'UA 命中黑名单关键词'), $ip, $ua, $path);
            }

            if (self::cfg('block_bad_ua', '1') === '1' && self::isBadUa($ua)) {
                self::block('bad_ua', Language::get('guard_reason_bad_ua', '识别为扫描器或非浏览器客户端'), $ip, $ua, $path);
            }

            if (self::cfg('rate_limit', '1') === '1' && self::rateLimited($ip)) {
                self::block('rate_limit', Language::get('guard_reason_rate_limit', '请求频率超出限制'), $ip, $ua, $path);
            }

            if (self::cfg('wechat_block', '0') === '1' && self::inWechat($ua)) {
                self::block('wechat', Language::get('guard_reason_wechat', '内置浏览器打开，请用外部浏览器访问'), $ip, $ua, $path);
            }
        } catch (\Throwable $e) {

        }
    }

    private static function cfg(string $key, string $default): string
    {
        if (array_key_exists($key, self::$cfg)) return self::$cfg[$key];
        try {
            $val = (string)SettingModel::get('sg_' . $key, $default);
        } catch (\Throwable $e) {
            $val = $default;
        }
        return self::$cfg[$key] = $val;
    }

    private static function ip(): string
    {
        if (self::$ip !== null) return self::$ip;
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        self::$ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
        return self::$ip;
    }

    private static function path(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        return parse_url($uri, PHP_URL_PATH) ?: '/';
    }

    private static function isAjax(): bool
    {
        $x = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return strtolower($x) === 'xmlhttprequest';
    }

    private static function isWhitelisted(): bool
    {
        if (defined('ADMIN_BOOT')) return true;

        $path = strtolower(trim(self::path(), '/'));
        $adminDir = strtolower(admin_dir());
        $whitelist = [
            $adminDir,
            'install',
            'pay/notify',
            'pay/return',
            'api/captcha',
        ];
        foreach ($whitelist as $prefix) {
            if ($prefix !== '' && strpos($path, $prefix) === 0) return true;
        }
        return false;
    }

    private static function ipBlocked(string $ip): bool
    {
        $list = self::blacklistLines('blacklist_ip');
        if ($list === []) return false;
        foreach ($list as $entry) {
            if ($entry === $ip) return true;

            if (str_ends_with($entry, '.') && strpos($ip, $entry) === 0) return true;
        }
        return false;
    }

    private static function uaBlocked(string $ua): bool
    {
        $list = self::blacklistLines('blacklist_ua');
        if ($list === [] || $ua === '') return false;
        $uaLower = mb_strtolower($ua);
        foreach ($list as $kw) {
            if (strpos($uaLower, $kw) !== false) return true;
        }
        return false;
    }

    private static function blacklistLines(string $key): array
    {
        $raw = self::cfg($key, '');
        if (trim($raw) === '') return [];
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '#')) $out[] = mb_strtolower($line);
        }
        return $out;
    }

    private static function isBadUa(string $ua): bool
    {
        if ($ua === '') return false;

        $patterns = [
            'sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab', 'acunetix', 'dirbuster',
            'python-requests', 'python-urllib', 'python-httpx', 'curl/', 'wget',
            'scrapy', 'libwww-perl', 'go-http-client', 'java/', 'apache-httpclient',
            'okhttp', 'ahrefsbot', 'semrushbot', 'mj12bot', 'gptbot',
            'claude-web', 'dotbot', 'facebookexternalhit',
        ];
        $uaLower = mb_strtolower($ua);
        foreach ($patterns as $p) {
            if (strpos($uaLower, $p) !== false) return true;
        }
        return false;
    }

    private static function inWechat(string $ua): bool
    {
        if ($ua === '') return false;
        return (bool)preg_match('/MicroMessenger|\bQQ\//i', $ua);
    }

    private static function rateLimited(string $ip): bool
    {
        $window = max(10, (int)self::cfg('rate_window', '60'));
        $max    = max(1, (int)self::cfg('rate_max', '60'));
        $block  = max(0, (int)self::cfg('rate_block', '300'));

        $dir = self::rateDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
        $file = $dir . md5($ip) . '.json.php';

        $data = ['blocked_until' => 0, 'hits' => []];
        if (is_file($file)) {
            $raw = (string)@file_get_contents($file);
            $guard = "<?php exit; ?>\n";
            if (strncmp($raw, $guard, strlen($guard)) === 0) $raw = substr($raw, strlen($guard));
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $data = $decoded + $data;
        }

        $now = time();

        if (!empty($data['blocked_until']) && $now < (int)$data['blocked_until']) {
            return true;
        }

        $cutoff = $now - $window;
        $hits = array_values(array_filter((array)($data['hits'] ?? []), fn($t) => (int)$t >= $cutoff));

        if (count($hits) >= $max) {
            $data['blocked_until'] = $block > 0 ? $now + $block : $now + $window;
            $data['hits'] = $hits;
            self::writeRateFile($file, $data);
            return true;
        }

        $hits[] = $now;
        $data['blocked_until'] = 0;
        $data['hits'] = $hits;
        self::writeRateFile($file, $data);
        return false;
    }

    private static function writeRateFile(string $file, array $data): void
    {
        @file_put_contents($file, "<?php exit; ?>\n" . json_encode($data), LOCK_EX);
    }

    private static function log(string $type, string $reason, string $ip, string $ua, string $path): void
    {
        if (self::cfg('log_enabled', '1') !== '1') return;
        try {
            SecurityLogModel::record([
                'ip'       => $ip,
                'ua'       => $ua,
                'method'   => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
                'url'      => $path,
                'type'     => $type,
                'reason'   => $reason,
                'user_id'  => (int)($_SESSION['user_id'] ?? 0),
            ]);
            self::lazyPurge();
        } catch (\Throwable $e) {
        }
    }

    private static function lazyPurge(): void
    {
        $days = (int)self::cfg('log_days', '30');
        if ($days <= 0) return;
        if (random_int(1, 100) !== 1) return;
        try {
            SecurityLogModel::purgeOlderThan($days);
        } catch (\Throwable $e) {
        }
    }

    private static function block(string $type, string $reason, string $ip, string $ua, string $path): void
    {
        self::log($type, $reason, $ip, $ua, $path);

        while (ob_get_level() > 0) { ob_end_clean(); }

        $code = $type === 'rate_limit' ? 429 : 403;

        if (self::isAjax()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => $reason], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($type === 'wechat') {
            self::renderWechatPage($reason);
            exit;
        }

        self::renderBlockPage($reason, $code);
        exit;
    }

    private static function renderBlockPage(string $reason, int $code = 403): void
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        $msg = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        $pageTitle = Language::get('block_page_title', '访问已被拦截');
        $heading = Language::get('block_page_heading', '访问已被安全策略拦截');
        $tail = Language::get('block_page_tail', '如为正常访问，请稍后重试或联系网站管理员。');
        $backPrev = Language::get('back_to_previous', '返回上一页');
        $goHome = Language::get('go_home', '回到首页');
        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$pageTitle}</title>
<link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
<style>
:root{--bg:#f5f7fb;--card:#fff;--text:#1e293b;--sub:#64748b;--border:#e2e8f0;--primary:#2563eb;--danger:#ef4444;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:"PingFang SC","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;max-width:480px;width:100%;padding:36px 28px;text-align:center;box-shadow:0 2px 20px rgba(0,0,0,.06);}
.icon{width:64px;height:64px;border-radius:50%;background:#fee2e2;color:var(--danger);display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 18px;}
h2{font-size:18px;margin-bottom:10px;}
p{color:var(--sub);font-size:14px;line-height:1.7;margin-bottom:24px;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 22px;border-radius:40px;text-decoration:none;font-size:14px;}
.btn-primary{background:var(--primary);color:#fff;}
.btn-outline{border:1px solid var(--border);color:var(--sub);}
</style>
</head>
<body>
<div class="card">
  <div class="icon"><i class="fas fa-shield-halved"></i></div>
  <h2>{$heading}</h2>
  <p>{$msg}<br>{$tail}</p>
  <a href="javascript:history.go(-1)" class="btn btn-outline">{$backPrev}</a>
  <a href="/" class="btn btn-primary">{$goHome}</a>
</div>
</body>
</html>
HTML;
    }

    private static function renderWechatPage(string $reason): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        $msg = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        $pageTitle = Language::get('wechat_open_browser_title', '请在浏览器中打开');
        $heading = Language::get('wechat_open_external_heading', '请在外部浏览器中打开');
        $tip = Language::get('wechat_open_browser_tip', '点击右上角 ··· 菜单，选择「在浏览器打开」或「复制链接」后，使用系统浏览器访问本站，即可正常阅读与操作。');
        $goHome = Language::get('go_home', '回到首页');
        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$pageTitle}</title>
<link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
<style>
:root{--bg:#f5f7fb;--card:#fff;--text:#1e293b;--sub:#64748b;--border:#e2e8f0;--primary:#2563eb;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:"PingFang SC","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;max-width:480px;width:100%;padding:36px 28px;text-align:center;box-shadow:0 2px 20px rgba(0,0,0,.06);}
.icon{width:64px;height:64px;border-radius:50%;background:#eff6ff;color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 18px;}
h2{font-size:18px;margin-bottom:10px;}
p{color:var(--sub);font-size:14px;line-height:1.7;margin-bottom:24px;}
.tip{display:flex;gap:10px;align-items:flex-start;text-align:left;background:#f8fafc;border:1px dashed var(--border);border-radius:10px;padding:14px 16px;font-size:13px;color:var(--sub);line-height:1.8;margin-bottom:24px;}
</style>
</head>
<body>
<div class="card">
  <div class="icon"><i class="fas fa-arrow-up-right-from-square"></i></div>
  <h2>{$heading}</h2>
  <p>{$msg}</p>
  <div class="tip">
    <i class="fas fa-circle-info" style="margin-top:3px;"></i>
    <span>{$tip}</span>
  </div>
  <a href="/" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;padding:9px 22px;border-radius:40px;background:var(--primary);color:#fff;text-decoration:none;font-size:14px;">{$goHome}</a>
</div>
</body>
</html>
HTML;
    }
}
