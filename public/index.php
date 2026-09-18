<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';

$pageTitle = t($copy['hero'], 'headline');
$activeNav = 'browse';
$heroCopy = $copy['hero'][locale_current()] ?? $copy['hero']['en'];
require __DIR__ . '/partials/header.php';
?>

<main id="main-content">
<!-- Hero -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <p class="eyebrow-pill mb-3"><?= t($copy['hero'], 'eyebrow') ?></p>
                <h1 class="hero-title editorial-serif"><?= preg_replace('/(24\s*hours|24\s*oras)/', '<span class="nowrap">$1</span>', e($heroCopy['headline'])) ?></h1>
                <p class="hero-lead"><?= e($heroCopy['subhead']) ?></p>
                <div class="d-flex flex-column flex-sm-row gap-3 mb-4">
                    <a href="browse.php" class="btn btn-primary btn-lg px-4 py-3"><?= t($copy['hero'], 'cta_primary') ?></a>
                    <a href="#steps" class="btn btn-outline-primary btn-lg px-4 py-3"><?= t($copy['hero'], 'cta_secondary') ?></a>
                </div>
                <div class="hero-stats bento-grid gap-4">
                    <?php foreach ($copy['stats'][locale_current()]['items'] ?? $copy['stats']['en']['items'] as $stat): ?>
                    <div class="stat stat-32px">
                        <div class="num"><?= e($stat['num']) ?><?= e($stat['suffix']) ?></div>
                        <div class="lbl"><?= e($stat['label']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-5 position-relative">
                <div class="hero-image-wrapper">
                    <img src="assets/images/products/mango.jpg"
                         alt="Fresh mangoes"
                         class="hero-image"
                         onerror="this.onerror=null;this.parentNode.classList.add('image-failed');">
                </div>
                <!-- Floating live price pill and Verified Origin tag -->
                <div class="hero-overlay">
                    <div class="price-pill">₱180.00 / kg</div>
                    <div class="origin-badge">Verified Origin</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust Bar -->
<div class="trust-bar">
    <div class="container">
        <div class="row g-3">
            <?php foreach ($copy['trustbar'][locale_current()]['items'] ?? $copy['trustbar']['en']['items'] as $item): ?>
            <div class="col-6 col-md-3">
                <div class="trust-bar-item d-flex align-items-center gap-2">
                    <span class="trust-bar-dot" aria-hidden="true"></span>
                    <span><?= $item['label'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- How It Works -->
<section id="steps" class="how-it-works" data-reveal>
    <div class="container">
        <div class="text-center mb-5">
            <p class="eyebrow-pill"><?= t($copy['how'], 'kicker') ?></p>
            <h2 class="section-title mb-3"><?= t($copy['how'], 'section_title') ?></h2>
            <p class="section-lead col-lg-8 mx-auto"><?= t($copy['how'], 'intro') ?></p>
        </div>
        <div class="process-strip-container">
            <div class="process-strip">
                <?php
                $howLang = locale_current();
                $howData = $copy['how'][$howLang] ?? $copy['how']['en'];
                $steps = [
                    ['num' => $howData['step_1']['number'], 'title' => $howData['step_1']['title'], 'desc' => $howData['step_1']['description']],
                    ['num' => $howData['step_2']['number'], 'title' => $howData['step_2']['title'], 'desc' => $howData['step_2']['description']],
                    ['num' => $howData['step_3']['number'], 'title' => $howData['step_3']['title'], 'desc' => $howData['step_3']['description']],
                ];
                foreach ($steps as $i => $step): ?>
                <div class="process-step">
                    <div class="process-step-number"><?= $step['num'] ?></div>
                    <div class="process-step-content">
                        <h3 class="process-step-title"><?= $step['title'] ?></h3>
                        <p class="process-step-description"><?= $step['desc'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Story Section -->
<section id="story" class="story-section py-5" data-reveal>
    <div class="container">
        <div class="story-content">
            <p class="eyebrow-pill"><?= t($copy['story'], 'kicker') ?></p>
            <h2 class="section-title mb-3"><?= t($copy['story'], 'heading') ?></h2>
            <p class="story-body"><?= t($copy['story'], 'body') ?></p>
            <blockquote class="story-quote">
                <p><?= t($copy['story'], 'quote') ?></p>
                <footer><?= t($copy['story'], 'quote_attr') ?></footer>
            </blockquote>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section id="faq" class="faq-section py-5" data-reveal>
    <div class="container">
        <div class="text-center mb-4">
            <p class="eyebrow-pill"><?= t($copy['faq'], 'kicker') ?></p>
            <h2 class="section-title fw-bold mb-2"><?= t($copy['faq'], 'section_title') ?></h2>
            <p class="section-lead col-lg-8 mx-auto"><?= t($copy['ui'], 'faq_lead') ?></p>
        </div>
        <div class="accordion accordion-flush" id="faqAccordion">
            <?php
            $faqItemsEn = $copy['faq'][locale_current()]['items'] ?? $copy['faq']['en']['items'];
            ?>
            <?php foreach ($faqItemsEn as $index => $faq): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="faqHeading<?= $index ?>">
                    <button class="accordion-button collapsed fw-medium" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse<?= $index ?>" aria-expanded="false" aria-controls="faqCollapse<?= $index ?>">
                        <?= $faq['question'] ?>
                    </button>
                </h2>
                <div id="faqCollapse<?= $index ?>" class="accordion-collapse collapse" aria-labelledby="faqHeading<?= $index ?>" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        <p><?= $faq['answer'] ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Final CTA -->
<section class="cta-section py-5" data-reveal>
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="section-title fw-bold mb-2"><?= t($copy['cta'], 'heading') ?></h2>
            <p class="section-lead"><?= t($copy['cta'], 'intro') ?></p>
        </div>
        <div class="rail mt-4">
            <div class="rail-body">
                <div class="rail-items rail-items--center">
                    <div class="rail-item cta-rail-item">
                        <a href="browse.php" class="btn btn-primary btn-lg px-4 py-3 me-3"><?= t($copy['cta'], 'primary') ?></a>
                    </div>
                    <div class="rail-item cta-rail-item">
                        <a href="create_listing.php" class="btn btn-outline-primary btn-lg px-4 py-3"><?= t($copy['cta'], 'secondary') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

</main>

<div class="mobile-cta-bar" role="navigation" aria-label="Quick actions">
   <a href="browse.php" class="btn btn-outline">Browse</a>
   <a href="create_listing.php" class="btn btn-primary">Sell</a>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

