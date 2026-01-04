<?php
/**
 * Proxima Admin Panel - Model Records View
 * 
 * Displays records for a specific model with pagination and search
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();
initDatabase();

// Get model class from URL
$modelClass = $_GET['class'] ?? null;

if (!$modelClass || !class_exists($modelClass)) {
    setFlash('error', 'Invalid model class');
    header('Location: index.php');
    exit;
}

// Get model info
$modelInfo = getModelByClass($modelClass);
if (!$modelInfo) {
    setFlash('error', 'Model not found');
    header('Location: index.php');
    exit;
}

// Current model for sidebar highlighting
$currentModel = $modelClass;

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

// Search
$search = $_GET['search'] ?? '';
$searchColumn = $_GET['column'] ?? 'all';

// Get records
$result = getRecords($modelClass, $page, $perPage);
$records = $result['data'];
$columns = $result['columns'];
$pagination = $result['pagination'];

// Filter records if search is active
if ($search !== '') {
    $searchLower = strtolower($search);
    $records = array_filter($records, function($row) use ($searchLower, $searchColumn, $columns) {
        if ($searchColumn === 'all') {
            foreach ($columns as $col) {
                if ($row[$col] !== null && stripos((string) $row[$col], $searchLower) !== false) {
                    return true;
                }
            }
            return false;
        } else {
            return isset($row[$searchColumn]) && 
                   $row[$searchColumn] !== null && 
                   stripos((string) $row[$searchColumn], $searchLower) !== false;
        }
    });
    $records = array_values($records);
    
    // Update pagination for filtered results
    $pagination['total'] = count($records);
    $pagination['totalPages'] = 1;
    $pagination['hasNext'] = false;
    $pagination['hasPrev'] = false;
}

// Page settings
$pageTitle = $modelInfo['shortName'] . ' Records';
$showBackButton = true;
$backUrl = 'index.php';

// Topbar actions
$topbarActions = '
    <a href="create.php?class=' . urlencode($modelClass) . '" class="btn btn-cyan">✚ Create New</a>
';

include __DIR__ . '/includes/header.php';
?>

<!-- Toolbar -->
<div class="toolbar">
    <div class="toolbar-left">
        <span class="data-count">
            <strong><?= $pagination['total'] ?></strong> record<?= $pagination['total'] !== 1 ? 's' : '' ?>
            <?php if ($search !== ''): ?>
                matching "<?= e($search) ?>"
            <?php endif; ?>
        </span>
    </div>
    <div class="toolbar-right">
        <form method="GET" class="search-form">
            <input type="hidden" name="class" value="<?= e($modelClass) ?>">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search..." class="search-input">
            <select name="column" class="search-select">
                <option value="all" <?= $searchColumn === 'all' ? 'selected' : '' ?>>All Columns</option>
                <?php foreach ($columns as $col): ?>
                    <option value="<?= e($col) ?>" <?= $searchColumn === $col ? 'selected' : '' ?>><?= e($col) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if ($search !== ''): ?>
                <a href="model.php?class=<?= urlencode($modelClass) ?>" class="btn btn-danger btn-sm">✕ Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="table-scroll-container">
        <table class="data-table">
            <thead>
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <th><?= e($col) ?></th>
                    <?php endforeach; ?>
                    <th class="th-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="<?= count($columns) + 1 ?>">
                            <div class="empty-state">
                                <div class="empty-icon">📭</div>
                                <div class="empty-title">No Records Found</div>
                                <div class="empty-text">
                                    <?php if ($search !== ''): ?>
                                        No records match your search criteria
                                    <?php else: ?>
                                        This table is empty. <a href="create.php?class=<?= urlencode($modelClass) ?>">Create a new record</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $row): ?>
                        <?php $recordId = $row['id'] ?? $row[$columns[0]] ?? null; ?>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <td title="<?= e($row[$col]) ?>"><?= formatValue($row[$col]) ?></td>
                            <?php endforeach; ?>
                            <td class="td-actions">
                                <a href="record.php?class=<?= urlencode($modelClass) ?>&id=<?= $recordId ?>" 
                                   class="action-btn view" title="View Details">👁️</a>
                                <a href="edit.php?class=<?= urlencode($modelClass) ?>&id=<?= $recordId ?>" 
                                   class="action-btn edit" title="Edit">✏️</a>
                                <a href="actions.php?action=delete_record&class=<?= urlencode($modelClass) ?>&id=<?= $recordId ?>" 
                                   class="action-btn delete" title="Delete"
                                   onclick="return confirmDelete()">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($pagination['totalPages'] > 1): ?>
<div class="pagination">
    <div class="pagination-links">
        <?php if ($pagination['hasPrev']): ?>
            <a href="model.php?class=<?= urlencode($modelClass) ?>&page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) . '&column=' . urlencode($searchColumn) : '' ?>" 
               class="pagination-btn">← Previous</a>
        <?php else: ?>
            <button class="pagination-btn" disabled>← Previous</button>
        <?php endif; ?>
    </div>
    
    <span class="pagination-info">
        Page <?= $pagination['page'] ?> of <?= $pagination['totalPages'] ?>
    </span>
    
    <div class="pagination-links">
        <?php if ($pagination['hasNext']): ?>
            <a href="model.php?class=<?= urlencode($modelClass) ?>&page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) . '&column=' . urlencode($searchColumn) : '' ?>" 
               class="pagination-btn">Next →</a>
        <?php else: ?>
            <button class="pagination-btn" disabled>Next →</button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
