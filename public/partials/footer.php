<?php
// Footer i18n is self-contained so this partial works on ANY page
// without depending on a $copy or $l variable from the including script.
$footerCopy = $copy['footer'] ?? [
    'en' => [
        'brand_tagline' => 'Philippine Agricultural Marketplace',
        'heading_market' => 'Market',
        'heading_for_farmers' => 'For farmers',
        'heading_company' => 'Company',
        'link_browse_listings' => 'Browse listings',
        'link_how_it_works' => 'How it works',
        'link_faq' => 'FAQ',
        'link_become_a_seller' => 'Become a seller',
        'link_seller_handbook' => 'Seller handbook',
        'link_verification' => 'Verification',
        'link_our_story' => 'Our story',
        'link_contact' => 'Contact',
        'link_privacy' => 'Privacy',
        'link_terms' => 'Terms',
    ],
    'tl' => [
        'brand_tagline' => 'Pamilihang Agrikultura ng Pilipinas',
        'heading_market' => 'Mercado',
        'heading_for_farmers' => 'Para sa mga magsasaka',
        'heading_company' => 'Kumpanya',
        'link_browse_listings' => 'Mag-browse',
        'link_how_it_works' => 'Paano ito gumagana',
        'link_faq' => 'FAQ',
        'link_become_a_seller' => 'Magbenta',
        'link_seller_handbook' => 'Seller handbook',
        'link_verification' => 'Verification',
        'link_our_story' => 'Ang kwento namin',
        'link_contact' => 'Contact us',
        'link_privacy' => 'Privacy',
        'link_terms' => 'Mga termino',
    ],
];
$f = function ($key) use ($footerCopy) { return t($footerCopy, $key); };
$cookieCopy = $copy['cookie'] ?? [
    'en' => ['text' => 'We use essential cookies to make this site work and optional analytics cookies to improve your experience.', 'learn_more' => 'Learn more', 'accept' => 'Accept'],
    'tl' => ['text' => 'Gumagamit kami ng mga essential cookies para gumana ang site at optional analytics cookies para mapabuti ang iyong karanasan.', 'learn_more' => 'Matuto pa', 'accept' => 'Tanggapin'],
];
$ck = function ($key) use ($cookieCopy) { return t($cookieCopy, $key); };
?>
<footer class="footer" role="contentinfo">
  <div class="inside container">
    <div class="brand">
      <img src="assets/branding/logo-reverse.svg" alt="AniMAL" class="brand-logo">
      <div class="brand-tagline"><?= $f('brand_tagline') ?></div>
    </div>
    <div class="cols">
      <div>
        <h2><?= $f('heading_market') ?></h2>
        <a href="browse.php"><?= $f('link_browse_listings') ?></a>
        <a href="index.php#steps"><?= $f('link_how_it_works') ?></a>
        <a href="index.php#faq"><?= $f('link_faq') ?></a>
      </div>
      <div>
        <h2><?= $f('heading_for_farmers') ?></h2>
        <a href="register.php"><?= $f('link_become_a_seller') ?></a>
        <a href="index.php#faq"><?= $f('link_seller_handbook') ?></a>
        <a href="index.php#faq"><?= $f('link_verification') ?></a>
      </div>
      <div>
        <h2><?= $f('heading_company') ?></h2>
        <a href="index.php#story"><?= $f('link_our_story') ?></a>
        <a href="mailto:support@animal.ph"><?= $f('link_contact') ?></a>
        <a href="privacy.php"><?= $f('link_privacy') ?></a>
        <a href="terms.php"><?= $f('link_terms') ?></a>
      </div>
    </div>
  </div>
  <p class="copyright">&copy; <?= date('Y') ?> AniMAL &middot; FarmRoute Cooperative. Built for Philippine farms.</p>
</footer>

<!-- Toast region -->
<div id="toast-region" aria-live="polite" aria-relevant="additions"></div>

<!-- Cookie Consent Banner -->
<div id="cookie-banner" class="cookie-banner" role="dialog" aria-label="Cookie consent" aria-describedby="cookie-banner-text">
    <div class="cookie-banner__content">
        <p id="cookie-banner-text" class="cookie-banner__text mb-0">
            <?= $ck('text') ?>
            <a href="cookies.php"><?= $ck('learn_more') ?></a>
        </p>
        <div class="cookie-banner__actions">
            <button type="button" class="btn btn-primary btn-sm" data-cookie-accept><?= $ck('accept') ?></button>
        </div>
    </div>
</div>

    <script src="<?= e(asset('assets/js/motion.min.js')) ?>" defer></script>
    <script src="<?= e(asset('assets/js/swipe.min.js')) ?>" defer></script>
<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').then((reg) => {
        console.log('SW registered:', reg.scope);
      }).catch((err) => {
        console.log('SW registration failed:', err);
      });
    });
  }
</script>
</body>
</html>