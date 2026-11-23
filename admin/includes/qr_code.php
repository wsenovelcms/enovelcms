<?php

if (!defined('ADMIN_BOOT')) exit;

$userId = (int)($_GET['user_id'] ?? 0);
if ($userId <= 0) { http_response_code(404); exit; }

$user = UserModel::findById($userId, 'withdraw_qrcode');
$relative = (string)($user['withdraw_qrcode'] ?? '');
if ($relative === '' || strpos($relative, 'data/uploads/qrcodes/') !== 0) {
    http_response_code(404); exit;
}

$root = realpath(ROOT_PATH . 'data/uploads/qrcodes/');
$realFile = realpath(ROOT_PATH . $relative);
if ($root === false || $realFile === false
    || strpos($realFile, $root . DIRECTORY_SEPARATOR) !== 0
    || !is_file($realFile)) {
    http_response_code(404); exit;
}

$allowedMime = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp');
$mime = '';
if (function_exists('mime_content_type')) {
    $detected = @mime_content_type($realFile);
    if (isset($allowedMime[$detected])) $mime = $detected;
}
if ($mime === '') {
    $ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
    $map = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp');
    $mime = $map[$ext] ?? '';
}
if ($mime === '') { http_response_code(404); exit; }

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');
readfile($realFile);
exit;
