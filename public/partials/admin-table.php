<?php
/**
 * Admin Table Partial — Enhanced with sorting, pagination, sticky header
 *
 * Usage:
 *   include __DIR__ . '/partials/admin-table.php';
 *   Requires variables:
 *     $columns (array)     — column definitions:
 *       ['key' => 'col_key', 'label' => 'Label', 'sortable' => true, 'formatter' => callable, 'class' => 'text-nowrap']
 *     $data (array)        — rows (each row is array with keys matching column keys)
 *     $sortable (bool)     — enable column sorting (default true)
 *     $paginated (bool)    — enable pagination (default true)
 *     $perPage (int)       — items per page (default 25)
 *     $filters (array)     — current filter GET params to preserve in pagination/sort links
 *     $emptyMessage (string) — empty state message
 *     $tableId (string)    — unique ID for the table
 *     $stickyHeader (bool) — make header sticky (default true)
 *     $rowClick (string)   — JS click handler for rows
 *     $actions (callable)  — function($row) returning action HTML for last column
 */
$sortable = $sortable ?? true;
$paginated = $paginated ?? true;
$perPage = $perPage ?? 25;
$filters = $filters ?? [];
$emptyMessage = $emptyMessage ?? 'No records found';
$tableId = $tableId ?? 'admin-table-' . uniqid();
$stickyHeader = $stickyHeader ?? true;
$rowClick = $rowClick ?? '';
$actions = $actions ?? null;

// Build query string for links (preserving filters)
$qs = http_build_query($filters);
$qsPrefix = $qs ? $qs . '&' : '';

// Sorting
$sortCol = $_GET['sort'] ?? '';
$sortDir = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
$validSortCols = array_filter($columns, fn($c) => !empty($c['sortable'])) ? array_column($columns, 'key') : [];
$isValidSort = in_array($sortCol, $validSortCols, true);

// Pagination
$total = count($data);
$totalPages = $paginated ? max(1, ceil($total / $perPage)) : 1;
$page = $paginated ? max(1, min((int)($_GET['page'] ?? 1), $totalPages)) : 1;
$offset = ($page - 1) * $perPage;
$pageData = $paginated ? array_slice($data, $offset, $perPage) : $data;

// Sort data if sortable
if ($isValidSort && $sortable) {
    usort($pageData, function ($a, $b) use ($sortCol, $sortDir) {
        $av = $a[$sortCol] ?? '';
        $bv = $b[$sortCol] ?? '';
        if (is_numeric($av) && is_numeric($bv)) return $sortDir === 'asc' ? $av <=> $bv : $bv <=> $av;
        return $sortDir === 'asc' ? strnatcmp($av, $bv) : strnatcmp($bv, $av);
    });
}

// Build sort link
function sortLink(string $col, string $label, string $currentSort, string $currentDir, string $qsPrefix, array $columns): string
{
    $sortable = isset($columns[$col]) && ($columns[$col]['sortable'] ?? false);
    if (!$sortable) return $label;
    $newDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $icon = $currentSort === $col ? ($currentDir === 'asc' ? '↑' : '↓') : '↕';
    $href = '?' . $qsPrefix . 'sort=' . urlencode($col) . '&dir=' . $newDir;
    return '<a href="' . e($href) . '" class="text-decoration-none" aria-label="Sort by ' . e($label) . ' ' . ($newDir === 'asc' ? 'ascending' : 'descending') . '">' . e($label) . ' <span class="sort-icon ms-1">' . $icon . '</span></a>';
}
?>
<?php if (!empty($filters)): ?>
  <input type="hidden" name="filters_json" value="<?= e(json_encode($filters)) ?>">
<?php endif; ?>

<?php if ($paginated && $totalPages > 1): ?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
      <label class="visually-hidden" for="<?= e($tableId) ?>-per-page">Items per page</label>
      <select id="<?= e($tableId) ?>-per-page" class="form-select form-select-sm" style="width: auto;"
              onchange="window.location.href='?<?= e($qsPrefix) ?>page=1&per_page='+this.value">
        <?php foreach ([10, 25, 50, 100] as $pp): ?>
          <option value="<?= $pp ?>" <?= $perPage == $pp ? 'selected' : '' ?>><?= $pp ?> per page</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="d-flex align-items-center gap-2">
      <?php if ($page > 1): ?>
        <a href="?<?= e($qsPrefix) ?>page=1&per_page=<?= e($perPage) ?>&sort=<?= e($sortCol) ?>&dir=<?= e($sortDir) ?>" class="btn btn-sm btn-outline-primary" aria-label="First page"><i class="bi bi-chevron-double-left"></i></a>
        <a href="?<?= e($qsPrefix) ?>page=<?= $page - 1 ?>&per_page=<?= e($perPage) ?>&sort=<?= e($sortCol) ?>&dir=<?= e($sortDir) ?>" class="btn btn-sm btn-outline-primary" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
      <?php else: ?>
        <button class="btn btn-sm btn-outline-primary" disabled aria-label="First page"><i class="bi bi-chevron-double-left"></i></button>
        <button class="btn btn-sm btn-outline-primary" disabled aria-label="Previous page"><i class="bi bi-chevron-left"></i></button>
      <?php endif; ?>
      <span class="px-3 text-soft small">Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="?<?= e($qsPrefix) ?>page=<?= $page + 1 ?>&per_page=<?= e($perPage) ?>&sort=<?= e($sortCol) ?>&dir=<?= e($sortDir) ?>" class="btn btn-sm btn-outline-primary" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
        <a href="?<?= e($qsPrefix) ?>page=<?= $totalPages ?>&per_page=<?= e($perPage) ?>&sort=<?= e($sortCol) ?>&dir=<?= e($sortDir) ?>" class="btn btn-sm btn-outline-primary" aria-label="Last page"><i class="bi bi-chevron-double-right"></i></a>
      <?php else: ?>
        <button class="btn btn-sm btn-outline-primary" disabled aria-label="Next page"><i class="bi bi-chevron-right"></i></button>
        <button class="btn btn-sm btn-outline-primary" disabled aria-label="Last page"><i class="bi bi-chevron-double-right"></i></button>
      <?php endif; ?>
      <span class="cell-muted small ms-2"><?= $total ?> total</span>
    </div>
  </div>
<?php endif; ?>

<div class="table-responsive" style="<?= $stickyHeader ? '--sticky-top: 0;' : '' ?>">
  <table id="<?= e($tableId) ?>" class="dash-table align-middle" role="grid">
    <thead class="<?= $stickyHeader ? 'sticky-top' : '' ?>">
      <tr>
        <?php foreach ($columns as $col): ?>
          <th scope="col" class="<?= e($col['class'] ?? '') ?>">
            <?= sortLink($col['key'], $col['label'], $sortCol, $sortDir, $qsPrefix, array_column($columns, 'key', 'key')) ?>
          </th>
        <?php endforeach; ?>
        <?php if ($actions): ?><th scope="col" class="text-end">Actions</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($pageData)): ?>
        <tr>
          <td colspan="<?= count($columns) + ($actions ? 1 : 0) ?>" class="text-center py-5">
            <div class="empty-state p-4 m-0">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon text-soft">
                <path d="M2 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H1.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2V6h-.5a.5.5 0 0 1 0-1H2v-1h-.5a.5.5 0 0 1 0-1H2V4h-.5a.5.5 0 0 1 0-1H2V2h-.5a.5.5 0 0 1 0-1H2zm9.853-1.354a.5.5 0 0 1 .354-.853H14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H8.5a.5.5 0 0 1 0-1H14a1 1 0 0 0 1-1V4H8.5a.5.5 0 0 1 0-1H15V2a1 1 0 0 0-1-1H10.5v2.5a.5.5 0 0 1-1 0V2a.5.5 0 0 1 .146-.354l.207-.207z"/>
              </svg>
              <h3 class="empty-state-title"><?= e($emptyMessage) ?></h3>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($pageData as $row): ?>
          <tr <?= $rowClick ? 'onclick="' . e($rowClick) . '" style="cursor:pointer;" tabindex="0" onkeydown="if(event.key===\'Enter\'){'.$rowClick.'}"' : '' ?>>
            <?php foreach ($columns as $col): ?>
              <td class="<?= e($col['class'] ?? '') ?>">
                <?php if (isset($col['formatter'])): ?>
                  <?= $col['formatter']($row) ?>
                <?php else: ?>
                  <?= e($row[$col['key']] ?? '—') ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <?php if ($actions): ?>
              <td class="text-end"><?= $actions($row) ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($paginated && $totalPages > 1): ?>
  <div class="d-flex justify-content-between align-items-center mt-3">
    <div class="d-flex align-items-center gap-2">
      <label class="visually-hidden" for="<?= e($tableId) ?>-per-page-bot">Items per page</label>
      <select id="<?= e($tableId) ?>-per-page-bot" class="form-select form-select-sm" style="width: auto;"
              onchange="window.location.href='?<?= e($qsPrefix) ?>page=1&per_page='+this.value">
        <?php foreach ([10, 25, 50, 100] as $pp): ?>
          <option value="<?= $pp ?>" <?= $perPage == $pp ? 'selected' : '' ?>><?= $pp ?> per page</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="d-flex align-items-center gap-2">
      <?php if ($page > 1): ?>
        <a href="?<?= e($qsPrefix) ?>page=1&per_page=<?= e($perPage) ?>&sort=<?= e($sortCol) ?>&dir=<?= e($sortDir) ?>" class="btn btn-sm btn-outline-primary" aria-label="First page"><i class="bi bi-chevron-double-left"></i></a>
        <a href="?<?= e($qsPrefix) ?>page=<?= $page - 1 ?>&per_page=<?= e($perPage) ?>&sort=<?= e($sortCol) ?>&dir=<?= e($sortDir) ?>" class="btn btn-sm btn-outline-primary" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
      <?php else: ?>
        <button class="btn btn-sm btn-outline-primary" disabled aria-label="First page"><i class="bi bi-chevron-double-left"></i></button>
        <button class="btn btn-sm btn-outline-primary" disabled aria-label="Previous page"><i class="bi bi-chevron-left"></i></button>
      <?php endif; ?>
      <span class="px-3 text-soft small">Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="?<?= e($qsPrefix) ?>page=<?= $page + 1 ?>&per_page=<?= e($perPage) ?>&sort=<?= e($sortCol) ?>&dir=<?= e($sortDir) ?>" class="btn btn-sm btn-outline-primary" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
        <a href="?<?= e($qsPrefix) ?>page=<?= $totalPages ?>&per_page=<?= e($perPage) ?>&sort=<?= e($sortCol) ?>&dir=<?= e($sortDir) ?>" class="btn btn-sm btn-outline-primary" aria-label="Last page"><i class="bi bi-chevron-double-right"></i></a>
      <?php else: ?>
        <button class="btn btn-sm btn-outline-primary" disabled aria-label="Next page"><i class="bi bi-chevron-right"></i></button>
        <button class="btn btn-sm btn-outline-primary" disabled aria-label="Last page"><i class="bi bi-chevron-double-right"></i></button>
      <?php endif; ?>
      <span class="cell-muted small ms-2"><?= $total ?> total</span>
    </div>
  </div>
<?php endif; ?>

<?php if ($stickyHeader): ?>
  <style>
    ##<?= e($tableId) ?> thead th.sticky-top {
      position: sticky;
      top: 0;
      z-index: 10;
      background: var(--canvas-deep);
    }
    @media (prefers-reduced-motion: reduce) {
      ##<?= e($tableId) ?> thead th.sticky-top {
        position: static;
      }
    }
  </style>
<?php endif; ?>