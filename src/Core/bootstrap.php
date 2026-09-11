<?php
// src/Core/bootstrap.php — boots every public page. Frontend/Backend: add features below.

if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);
}

session_start();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/DB.php';

$config = require dirname(__DIR__, 2) . '/config/config.php';

date_default_timezone_set(
    $config['app']['timezone'] ?? 'Asia/Manila'
);

error_reporting(E_ALL);

ini_set(
    'display_errors',
    $config['app']['env'] === 'development'
        ? '1'
        : '0'
);

ini_set('log_errors', '1');

ini_set(
    'error_log',
    dirname(__DIR__, 2) . '/logs/app.log'
);
