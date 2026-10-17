<?php
/**
 * PHP built-in server router (php -S localhost:8000 -t public router.php).
 * Only handles requests that would otherwise 404; returns false for existing files.
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$requestPath = urldecode($requestPath);

// Routes handled by PHP scripts instead of static files
$specialRoutes = [
    '/sitemap.xml' => __DIR__ . '/sitemap.php',
];
// Numbered child sitemaps behind the sitemap index (50k URLs per file).
$specialRoutes += array_combine(
    array_map(fn($n) => "/sitemap-$n.xml", range(1, 50)),
    array_fill(0, 50, __DIR__ . '/sitemap.php')
) ?: [];

// Serve an SVG favicon for the default /favicon.ico request
if ($requestPath === '/favicon.ico') {
    $favicon = __DIR__ . '/assets/branding/mark.svg';
    if (is_file($favicon)) {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        readfile($favicon);
        return true;
    }
}

if (isset($specialRoutes[$requestPath])) {
    require $specialRoutes[$requestPath];
    return true;
}

$documentRoot = __DIR__;
$requestedFile = $documentRoot . $requestPath;

// Normalize path (resolve . and ..)
$requestedFile = realpath($requestedFile) ?: $requestedFile;

if (is_file($requestedFile) && str_starts_with($requestedFile, realpath($documentRoot) . DIRECTORY_SEPARATOR)) {
    // Static assets: served with Cache-Control
    $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
    $staticExtensions = ['css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'ico', 'json', 'txt', 'woff', 'woff2'];

    // dotfiles (.htaccess, .env, backups) must not be served
    if (str_starts_with(basename($requestedFile), '.')) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return true;
    }

    // uploads/ serves static assets only: the built-in server ignores .htaccess
    $uploadsRoot = realpath($documentRoot . '/uploads');
    $isUpload = $uploadsRoot !== false
        && str_starts_with($requestedFile, $uploadsRoot . DIRECTORY_SEPARATOR);
    if ($isUpload && !in_array($ext, $staticExtensions, true)) {
        http_response_code(403);
        exit('Forbidden');
    }

    if (in_array($ext, $staticExtensions, true)) {
        // Skip caching for robots.txt and sitemap (special routes)
        $basename = basename($requestedFile);
        if ($basename !== 'robots.txt' && $basename !== 'sitemap.xml') {
            header('Cache-Control: public, max-age=86400');
        }
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'json' => 'application/json',
            'txt' => 'text/plain',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        readfile($requestedFile);
        return true;
    }
    // PHP and other files: let the built-in server handle them
    return false;
}

$indexFile = $requestedFile . '/index.php';
if (is_dir($requestedFile) && is_file($indexFile)) {
    return false;
}

// Fallback: custom 404 page
http_response_code(404);
require __DIR__ . '/404.php';
return true;