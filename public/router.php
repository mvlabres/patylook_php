<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in web server (local development only):
 *
 *     php -S localhost:8080 -t public public/router.php
 *
 * Existing files (CSS, JS) are served as they are; everything else goes to the front controller,
 * the same way .htaccess does on Apache.
 */

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false; // let the built-in server send the static file
}
require __DIR__ . '/index.php';
