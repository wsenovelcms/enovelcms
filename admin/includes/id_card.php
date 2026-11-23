<?php

if (!defined('ADMIN_BOOT')) exit;

$appId = (int)($_GET['app_id'] ?? 0);
$side = ((g('side', 'front', 'str')) === 'back') ? 'back' : 'front';
if ($appId <= 0) { http_response_code(404); exit; }

$app = AuthorApplicationModel::findById($appId);
if (!$app) { http_response_code(404); exit; }

$relative = $side === 'back' ? ($app['id_card_back'] ?? '') : ($app['id_card_front'] ?? '');
if ($relative === '' || strpos($relative, '/data/uploads/idcards/') !== 0) {
    http_response_code(404); exit;
}

$file = ROOT_PATH . $relative;
$idCardRoot = realpath(ROOT_PATH . 'data/uploads/idcards/');
$realFile = realpath($file);
if ($idCardRoot === false || $realFile === false
    || strpos($realFile, $idCardRoot . DIRECTORY_SEPARATOR) !== 0
    || !is_file($realFile)) {
    http_response_code(404); exit;
}

$allowedMime = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp');
$mime = '';
if (function_exists('mime_content_type')) {
    $detected = @mime_content_type($realFile);
    if (isset($allowedMime[$detected])) { $mime = $detected; }
}
if ($mime === '') {
    $ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
    $map = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp');
    $mime = isset($map[$ext]) ? $map[$ext] : '';
}
if ($mime === '') { http_response_code(404); exit; }

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');
readfile($realFile);
exit;
