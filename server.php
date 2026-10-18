<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    if (in_array($ext, ['woff', 'woff2', 'ttf', 'eot', 'svg'])) {
        $mimes = [
            'woff2' => 'font/woff2',
            'woff'  => 'font/woff',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            'svg'   => 'image/svg+xml'
        ];
        header("Content-Type: " . $mimes[$ext]);
        header("Access-Control-Allow-Origin: *");
        readfile(__DIR__.'/public'.$uri);
        return true;
    }
    return false;
}

require_once __DIR__.'/index.php';
