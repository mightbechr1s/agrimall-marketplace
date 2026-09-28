<?php
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/i18n.php';
$copy = include __DIR__ . '/../src/i18n_copy.php';
$locale = locale_current();
$l = function ($dict, $key) use ($copy, $locale) {
    return $dict[$locale][$key] ?? $dict['en'][$key] ?? $key;
};

require_login();
$userId = current_user_id();
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
    if (($_POST['action'] ?? '') === 'mark_all_read') {
        DB::pdo()->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = :id AND read_at IS NULL')
            ->execute(['id' => $userId]);
        $success = $l($copy['notifications'], 'marked_read');
    }
}

$stmt = DB::pdo()->prepare(
    'SELECT id, category, title, body, link, read_at, created_at
     FROM notifications WHERE user_id = :id
     ORDER BY created_at DESC, id DESC LIMIT 50'
);
$stmt->execute(['id' => $userId]);
$items = $stmt->fetchAll();

$unread = 0;
foreach ($items as $item) {
    if ($item['read_at'] === null) {
        $unread++;
    }
}

$pageTitle = $l($copy['notifications'], 'page_title');
require __DIR__ . '/partials/header.php';
?>
<main id="main-content" class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1><?= e($l($copy['notifications'], 'page_title')) ?></h1>
            <p><?= e($l($copy['notifications'], 'page_description')) ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-outline mb-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back</a>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="status">
                <i class="bi bi-check-circle me-1"></i> <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($unread > 0): ?>
            <form method="post" action="notifications.php" class="mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button class="btn btn-outline" type="submit">
                    <i class="bi bi-check2-all me-1" aria-hidden="true"></i>
                    <?= e($l($copy['notifications'], 'mark_all_read')) ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if (!$items): ?>
            <p class="text-muted"><?= e($l($copy['notifications'], 'empty')) ?></p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($items as $item): ?>
                    <?php
                    $isUnread = $item['read_at'] === null;
                    $safeLink = is_string($item['link']) && $item['link'] !== '' && is_safe_path($item['link'])
                        ? $item['link']
                        : '';
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <?php if ($safeLink !== ''): ?>
                                <a class="<?= $isUnread ? 'fw-semibold' : '' ?>" href="<?= e($safeLink) ?>"><?= e($item['title']) ?></a>
                            <?php else: ?>
                                <span class="<?= $isUnread ? 'fw-semibold' : '' ?>"><?= e($item['title']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item['body'])): ?>
                                <div class="small text-muted"><?= e($item['body']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-nowrap small text-muted">
                            <?= e(date('M j, g:i A', strtotime((string) $item['created_at']))) ?>
                            <?php if ($isUnread): ?>
                                <span class="visually-hidden">(unread)</span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
