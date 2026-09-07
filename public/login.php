<?php
require __DIR__ . '/../src/Core/bootstrap.php';
$pageTitle = 'Log in';
include __DIR__ . '/partials/header.php';
?>

<main class="container">
  <div class="card">
    <h1>Log in</h1>
    <p class="muted">Skeleton placeholder — the real form is a Backend + Frontend task in Sprint 2.</p>
    <div class="todo">
      <strong>TODO (Backend):</strong> register/login/logout with roles.
      <strong>TODO (Frontend):</strong> design this form to match the approved direction.<br>
      Use <code>csrf_field()</code> in any POST form, and <code>csrf_check()</code> before handling it.
    </div>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>