<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';

// Hosted as a GitHub Release asset: the free host caps a single file at ~10 MB
// and the APK is 76.6 MB. Bump the tag and the size together on a new build.
$apkFile = 'AgriMALL-0.1.0.apk';
$apkUrl = 'https://github.com/mightbechr1s/animal-marketplace/releases/download/apk-0.1.0/' . $apkFile;
$apkSizeLabel = '76.6 MB';

$pageTitle = 'Get the app';
require __DIR__ . '/partials/header.php';
?>
<main id="main-content" class="flex-grow-1">
<section class="hero-section">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <span class="eyebrow-pill">AgriMALL for Android</span>
        <h1 class="hero-title">The market, in your pocket.</h1>
        <p class="hero-lead">Browse fresh produce and livestock, message farmers, and place orders from your phone. Same accounts, same listings, same direct farmer-to-buyer deals as the website.</p>
        <div class="d-flex flex-wrap gap-3 mt-4">
          <a class="btn btn-primary" href="<?= e($apkUrl) ?>">
            <i class="bi bi-download me-2" aria-hidden="true"></i>Download for Android<?= $apkSizeLabel !== '' ? ' (' . e($apkSizeLabel) . ')' : '' ?>
          </a>
          <a class="btn btn-outline" href="browse.php">Browse on the web</a>
        </div>
        <p class="mt-3 mb-0"><small>Version 0.1.0 &middot; Android 7.0 and newer &middot; ARM and x86 devices</small></p>
      </div>
      <div class="col-lg-5">
        <div class="card p-4">
          <h2 class="h5 mb-3">Before you install</h2>
          <ul class="mb-0 ps-3">
            <li class="mb-2">This is a direct download, not a Play Store release. Android will warn you about installing apps from outside the store.</li>
            <li class="mb-2">The build is signed with a debug certificate, so it is for testing and direct distribution only.</li>
            <li>AgriMALL never handles payments. You pay the farmer or buyer directly, as agreed between you.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-7">
        <h2 class="h4 mb-3">How to install</h2>
        <ol class="ps-3">
          <li class="mb-3">
            <strong>Download the file.</strong> Tap the download button above. The file is named <code><?= e($apkFile) ?></code>.
          </li>
          <li class="mb-3">
            <strong>Allow the install.</strong> When Android asks, open Settings and turn on <em>Install unknown apps</em> for the app you downloaded with (Chrome, Files, etc.). Then go back and continue.
          </li>
          <li class="mb-3">
            <strong>Open and install.</strong> Open the downloaded file, tap <em>Install</em>, and wait for it to finish. You can turn the permission off again afterwards.
          </li>
          <li>
            <strong>Connect the app to this site.</strong> On the sign-in screen, enter the server address shown in the box below, then sign in with your usual AgriMALL account.
          </li>
        </ol>
      </div>
      <div class="col-lg-5">
        <div class="card p-4">
          <h2 class="h5 mb-2">Server address</h2>
          <p class="mb-2">The app needs to know where this AgriMALL installation lives. Copy this address into the server field on the sign-in screen:</p>
          <p class="mb-2"><code class="d-block text-break"><?= e(app_base_url()) ?></code></p>
          <p class="mb-0"><small>If the site moves to a new address, update this field in the app. Your account and data stay on the server.</small></p>
        </div>
      </div>
    </div>
  </div>
</section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
