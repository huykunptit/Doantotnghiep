<?php
// Custom router for `php -c dev-php.ini -S 0.0.0.0:8000 dev-server.php`.
// Used by `composer serve`. Replaces Laravel's framework server.php which
// uses getcwd() incorrectly when launched outside the public/ directory.

$publicPath = __DIR__ . '/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

// If the URI maps to a real file under public/, let PHP's built-in server
// serve it directly with proper Content-Type (return false signals that).
if ($uri !== '/' && file_exists($publicPath . $uri) && !is_dir($publicPath . $uri)) {
    return false;
}

require_once $publicPath . '/index.php';
