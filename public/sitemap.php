<?php
/**
 * Sitemap generator - outputs XML sitemap at /sitemap.xml
 * Routed via router.php
 */

require __DIR__ . '/../src/bootstrap.php';

// Determine base URL
$config = $GLOBALS['config'] ?? require __DIR__ . '/../config/config.php';
$baseUrl = $config['app']['base_url'] ?? '';

// If base_url is empty, derive from current request (for dev server)
if (empty($baseUrl)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $baseUrl = $scheme . '://' . $host;
}

// Ensure no trailing slash
$baseUrl = rtrim($baseUrl, '/');

// Set XML content type
header('Content-Type: application/xml; charset=UTF-8');

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Helper to output a URL entry
function outputUrl(string $url, string $lastmod, string $changefreq, float $priority): void
{
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>' . "\n";
    echo '    <lastmod>' . htmlspecialchars($lastmod, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</lastmod>' . "\n";
    echo '    <changefreq>' . htmlspecialchars($changefreq, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</changefreq>' . "\n";
    echo '    <priority>' . htmlspecialchars(number_format($priority, 1), ENT_QUOTES | ENT_XML1, 'UTF-8') . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

// Current date for static pages (fallback)
$today = date('Y-m-d');

// --- Static pages ---
outputUrl($baseUrl . '/', $today, 'weekly', 1.0);
outputUrl($baseUrl . '/login.php', $today, 'monthly', 0.5);
outputUrl($baseUrl . '/register.php', $today, 'monthly', 0.5);
outputUrl($baseUrl . '/index.php', $today, 'weekly', 0.8);

// --- Dynamic listings ---
try {
    $pdo = DB::pdo();
    // Use the view v_active_listings which already filters for active, verified farms, etc.
    // We need the listing URL and the lastmod date (use published_at or created_at)
    $stmt = $pdo->prepare('
        SELECT id, name, COALESCE(published_at, created_at) AS lastmod_date
        FROM v_active_listings
        ORDER BY lastmod_date DESC
    ');
    $stmt->execute();
    $listings = $stmt->fetchAll();

    foreach ($listings as $listing) {
        $url = $baseUrl . '/listing.php?id=' . (int) $listing['id'];
        // Format lastmod as YYYY-MM-DD (sitemap spec)
        $lastmod = date('Y-m-d', strtotime($listing['lastmod_date']));
        outputUrl($url, $lastmod, 'daily', 0.8);
    }
} catch (Throwable $e) {
    // Log but don't break sitemap - static URLs already output
    error_log('Sitemap generation error: ' . $e->getMessage());
}

echo '</urlset>' . "\n";