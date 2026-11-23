<?php

define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'config/config.php';
require_once ROOT_PATH . 'app/core/functions.php';

spl_autoload_register(function ($class) {
    foreach (['app/core/', 'app/models/', 'app/services/'] as $dir) {
        $f = ROOT_PATH . $dir . $class . '.php';
        if (file_exists($f)) { require $f; return; }
    }
});

ErrorHandler::register();
$db = new Database();
Model::setDb($db);
Cache::init();
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost');
Plugin::setDb($db);
PluginDB::setDb($db);
PluginConfig::setDb($db);
Plugin::init($db);

if (PHP_SAPI !== 'cli') {
    $key = $_GET['key'] ?? '';
    if (!defined('SECRET_KEY') || !hash_equals(substr(SECRET_KEY, 0, 8), $key)) {
        http_response_code(403);
        exit('Forbidden');
    }
    $allow = trim((string)SettingModel::get('cron_web_ip_allow', ''));
    if ($allow !== '') {
        $ips = array_filter(array_map('trim', explode('|', $allow)));
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $hit = false;
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) && $ip === $remote) { $hit = true; break; }
        }
        if (!$hit) {
            http_response_code(403);
            exit('Forbidden');
        }
    }
}

$task = $argv[1] ?? 'run';
$taskKey = $argv[2] ?? '';

switch ($task) {
    case 'warm':
        Cron::warmCache();
        echo "[" . date('Y-m-d H:i:s') . "] " . t_admin('adm_cron_cli_warm_done', '缓存预热完成') . "\n";
        break;
    case 'cleanup':
        Cron::cleanup();
        echo "[" . date('Y-m-d H:i:s') . "] " . t_admin('adm_cron_cli_clean_done', '过期数据清理完成') . "\n";
        break;
    case 'run':
        if ($taskKey !== '') {
            $result = Cron::runTask($taskKey, true);
            echo "[" . date('Y-m-d H:i:s') . "] {$taskKey}: " . ($result['ok'] ? 'OK' : 'FAIL') . " " . $result['message'] . "\n";
        } else {
            $cron = new Cron();
            $cron->runAll();
            echo "[" . date('Y-m-d H:i:s') . "] " . t_admin('adm_cron_cli_all_done', '所有定时任务执行完毕') . "\n";
        }
        break;
    default:
        $cron = new Cron();
        $cron->runAll();
        echo "[" . date('Y-m-d H:i:s') . "] " . t_admin('adm_cron_cli_all_done', '所有定时任务执行完毕') . "\n";
        break;
}
