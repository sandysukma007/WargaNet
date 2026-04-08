<?php

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Force APP_DEBUG for Vercel troubleshooting
$_ENV['APP_DEBUG'] = 'true';
putenv('APP_DEBUG=true');

// Ensure Laravel uses /tmp for all writable directories
$tmpPath = '/tmp';
$storagePaths = [
    "$tmpPath/framework/views",
    "$tmpPath/framework/sessions",
    "$tmpPath/framework/cache",
    "$tmpPath/logs",
];

foreach ($storagePaths as $path) {
    if (!is_dir($path)) { mkdir($path, 0777, true); }
}

putenv("VIEW_COMPILED_PATH=$tmpPath/framework/views");
putenv("SESSION_DRIVER=cookie");
putenv("LOG_CHANNEL=stderr");
putenv("LIVEWIRE_MANIFEST_PATH=$tmpPath/framework/cache/livewire-components.php");

// Forward Vercel requests to the normal Laravel index.php
require __DIR__ . '/../public/index.php';
