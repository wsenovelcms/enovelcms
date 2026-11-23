<?php

$configFile = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configFile)) {
    http_response_code(404);
    exit;
}
require_once $configFile;

spl_autoload_register(function ($class) {
    foreach (array('app/core/', 'app/models/', 'app/services/') as $dir) {
        $f = dirname(__DIR__) . '/' . $dir . $class . '.php';
        if (file_exists($f)) {
            require $f;
            return;
        }
    }
});

if (session_status() === PHP_SESSION_NONE) {
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
}
Security::generateCaptcha();
exit;
