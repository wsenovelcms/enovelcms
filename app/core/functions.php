<?php

if (!function_exists('tb')) {
    function tb(string $table): string
    {
        return '`' . DB_PREFIX . $table . '`';
    }
}

if (!function_exists('tb_raw')) {
    function tb_raw(string $table): string
    {
        return DB_PREFIX . $table;
    }
}

if (!function_exists('filter_value')) {

    function filter_value($val, string $type = 'str')
    {
        switch ($type) {
            case 'int':
                return (int)$val;
            case 'float':
                return (float)$val;
            case 'bool':
                return (bool)$val;
            case 'arr':
                if (!is_array($val)) return [];
                return array_map(fn($v) => is_array($v) ? filter_value($v, 'arr') : filter_value($v, 'str'), $val);
            case 'raw':

                return $val;
            case 'text':

                if (is_array($val)) return '';
                return trim((string)$val);
            case 'str':

                if (is_array($val)) return '';
                return trim(strip_tags((string)$val));
            case 'text':

                if (is_array($val)) return '';
                return trim((string)$val);
            case 'code':

                if (is_array($val)) return '';
                return (string)$val;
        }
    }
}

if (!function_exists('inp')) {

    function inp(string $key, $default = null, string $type = 'str')
    {
        $val = $_POST[$key] ?? $_GET[$key] ?? $default;
        return filter_value($val, $type);
    }
}

if (!function_exists('g')) {

    function g(string $key, $default = null, string $type = 'str')
    {
        $val = $_GET[$key] ?? $default;
        return filter_value($val, $type);
    }
}

if (!function_exists('p')) {

    function p(string $key, $default = null, string $type = 'str')
    {
        $val = $_POST[$key] ?? $default;
        return filter_value($val, $type);
    }
}

if (!function_exists('e')) {

    function e($str): string
    {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('h')) {

    function h($str): string
    {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('device_type')) {
    function device_type(?string $ua = null): string
    {
        return is_mobile($ua) ? 'mobile' : 'pc';
    }
}

if (!function_exists('is_mobile')) {
    function is_mobile(?string $ua = null): bool
    {
        $ua = $ua === null ? (string)($_SERVER['HTTP_USER_AGENT'] ?? '') : $ua;
        if ($ua === '') return false;
        if (preg_match('/(iPad|Tablet|PlayBook|Silk|Macintosh|Windows NT|Windows OS)/i', $ua)) {
            if (!preg_match('/iPad/i', $ua)) return false;
        }
        return (bool)preg_match('/(Android|iPhone|iPod|Symbian|Windows Phone|Mobile|HarmonyOS|MiuiBrowser|MicroMessenger.*(iPhone|Android))/i', $ua);
    }
}

if (!function_exists('is_pc')) {
    function is_pc(?string $ua = null): bool
    {
        return !is_mobile($ua);
    }
}

if (!function_exists('t_admin')) {

    function t_admin(string $key, string $default = '', ...$args): string
    {
        if (!class_exists('AdminLanguage')) return $default !== '' ? $default : $key;
        return AdminLanguage::get($key, $default, ...$args);
    }
}

if (!function_exists('admin_dir')) {

    function admin_dir(bool $reset = false): string
    {
        static $cached = null;
        if ($reset) $cached = null;
        if ($cached !== null) return $cached;

        $custom = '';
        try {
            if (class_exists('SettingModel')) {
                $custom = (string)SettingModel::get('admin_dir', '');
                if ($custom === '') {

                    $legacy = (string)SettingModel::get('admin_entry', '');
                    if ($legacy !== '') $custom = $legacy;
                }
            }
        } catch (\Throwable $e) {
            $custom = '';
        }

        $reserved = ['admin', 'install', 'api', 'pay', 'user', 'plugin', 'lang',
            'search', 'library', 'rank', 'novel', 'read', 'author', 'authors',
            'assets', 'templates', 'data', 'config', 'app'];

        if (preg_match('/^[A-Za-z0-9_-]{3,32}$/', $custom) && !in_array(strtolower($custom), $reserved, true)) {
            $cached = $custom;
        } else {
            $cached = 'admin';
        }
        return $cached;
    }
}

if (!function_exists('admin_url')) {

    function admin_url(string $action = '', array $params = []): string
    {
        $base = defined('BASE_URL') ? BASE_URL : '';
        $u = $base . '/' . admin_dir() . '/';
        if ($action !== '') $params = ['action' => $action] + $params;
        if ($params) $u .= '?' . http_build_query($params);
        return $u;
    }
}

if (!function_exists('admin_query')) {

    function admin_query(string $action = '', array $params = []): string
    {
        if ($action !== '') $params = ['action' => $action] + $params;
        return $params ? '?' . http_build_query($params) : '?';
    }
}

if (!function_exists('admin_asset')) {

    function admin_asset(string $rel = ''): string
    {
        $base = defined('BASE_URL') ? BASE_URL : '';
        $rel = ltrim($rel, '/');
        return $base . '/' . admin_dir() . '/assets/' . $rel;
    }
}

if (!function_exists('excerpt')) {

    function excerpt($string, $length = 30, string $etc = '...'): string
    {
        $text = (string)$string;
        $len = (int)$length;
        if ($len <= 0) $len = 30;
        if (mb_strlen($text, 'UTF-8') <= $len) return $text;
        $cut = mb_substr($text, 0, $len, 'UTF-8');
        return rtrim($cut, "\r\n\t ") . $etc;
    }
}

if (!function_exists('url')) {

    function url(string $route, array $params = []): string
    {
        return UrlService::build($route, $params);
    }
}

if (!function_exists('url_full')) {

    function url_full(string $route, array $params = []): string
    {
        return BASE_URL . UrlService::build($route, $params);
    }
}

if (!function_exists('novelUrl')) {

    function novelUrl($id, $page = null): string
    {
        $params = ['id' => (int)$id];
        if ($page !== null) $params['page'] = $page;
        return url('novel', $params);
    }
}

if (!function_exists('readUrl')) {

    function readUrl($novelId, $chapterId): string
    {
        return url('read', ['novel' => (int)$novelId, 'chapter' => (int)$chapterId]);
    }
}

if (!function_exists('userUrl')) {

    function userUrl($action = null, $query = []): string
    {
        $params = [];
        if (is_array($action)) { $query = $action; $action = null; }
        if ($action !== null && $action !== '') $params['action'] = $action;
        if (!empty($query)) $params['query'] = $query;
        return url('user', $params);
    }
}

if (!function_exists('libraryUrl')) {

    function libraryUrl($query = []): string
    {
        return url('library', is_array($query) ? $query : ['query' => $query]);
    }
}

if (!function_exists('rankUrl')) {

    function rankUrl(string $type = 'views'): string
    {
        return url('rank', $type !== '' ? ['type' => $type] : []);
    }
}

if (!function_exists('authorsUrl')) {

    function authorsUrl(): string
    {
        return url('authors');
    }
}

if (!function_exists('urlPrefix')) {

    function urlPrefix(string $name, ?string $default = null): string
    {
        return UrlService::prefix($name);
    }
}

if (!function_exists('enovelcms_plugins_run')) {

    function enovelcms_plugins_run(string $file)
    {
        if (!function_exists('bolt_decrypt')) {
            throw new \RuntimeException('所需扩展未加载，无法加载应用文件：' . basename($file));
        }

        $db = null;
        if (class_exists('Plugin')) {
            try { $db = Plugin::db(); } catch (\Throwable $e) { $db = null; }
        }
        if (!$db && class_exists('Model')) {
            try { $db = Model::db(); } catch (\Throwable $e) { $db = null; }
        }

        $clientIdentity = '';
        $userId = 0;
        if ($db) {
            try {
                $row = $db->find('ac_bind', ['id' => 1]);
                $clientIdentity = (string)($row['client_identity'] ?? '');
                $userId = (int)($row['center_user_id'] ?? 0);
            } catch (\Throwable $e) {
                $clientIdentity = '';
                $userId = 0;
            }
        }
        if ($clientIdentity === '' || $userId <= 0) {
            throw new \RuntimeException('本站未连接应用中心或绑定凭证缺失，无法编译插件文件：' . basename($file));
        }

        $key = md5(trim($clientIdentity) . '|' . $userId);
        bolt_decrypt($file, $key);
        // body 顶层 return 已在加密前改写为该桥接变量；无返回值文件（类/函数定义）为 null
        return $__ac_bolt_return__ ?? null;
    }
}

if (!function_exists('qr_code_library')) {

    function qr_code_library(): bool
    {
        if (class_exists('QRcode')) return true;
        if (!extension_loaded('gd')) return false;
        $file = ROOT_PATH . 'vendor/QR/phpqrcode.php';
        if (!is_file($file)) return false;
        require_once $file;
        return class_exists('QRcode');
    }
}

if (!function_exists('qr_code_color')) {

    function qr_code_color($color, int $default): int
    {
        if (is_int($color)) return $color;
        $s = ltrim((string)$color, '#');
        return preg_match('/^[0-9a-fA-F]{6}$/', $s) ? (int)hexdec($s) : $default;
    }
}

if (!function_exists('qr_code_png')) {

    function qr_code_png(string $content, array $opts = []): ?string
    {
        $content = trim($content);
        if ($content === '' || mb_strlen($content, 'UTF-8') > 500) return null;

        $tmp = tempnam(sys_get_temp_dir(), 'qr_');
        if ($tmp === false) return null;
        $target = $tmp . '.png';
        @unlink($tmp);

        if (!qr_code_save($content, $target, $opts)) {
            @unlink($target);
            return null;
        }
        $bin = file_get_contents($target);
        @unlink($target);
        if ($bin === false || $bin === '' || substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") return null;
        return $bin;
    }
}

if (!function_exists('qr_code_data_uri')) {

    function qr_code_data_uri(string $content, array $opts = []): string
    {
        $png = qr_code_png($content, $opts);
        return $png === null ? '' : 'data:image/png;base64,' . base64_encode($png);
    }
}

if (!function_exists('qr_code_save')) {

    function qr_code_save(string $content, string $absPath, array $opts = []): bool
    {
        $content = trim($content);
        if ($content === '' || mb_strlen($content, 'UTF-8') > 500) return false;
        if (!qr_code_library()) return false;

        $levelMap = [
            'L' => QR_ECLEVEL_L, 'M' => QR_ECLEVEL_M,
            'Q' => QR_ECLEVEL_Q, 'H' => QR_ECLEVEL_H,
        ];
        $level = strtoupper((string)($opts['level'] ?? 'M'));
        $level = $levelMap[$level] ?? QR_ECLEVEL_M;
        $size  = max(1, (int)($opts['size'] ?? 4));
        $margin = max(0, (int)($opts['margin'] ?? 4));
        $back  = qr_code_color($opts['back'] ?? 0xFFFFFF, 0xFFFFFF);
        $fore  = qr_code_color($opts['fore'] ?? 0x000000, 0x000000);

        $dir = dirname($absPath);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;

        try {
            QRcode::png($content, $absPath, $level, $size, $margin, false, $back, $fore);
        } catch (\Throwable $e) {
            return false;
        }
        if (!is_file($absPath) || filesize($absPath) < 8) return false;
        $fp = fopen($absPath, 'rb');
        $head = $fp ? fread($fp, 8) : '';
        if ($fp) fclose($fp);
        return $head === "\x89PNG\r\n\x1a\n";
    }
}

if (!function_exists('cms_minor')) {

    function cms_minor(string $ver): string
    {
        $ver = trim($ver);
        if ($ver === '') return '';
        if (preg_match('/(\d+)\.(\d+)/', $ver, $m)) return $m[1] . '.' . $m[2];
        return $ver;
    }
}

if (!function_exists('brand_title')) {

    function brand_title(string $html): string
    {
        if ($html === '' || stripos($html, '<title') === false) return $html;
        if (stripos($html, 'Powered by EnovelCms') !== false) return $html;
        $done = false;
        $out = preg_replace_callback('/(<title[^>]*>)(.*?)(<\/title>)/is', function ($m) use (&$done) {
            if ($done) return $m[0];
            $done = true;
            $t = trim($m[2]);
            return $m[1] . ($t === '' ? 'Powered by EnovelCms' : $t . ' - Powered by EnovelCms') . $m[3];
        }, $html, 1);
        return is_string($out) ? $out : $html;
    }
}

if (!function_exists('asset')) {

    function asset(string $rel = ''): string
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        return BASE_URL . '/assets/' . $rel;
    }
}

if (!function_exists('theme_asset')) {

    function theme_asset(string $rel = '', ?string $theme = null): string
    {
        $theme = $theme !== null && $theme !== '' ? $theme : View::getTheme();
        if ($theme === '') $theme = 'default';
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        return BASE_URL . '/templates/' . $theme . '/' . $rel;
    }
}

if (!function_exists('plugin_asset')) {

    function plugin_asset(string $id, string $rel = ''): string
    {
        if (!Security::validPluginId($id) || !is_dir(ROOT_PATH . 'plugins/' . $id)) return '';
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        return BASE_URL . '/plugins/' . $id . '/' . $rel;
    }
}
