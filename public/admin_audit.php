<?php
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

$pdo = DB::pdo();

function fmt_json(?string $json): string
{
    if ($json === null || $json === '') {
        return '—';
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return e($json);
    }
    return e(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$entityFilter = trim((string) ($_GET['entity'] ?? ''));
$actionFilter = trim((string) ($_GET['action'] ?? ''));

$sql = 'SELECT al.*, u.email AS admin_email
        FROM admin_audit_log al
        LEFT JOIN users u ON u.id = al.admin_id';
$params = [];
$where = [];
if ($entityFilter !== '') {
    $where[] = 'al.entity_type = :entity';
    $params['entity'] = $entityFilter;
}
if ($actionFilter !== '') {
    $where[] = 'al.action LIKE :action';
    $params['action'] = '%' . $actionFilter . '%';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY al.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$entities = $pdo->query('SELECT DISTINCT entity_type FROM admin_audit_log ORDER BY entity_type')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Audit Log';
$activePage = 'audit';
require __DIR__ . '/partials/dashboard-layout.php';
?>

<div class="dash-section-header">
    <h1>Audit Log</h1>
    <p>Append-only record of sensitive administrator actions.</p>
</div>

<form method="get" action="admin_audit.php" class="d-flex flex-wrap align-items-center gap-2 mb-4">
    <label class="visually-hidden" for="entity-filter">Entity type</label>
    <select name="entity" id="entity-filter" class="form-select form-select-sm" style="max-width: 220px;">
        <option value="">All entity types</option>
        <?php foreach ($entities as $entity): ?>
            <option value="<?= e($entity) ?>" <?= $entityFilter === $entity ? 'selected' : '' ?>><?= e($entity) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="visually-hidden" for="action-filter">Action</label>
    <input type="text" name="action" id="action-filter" value="<?= e($actionFilter) ?>"
           class="form-control form-control-sm" style="max-width: 220px;" placeholder="Action contains...">
    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
    <a href="admin_audit.php" class="btn btn-sm btn-outline-primary">Reset</a>
    <span class="cell-muted small ms-auto"><?= count($events) ?> events shown</span>
</form>

<div class="table-responsive">
    <table class="dash-table align-middle">
        <thead>
            <tr>
                <th class="text-nowrap">Timestamp</th>
                <th>Admin</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Change</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event): ?>
            <tr>
                <td class="cell-muted text-nowrap"><?= e(date('Y-m-d H:i:s', strtotime($event['created_at']))) ?></td>
                <td><?= e($event['admin_email'] ?? '—') ?></td>
                <td><span class="dash-badge dash-badge--secondary"><?= e($event['action']) ?></span></td>
                <td class="cell-muted">
                    <?= e($event['entity_type']) ?>
                    <?= $event['entity_id'] !== null ? '#' . e((string) $event['entity_id']) : '' ?>
                </td>
                <td class="cell-muted">
                    <details class="audit-details">
                        <summary class="small">before / after</summary>
                        <pre class="audit-json m-0 mt-1"><?= fmt_json($event['old_values']) ?></pre>
                        <pre class="audit-json m-0"><?= fmt_json($event['new_values']) ?></pre>
                    </details>
                </td>
                <td class="cell-muted small"><?= e($event['ip_address'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (!$events): ?>
<div class="empty-state">
    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon">
        <path d="M2 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H1.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2V6h-.5a.5.5 0 0 1 0-1H2V4h-.5a.5.5 0 0 1 0-1H2V2h-.5a.5.5 0 0 1 0-1H2zm9.853-1.354a.5.5 0 0 1 .354-.853H14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H8.5a.5.5 0 0 1 0-1H14a1 1 0 0 0 1-1V4H8.5a.5.5 0 0 1 0-1H15V2a1 1 0 0 0-1-1H10.5v2.5a.5.5 0 0 1-1 0V2a.5.5 0 0 1 .146-.354l.207-.207z"/>
    </svg>
    <h2 class="empty-state-title">No audit events found</h2>
    <p class="empty-state-description">Adjust the filters to broaden the search.</p>
</div>
<?php endif; ?>

<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>