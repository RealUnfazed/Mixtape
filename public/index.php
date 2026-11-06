<?php
declare(strict_types=1);

// Front controller for Apache, nginx and PHP's built-in server. Vercel uses api/index.php instead.

// PHP's built-in server: let it serve real files (CSS, JS, images) itself, but never run a .php file on its own.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    if (!str_ends_with($path, '.php') && is_file(__DIR__ . $path)) {
        return false;
    }
}

require __DIR__ . '/../src/app.php';
