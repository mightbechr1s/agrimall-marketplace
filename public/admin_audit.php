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

// Prepare data for admin-table partial
$columns = [
    ['key' => 'created_at', 'label' => 'Timestamp', 'sortable' => true, 'class' => 'text-nowrap',
     'formatter' => function($r) { return date('Y-m-d H:i:s', strtotime($r['created_at'])); }],
    ['key' => 'admin_email', 'label' => 'Admin', 'sortable' => true],
    ['key' => 'action', 'label' => 'Action', 'sortable' => true,
     'formatter' => function($r) { return '<span class="dash-badge dash-badge--secondary">' . e($r['action']) . '</span>'; }],
    ['key' => 'entity_type', 'label' => 'Entity', 'sortable' => true,
     'formatter' => function($r) { return e($r['entity_type']) . ($r['entity_id'] !== null ? ' #' . e((string) $r['entity_id']) : ''); }],
    ['key' => 'change', 'label' => 'Change', 'sortable' => false,
     'formatter' => function($r) {
         return '<details class="audit-details"><summary class="small">before / after</summary><pre class="audit-json m-0 mt-1">' . fmt_json($r['old_values']) . '</pre><pre class="audit-json m-0">' . fmt_json($r['new_values']) . '</pre></details>';
     }],
    ['key' => 'ip_address', 'label' => 'IP', 'sortable' => true, 'class' => 'cell-muted small',
     'formatter' => function($r) { return e($r['ip_address'] ?? '—'); }],
];

$filters = [
    'entity' => $entityFilter,
    'action' => $actionFilter,
];

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
    <input type="text" name="action" id="action-filter" value="<?= e($actionFilter) ?>" class="form-control form-control-sm" style="max-width: 220px;" maxlength="100" placeholder="Action contains...">
    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
    <a href="admin_audit.php" class="btn btn-sm btn-outline-primary">Reset</a>
</form>

<?php
$data = $events;
$filters = array_merge($filters, ['sort' => '', 'dir' => '']);
include __DIR__ . '/partials/admin-table.php';
?>

<?php require __DIR__ . '/partials/dashboard-footer.php'; ?>