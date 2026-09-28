<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';

$pageTitle = 'Privacy Policy';
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
    color: var(--text);
}
.toc-link.is-active {
    color: var(--text);
    font-weight: 600;
    border-left-color: var(--accent);
}
.legal-divider {
    border: 0;
    border-top: 1px solid var(--borders);
    margin: 2rem 0;
    opacity: 0.5;
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
                        <h2 id="who-we-are">1. Who we are</h2>
                        <p>AniMAL ("we", "our", "us") operates this Philippine agricultural marketplace at the domain you are visiting. Our registered office is in the Philippines. You can contact us at <a href="mailto:support@animal.ph">support@animal.ph</a>.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="what-data-we-collect">2. What data we collect</h2>
                        <p>We only collect data you give us or that is necessary for the service to function:</p>
                        <ul>
                            <li><strong>Account data:</strong> Name, email, password hash, phone number, role (farmer/buyer), and verification documents (government ID, farm photos) for farmers.</li>
                            <li><strong>Listing data:</strong> Product names, descriptions, prices, quantities, categories, provinces, images you upload, and organic certification flags.</li>
                            <li><strong>Transaction data:</strong> Order details, messages between buyers and sellers, pickup/delivery preferences.</li>
                            <li><strong>Technical data:</strong> IP address, browser type, device info, pages visited, referral source — via standard web server logs and our own analytics (no third-party trackers; we use a self-hosted, cookie-free counter).</li>
                            <li><strong>Preferences:</strong> Language choice (EN/TL), UI theme, dismissed banner states — stored in <code>localStorage</code> and a first-party cookie (<code>animal_lang</code>).</li>
                        </ul>
                    </section>

                    <section class="legal-block" data-reveal>
                        <h2 id="why-we-collect-it-lawful-bases">3. Why we collect it (lawful bases)</h2>
                        <ul>
                            <li><strong>Contract performance:</strong> To create your account, verify farmers, publish listings, and facilitate buyer–seller communication.</li>
                            <li><strong>Legal obligation:</strong> To comply with the Philippine Data Privacy Act (RA 10173) and tax regulations (e.g., issuing receipts).</li>
                            <li><strong>Legitimate interest:</strong> Platform security, fraud prevention, improving the marketplace experience, and anonymous aggregate analytics.</li>
                            <li><strong>Consent:</strong> Optional analytics cookies (you can decline via the cookie banner).</li>
                        </ul>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="sharing-your-data">4. Sharing your data</h2>
                        <p>We do not sell your personal data. We share it only:</p>
                        <ul>
                            <li>With the other party in a transaction (buyer ↔ farmer) — name, farm name, contact info, order details.</li>
                            <li>With service providers who host the database, send transactional emails, or process uploads — under strict data processing agreements.</li>
                            <li>When required by law or to protect rights/safety.</li>
                        </ul>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="data-retention">5. Data retention</h2>
                        <ul>
                            <li>Account & verification data: kept while your account is active, plus 3 years after closure for legal compliance.</li>
                            <li>Listings & orders: kept for 5 years for tax and dispute resolution.</li>
                            <li>Server logs & anonymous analytics: 90 days.</li>
                        </ul>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="your-rights-data-privacy-act">6. Your rights (Data Privacy Act)</h2>
                        <p>You have the right to:</p>
                        <ul>
                            <li><strong>Access</strong> — request a copy of the personal data we hold about you.</li>
                            <li><strong>Rectification</strong> — correct inaccurate data.</li>
                            <li><strong>Erasure</strong> — delete your account and associated data (see below).</li>
                            <li><strong>Restriction</strong> — limit processing in certain cases.</li>
                            <li><strong>Portability</strong> — receive your data in a machine-readable format.</li>
                            <li><strong>Object</strong> — to processing based on legitimate interest.</li>
                        </ul>
                        <p>To exercise any right, email <a href="mailto:support@animal.ph">support@animal.ph</a> with "Privacy Request" in the subject. We respond within 30 days.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="deleting-your-account">7. Deleting your account</h2>
                        <p>Logged-in users can delete their account from <a href="dashboard.php">Dashboard → Settings → Delete Account</a>. This permanently removes your profile, listings, messages, and uploaded images within 30 days. Order records are anonymized but retained for legal compliance.</p>
                    </section>

                    <section class="legal-block" data-reveal>
                        <h2 id="cookies-localstorage">8. Cookies & localStorage</h2>
                        <p>See our <a href="cookies.php">Cookie Policy</a> for details. In summary:</p>
                        <ul>
                            <li><strong>Essential session cookie</strong> (ANIMAL_SESSID) — keeps you logged in; expires 30 minutes after your last activity.</li>
                            <li><strong>Remember-me cookie</strong> (<code>animal_remember</code>) — only set when you select &ldquo;Remember me&rdquo; on login; signs you back in for 30 days. Clearing it signs you out permanently.</li>
                            <li><strong>Language cookie</strong> (<code>animal_lang</code>) — remembers EN/TL choice for 1 year.</li>
                            <li><strong>localStorage</strong> — UI preferences (dismissed banners, theme); no expiry.</li>
                            <li><strong>Optional analytics</strong> — only if you accept via the banner.</li>
                        </ul>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="security">9. Security</h2>
                        <p>We use HTTPS everywhere, bcrypt password hashing, prepared SQL statements, CSRF tokens on forms, and role-based access control. No system is 100% secure; we encourage strong passwords and prompt reporting of suspicious activity.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="changes-to-this-policy">10. Changes to this policy</h2>
                        <p>We may update this policy. Material changes will be announced on the site and via email (if you have an account). Continued use after the effective date constitutes acceptance.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="contact">11. Contact</h2>
                        <p>Questions? Email <a href="mailto:support@animal.ph">support@animal.ph</a> or write to our registered office.</p>
                    </section>
                </article>
            </div>
            <div class="col-md-4">
                <aside class="sticky-sidebar">
                    <nav class="toc">
                        <h5 class="mb-3">Table of Contents</h5>
                        <ul class="list-unstyled">
                            <li><a href="#who-we-are" class="toc-link">1. Who we are</a></li>
                            <li><a href="#what-data-we-collect" class="toc-link">2. What data we collect</a></li>
                            <li><a href="#why-we-collect-it-lawful-bases" class="toc-link">3. Why we collect it (lawful bases)</a></li>
                            <li><a href="#sharing-your-data" class="toc-link">4. Sharing your data</a></li>
                            <li><a href="#data-retention" class="toc-link">5. Data retention</a></li>
                            <li><a href="#your-rights-data-privacy-act" class="toc-link">6. Your rights (Data Privacy Act)</a></li>
                            <li><a href="#deleting-your-account" class="toc-link">7. Deleting your account</a></li>
                            <li><a href="#cookies-localstorage" class="toc-link">8. Cookies & localStorage</a></li>
                            <li><a href="#security" class="toc-link">9. Security</a></li>
                            <li><a href="#changes-to-this-policy" class="toc-link">10. Changes to this policy</a></li>
                            <li><a href="#contact" class="toc-link">11. Contact</a></li>
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