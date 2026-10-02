<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));


/*
|--------------------------------------------------------------------------
| Check If Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is maintenance / demo mode via the "down" command we
| will require this file so that any prerendered template can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists(__DIR__.'/casino/storage/framework/maintenance.php')) {
    require __DIR__.'/casino/storage/framework/maintenance.php';
}

/*
|--------------------------------------------------------------------------
| Serve Static Assets With Correct MIME Types
|--------------------------------------------------------------------------
|
| Under the PHP built-in server this file is used as the router, so every
| request (including .svg/.css/.js) would otherwise be handed to Laravel and
| returned as text/html. Browsers refuse to render images served that way.
| When the request maps to a real asset, stream it directly with the right
| Content-Type. PHP source and dotfiles are never served.
|
*/

if (PHP_SAPI === 'cli-server' && isset($_SERVER['REQUEST_URI'])) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET' || $method === 'HEAD') {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = rawurldecode($path);
        if (strpos($path, '..') === false && strpos($path, "\0") === false) {
            $assetTypes = [
                'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
                'avif' => 'image/avif', 'ico' => 'image/x-icon', 'css' => 'text/css',
                'js' => 'application/javascript', 'mjs' => 'application/javascript',
                'json' => 'application/json', 'map' => 'application/json',
                'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
                'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject',
                'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg',
                'wasm' => 'application/wasm', 'txt' => 'text/plain', 'xml' => 'application/xml',
                'pdf' => 'application/pdf', 'webmanifest' => 'application/manifest+json',
            ];
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $allowedDirs = ['frontend/', 'games/', 'js/', 'css/', 'img/', 'images/', 'icons/', 'assets/', 'uploads/', 'storage/', 'minimal/'];
            $allowedRootFiles = ['manifest.json', 'favicon.ico', 'robots.txt', 'sw.js'];
            $relative = ltrim($path, '/');
            $inAllowedDir = false;
            foreach ($allowedDirs as $dir) {
                if (strpos($relative, $dir) === 0) { $inAllowedDir = true; break; }
            }
            $isAllowedRootFile = in_array(strtolower($relative), $allowedRootFiles, true);
            if (isset($assetTypes[$ext]) && ($inAllowedDir || $isAllowedRootFile)) {
                $file = realpath(__DIR__ . $path);
                if ($file !== false && is_file($file)
                    && strpos($file, __DIR__ . DIRECTORY_SEPARATOR) === 0
                    && basename($file)[0] !== '.') {
                    header('Content-Type: ' . $assetTypes[$ext]);
                    header('Content-Length: ' . filesize($file));
                    header('Cache-Control: public, max-age=604800');
                    if ($method === 'HEAD') {
                        exit;
                    }
                    readfile($file);
                    exit;
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/casino/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/casino/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = tap($kernel->handle(
    $request = Request::capture()
))->send();

$kernel->terminate($request, $response);
