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
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
    if (($_POST['action'] ?? '') === 'mark_all_read') {
        DB::pdo()->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = :id AND read_at IS NULL')
            ->execute(['id' => $userId]);
        $success = $l($copy['notifications'], 'marked_read');
    }
}

$loadError = null;
$totalPages = 1;
try {
    $countStmt = DB::pdo()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :id');
    $countStmt->execute(['id' => $userId]);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $stmt = DB::pdo()->prepare(
        'SELECT id, category, title, body, link, read_at, created_at
         FROM notifications WHERE user_id = :id
         ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    $unread = 0;
    foreach ($items as $item) {
        if ($item['read_at'] === null) {
            $unread++;
        }
    }
} catch (Throwable $e) {
    error_log('Notifications load failed: ' . $e->getMessage());
    $items = [];
    $unread = 0;
    $loadError = 'We could not load your notifications right now. Please try again later.';
}

$activePage = 'notifications';
$pageTitle = $l($copy['notifications'], 'page_title');
require __DIR__ . '/partials/dashboard-layout.php';
?>
<div class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1><?= e($l($copy['notifications'], 'page_title')) ?></h1>
            <p><?= e($l($copy['notifications'], 'page_description')) ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-outline btn-sm mb-3"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back</a>

        <?php if (!empty($loadError)): ?>
            <div class="alert alert-danger" role="alert"><?= e($loadError) ?></div>
        <?php endif; ?>

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
            <?php $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon"><path d="M3 1.5h10v13H3z"/><path d="M5 4.5h6M5 7.5h4M5 10.5h3"/></svg>'; ?>
            <?php $title = $l($copy['notifications'], 'empty'); ?>
            <?php $description = ''; ?>
            <?php $actions = []; ?>
            <?php include __DIR__ . '/partials/empty-state.php'; ?>
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

            <?php if ($totalPages > 1): ?>
                <?php
                $labels = [
                    'prev' => $l($copy['ui'], 'pager_prev'),
                    'next' => $l($copy['ui'], 'pager_next'),
                    'page' => $l($copy['ui'], 'pager_page'),
                    'of'   => $l($copy['ui'], 'pager_of'),
                ];
                include __DIR__ . '/partials/pager.php';
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
