<?php
declare(strict_types=1);

// Stand-in for Vercel's routing, used only by tests/vercel-sim.sh with `php -S`:
// real files in public/ are served as they are (never .php ones), everything else goes to the function in api/index.php.
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if ($path !== '/' && !str_ends_with($path, '.php')) {
    $file = realpath($root . '/public' . $path);
    if ($file !== false && str_starts_with($file, $root . '/public/') && is_file($file)) {
        return false;
    }
}
require $root . '/api/index.php';
