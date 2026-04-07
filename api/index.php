<?php

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Force APP_DEBUG for Vercel troubleshooting
$_ENV['APP_DEBUG'] = 'true';
putenv('APP_DEBUG=true');

// Ensure Laravel uses /tmp for all writable directories
$tmpPath = '/tmp';
$viewPath = "$tmpPath/views";
$cachePath = "$tmpPath/cache";
$sessionPath = "$tmpPath/sessions";

foreach ([$viewPath, $cachePath, $sessionPath] as $path) {
    if (!is_dir($path)) { mkdir($path, 0777, true); }
}

putenv("VIEW_COMPILED_PATH=$viewPath");
putenv("SESSION_DRIVER=cookie");
putenv("LOG_CHANNEL=stderr");
putenv("LIVEWIRE_MANIFEST_PATH=$cachePath/livewire-components.php");

// Forward Vercel requests to the normal Laravel index.php
require __DIR__ . '/../public/index.php';
