<?php

class Bootstrap
{
    private Database $db;
    private Request $request;
    private Router $router;

    public function run(): void
    {

        $this->defineConstants();

        $this->registerAutoloader();

        require_once ROOT_PATH . 'app/core/functions.php';

        $this->initErrorHandler();

        $this->assertRuntimeDirs();

        $this->initSession();

        Cache::init();

        $this->db = new Database();
        Model::setDb($this->db);

        if ($this->handleAdminProxy()) {
            return;
        }

        try {
            $row = $this->db->query("SELECT `value` FROM " . tb('settings') . " WHERE `key`='debug_mode'")->fetch();
            if ($row && (string)$row['value'] === '1') {
                ErrorHandler::setDebug(true);
            }
        } catch (\Throwable $e) {  }

        SecurityGuard::handle();

        Security::sendSecurityHeaders();

        $activeTheme = $this->getActiveTheme();
        View::init($activeTheme);
        Language::init($activeTheme);

        Ui::registerFront();

        ThemeConfig::setDb($this->db);
        ThemeConfig::setCurrent($activeTheme);
        Extension::setDb($this->db);
        Theme::setDb($this->db);
        Theme::loadInclude($activeTheme);

        Plugin::setDb($this->db);
        PluginDB::setDb($this->db);
        PluginConfig::setDb($this->db);
        PluginForm::setDb($this->db);
        Plugin::init($this->db);

        Theme::setDb($this->db);
        Theme::init($this->db);

        $this->request = new Request();

        $this->router = new Router();
        $route = $this->router->dispatch($this->request->path);
        $this->router->execute($route, $this->db, $this->request);
    }

    private function defineConstants(): void
    {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 2) . '/');
        }

        if (!defined('PRIVATE_PATH')) {
            define('PRIVATE_PATH', ROOT_PATH . 'data/');
        }

        if (!defined('APP_CENTER_URL')) {
            define('APP_CENTER_URL', 'https://appcenter.enovelbbs.cn');
        }

        $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!preg_match('/^[a-zA-Z0-9.\-]{1,253}(:\d{1,5})?$/', $rawHost)) {
            $rawHost = 'localhost';
        }
        if (!defined('BASE_URL')) {
            define('BASE_URL',
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . $rawHost
            );
        }
        if (!defined('ADMIN_URL')) {
            define('ADMIN_URL', BASE_URL . '/' . ADMIN_DIR);
        }
    }

    private function assertRuntimeDirs(): void
    {
        $dirs = require ROOT_PATH . 'app/core/runtime_dirs.php';
        $missing = array();

        foreach ($dirs as $sub) {
            if (!is_dir(PRIVATE_PATH . $sub)) {
                $missing[] = 'data/' . $sub;
            }
        }

        if (!empty($missing)) {
            $title = '运行目录缺失';
            $listLabel = '以下目录不存在：';
            $desc = '安装包内已包含全部运行目录，请重新解压完整安装包；若目录确实丢失，重新运行 install 让安装程序检测并补齐。';
            $langCode = 'zh-CN';
            try {
                $title = Language::get('runtime_dir_missing_title', $title);
                $listLabel = Language::get('runtime_dir_missing_list', $listLabel);
                $desc = Language::get('runtime_dir_missing_desc', $desc);
                $langCode = htmlspecialchars(Language::current(), ENT_QUOTES, 'UTF-8');
            } catch (\Throwable $e) {
            }
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="' . $langCode . '"><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
                . '<div style="font:15px/1.7 -apple-system,\'Microsoft YaHei\',sans-serif;color:#1f2330;background:#f6f7f9;padding:28px 24px;">'
                . '<div style="max-width:760px;margin:0 auto;background:#fff;border:1px solid #d7dbe3;border-radius:8px;padding:22px 24px;">'
                . '<div style="font-size:19px;font-weight:700;margin-bottom:10px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div style="margin-bottom:10px;">' . htmlspecialchars($listLabel, ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div style="font-family:ui-monospace,Consolas,monospace;color:#b91c1c;margin-bottom:14px;">' . htmlspecialchars(implode(', ', $missing), ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</div>'
                . '</div></div></html>';
            exit;
        }
    }

    private function registerAutoloader(): void
    {        spl_autoload_register(function($class) {

            $coreFile = ROOT_PATH . 'app/core/' . $class . '.php';
            if (file_exists($coreFile)) { require $coreFile; return; }

            $modelFile = ROOT_PATH . 'app/models/' . $class . '.php';
            if (file_exists($modelFile)) { require $modelFile; return; }

            $ctrlFile = ROOT_PATH . 'app/controllers/' . $class . '.php';
            if (file_exists($ctrlFile)) { require $ctrlFile; return; }

            $svcFile = ROOT_PATH . 'app/services/' . $class . '.php';
            if (file_exists($svcFile)) { require $svcFile; return; }
        });
    }

    private function initErrorHandler(): void
    {
        if (DEBUG) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
        }
        ErrorHandler::register();
    }

    private function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {

            ini_set('session.use_strict_mode', '1');
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                || (($_SERVER['SERVER_PORT'] ?? '') == 443);
            session_name(SESSION_NAME);
            session_set_cookie_params(array(
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $https,
                'httponly' => true,
                'samesite' => 'Lax',
            ));
            session_start();

            if (empty($_SESSION['_created'])) {
                $_SESSION['_created'] = time();
            } elseif (time() - (int)$_SESSION['_created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['_created'] = time();
            }
        }
    }

    private function getActiveTheme(): string
    {
        try {
            $db = new Database();
            $pcTheme = $db->find('settings', ['key' => 'active_theme']);
            $pcTheme = $pcTheme['value'] ?? 'default';
            $mobileTheme = $db->find('settings', ['key' => 'mobile_theme']);
            $mobileTheme = $mobileTheme['value'] ?? '';

            if ($mobileTheme !== '' && $this->isMobileDevice()) {
                $mobileDir = ROOT_PATH . 'templates/' . $mobileTheme;
                if (is_dir($mobileDir)) {
                    return $mobileTheme;
                }
            }
            return $pcTheme;
        } catch (\Throwable $e) {
            return 'default';
        }
    }

    private function isMobileDevice(): bool
    {
        return is_mobile();
    }

    private function handleAdminProxy(): bool
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $seg  = strtolower(trim(explode('/', $path)[1] ?? ''));
        $adminDir = admin_dir();

        if ($adminDir !== 'admin' && $seg === 'admin') {
            return false;
        }
        if ($seg !== $adminDir) return false;

        $rest = trim(substr($path, strlen('/' . $adminDir)), '/');

        if ($rest !== '' && preg_match('#^assets/[A-Za-z0-9_\-/]+\.(css|js|map|png|jpe?g|gif|svg|woff2?|ttf|eot)$#i', $rest)) {
            $this->serveAdminAsset($rest);
            return true;
        }

        $firstSeg = (string)explode('/', $rest)[0];
        if ($firstSeg !== '' && preg_match('/\.php$/i', $firstSeg)) {
            http_response_code(404);
            exit('404 Not Found');
        }

        define('ADMIN_PROXIED', 1);
        require ROOT_PATH . 'admin/index.php';
        return true;
    }

    private function serveAdminAsset(string $rel): void
    {
        $base = realpath(ROOT_PATH . 'admin/assets');
        $file = realpath(ROOT_PATH . 'admin/' . $rel);
        if ($base === false || $file === false || strpos($file, $base . DIRECTORY_SEPARATOR) !== 0) {
            http_response_code(404);
            exit('404 Not Found');
        }
        $stat = @stat($file);
        if ($stat === false) {
            http_response_code(404);
            exit('404 Not Found');
        }
        $mtime = (int)$stat['mtime'];
        $size  = (int)$stat['size'];

        header_remove('Set-Cookie');
        header_remove('Cache-Control');
        header_remove('Expires');
        header_remove('Pragma');

        $mime = [
            'css' => 'text/css; charset=utf-8',
            'js'  => 'application/javascript; charset=utf-8',
            'map' => 'application/json',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml',
            'woff' => 'font/woff', 'woff2' => 'font/woff2',
            'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject',
        ];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $etag = '"' . md5($file . '|' . $mtime . '|' . $size) . '"';

        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Cache-Control: public, max-age=2592000');

        $inm  = (string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
        $inet = (string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        $notModified = ($inet !== '' && $inet === $etag);
        if (!$notModified && $inm !== '') {
            $ts = @strtotime($inm);
            if ($ts !== false && $ts >= $mtime) $notModified = true;
        }
        if ($notModified) {
            http_response_code(304);
            exit;
        }

        header('Content-Type: ' . ($mime[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . $size);
        readfile($file);
        exit;
    }
}

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
