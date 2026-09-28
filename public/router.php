<?php
/**
 * PHP built-in server router.
 * Used with: php -S localhost:8000 -t public router.php
 *
 * This router ONLY handles requests that would otherwise 404.
 * For existing files (assets, PHP pages), it returns false to let
 * the built-in server serve them normally.
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$requestPath = urldecode($requestPath);

// Map of special routes that should be handled by PHP scripts
$specialRoutes = [
    '/sitemap.xml' => __DIR__ . '/sitemap.php',
];

// Serve an SVG favicon for browsers that request the default /favicon.ico
if ($requestPath === '/favicon.ico') {
    $favicon = __DIR__ . '/assets/branding/mark.svg';
    if (is_file($favicon)) {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        readfile($favicon);
        return true;
    }
}

// Check if this is a special route
if (isset($specialRoutes[$requestPath])) {
    require $specialRoutes[$requestPath];
    return true;
}

// Check if the requested file exists in the document root
$documentRoot = __DIR__;
$requestedFile = $documentRoot . $requestPath;

// Normalize path (resolve . and ..)
$requestedFile = realpath($requestedFile) ?: $requestedFile;

// If the file exists and is within document root
if (is_file($requestedFile) && str_starts_with($requestedFile, realpath($documentRoot) . DIRECTORY_SEPARATOR)) {
    // Static assets: serve with Cache-Control headers
    $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
    $staticExtensions = ['css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'ico', 'json', 'txt', 'woff', 'woff2'];

    // Never expose dotfiles (.htaccess, .env, editor backups)
    if (str_starts_with(basename($requestedFile), '.')) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        return true;
    }

    // Deny script execution inside uploads/. Apache's .htaccess is ignored by the
    // built-in server, so mirror it here: uploads may only serve static assets.
    $uploadsRoot = realpath($documentRoot . '/uploads');
    $isUpload = $uploadsRoot !== false
        && str_starts_with($requestedFile, $uploadsRoot . DIRECTORY_SEPARATOR);
    if ($isUpload && !in_array($ext, $staticExtensions, true)) {
        http_response_code(403);
        exit('Forbidden');
    }

    if (in_array($ext, $staticExtensions, true)) {
        // Skip caching for robots.txt and sitemap (handled by special routes)
        $basename = basename($requestedFile);
        if ($basename !== 'robots.txt' && $basename !== 'sitemap.xml') {
            header('Cache-Control: public, max-age=86400');
        }
        // Serve the file
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
    // PHP files and other files: let built-in server handle
    return false;
}

// Check if it's a directory with index.php
$indexFile = $requestedFile . '/index.php';
if (is_dir($requestedFile) && is_file($indexFile)) {
    return false;
}

// Handle 404 - render custom 404 page
http_response_code(404);
require __DIR__ . '/404.php';
return true;