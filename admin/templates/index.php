<?php
/**
 * Proxima Admin Panel - Index / Dashboard
 * 
 * Shows login form or dashboard based on authentication status
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Handle logout
if (isset($_GET['logout'])) {
    logoutUser();
    header('Location: index.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (verifyLogin($username, $password)) {
        loginUser($username);
        header('Location: index.php');
        exit;
    } else {
        $loginError = t('invalid_credentials');
    }
}

// Show login form if not authenticated
if (!isAuthenticated()):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('login') ?> - Proxima Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-brand"><span>◆</span> Proxima</div>
                <h1 class="login-title"><?= t('admin_login') ?></h1>
            </div>
            
            <?php if (isset($loginError)): ?>
                <div class="login-error"><?= e($loginError) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="login-content">
                    <div class="form-group">
                        <label class="form-label"><?= t('username') ?></label>
                        <input type="text" name="username" class="form-input" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?= t('password') ?></label>
                        <input type="password" name="password" class="form-input" required>
                    </div>
                </div>
                
                <div class="login-footer">
                    <button type="submit" name="login" class="btn-login"><?= t('sign_in') ?></button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php exit; endif; ?>

<?php
// === AUTHENTICATED DASHBOARD ===

$pageTitle = t('database_management');
initDatabase();
$models = getModelsWithStatus();

// Calculate stats
$totalModels = count(array_filter($models, fn($m) => !$m['isOrphaned']));
$syncedCount = count(array_filter($models, fn($m) => $m['status'] === 'synced'));
$pendingCount = count(array_filter($models, fn($m) => $m['status'] === 'pending'));
$orphanedCount = count(array_filter($models, fn($m) => $m['status'] === 'orphaned'));

// Topbar actions
$topbarActions = '
    <a href="actions.php?action=sync_all" class="btn btn-primary" onclick="return confirmAction(\'' . t('confirm_sync_all') . '\')">' . t('sync_all') . '</a>
    <a href="actions.php?action=fresh" class="btn btn-danger" onclick="return confirmFreshMigration()">' . t('fresh_migration') . '</a>
';

include __DIR__ . '/includes/header.php';
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label"><?= t('total_models') ?></div>
        <div class="stat-value"><?= $totalModels ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('synced') ?></div>
        <div class="stat-value"><?= $syncedCount ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('pending_changes') ?></div>
        <div class="stat-value"><?= $pendingCount ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= t('orphaned_tables') ?></div>
        <div class="stat-value <?= $orphanedCount > 0 ? 'danger' : '' ?>"><?= $orphanedCount ?></div>
    </div>
</div>

<!-- Models Table -->
<div class="card">
    <div class="card-header">
        <h2><?= t('all_models') ?></h2>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th><?= t('model') ?></th>
                <th><?= t('table_name') ?></th>
                <th><?= t('status') ?></th>
                <th><?= t('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($models)): ?>
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <div class="empty-icon">◆</div>
                            <div class="empty-title"><?= t('no_models_found') ?></div>
                            <div class="empty-text"><?= t('create_models_hint') ?></div>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($models as $model): ?>
                    <tr class="<?= $model['isOrphaned'] ? 'orphaned-row' : '' ?>">
                        <td>
                            <div class="model-cell">
                                <div class="model-icon"><?= $model['isOrphaned'] ? '⚠' : '◆' ?></div>
                                <div class="model-info">
                                    <div class="model-name">
                                        <?= e($model['shortName']) ?>
                                        <?php if ($model['isOrphaned']): ?>
                                            <small>(no model file)</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="table-name"><?= e($model['tableName']) ?></span>
                        </td>
                        <td>
                            <?php if ($model['status'] === 'synced'): ?>
                                <span class="status-badge status-synced"><?= t('synced_badge') ?></span>
                            <?php elseif ($model['status'] === 'orphaned'): ?>
                                <span class="status-badge status-orphaned"><?= t('orphaned_badge') ?></span>
                            <?php else: ?>
                                <span class="status-badge status-pending"><?= t('pending_badge') ?></span>
                                <?php if ($model['hasDestructive']): ?>
                                    <span class="status-badge status-destructive"><?= t('data_loss_warning') ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if (!$model['isOrphaned']): ?>
                                <a href="model.php?class=<?= urlencode($model['className']) ?>" class="btn btn-sm btn-secondary"><?= t('view') ?></a>
                                <a href="actions.php?action=sync&class=<?= urlencode($model['className']) ?>" 
                                   class="btn btn-sm btn-primary"
                                   onclick="return confirmAction('<?= t('confirm_sync_model') ?> <?= e($model['shortName']) ?>?')"><?= t('sync') ?></a>
                            <?php endif; ?>
                            <a href="actions.php?action=delete_table&table=<?= urlencode($model['tableName']) ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirmDeleteTable('<?= e($model['tableName']) ?>')"><?= t('delete') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
