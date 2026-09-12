<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'AnimalMarketplace') ?> · <?= e(config('app.name', 'AnimalMarketplace')) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/styles.css')) ?>">
</head>
<body>

<?php foreach (flash_pull() as $message): ?>
  <div class="flash flash-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
<?php endforeach; ?>

<nav class="nav">
  <a class="brand" href="<?= e(url('index.php')) ?>"><?= e(config('app.name', 'AnimalMarketplace')) ?></a>
  <div class="nav-links">
    <a href="<?= e(url('index.php')) ?>">Home</a>
    <a href="<?= e(url('listing.php')) ?>">Marketplace</a>
    <a href="<?= e(url('login.php')) ?>">Log in</a>
    <a href="<?= e(url('register.php')) ?>">Register</a>
  </div>
</nav>