<?php
/**
 * Proxima Admin Panel - Create New Record
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();
initDatabase();

// Get model class
$modelClass = $_GET['class'] ?? null;

if (!$modelClass || !class_exists($modelClass)) {
    setFlash('error', 'Invalid model class');
    header('Location: index.php');
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

// Current model for sidebar
$currentModel = $modelClass;

// Page settings
$pageTitle = t('create') . ' ' . $modelInfo['shortName'];
$showBackButton = true;
$backUrl = 'model.php?class=' . urlencode($modelClass);

include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h2><?= t('new') ?> <?= e($modelInfo['shortName']) ?></h2>
    </div>
    
    <form method="POST" action="actions.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create_record">
        <input type="hidden" name="class" value="<?= e($modelClass) ?>">
        <?= csrfField() ?>
        
        <div style="padding: 24px;">
            <?php foreach ($schema as $fieldName => $config): ?>
                <?php 
                // Skip auto-increment primary keys
                if ($config['autoIncrement'] && $config['primaryKey']) {
                    continue;
                }
                
                $required = !$config['nullable'];
                $requiredMark = $required ? '<span class="required">*</span>' : '';
                ?>
                
                <div class="form-group">
                    <label class="form-label"><?= e($fieldName) ?> <?= $requiredMark ?></label>
                    
                    <?php if (!empty($config['isImage'])): ?>
                        <input type="file" name="<?= e($fieldName) ?>" class="form-input" 
                               accept="image/*" <?= $required ? 'required' : '' ?>>
                        <div class="form-hint"><?= t('upload_image') ?></div>
                    
                    <?php elseif ($config['type'] === 'text'): ?>
                        <?php $editorClass = ($config['useEditor'] ?? true) ? 'hugerte-editor' : ''; ?>
                        <textarea name="<?= e($fieldName) ?>" class="form-textarea <?= $editorClass ?>" 
                                  <?= $required ? 'required' : '' ?>
                                  placeholder="<?= t('enter') ?> <?= e($fieldName) ?>"></textarea>
                    
                    <?php elseif ($config['type'] === 'boolean'): ?>
                        <select name="<?= e($fieldName) ?>" class="form-select" <?= $required ? 'required' : '' ?>>
                            <option value=""><?= t('select') ?></option>
                            <option value="1"><?= t('true') ?></option>
                            <option value="0"><?= t('false') ?></option>
                        </select>
                    
                    <?php elseif ($config['type'] === 'integer'): ?>
                        <input type="number" name="<?= e($fieldName) ?>" class="form-input" 
                               <?= $required ? 'required' : '' ?> step="1"
                               placeholder="<?= t('enter') ?> <?= e($fieldName) ?>">
                        <?php if ($config['length']): ?>
                            <div class="form-hint"><?= t('maximum') ?>: <?= $config['length'] ?></div>
                        <?php endif; ?>
                    
                    <?php elseif ($config['type'] === 'decimal'): ?>
                        <input type="number" name="<?= e($fieldName) ?>" class="form-input" 
                               <?= $required ? 'required' : '' ?> step="0.01"
                               placeholder="<?= t('enter') ?> <?= e($fieldName) ?>">
                        <div class="form-hint"><?= t('format') ?>: <?= $config['length'] ?>,<?= $config['scale'] ?></div>
                    
                    <?php elseif ($config['type'] === 'datetime'): ?>
                        <input type="datetime-local" name="<?= e($fieldName) ?>" class="form-input" 
                               <?= $required ? 'required' : '' ?>>
                    
                    <?php else: ?>
                        <input type="text" name="<?= e($fieldName) ?>" class="form-input" 
                               <?= $required ? 'required' : '' ?>
                               maxlength="<?= $config['length'] ?>"
                               placeholder="<?= t('enter') ?> <?= e($fieldName) ?>">
                        <div class="form-hint"><?= t('maximum_length') ?>: <?= $config['length'] ?></div>
                    <?php endif; ?>
                    
                    <?php if ($config['default'] !== null): ?>
                        <div class="form-hint"><?= t('default') ?>: <?= e($config['default']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="form-actions" style="padding: 20px 24px; margin-top: 0;">
            <button type="submit" class="btn btn-cyan"><?= t('create_record') ?></button>
            <a href="model.php?class=<?= urlencode($modelClass) ?>" class="btn btn-secondary"><?= t('cancel') ?></a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
