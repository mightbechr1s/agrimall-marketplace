<?php
/**
 * Custom 500 page - self-contained, no bootstrap.php (avoids cascading failures).
 */

// Support reference code: short hash from time
$refCode = substr(md5((string) microtime(true) . (string) random_bytes(4)), 0, 8);

// Handler may pass a reference code in the query string
$displayMessage = '';
if (isset($_GET['ref']) && ctype_alnum($_GET['ref'])) {
    $refCode = htmlspecialchars($_GET['ref'], ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Server Error | AniMAL</title>
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary: #1B3B2B;
            --primary-dark: #152D22;
            --muted: #5C5F63;
            --bg: #FAF8F5;
            --card-bg: #FFFFFF;
            --borders: #E5E2D9;
        }
        body { background: var(--bg); font-family: 'Plus Jakarta Sans', -apple-system, 'Segoe UI', system-ui, sans-serif; }
        .error-500 { font-size: 6rem; font-weight: 800; color: var(--primary); line-height: 1; letter-spacing: -0.02em; }
        .card { border: 1px solid var(--borders); background: var(--card-bg); border-radius: 0; box-shadow: none; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        .ref-code { font-family: 'JetBrains Mono', 'SF Mono', 'Monaco', 'Inconsolata', monospace; font-size: 0.875rem; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <!-- Minimal header -->
    <header class="py-3 border-bottom bg-white">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="/" class="text-decoration-none fw-bold fs-4 text-primary">AniMAL</a>
        </div>
    </header>

    <main class="flex-grow-1 d-flex align-items-center justify-content-center py-5">
        <div class="container">
            <div class="card p-5 p-md-5 text-center" style="max-width: 480px; margin: 0 auto;">
                <div class="error-500 mb-3" aria-hidden="true">500</div>
                <h1 class="h3 fw-bold mb-3">Something went wrong</h1>
                <p class="text-muted mb-4">
                    We're sorry — an unexpected error occurred. Our team has been notified.
                </p>

                <div class="alert alert-light border mb-4 p-3">
                    <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                        <span class="text-muted small">Reference:</span>
                        <code class="ref-code bg-white px-2 py-1 border"><?= $refCode ?></code>
                    </div>
                    <p class="text-muted small mb-0 mt-2">Please share this code if you contact support.</p>
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                    <a href="/" class="btn btn-primary">
                        <svg width="16" height="16" class="me-2" fill="currentColor" viewBox="0 0 16 16"><path d="M8.354 1.146a.5.5 0 0 0-.708 0l-6 6a.5.5 0 0 0 .708.708L8 2.707l5.646 5.647a.5.5 0 0 0 .708-.708l-6-6z"/></svg>
                        Go Home
                    </a>
                    <a href="mailto:support@animal.ph?subject=Error%20Ref%20<?= $refCode ?>" class="btn btn-outline-primary">
                        <svg width="16" height="16" class="me-2" fill="currentColor" viewBox="0 0 16 16"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 13.116l4.708-2.897L1 5.383v7.733Z"/></svg>
                        Contact Support
                    </a>
                </div>

                <p class="text-muted small mt-4 mb-0">
                    Try refreshing the page or <a href="/" class="text-decoration-none">returning home</a>.
                </p>
            </div>
        </div>
    </main>

    <footer class="py-4 border-top bg-white">
        <div class="container text-center text-muted small">
            &copy; <?= date('Y') ?> AniMAL. All rights reserved.
        </div>
    </footer>

    <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>