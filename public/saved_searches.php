<?php
require __DIR__ . '/../src/bootstrap.php';
require_login();
require_role('buyer', 'admin', 'manager');

$pdo = DB::pdo();
$userId = current_user_id();
$pageTitle = 'Saved Searches';
$activeNav = 'browse';
$activePage = 'saved';
$sidebarShell = (bool) current_user_id();
if ($sidebarShell) {
    $role = current_user_role();
    $activePage = 'saved';
    require __DIR__ . '/partials/dashboard-layout.php';
} else {
    require __DIR__ . '/partials/header.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort_if_invalid($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $pdo->prepare('DELETE FROM saved_searches WHERE id = :id AND user_id = :u')
            ->execute(['id' => $id, 'u' => $userId]);
        flash_set('success', 'Saved search deleted.');
    } elseif ($action === 'toggle_notify' && $id > 0) {
        $pdo->prepare('UPDATE saved_searches SET notify = NOT notify WHERE id = :id AND user_id = :u')
            ->execute(['id' => $id, 'u' => $userId]);
        flash_set('success', 'Notification preference updated.');
    }
    redirect_to('saved_searches.php');
}

$searches = $pdo->prepare('SELECT * FROM saved_searches WHERE user_id = :u ORDER BY created_at DESC');
$searches->execute(['u' => $userId]);
$searches = $searches->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="settings-wrap">
    <div class="settings-container">
        <div class="settings-header">
            <h1>Saved Searches</h1>
            <p>Your saved search filters. You'll be notified when new listings match.</p>
        </div>
        <a href="browse.php" class="btn btn-outline btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i> Back to browse</a>

        <?php $flash = flash_get('success'); if ($flash): ?>
            <div class="alert alert-success"><?= e($flash) ?></div>
        <?php endif; ?>

        <?php if (!$searches): ?>
            <div class="empty-state text-center">
                <i class="bi bi-bookmark empty-state-icon" aria-hidden="true"></i>
                <h3 class="empty-state-title">No saved searches</h3>
                <p class="empty-state-description">Save a search from the browse page to get notified about new listings.</p>
                <a class="btn btn-primary" href="browse.php">Browse Listings</a>
            </div>
        <?php else: ?>
            <div class="table-responsive card">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Query</th><th>Category</th><th>Price range</th><th>Notify</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($searches as $s): ?>
                        <tr>
                            <td><?= e($s['query'] !== '' ? $s['query'] : 'All') ?></td>
                            <td><?= e($s['category'] !== '' ? $s['category'] : 'All') ?></td>
                            <td>
                                <?php if ($s['price_min'] || $s['price_max']): ?>
                                    <?= $s['price_min'] ? money($s['price_min']) : '—' ?> – <?= $s['price_max'] ? money($s['price_max']) : '—' ?>
                                <?php else: ?>
                                    <span class="text-muted">Any</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="saved_searches.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <input type="hidden" name="action" value="toggle_notify">
                                    <button type="submit" class="btn btn-sm <?= $s['notify'] ? 'btn-success' : 'btn-outline-secondary' ?>" title="<?= $s['notify'] ? 'Notifications on' : 'Notifications off' ?>">
                                        <i class="bi <?= $s['notify'] ? 'bi-bell-fill' : 'bi-bell-slash' ?>"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="post" action="saved_searches.php" class="d-inline" onsubmit="return confirm('Delete this saved search?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>
PHP;
file_put_contents('C:/Users/your4/Projects/animal-marketplace/public/saved_searches.php', $content);
echo "written\n";
