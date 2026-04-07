<?php

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Force APP_DEBUG for Vercel troubleshooting
$_ENV['APP_DEBUG'] = 'true';
putenv('APP_DEBUG=true');

// Forward Vercel requests to the normal Laravel index.php
require __DIR__ . '/../public/index.php';
