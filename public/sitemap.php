<?php
/**
 * Sitemap generator - outputs XML at /sitemap.xml (routed via router.php).
 */

require __DIR__ . '/../src/bootstrap.php';

$config = $GLOBALS['config'] ?? require __DIR__ . '/../config/config.php';
$baseUrl = $config['app']['base_url'] ?? '';

// Empty base_url: derive from the current request (dev server)
if (empty($baseUrl)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $baseUrl = $scheme . '://' . $host;
}

$baseUrl = rtrim($baseUrl, '/');

header('Content-Type: application/xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Outputs one <url> entry
function outputUrl(string $url, string $lastmod, string $changefreq, float $priority): void
{
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>' . "\n";
    echo '    <lastmod>' . htmlspecialchars($lastmod, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</lastmod>' . "\n";
    echo '    <changefreq>' . htmlspecialchars($changefreq, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</changefreq>' . "\n";
    echo '    <priority>' . htmlspecialchars(number_format($priority, 1), ENT_QUOTES | ENT_XML1, 'UTF-8') . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

// Current date, used for static pages
$today = date('Y-m-d');

// --- Static pages ---
outputUrl($baseUrl . '/', $today, 'weekly', 1.0);
outputUrl($baseUrl . '/login.php', $today, 'monthly', 0.5);
outputUrl($baseUrl . '/register.php', $today, 'monthly', 0.5);
outputUrl($baseUrl . '/index.php', $today, 'weekly', 0.8);

// --- Dynamic listings ---
try {
    $pdo = DB::pdo();
    // v_active_listings filters to active verified farms; lastmod falls back to created_at
    $stmt = $pdo->prepare('
        SELECT id, name, COALESCE(published_at, created_at) AS lastmod_date
        FROM v_active_listings
        ORDER BY lastmod_date DESC
    ');
    $stmt->execute();
    $listings = $stmt->fetchAll();

    foreach ($listings as $listing) {
        $url = $baseUrl . '/listing.php?id=' . (int) $listing['id'];
        // Sitemap spec wants YYYY-MM-DD
        $lastmod = date('Y-m-d', strtotime($listing['lastmod_date']));
        outputUrl($url, $lastmod, 'daily', 0.8);
    }
} catch (Throwable $e) {
    // Log only: static URLs are already output
    error_log('Sitemap generation error: ' . $e->getMessage());
}

echo '</urlset>' . "\n";