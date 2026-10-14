<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';

$pageTitle = 'Cookie Policy';
$activeNav = 'browse';
require __DIR__ . '/partials/header.php';
?>
<main id="main-content" class="flex-grow-1">
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
                        <p>We use only first-party cookies and <code>localStorage</code>. There are no third-party trackers, ad pixels, or analytics scripts at all, whether you accept or decline.</p>

                        <h3>Essential (always active)</h3>
                        <div class="legal-table-wrap">
                        <table class="legal-table">
                            <thead>
                                <tr><th>Name</th><th>Type</th><th>Purpose</th><th>Expires</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>ANIMAL_SESSID</code></td><td>Session cookie</td><td>Keeps you logged in across pages</td><td>30 minutes after your last activity</td></tr>
                                <tr><td><code>animal_remember</code></td><td>Persistent cookie</td><td>Keeps you signed in for 30 days when you select &ldquo;Remember me&rdquo; on login</td><td>30 days</td></tr>
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
                                <tr><td><code>animCk</code></td><td>Remembers your cookie banner choice: <code>"1"</code> accepted, <code>"0"</code> declined</td><td>No expiry (until cleared)</td></tr>
                                <tr><td><code>animal_theme</code></td><td>Remembers light/dark preference</td><td>No expiry</td></tr>
                                <tr><td><code>animations</code></td><td>Remembers whether animations are on or off</td><td>No expiry</td></tr>
                            </tbody>
                        </table>
                        </div>

                        <h3>Optional features (opt-in)</h3>
                        <p>None are enabled today. This site contains no analytics counter, no page-view tracking, and no third-party script, so accepting and declining produce the same behaviour: the site works exactly the same either way. The banner's choice is still recorded as <code>animCk</code>, and that flag is the gate any optional feature would have to pass before it could load.</p>
                    </section>

                    <section class="legal-block" data-reveal>
                        <h2 id="the-cookie-banner">3. The cookie banner</h2>
                        <p>On your first visit, a banner appears at the bottom of the page. It explains what we use and links to this policy. Clicking &ldquo;Accept&rdquo; sets <code>localStorage.animCk = "1"</code>; clicking &ldquo;Decline&rdquo; sets <code>localStorage.animCk = "0"</code>. Either way the banner stays dismissed. Declining is safe: no optional feature exists to turn off, and essential cookies keep working. Clear the key in your browser's site data to see the banner again.</p>
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
                        <p>We do not use Google Analytics, Facebook Pixel, TikTok Pixel, or any third-party advertising/tracking cookies, and we do not run a self-hosted analytics counter either. No page-view or behaviour tracking exists in this codebase.</p>
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