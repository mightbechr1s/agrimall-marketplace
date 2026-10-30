<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$locale = locale_current();
$l = function ($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="UTF-8">
    <title>Privacy Policy & Terms of Use</title>
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Legal sheet styling is scoped under body.legal-page in design-system.css -->
    <link href="assets/css/design-system.css" rel="stylesheet">
</head>
<body class="legal-page">
    <h1>Privacy Policy & Terms of Use</h1>
    <section>
        <h2>Privacy Policy</h2>
        <p>We respect your privacy. Your data is used only to operate the AgriMALL marketplace and is never sold to third parties.</p>
        <p>We collect account details, order history, and usage data to provide and improve the service.</p>
    </section>
    <section>
        <h2>Terms of Use</h2>
        <p>By creating an account, you agree to use AgriMALL responsibly and not to post misleading or fraudulent listings.</p>
        <p>Farmers must verify their identity before selling. Buyers must honor confirmed orders.</p>
    </section>
    <div class="text-center mt-4">
        <button class="btn btn-primary" onclick="window.close()">I have read both — close</button>
    </div>
</body>
</html>
