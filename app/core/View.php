<?php

class View
{
    private static ?\Smarty\Smarty $smarty = null;
    private static array $vars = [];
    private static string $theme = 'default';
    private static array $templateDirs = [];

    public static function init(string $theme): void
    {
        self::$theme = $theme;

        $smartyEntry = ROOT_PATH . 'vendor/smarty/libs/Smarty.class.php';
        if (!file_exists($smartyEntry)) {
            die('Smarty library not found: ' . $smartyEntry);
        }
        require_once $smartyEntry;

        self::$smarty = new \Smarty\Smarty();

        $templateDir = ROOT_PATH . 'templates/' . $theme . '/views/';
        $dirs = [$templateDir];
        $fallback = ROOT_PATH . 'templates/default/views/';
        if (is_dir($fallback) && realpath($fallback) !== realpath($templateDir)) {
            $dirs[] = $fallback;
        }
        try {
            $extra = Hook::applyFilters('view_template_dirs', []);
        } catch (\Throwable $e) {
            $extra = [];
        }
        if (is_array($extra)) {
            foreach ($extra as $dir) {
                if (!is_string($dir) || $dir === '' || !is_dir($dir)) continue;
                if (!in_array(realpath($dir), array_map('realpath', $dirs), true)) {
                    $dirs[] = rtrim($dir, '/\\') . '/';
                }
            }
        }
        self::$templateDirs = $dirs;
        self::$smarty->setTemplateDir($dirs);

        $compileDir = ROOT_PATH . 'data/cache/templates/';
        if (!is_dir($compileDir)) @mkdir($compileDir, 0755, true);
        self::$smarty->setCompileDir($compileDir);

        $cacheDir = ROOT_PATH . 'data/cache/smarty/';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        self::$smarty->setCacheDir($cacheDir);

        self::$smarty->setCompileCheck(true);
        self::$smarty->setCaching(false);
        self::$smarty->setDebugging(false);
        self::$smarty->setEscapeHtml(true);

        try {
            self::$smarty->assign('captcha_enabled', Security::captchaEnabled());
        } catch (\Throwable $e) {
            self::$smarty->assign('captcha_enabled', true);
        }

        try {
            self::$smarty->assign('point_name', SettingModel::get('point_name', Language::get('points_default_name', '金币')));
        } catch (\Throwable $e) {
            self::$smarty->assign('point_name', Language::get('points_default_name', '金币'));
        }

        try {
            self::$smarty->assign('currency_name', SettingModel::get('currency_name', '元'));
            self::$smarty->assign('currency_symbol', SettingModel::get('currency_symbol', '¥'));
            self::$smarty->assign('exchange_rate', (int)SettingModel::get('recharge_exchange_rate', 10));
        } catch (\Throwable $e) {
            self::$smarty->assign('currency_name', Language::get('currency_default_name', '元'));
            self::$smarty->assign('currency_symbol', '¥');
            self::$smarty->assign('exchange_rate', 10);
        }

        self::$smarty->registerPlugin('function', 'hook', [__CLASS__, 'smartyHook']);

        self::$smarty->registerPlugin('function', 'sidebar', [__CLASS__, 'smartySidebar']);

        self::$smarty->registerPlugin('function', 'ad', [__CLASS__, 'smartyAd']);

        self::$smarty->registerPlugin('function', 'url', [__CLASS__, 'smartyUrl']);

        self::$smarty->registerPlugin('function', 'qr', [__CLASS__, 'smartyQr']);

        self::$smarty->registerPlugin('function', 't', [__CLASS__, 'smartyTranslate']);

        self::$smarty->registerPlugin('modifier', 't', [__CLASS__, 'smartyTranslateModifier']);
        self::$smarty->registerPlugin('modifier', 'truncate', [__CLASS__, 'smartyTruncate']);
        self::$smarty->registerPlugin('modifier', 'date_format', [__CLASS__, 'smartyDateFormat']);
        self::$smarty->registerPlugin('modifier', 'number_format', [__CLASS__, 'smartyNumberFormat']);
        self::$smarty->registerPlugin('modifier', 'ltrim', function ($str, $chars = " \t\n\r\0\x0B") { return ltrim((string)$str, $chars); });
        self::$smarty->registerPlugin('modifier', 'rtrim', function ($str, $chars = " \t\n\r\0\x0B") { return rtrim((string)$str, $chars); });
        self::$smarty->registerPlugin('modifier', 'trim', function ($str, $chars = " \t\n\r\0\x0B") { return trim((string)$str, $chars); });
        self::$smarty->registerPlugin('modifier', 'substr', function ($str, $start, $len = null) { return substr((string)$str, $start, $len ?? PHP_INT_MAX); });
        self::$smarty->registerPlugin('modifier', 'strpos', function ($haystack, $needle, $offset = 0) { return strpos((string)$haystack, (string)$needle, $offset); });
        self::$smarty->registerPlugin('modifier', 'strtolower', function ($str) { return strtolower((string)$str); });
        self::$smarty->registerPlugin('modifier', 'strtoupper', function ($str) { return strtoupper((string)$str); });
        self::$smarty->registerPlugin('modifier', 'ucfirst', function ($str) { return ucfirst((string)$str); });
        self::$smarty->registerPlugin('modifier', 'count', function ($arr) { return is_array($arr) ? count($arr) : 0; });
        self::$smarty->registerPlugin('modifier', 'empty', function ($v) { return empty($v); });
        self::$smarty->registerPlugin('modifier', 'sprintf', function ($str, ...$args) { return vsprintf((string)$str, $args); });
        self::$smarty->registerPlugin('modifier', 'cat', function (...$args) { return implode('', $args); });
        self::$smarty->registerPlugin('modifier', 'json_encode', function ($v) { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); });
        self::$smarty->registerPlugin('modifier', 'max', function (...$args) { return max($args); });
        self::$smarty->registerPlugin('modifier', 'min', function (...$args) { return min($args); });

        self::$smarty->registerPlugin('function', 'csrf_field', function ($params, $smarty) {
            $token = method_exists('Security', 'csrfToken') ? Security::csrfToken() : '';
            if ($token === '' && session_status() === PHP_SESSION_NONE) {
                if (!empty($_SESSION['_csrf'])) $token = $_SESSION['_csrf'];
            }
            if ($token === '' && !empty($_SESSION['_csrf'])) $token = $_SESSION['_csrf'];
            return '<input type="hidden" name="_csrf" value="' . htmlspecialchars((string)$token) . '">';
        });

        self::$smarty->registerPlugin('function', 'captcha_field', function ($params, $smarty) {
            $name = $params['name'] ?? 'captcha';
            $placeholder = $params['placeholder'] ?? Language::get('captcha_field_placeholder', '请输入验证码');
            return Security::captchaHtml((string)$name, (string)$placeholder);
        });

        self::$smarty->registerPlugin('function', 'pagination', [__CLASS__, 'smartyPagination']);
        self::$smarty->registerPlugin('function', 'asset', [__CLASS__, 'smartyAsset']);
        self::$smarty->registerPlugin('function', 'theme_asset', [__CLASS__, 'smartyThemeAsset']);
    }

    public static function smartyAsset(array $params, $smarty = null): string
    {
        $file = (string)($params['file'] ?? '');
        return htmlspecialchars(asset($file), ENT_QUOTES, 'UTF-8');
    }

    public static function smartyThemeAsset(array $params, $smarty = null): string
    {
        $file = (string)($params['file'] ?? '');
        $theme = isset($params['theme']) ? (string)$params['theme'] : null;
        return htmlspecialchars(theme_asset($file, $theme), ENT_QUOTES, 'UTF-8');
    }

    public static function smartyPagination(array $params, $smarty): string
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $totalPages = max(1, (int)($params['totalPages'] ?? 1));
        $urlTpl = (string)($params['urlTpl'] ?? '?page=@@PAGE@@');
        $showJump = !isset($params['showJump']) || $params['showJump'];
        if ($totalPages <= 1) return '';

        $urlFor = function ($p) use ($urlTpl) {
            return str_replace('@@PAGE@@', (string)$p, $urlTpl);
        };

        $half = function_exists('is_mobile') && is_mobile() ? 1 : 5;
        $start = max(1, $page - $half);
        $end = min($totalPages, $page + $half);

        $html = '<nav class="pagination">';

        $firstPage = Language::get('first_page', '首页');
        $prevPage = Language::get('prev_page', '上一页');
        if ($page > 1) {
            $html .= '<a class="page-link page-first" href="' . htmlspecialchars($urlFor(1)) . '">' . $firstPage . '</a>';
            $html .= '<a class="page-link page-prev" href="' . htmlspecialchars($urlFor($page - 1)) . '">' . $prevPage . '</a>';
        } else {
            $html .= '<span class="page-link page-first disabled">' . $firstPage . '</span>';
            $html .= '<span class="page-link page-prev disabled">' . $prevPage . '</span>';
        }

        if ($start > 1) $html .= '<span class="page-ellipsis">…</span>';

        for ($i = $start; $i <= $end; $i++) {
            if ($i === $page) {
                $html .= '<span class="page-link active">' . $i . '</span>';
            } else {
                $html .= '<a class="page-link" href="' . htmlspecialchars($urlFor($i)) . '">' . $i . '</a>';
            }
        }

        if ($end < $totalPages) $html .= '<span class="page-ellipsis">…</span>';

        $nextPage = Language::get('next_page', '下一页');
        $lastPage = Language::get('last_page', '尾页');
        if ($page < $totalPages) {
            $html .= '<a class="page-link page-next" href="' . htmlspecialchars($urlFor($page + 1)) . '">' . $nextPage . '</a>';
            $html .= '<a class="page-link page-last" href="' . htmlspecialchars($urlFor($totalPages)) . '">' . $lastPage . '</a>';
        } else {
            $html .= '<span class="page-link page-next disabled">' . $nextPage . '</span>';
            $html .= '<span class="page-link page-last disabled">' . $lastPage . '</span>';
        }

        if ($showJump) {
            $safeUrl = htmlspecialchars($urlTpl, ENT_QUOTES);
            $jumpTo = Language::get('jump_to', '跳至');
            $pageUnit = Language::get('page_unit', '页');
            $html .= '<span class="page-jump">' . $jumpTo . ' <input type="number" min="1" max="' . $totalPages . '" value="' . $page . '" data-url="' . $safeUrl . '" onchange="var p=parseInt(this.value);if(p>=1&&p<=' . $totalPages . ')location.href=this.dataset.url.replace(/@@PAGE@@/g,p);"> / ' . $totalPages . ' ' . $pageUnit . '</span>';
        }
        $html .= '</nav>';
        return $html;
    }

    public static function smartyHook(array $params, $smarty): string
    {
        $name = $params['name'] ?? '';
        unset($params['name']);
        if ($name) {
            ob_start();
            Hook::doAction($name, ...array_values($params));
            $out = ob_get_clean();
            if (defined('HOOK_INSPECTOR_ON') && HOOK_INSPECTOR_ON) {
                return '<span class="hook-marker" data-hook="' . htmlspecialchars($name)
                     . '" title="hook: ' . htmlspecialchars($name) . '">[hook:' . htmlspecialchars($name) . ']</span>'
                     . $out;
            }
            return $out;
        }
        return '';
    }

    public static function smartySidebar(array $params, $smarty): string
    {
        $slot = (int)($params['slot'] ?? 0);
        if ($slot < 1 || $slot > 9) return '';
        ob_start();
        try {
            Hook::doAction('sidebar_' . $slot);
            Hook::doAction('sidebar', $slot);
        } catch (\Throwable $e) {
            error_log('[sidebar:' . $slot . '] ' . $e->getMessage());
        }
        return ob_get_clean();
    }

    public static function smartyAd(array $params, $smarty): string
    {
        $name = $params['name'] ?? '';
        if ($name === '') return '';

        $adHtml = '';
        try {
            if (class_exists('AdsModel')) {
                $adHtml = AdsModel::render($name);
            }
        } catch (\Throwable $e) {
            error_log('[ad:' . $name . '] ' . $e->getMessage());
            $adHtml = '';
        }

        ob_start();
        Hook::doAction('ad_' . $name);
        $out = $adHtml . ob_get_clean();

        if (defined('HOOK_INSPECTOR_ON') && HOOK_INSPECTOR_ON) {
            return '<span class="hook-marker" data-hook="ad_' . htmlspecialchars($name)
                 . '" title="hook: ad_' . htmlspecialchars($name) . '">[ad:' . htmlspecialchars($name) . ']</span>'
                 . $out;
        }
        return $out;
    }

    public static function smartyUrl(array $params, $smarty): string
    {
        $route = $params['route'] ?? '';
        if ($route === '') return '';
        $assign = $params['assign'] ?? null;
        unset($params['route'], $params['assign']);
        $url = UrlService::build($route, $params);
        if ($assign) {
            $smarty->assign($assign, $url);
            return '';
        }
        return $url;
    }

    public static function smartyQr(array $params, $smarty): string
    {
        $content = (string)($params['content'] ?? '');
        if ($content === '') return '';
        $opts = [];
        foreach (['level', 'size', 'margin', 'back', 'fore'] as $k) {
            if (isset($params[$k])) $opts[$k] = $params[$k];
        }
        $uri = qr_code_data_uri($content, $opts);
        $assign = $params['assign'] ?? null;
        if ($assign) {
            $smarty->assign($assign, $uri);
            return '';
        }
        return $uri;
    }

    public static function smartyTranslate(array $params, $smarty): string
    {
        $key = $params['name'] ?? '';
        if ($key === '') return '';
        $default = $params['default'] ?? '';
        if (isset($params['args']) && is_array($params['args'])) {
            return Language::get($key, $default, ...array_values($params['args']));
        }
        return Language::get($key, $default);
    }

    public static function smartyTranslateModifier($key, ...$args): string
    {
        if ($args) { return Language::get((string)$key, '', ...$args); }
        return Language::get((string)$key);
    }

    public static function smartyTruncate($string, $length = 30, $etc = '...'): string
    {
        return excerpt($string, (int)$length, (string)$etc);
    }

    public static function smartyDateFormat($string, $format = 'Y-m-d H:i:s'): string
    {
        $ts = is_numeric($string) ? (int)$string : strtotime((string)$string);
        return date($format, $ts ?: time());
    }

    public static function smartyNumberFormat($number, $decimals = 0): string
    {
        return number_format((float)$number, $decimals);
    }

    public static function assign(string $key, $value): void
    {
        self::$vars[$key] = $value;
        if (self::$smarty) {
            self::$smarty->assign($key, $value);
        }
    }

    public static function assignArray(array $data): void
    {
        self::$vars = array_merge(self::$vars, $data);
        if (self::$smarty) {
            self::$smarty->assign($data);
        }
    }

    public static function render(string $template, array $data = []): void
    {
        if ($data) {
            self::$vars = array_merge(self::$vars, $data);
            self::$smarty->assign($data);
        }

        self::$vars = Hook::applyFilters('view_before_render', self::$vars, $template);
        self::$vars['js_lang'] = json_encode(Language::jsStrings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::$vars['html_lang'] = Language::current();
        self::$smarty->assign(self::$vars);

        $template = (string)Hook::applyFilters('view_template', $template, self::$vars);
        $tplPath = self::resolveTemplate($template);
        if ($tplPath === '') {
            self::renderErrorPage(500, Language::get('view_template_missing', '模板文件不存在'), $template, '查找目录：' . implode(' , ', self::$templateDirs));
            return;
        }

        try {

            ob_start();
            self::$smarty->display($template);
            $html = ob_get_clean();
            $html = Hook::applyFilters('output_filter', $html, $template);
            echo function_exists('brand_title') ? brand_title($html) : $html;
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) ob_end_clean();
            self::renderErrorPage(500, Language::get('view_template_compile_error', '模板编译/执行错误'), $template, $e->getMessage() . '（' . basename($e->getFile()) . ':' . $e->getLine() . '）');
        }
    }

    public static function resolveTemplate(string $template): string
    {
        foreach (self::$templateDirs as $dir) {
            $file = rtrim($dir, '/\\') . '/' . ltrim($template, '/');
            if (is_file($file)) return $file;
        }
        return '';
    }

    public static function templateDirs(): array
    {
        return self::$templateDirs;
    }

    public static function addTemplateDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $dir = rtrim($dir, '/\\') . '/';
        if (!in_array($dir, self::$templateDirs, true)) {
            self::$templateDirs[] = $dir;
        }
        if (self::$smarty) {
            self::$smarty->addTemplateDir($dir);
        }
    }

    private static function renderErrorPage(int $code, string $title, string $template, string $detail = ''): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=utf-8');
        }
        $t = htmlspecialchars($title);
        $tp = htmlspecialchars($template);
        $d = htmlspecialchars($detail);
        $btn = defined('BASE_URL') ? htmlspecialchars(BASE_URL) : '/';
        $bodyMsg = Language::get('view_render_failed_message', '页面渲染时遇到问题，请联系管理员或稍后再试。');
        $detailLabel = Language::get('detail_label', '详情：');
        $templateLabel = Language::get('template_label', '模板：');
        $backPrev = Language::get('back_to_previous', '返回上一页');
        $goHome = Language::get('go_home', '回到首页');
        $dLine = $d !== '' ? '<br>' . $detailLabel . $d : '';
        $pageHtml = <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>{$t} · EnovelCms</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#faf7f2;color:#333;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .err-card{background:#fff;border-radius:10px;padding:36px 40px;max-width:560px;width:100%;box-shadow:0 6px 24px rgba(0,0,0,.06);border-top:3px solid #b8860b}
  .err-code{font-size:13px;letter-spacing:2px;color:#b8860b;font-weight:600;margin-bottom:10px}
  .err-title{font-size:20px;font-weight:600;margin-bottom:8px}
  .err-msg{color:#666;line-height:1.7;font-size:14px;margin-bottom:18px}
  .err-meta{background:#faf7f2;border-radius:6px;padding:12px 16px;font-size:12.5px;color:#888;line-height:1.8;font-family:Consolas,Menlo,monospace;margin-bottom:24px;word-break:break-all}
  .err-actions{display:flex;gap:10px}
  .err-actions a{text-decoration:none;padding:8px 18px;border-radius:6px;font-size:13px}
  .err-back{background:#333;color:#fff}
  .err-home{background:transparent;color:#333;border:1px solid #ddd}
</style>
</head>
<body>
  <div class="err-card">
    <div class="err-code">ERROR {$code}</div>
    <div class="err-title">{$t}</div>
    <div class="err-msg">{$bodyMsg}</div>
    <div class="err-meta">{$templateLabel}{$tp}{$dLine}</div>
    <div class="err-actions">
      <a class="err-back" href="javascript:history.back()">{$backPrev}</a>
      <a class="err-home" href="{$btn}">{$goHome}</a>
    </div>
  </div>
</body>
</html>
HTML;
        echo function_exists('brand_title') ? brand_title($pageHtml) : $pageHtml;
    }

    public static function fetch(string $template, array $data = []): string
    {
        if ($data) self::$smarty->assign($data);
        return self::$smarty->fetch($template);
    }

    public static function getVars(): array { return self::$vars; }
    public static function getTheme(): string { return self::$theme; }
    public static function getSmarty(): ?\Smarty\Smarty { return self::$smarty; }
}
