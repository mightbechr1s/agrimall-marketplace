<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';

$pageTitle = 'Cookie Policy';
$activeNav = 'browse';
require __DIR__ . '/partials/header.php';
?>
<main id="main-content" class="flex-grow-1">
<style>
.legal-container {
    max-width: 1080px;
    margin: 0 auto;
    padding: 2rem;
}
@media (min-width: 768px) {
    .legal-container {
        padding: 3rem;
    }
}
.sticky-sidebar {
    position: sticky;
    top: 6rem;
    align-self: start;
}
.legal-body h2[id] {
    scroll-margin-top: 6rem;
}
.toc-link {
    display: block;
    padding: 0.5rem 0;
    font-size: 0.9rem;
    color: var(--ink-soft);
    text-decoration: none;
    border-left: 2px solid transparent;
    padding-left: 0.75rem;
    margin-left: -0.75rem;
}
.toc-link:hover {
    color: var(--ink);
}
.toc-link.is-active {
    color: var(--ink);
    font-weight: 600;
    border-left-color: var(--accent);
}
.legal-divider {
    border: 0;
    border-top: 1px solid var(--borders);
    margin: 2rem 0;
    opacity: 0.5;
}
.legal-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 1rem 0;
}
.legal-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}
.legal-table th,
.legal-table td {
    text-align: left;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--borders);
    vertical-align: top;
}
.legal-table th {
    font-weight: 600;
    color: var(--ink);
}
@media (min-width: 768px) {
    .sticky-sidebar {
        padding-left: 1.5rem;
        border-left: 1px solid var(--borders);
    }
}
</style>
<section class="legal-section">
    <div class="container legal-container">
        <div class="row g-4">
            <div class="col-md-8">
                <article class="legal-body">
                    <section class="legal-block" data-reveal>
                        <h2 id="what-are-cookies">1. What are cookies</h2>
                        <p>Cookies are small text files stored on your device when you visit a website. They help the site remember your preferences and improve your experience.</p>
                    </section>

                    <section class="legal-block" data-reveal>
                        <h2 id="cookies-we-use">2. Cookies we use</h2>
                        <p>We use only first-party cookies and <code>localStorage</code>. No third-party trackers, ad pixels, or analytics cookies unless you explicitly accept.</p>

                        <h3>Essential (always active)</h3>
                        <div class="legal-table-wrap">
                        <table class="legal-table">
                            <thead>
                                <tr><th>Name</th><th>Type</th><th>Purpose</th><th>Expires</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>PHPSESSID</code></td><td>Session cookie</td><td>Keeps you logged in across pages</td><td>Browser close</td></tr>
                                <tr><td><code>animal_lang</code></td><td>Persistent cookie</td><td>Remembers your language choice (EN/TL)</td><td>1 year</td></tr>
                            </tbody>
                        </table>
                        </div>

                        <h3>UI Preferences (<code>localStorage</code>)</h3>
                        <div class="legal-table-wrap">
                        <table class="legal-table">
                            <thead>
                                <tr><th>Key</th><th>Purpose</th><th>Expires</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>animCk</code></td><td>Remembers you accepted the cookie banner</td><td>No expiry (until cleared)</td></tr>
                                <tr><td><code>animal_theme</code></td><td>Remembers light/dark preference</td><td>No expiry</td></tr>
                                <tr><td><code>animations</code></td><td>Remembers whether animations are on or off</td><td>No expiry</td></tr>
                            </tbody>
                        </table>
                        </div>

                        <h3>Analytics (optional, opt-in)</h3>
                        <p>If you accept analytics via the cookie banner, we set a self-hosted, cookie-free counter that aggregates page views without identifying you. No Google Analytics, Meta Pixel, or third-party scripts.</p>
                    </section>

                    <section class="legal-block" data-reveal>
                        <h2 id="the-cookie-banner">3. The cookie banner</h2>
                        <p>On your first visit, a banner appears at the bottom of the page. It explains what we use and links to this policy. Clicking "Accept" sets <code>localStorage.animCk = "1"</code> and hides the banner. You can clear this anytime in your browser settings to see the banner again.</p>
                        <p>If you have "Do Not Track" or "Reduce Motion" enabled, the banner still appears but without animation.</p>
                    </section>

                    <section class="legal-block" data-reveal>
                        <h2 id="managing-cookies">4. Managing cookies</h2>
                        <ul>
                            <li><strong>Browser settings:</strong> Block or delete cookies (may log you out and reset language).</li>
                            <li><strong>localStorage:</strong> Clear site data in dev tools → Application → Local Storage.</li>
                            <li><strong>Re-show banner:</strong> Delete <code>animCk</code> from localStorage and reload.</li>
                        </ul>
                    </section>

                    <section class="legal-block" data-reveal>
                        <h2 id="no-third-party-cookies">5. No third-party cookies</h2>
                        <p>We do not use Google Analytics, Facebook Pixel, TikTok Pixel, or any third-party advertising/tracking cookies. All analytics (if enabled) are self-hosted and anonymous.</p>
                    </section>

                    <section class="legal-block" data-reveal>
                        <h2 id="changes">6. Changes</h2>
                        <p>We may update this policy. Changes will be posted here with a new date. Continued use after changes constitutes acceptance.</p>
                    </section>

                    <section class="legal-block" data-reveal>
                        <h2 id="contact">7. Contact</h2>
                        <p>Questions about cookies? Email <a href="mailto:support@animal.ph">support@animal.ph</a>.</p>
                    </section>
                </article>
            </div>
            <div class="col-md-4">
                <aside class="sticky-sidebar">
                    <nav class="toc">
                        <h5 class="mb-3">Table of Contents</h5>
                        <ul class="list-unstyled">
                            <li><a href="#what-are-cookies" class="toc-link">1. What are cookies</a></li>
                            <li><a href="#cookies-we-use" class="toc-link">2. Cookies we use</a></li>
                            <li><a href="#the-cookie-banner" class="toc-link">3. The cookie banner</a></li>
                            <li><a href="#managing-cookies" class="toc-link">4. Managing cookies</a></li>
                            <li><a href="#no-third-party-cookies" class="toc-link">5. No third-party cookies</a></li>
                            <li><a href="#changes" class="toc-link">6. Changes</a></li>
                            <li><a href="#contact" class="toc-link">7. Contact</a></li>
                        </ul>
                    </nav>
                    <div class="mt-4">
                        <p class="text-center"><small>Legal Support: <a href="mailto:support@animal.ph">support@animal.ph</a></small></p>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>