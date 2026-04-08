<?php

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Force APP_DEBUG for Vercel troubleshooting
$_ENV['APP_DEBUG'] = 'true';
putenv('APP_DEBUG=true');

// Ensure Laravel uses /tmp for all writable directories
$tmpPath = '/tmp/storage';
$storagePaths = [
    "$tmpPath/framework/views",
    "$tmpPath/framework/sessions",
    "$tmpPath/framework/cache",
    "$tmpPath/logs",
    "$tmpPath/framework/views/livewire",
    "$tmpPath/framework/cache/livewire",
];

foreach ($storagePaths as $path) {
    if (!is_dir($path)) { mkdir($path, 0777, true); }
}

// Set necessary environment variables for Serverless
putenv("VIEW_COMPILED_PATH=$tmpPath/framework/views");
putenv("SESSION_DRIVER=cookie");
putenv("LOG_CHANNEL=stderr");
putenv("LIVEWIRE_MANIFEST_PATH=$tmpPath/framework/cache/livewire-components.php");

// Silence persistent tempnam warnings globally on Vercel
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

// Forward Vercel requests to the normal Laravel index.php
require __DIR__ . '/../public/index.php';
