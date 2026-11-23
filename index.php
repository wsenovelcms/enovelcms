<?php

$configFile = __DIR__ . '/config/config.php';
$lockFile    = __DIR__ . '/data/install.lock';

if (!file_exists($configFile) || !file_exists($lockFile)) {
    header('Location: install/install.php');
    exit;
}

require_once $configFile;
require_once __DIR__ . '/app/core/Bootstrap.php';

$app = new Bootstrap();
$app->run();
