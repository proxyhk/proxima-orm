<?php
/**
 * Proxima Admin Panel - Record Detail View
 * 
 * Shows all fields of a single record
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();
initDatabase();

// Get parameters
$modelClass = $_GET['class'] ?? null;
$recordId = $_GET['id'] ?? null;

if (!$modelClass || !class_exists($modelClass)) {
    setFlash('error', 'Invalid model class');
    header('Location: index.php');
    exit;
}

if (!$recordId) {
    setFlash('error', 'Record ID is required');
    header('Location: model.php?class=' . urlencode($modelClass));
    exit;
}

// Get model info
$modelInfo = getModelByClass($modelClass);
if (!$modelInfo) {
    setFlash('error', 'Model not found');
    header('Location: index.php');
    exit;
}

// Get schema to check for image fields
$schema = getModelSchema($modelClass);

// Get record
$record = getRecord($modelClass, (int) $recordId);
if (!$record) {
    setFlash('error', 'Record not found');
    header('Location: model.php?class=' . urlencode($modelClass));
    exit;
}

// Current model for sidebar
$currentModel = $modelClass;

// Page settings
$pageTitle = $modelInfo['shortName'] . ' #' . $recordId;
$showBackButton = true;
$backUrl = 'model.php?class=' . urlencode($modelClass);

// Topbar actions
$topbarActions = '
    <a href="edit.php?class=' . urlencode($modelClass) . '&id=' . $recordId . '" class="btn btn-primary">✏️ ' . t('edit') . '</a>
    <a href="actions.php?action=delete_record&class=' . urlencode($modelClass) . '&id=' . $recordId . '" 
       class="btn btn-danger" onclick="return confirmDelete()">🗑️ ' . t('delete') . '</a>
';

include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <table class="detail-table">
        <tbody>
            <?php foreach ($record as $field => $value): ?>
                <?php 
                $fieldConfig = $schema[$field] ?? [];
                $isImage = !empty($fieldConfig['isImage']);
                ?>
                <tr>
                    <th><?= e($field) ?></th>
                    <td><?php if ($isImage && $value): ?><a href="<?= e($value) ?>" target="_blank" style="color: #22d3ee; text-decoration: none;"><?= e($value) ?></a><?php else: ?><?= formatValue($value) ?><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="mt-3">
    <a href="model.php?class=<?= urlencode($modelClass) ?>" class="btn btn-secondary"><?= t('back_to_list') ?></a>
    <a href="edit.php?class=<?= urlencode($modelClass) ?>&id=<?= $recordId ?>" class="btn btn-primary"><?= t('edit_record') ?></a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
