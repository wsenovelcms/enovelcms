<?php

if (!file_exists(__DIR__ . '/config/config.php') || !file_exists(__DIR__ . '/data/install.lock')) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    exit('404 Not Found');
}

$_SERVER['REQUEST_URI']    = '/__not_found__';
$_SERVER['QUERY_STRING']   = '';
$_GET                      = array();
$_POST                     = array();
$_REQUEST                  = array();

require __DIR__ . '/index.php';
