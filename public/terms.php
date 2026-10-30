<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';

$pageTitle = 'Terms of Service';
$activeNav = 'browse';
require __DIR__ . '/partials/header.php';
?>
<main id="main-content" class="flex-grow-1">
<section class="legal-section">
    <div class="container legal-container">
        <h1 class="mb-4"><?= e($pageTitle) ?></h1>
        <div class="row g-4">
            <div class="col-md-8">
                <article class="legal-body">
                    <section class="legal-block" data-reveal>
                        <h2 id="agreement">1. Agreement</h2>
                        <p>By accessing or using AgriMALL (the "Platform"), you agree to these Terms. If you do not agree, do not use the Platform.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="eligibility">2. Eligibility</h2>
                        <p>You must be at least 18 years old and capable of forming binding contracts. Farmers must be legally authorized to sell agricultural products in the Philippines.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="accounts">3. Accounts</h2>
                        <ul>
                            <li>You are responsible for your credentials and all activity under your account.</li>
                            <li>Farmers must complete verification (valid ID, farm photos) before listing.</li>
                            <li>One account per person/farm. We may suspend accounts for fraud, abuse, or repeated policy violations.</li>
                        </ul>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="listings-content">4. Listings & Content</h2>
                        <ul>
                            <li>Farmers set their own prices, quantities, and descriptions. Listings must be accurate, current, and your own produce.</li>
                            <li>No prohibited items: controlled substances, stolen goods, mislabeled products, or anything illegal under Philippine law.</li>
                            <li>Images must be of the actual product. Stock/generic photos are not allowed for specific listings.</li>
                            <li>We may remove listings that violate these terms or appear misleading.</li>
                        </ul>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="transactions">5. Transactions</h2>
                        <ul>
                            <li>AgriMALL connects buyers and sellers; we are not a party to the sale.</li>
                            <li>Payment, delivery, and pickup are arranged directly between you and the other party.</li>
                            <li>We do not handle funds, offer escrow, or guarantee transactions (online payments coming soon).</li>
                            <li>Disputes: message the other party first. If unresolved, contact <a href="mailto:support@agrimall.ph">support@agrimall.ph</a>.</li>
                        </ul>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="refund-policy">6. Refund Policy</h2>
                        <p>As AgriMALL does not handle payments, refunds are arranged directly between the buyer and the seller. We encourage buyers to contact the seller first if there is an issue with the order. If the seller does not respond within 48 hours or refuses to resolve the issue, the buyer may contact AgriMALL support for assistance. AgriMALL can help mediate, but cannot reverse a payment it never handled.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="fees">7. Fees</h2>
                        <p>Joining and listing are currently free. We may introduce fees (commissions, featured placement, payment processing) with 30 days' notice via email and on-site banner.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="intellectual-property">8. Intellectual Property</h2>
                        <p>You retain ownership of your content (photos, descriptions, farm name). By posting, you grant us a non-exclusive, royalty-free license to display, promote, and optimize your listings on the Platform.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="prohibited-conduct">9. Prohibited Conduct</h2>
                        <p>You may not:</p>
                        <ul>
                            <li>Impersonate others or misrepresent your farm/products.</li>
                            <li>Harvest data, scrape, or automate access without permission.</li>
                            <li>Post defamatory, obscene, or unlawful content.</li>
                            <li>Interfere with platform security or other users' accounts.</li>
                        </ul>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="disclaimers">10. Disclaimers</h2>
                        <ul>
                            <li>The Platform is provided "as is" without warranties of any kind.</li>
                            <li>We do not guarantee produce quality, seller reliability, or uninterrupted access.</li>
                            <li>Buyers assume risk for perishable goods; inspect upon receipt.</li>
                        </ul>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="limitation-liability">11. Limitation of Liability</h2>
                        <p>To the fullest extent permitted by law, AgriMALL is not liable for indirect, incidental, special, or consequential damages, or for any loss of profit, data, or goodwill arising from your use of the Platform.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="termination">12. Termination</h2>
                        <p>You may close your account anytime (Dashboard → Settings). We may suspend or terminate access for violations of these Terms. Provisions that should survive (IP, liability, disputes) will survive.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="governing-law">13. Governing Law</h2>
                        <p>These Terms are governed by the laws of the Republic of the Philippines. Disputes will be resolved in the courts of the Philippines.</p>
                    </section>

                    <hr class="legal-divider">

                    <section class="legal-block" data-reveal>
                        <h2 id="changes">14. Changes</h2>
                        <p>We may update these Terms. Material changes will be posted on this page with a new "Last updated" date. Continued use constitutes acceptance.</p>
                    </section>

                    <section class="legal-block" data-reveal>
                        <h2 id="contact">15. Contact</h2>
                        <p>Questions? Email <a href="mailto:support@agrimall.ph">support@agrimall.ph</a>.</p>
                    </section>
                </article>
            </div>
            <div class="col-md-4">
                <aside class="sticky-sidebar">
                    <nav class="toc">
                        <h5 class="mb-3">Table of Contents</h5>
                        <ul class="list-unstyled">
                            <li><a href="#agreement" class="toc-link">1. Agreement</a></li>
                            <li><a href="#eligibility" class="toc-link">2. Eligibility</a></li>
                            <li><a href="#accounts" class="toc-link">3. Accounts</a></li>
                            <li><a href="#listings-content" class="toc-link">4. Listings & Content</a></li>
                            <li><a href="#transactions" class="toc-link">5. Transactions</a></li>
                            <li><a href="#refund-policy" class="toc-link">6. Refund Policy</a></li>
                            <li><a href="#fees" class="toc-link">7. Fees</a></li>
                            <li><a href="#intellectual-property" class="toc-link">8. Intellectual Property</a></li>
                            <li><a href="#prohibited-conduct" class="toc-link">9. Prohibited Conduct</a></li>
                            <li><a href="#disclaimers" class="toc-link">10. Disclaimers</a></li>
                            <li><a href="#limitation-liability" class="toc-link">11. Limitation of Liability</a></li>
                            <li><a href="#termination" class="toc-link">12. Termination</a></li>
                            <li><a href="#governing-law" class="toc-link">13. Governing Law</a></li>
                            <li><a href="#changes" class="toc-link">14. Changes</a></li>
                            <li><a href="#contact" class="toc-link">15. Contact</a></li>
                        </ul>
                    </nav>
                    <div class="mt-4">
                        <p class="text-center"><small>Legal Support: <a href="mailto:support@agrimall.ph">support@agrimall.ph</a></small></p>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>