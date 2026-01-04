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
$pageTitle = 'Create ' . $modelInfo['shortName'];
$showBackButton = true;
$backUrl = 'model.php?class=' . urlencode($modelClass);

include __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width: 600px;">
    <div class="card-header">
        <h2>New <?= e($modelInfo['shortName']) ?></h2>
    </div>
    
    <form method="POST" action="actions.php">
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
                    
                    <?php if ($config['type'] === 'text'): ?>
                        <textarea name="<?= e($fieldName) ?>" class="form-textarea" 
                                  <?= $required ? 'required' : '' ?>
                                  placeholder="Enter <?= e($fieldName) ?>"></textarea>
                    
                    <?php elseif ($config['type'] === 'boolean'): ?>
                        <select name="<?= e($fieldName) ?>" class="form-select" <?= $required ? 'required' : '' ?>>
                            <option value="">-- Select --</option>
                            <option value="1">True</option>
                            <option value="0">False</option>
                        </select>
                    
                    <?php elseif ($config['type'] === 'integer'): ?>
                        <input type="number" name="<?= e($fieldName) ?>" class="form-input" 
                               <?= $required ? 'required' : '' ?> step="1"
                               placeholder="Enter <?= e($fieldName) ?>">
                        <?php if ($config['length']): ?>
                            <div class="form-hint">Maximum: <?= $config['length'] ?></div>
                        <?php endif; ?>
                    
                    <?php elseif ($config['type'] === 'decimal'): ?>
                        <input type="number" name="<?= e($fieldName) ?>" class="form-input" 
                               <?= $required ? 'required' : '' ?> step="0.01"
                               placeholder="Enter <?= e($fieldName) ?>">
                        <div class="form-hint">Format: <?= $config['length'] ?>,<?= $config['scale'] ?></div>
                    
                    <?php elseif ($config['type'] === 'datetime'): ?>
                        <input type="datetime-local" name="<?= e($fieldName) ?>" class="form-input" 
                               <?= $required ? 'required' : '' ?>>
                    
                    <?php else: ?>
                        <input type="text" name="<?= e($fieldName) ?>" class="form-input" 
                               <?= $required ? 'required' : '' ?>
                               maxlength="<?= $config['length'] ?>"
                               placeholder="Enter <?= e($fieldName) ?>">
                        <div class="form-hint">Maximum length: <?= $config['length'] ?></div>
                    <?php endif; ?>
                    
                    <?php if ($config['default'] !== null): ?>
                        <div class="form-hint">Default: <?= e($config['default']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="form-actions" style="padding: 20px 24px; margin-top: 0;">
            <button type="submit" class="btn btn-cyan">Create Record</button>
            <a href="model.php?class=<?= urlencode($modelClass) ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
