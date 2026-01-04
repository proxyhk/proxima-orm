<?php
/**
 * Proxima Admin Panel - Edit Record
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

// Get model info and schema
$modelInfo = getModelByClass($modelClass);
if (!$modelInfo) {
    setFlash('error', 'Model not found');
    header('Location: index.php');
    exit;
}

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
$pageTitle = t('edit') . ' ' . $modelInfo['shortName'] . ' #' . $recordId;
$showBackButton = true;
$backUrl = 'record.php?class=' . urlencode($modelClass) . '&id=' . $recordId;

include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><?= t('edit') ?> <?= e($modelInfo['shortName']) ?> #<?= $recordId ?></h2>
    </div>
    
    <form method="POST" action="actions.php">
        <input type="hidden" name="action" value="update_record">
        <input type="hidden" name="class" value="<?= e($modelClass) ?>">
        <input type="hidden" name="id" value="<?= e($recordId) ?>">
        <?= csrfField() ?>
        
        <div style="padding: 24px;">
            <?php foreach ($schema as $fieldName => $config): ?>
                <?php 
                $value = $record[$fieldName] ?? null;
                $isPrimaryKey = $config['primaryKey'] ?? false;
                $required = !$config['nullable'] && !$isPrimaryKey;
                $requiredMark = $required ? '<span class="required">*</span>' : '';
                $readonly = $isPrimaryKey ? 'readonly style="background: #27272a; cursor: not-allowed;"' : '';
                ?>
                
                <div class="form-group">
                    <label class="form-label">
                        <?= e($fieldName) ?> <?= $requiredMark ?>
                        <?php if ($isPrimaryKey): ?>
                            <span class="text-muted">(Primary Key)</span>
                        <?php endif; ?>
                    </label>
                    
                    <?php if ($config['type'] === 'text'): ?>
                        <textarea name="<?= e($fieldName) ?>" class="form-textarea" 
                                  <?= $required ? 'required' : '' ?> <?= $readonly ?>><?= e($value) ?></textarea>
                    
                    <?php elseif ($config['type'] === 'boolean'): ?>
                        <select name="<?= e($fieldName) ?>" class="form-select" <?= $required ? 'required' : '' ?> <?= $readonly ?>>
                            <option value=""><?= t('select') ?></option>
                            <option value="1" <?= $value ? 'selected' : '' ?>><?= t('true') ?></option>
                            <option value="0" <?= !$value && $value !== null ? 'selected' : '' ?>><?= t('false') ?></option>
                        </select>
                    
                    <?php elseif ($config['type'] === 'integer' || $config['type'] === 'bigint'): ?>
                        <input type="number" name="<?= e($fieldName) ?>" class="form-input" 
                               value="<?= e($value) ?>"
                               <?= $required ? 'required' : '' ?> step="1" <?= $readonly ?>>
                    
                    <?php elseif (in_array($config['type'], ['decimal', 'float', 'double'])): ?>
                        <input type="number" name="<?= e($fieldName) ?>" class="form-input" 
                               value="<?= e($value) ?>"
                               <?= $required ? 'required' : '' ?> step="any" <?= $readonly ?>>
                    
                    <?php elseif ($config['type'] === 'date'): ?>
                        <?php $dateValue = $value ? explode(' ', $value)[0] : ''; ?>
                        <input type="date" name="<?= e($fieldName) ?>" class="form-input" 
                               value="<?= e($dateValue) ?>"
                               <?= $required ? 'required' : '' ?> <?= $readonly ?>>
                    
                    <?php elseif (in_array($config['type'], ['datetime', 'timestamp'])): ?>
                        <?php $datetimeValue = $value ? str_replace(' ', 'T', substr($value, 0, 16)) : ''; ?>
                        <input type="datetime-local" name="<?= e($fieldName) ?>" class="form-input" 
                               value="<?= e($datetimeValue) ?>"
                               <?= $required ? 'required' : '' ?> <?= $readonly ?>>
                    
                    <?php else: ?>
                        <input type="text" name="<?= e($fieldName) ?>" class="form-input" 
                               value="<?= e($value) ?>"
                               <?= $required ? 'required' : '' ?>
                               maxlength="<?= $config['length'] ?>" <?= $readonly ?>>
                        <?php if ($config['length'] && !$isPrimaryKey): ?>
                            <div class="form-hint"><?= t('maximum_length') ?>: <?= $config['length'] ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="form-actions" style="padding: 20px 24px; margin-top: 0;">
            <button type="submit" class="btn btn-cyan"><?= t('update_record') ?></button>
            <a href="record.php?class=<?= urlencode($modelClass) ?>&id=<?= $recordId ?>" class="btn btn-secondary"><?= t('cancel') ?></a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
