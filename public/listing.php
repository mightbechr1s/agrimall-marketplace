<?php
require __DIR__ . '/../src/Core/bootstrap.php';
$pageTitle = 'Marketplace';
include __DIR__ . '/partials/header.php';
?>

<main class="container">
  <h1>Marketplace</h1>
  <p class="muted">Skeleton placeholder — this becomes the listing grid + detail flow.</p>
  <div class="todo">
    <strong>TODO (Backend):</strong> listings — create (farmer), browse, detail, image upload.
    <strong>TODO (DBA):</strong> extend <code>database/schema.sql</code> with listings + categories, then migrate.
    <strong>TODO (Frontend):</strong> grid + card design from the clickable prototype.
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>