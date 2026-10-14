<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$locale = locale_current();
$l = function($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};

// Require login
if (!current_user_id()) {
    redirect_to('login.php');
}

$userId = current_user_id();

$prefs = [
    'locale' => 'en',
    'theme' => 'system',
    'notify_inapp' => 1,
    'notify_orders' => 1,
    'notify_messages' => 1,
    'notify_promotions' => 0,
    'reduce_motion' => 0,
    'larger_text' => 0,
];

try {
    $stmt = DB::pdo()->prepare('SELECT locale, theme, notify_inapp, notify_orders, notify_messages, notify_promotions, reduce_motion, larger_text FROM user_preferences WHERE user_id = :id');
    $stmt->execute(['id' => $userId]);
    $savedPrefs = $stmt->fetch();
    if ($savedPrefs) {
        $prefs = array_merge($prefs, $savedPrefs);
    }
} catch (Throwable $e) {
    error_log('Settings load error: ' . $e->getMessage());
}

$error = null;
$justSaved = flash_get('settings_saved') !== null;
$success = $justSaved ? ($copy['settings'][$locale]['saved_success'] ?? $copy['settings']['en']['saved_success']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);

    // Get and sanitize inputs
    $localeInput = isset($_POST['locale']) && in_array($_POST['locale'], ['en', 'tl']) ? $_POST['locale'] : 'en';
    $themeInput = isset($_POST['theme']) && in_array($_POST['theme'], ['system', 'light', 'dark']) ? $_POST['theme'] : 'system';
    $notifyInapp = isset($_POST['notify_inapp']) ? 1 : 0;
    $notifyOrders = isset($_POST['notify_orders']) ? 1 : 0;
    $notifyMessages = isset($_POST['notify_messages']) ? 1 : 0;
    $notifyPromotions = isset($_POST['notify_promotions']) ? 1 : 0;
    $reduceMotion = isset($_POST['reduce_motion']) ? 1 : 0;
    $largerText = isset($_POST['larger_text']) ? 1 : 0;

    // Upsert preferences
    $upsert = DB::pdo()->prepare('
        INSERT INTO user_preferences
            (user_id, locale, theme, notify_inapp, notify_orders, notify_messages, notify_promotions, reduce_motion, larger_text, updated_at)
        VALUES
            (:user_id, :locale, :theme, :notify_inapp, :notify_orders, :notify_messages, :notify_promotions, :reduce_motion, :larger_text, NOW())
        ON DUPLICATE KEY UPDATE
            locale = VALUES(locale),
            theme = VALUES(theme),
            notify_inapp = VALUES(notify_inapp),
            notify_orders = VALUES(notify_orders),
            notify_messages = VALUES(notify_messages),
            notify_promotions = VALUES(notify_promotions),
            reduce_motion = VALUES(reduce_motion),
            larger_text = VALUES(larger_text),
            updated_at = NOW()
    ');
    try {
        $upsert->execute([
            'user_id' => $userId,
            'locale' => $localeInput,
            'theme' => $themeInput,
            'notify_inapp' => $notifyInapp,
            'notify_orders' => $notifyOrders,
            'notify_messages' => $notifyMessages,
            'notify_promotions' => $notifyPromotions,
            'reduce_motion' => $reduceMotion,
            'larger_text' => $largerText,
        ]);
        // Persist the locale to session + cookie.
        locale_switch($localeInput);
        // Redirect, not re-render: locale_current() cached at line 5
        // before POST runs, so response would show old language.
        flash_set('settings_saved', $localeInput);
        redirect_to('settings.php');
    } catch (Exception $e) {
        error_log('Settings save error: ' . $e->getMessage());
        $error = $l($copy['settings'], 'save_error');
    }
}

$pageTitle = $l($copy['settings'], 'page_title');
require __DIR__ . '/partials/header.php';
?>
<main id="main-content" class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1><?= $l($copy['settings'], 'page_title') ?></h1>
            <p><?= $l($copy['settings'], 'page_description') ?></p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-circle me-1"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle me-1"></i> <?= e($success) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="settings.php" class="settings-form">
            <?= csrf_field() ?>
            <fieldset>
                <legend><?= $l($copy['settings'], 'legend_locale') ?></legend>
                <div class="form-group">
                    <label class="form-label" for="locale"><?= $l($copy['settings'], 'label_locale') ?></label>
                    <select class="form-select" id="locale" name="locale">
                        <option value="en" <?= $prefs['locale'] === 'en' ? 'selected' : '' ?>><?= $l($copy['settings'], 'option_en') ?></option>
                        <option value="tl" <?= $prefs['locale'] === 'tl' ? 'selected' : '' ?>><?= $l($copy['settings'], 'option_tl') ?></option>
                    </select>
                </div>
            </fieldset>
            <fieldset>
                <legend><?= $l($copy['settings'], 'legend_theme') ?></legend>
                <div class="form-group">
                    <label class="form-label" for="theme"><?= $l($copy['settings'], 'label_theme') ?></label>
                    <select class="form-select" id="theme" name="theme">
                        <option value="system" <?= $prefs['theme'] === 'system' ? 'selected' : '' ?>><?= $l($copy['settings'], 'option_system') ?></option>
                        <option value="light" <?= $prefs['theme'] === 'light' ? 'selected' : '' ?>><?= $l($copy['settings'], 'option_light') ?></option>
                        <option value="dark" <?= $prefs['theme'] === 'dark' ? 'selected' : '' ?>><?= $l($copy['settings'], 'option_dark') ?></option>
                    </select>
                </div>
            </fieldset>
            <fieldset>
                <legend><?= $l($copy['settings'], 'legend_notifications') ?></legend>
                <div class="form-group">
                    <input class="form-check-input" type="checkbox" id="notify_inapp" name="notify_inapp" <?= $prefs['notify_inapp'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notify_inapp"><?= $l($copy['settings'], 'notify_inapp') ?></label>
                </div>
                <div class="form-group">
                    <input class="form-check-input" type="checkbox" id="notify_orders" name="notify_orders" <?= $prefs['notify_orders'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notify_orders"><?= $l($copy['settings'], 'notify_orders') ?></label>
                </div>
                <div class="form-group">
                    <input class="form-check-input" type="checkbox" id="notify_messages" name="notify_messages" <?= $prefs['notify_messages'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notify_messages"><?= $l($copy['settings'], 'notify_messages') ?></label>
                </div>
                <div class="form-group">
                    <input class="form-check-input" type="checkbox" id="notify_promotions" name="notify_promotions" <?= $prefs['notify_promotions'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notify_promotions"><?= $l($copy['settings'], 'notify_promotions') ?></label>
                </div>
            </fieldset>
            <fieldset>
                <legend><?= $l($copy['settings'], 'legend_accessibility') ?></legend>
                <div class="form-group">
                    <input class="form-check-input" type="checkbox" id="larger_text" name="larger_text" <?= $prefs['larger_text'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="larger_text"><?= $l($copy['settings'], 'larger_text') ?></label>
                </div>
            </fieldset>
            <button class="btn btn-primary" type="submit"><?= $l($copy['settings'], 'button_save') ?></button>
            <a href="dashboard.php" class="btn btn-outline"><?= $l($copy['settings'], 'button_cancel') ?></a>
        </form>
    </div>
</main>
<script>
  // Sync theme select with effective theme (data-theme attr).
  // Single source of truth for what user sees.
  (function () {
    var sel = document.getElementById('theme');
    if (!sel) return;
    function updateSelect() {
      var theme = document.documentElement.getAttribute('data-theme') || 'light';
      // Map resolved theme back to select value
      var val = theme === 'dark' ? 'dark' : theme === 'light' ? 'light' : 'system';
      if (sel.value !== val) sel.value = val;
    }
    // Initial sync
    updateSelect();
    // Observe theme changes (toggle, FOUC guard, etc.)
    var observer = new MutationObserver(updateSelect);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
  })();
</script>
<?php if ($success): ?>
<script>
  // Settings saved server-side; sync client pref so FOUC guard
  // doesn't revert choice on next page.
  (function () {
    var pref = <?= json_encode($prefs['theme']) ?>;
    localStorage.setItem('animal_theme', pref);
    document.documentElement.setAttribute('data-theme',
      pref === 'system'
        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : pref);
  })();
</script>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>