<?php

class ErrorHandler
{

    private static bool $runtimeDebug = false;

    public static function setDebug(bool $on): void
    {
        self::$runtimeDebug = $on;
        if ($on) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
        }
    }

    private static function isDebug(): bool
    {
        return (defined('DEBUG') && DEBUG) || self::$runtimeDebug;
    }

    private static function wantsJson(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/api/')) {
            return true;
        }
        $xrw = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return $xrw === 'xmlhttprequest';
    }

    public static function register(): void
    {

        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

        $errorLog = ROOT_PATH . 'data/logs/php_errors.log.php';
        if (!file_exists($errorLog)) {
            @file_put_contents($errorLog, "<?php exit; ?>\n");
        }
        @ini_set('error_log', $errorLog);

        set_exception_handler(array(__CLASS__, 'handleException'));
        set_error_handler(array(__CLASS__, 'handleError'), E_ALL & ~E_DEPRECATED);
        register_shutdown_function(array(__CLASS__, 'handleShutdown'));
    }

    public static function handleException($exception): void
    {
        error_log('[Exception] ' . $exception->getMessage()
            . ' in ' . $exception->getFile() . ':' . $exception->getLine());
        self::renderErrorPage(self::buildDetails($exception));
    }

    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        error_log("[Error] {$errstr} in {$errfile}:{$errline}");
        if (in_array($errno, array(E_USER_ERROR, E_RECOVERABLE_ERROR), true)) {
            self::renderErrorPage(self::isDebug() ? "[Error] {$errstr} in {$errfile}:{$errline}" : '');
            return true;
        }
        return true;
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'],
            array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) {
            error_log('[Fatal] ' . $error['message'] . ' in '
                . $error['file'] . ':' . $error['line']);
            $details = '';
            if (self::isDebug()) {
                $details = '[Fatal] ' . $error['message'] . ' in '
                    . $error['file'] . ':' . $error['line'];
            }
            self::renderErrorPage($details);
        }
    }

    private static function buildDetails($exception): string
    {
        if (!self::isDebug()) {
            return '';
        }
        return '[Exception] ' . $exception->getMessage() . "\n"
            . 'File: ' . $exception->getFile() . ' (' . $exception->getLine() . ")\n"
            . "Trace:\n" . $exception->getTraceAsString();
    }

    private static function renderErrorPage(string $details): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);

        if (self::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $busyMsg = Language::get('server_busy_msg', '服务器暂时无法处理您的请求');
            } catch (\Throwable $e) {
                $busyMsg = '服务器暂时无法处理您的请求';
            }
            $payload = ['code' => 500, 'msg' => $busyMsg];
            if ($details !== '') {
                $payload['debug'] = $details;
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        try {
            $title = Language::get('system_error_title', '系统错误');
            $message = Language::get('server_busy_message', '服务器暂时无法处理您的请求，请稍后再试。');
            $home = Language::get('back_to_home', '返回首页');
        } catch (\Throwable $e) {
            $title = '系统错误';
            $message = '服务器暂时无法处理您的请求，请稍后再试。';
            $home = '返回首页';
        }
        $detailHtml = '';
        if ($details !== '') {

            $escaped = htmlspecialchars($details, ENT_QUOTES, 'UTF-8');

            $parts = explode("\nTrace:\n", $escaped, 2);
            $head = $parts[0];
            $trace = $parts[1] ?? '';
            $detailHtml = '<div class="dbg">'
                . '<div class="dbg-head">' . nl2br($head) . '</div>';
            if ($trace !== '') {
                $traceLines = array_filter(array_map('trim', explode("\n", $trace)));
                $detailHtml .= '<ol class="dbg-trace">';
                foreach ($traceLines as $ln) {
                    $detailHtml .= '<li>' . $ln . '</li>';
                }
                $detailHtml .= '</ol>';
            }
            $detailHtml .= '</div>';
        }
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $title . ' · ' . htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'EnovelCms') . ' - Powered by EnovelCms</title><style>'
            . '*{box-sizing:border-box;margin:0;padding:0;}'
            . 'body{font-family:"PingFang SC","Microsoft YaHei",sans-serif;background:#faf7f2;color:#333;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}'
            . '.card{max-width:720px;width:100%;background:#fff;border-radius:8px;box-shadow:0 2px 20px rgba(0,0,0,.06);overflow:hidden;}'
            . '.card-header{background:#333;color:#faf7f2;padding:16px 24px;font-size:15px;font-weight:600;display:flex;align-items:center;gap:10px;}'
            . '.card-header .dot{width:10px;height:10px;border-radius:50%;background:#b8860b;display:inline-block;}'
            . '.card-body{padding:36px 32px;text-align:center;}'
            . 'h1{color:#222;font-size:18px;font-weight:600;margin-bottom:10px;}'
            . '.msg{color:#666;line-height:1.8;font-size:14px;margin-bottom:20px;}'
            . 'a.btn{display:inline-block;padding:9px 26px;background:#333;color:#faf7f2;border-radius:4px;text-decoration:none;font-size:13px;transition:background .2s;}'
            . 'a.btn:hover{background:#b8860b;}'
            . '.detail{text-align:left;background:#faf7f2;border:1px solid #eee;border-radius:4px;padding:12px 14px;font-family:Consolas,Menlo,monospace;font-size:12px;line-height:1.7;color:#555;overflow:auto;margin:16px 0;white-space:pre-wrap;word-break:break-all;}'
            . '.dbg{text-align:left;margin:20px 0;border-radius:6px;overflow:hidden;border:1px solid #e5e5e5;background:#2b2b2b;color:#e6e6e6;font-family:Consolas,Menlo,monospace;font-size:12px;}'
            . '.dbg-head{padding:12px 14px;background:#3a2a2a;color:#ffb4b4;border-bottom:1px solid #1a1a1a;line-height:1.6;word-break:break-all;}'
            . '.dbg-trace{margin:0;padding:8px 0;list-style:none;max-height:280px;overflow:auto;}'
            . '.dbg-trace li{padding:4px 14px;color:#cfcfcf;line-height:1.6;border-left:3px solid transparent;}'
            . '.dbg-trace li:hover{background:#3a3a3a;border-left-color:#b8860b;}'
            . '.dbg-trace li:nth-child(odd){background:#323232;}'
            . '.card-footer{padding:14px 24px;border-top:1px solid #f0f0f0;text-align:center;font-size:12px;color:#999;background:#fcfbf8;}'
            . '.card-footer a{color:#b8860b;text-decoration:none;}'
            . '</style></head><body><div class="card">'
            . '<div class="card-header"><span class="dot"></span>' . $title . '</div>'
            . '<div class="card-body">'
            . '<h1>' . $title . '</h1>'
            . '<p class="msg">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . $detailHtml
            . '<a class="btn" href="/">' . $home . '</a>'
            . '</div>'
            . '<div class="card-footer">Powered By <a href="https://www.enovelcms.cn/" target="_blank" rel="noopener">EnovelCms'
            . (defined('ENOVELCMS_VERSION') ? ' v' . ENOVELCMS_VERSION : '')
            . '</a></div>'
            . '</div></body></html>';
    }
}
