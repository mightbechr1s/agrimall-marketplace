<?php
/**
 * Sitemap generator.
 *
 *   /sitemap.xml     -> <sitemapindex> listing the child sitemaps
 *   /sitemap-N.xml   -> <urlset> with that chunk's URLs
 *
 * The sitemaps.org protocol caps one sitemap at 50,000 URLs / 50MB, and
 * crawlers do not follow "?page=" links inside XML, so an unbounded
 * <urlset> cannot be paginated in place - it has to be split into numbered
 * files behind an index. Chunk 1 also carries the static pages.
 */
declare(strict_types=1);

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

/** Protocol maximum URLs per sitemap file. */
const SITEMAP_MAX_URLS = 50000;

$xml = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

/** Static, low-churn pages. Always in chunk 1. */
function staticUrls(string $baseUrl): array
{
    return [
        [$baseUrl . '/',         'weekly',  1.0],
        [$baseUrl . '/index.php', 'weekly',  0.8],
        [$baseUrl . '/login.php', 'monthly', 0.5],
        [$baseUrl . '/register.php', 'monthly', 0.5],
    ];
}

/** Total count of listable listings, or null if the DB cannot be reached. */
function countListings(): ?int
{
    try {
        $pdo = DB::pdo();
        return (int) $pdo->query('SELECT COUNT(*) FROM v_active_listings')->fetchColumn();
    } catch (Throwable $e) {
        app_log_exception($e, 'sitemap_count');
        return null;
    }
}

/** One chunk of listing rows, newest first, as [id, lastmod YYYY-MM-DD]. */
function listingChunk(int $chunk, int $perChunk): array
{
    $offset = ($chunk - 1) * $perChunk;
    try {
        $pdo = DB::pdo();
        // Keyset paging: OFFSET would re-scan the whole result set for every
        // chunk, which is what makes large sitemaps expensive to generate.
        $stmt = $pdo->prepare(
            'SELECT id, COALESCE(published_at, created_at) AS lastmod_date
             FROM v_active_listings
             ORDER BY COALESCE(published_at, created_at) DESC, id DESC
             LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue(':lim', $perChunk, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [(int) $r['id'], date('Y-m-d', strtotime($r['lastmod_date']))];
        }
        return $out;
    } catch (Throwable $e) {
        // Log only: never surface DB detail in a crawler-facing response.
        app_log_exception($e, 'sitemap_chunk');
        return [];
    }
}

header('Content-Type: application/xml; charset=UTF-8');

// ---- Child sitemap: /sitemap-N.xml -------------------------------------
if (preg_match('/^\/sitemap-(\d+)\.xml$/', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', $m)) {
    $chunk = max(1, (int) $m[1]);
    $total = countListings();
    // Chunk 1 also holds the static pages, so it is smaller on listings.
    $perChunk = $chunk === 1 ? SITEMAP_MAX_URLS - count(staticUrls($baseUrl)) : SITEMAP_MAX_URLS;
    $listings = listingChunk($chunk, max(1, $perChunk));

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    if ($chunk === 1) {
        $today = date('Y-m-d');
        foreach (staticUrls($baseUrl) as [$url, $freq, $prio]) {
            printf("  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>\n",
                $xml($url), $today, $freq, number_format($prio, 1));
        }
    }
    foreach ($listings as [$id, $lastmod]) {
        printf("  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n    <changefreq>daily</changefreq>\n    <priority>0.8</priority>\n  </url>\n",
            $xml($baseUrl . '/listing.php?id=' . $id), $lastmod);
    }
    echo '</urlset>' . "\n";
    exit;
}

// ---- Index: /sitemap.xml -------------------------------------------------
$total = countListings();
$staticCount = count(staticUrls($baseUrl));
// If listings exceed one full chunk, the static pages ride along in chunk 1.
$listingChunks = $total === null
    ? 1
    : max(1, (int) ceil(($total + ($total > SITEMAP_MAX_URLS ? $staticCount : 0)) / SITEMAP_MAX_URLS));

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
$today = date('Y-m-d');
for ($i = 1; $i <= $listingChunks; $i++) {
    printf("  <sitemap>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n  </sitemap>\n",
        $xml($baseUrl . '/sitemap-' . $i . '.xml'), $today);
}
echo '</sitemapindex>' . "\n";