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
        $loginError = 'Invalid username or password';
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
    <title>Login - Proxima Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-brand"><span>◆</span> Proxima</div>
                <h1 class="login-title">Admin Login</h1>
            </div>
            
            <?php if (isset($loginError)): ?>
                <div class="login-error"><?= e($loginError) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="login-content">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" required>
                    </div>
                </div>
                
                <div class="login-footer">
                    <button type="submit" name="login" class="btn-login">Sign In →</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php exit; endif; ?>

<?php
// === AUTHENTICATED DASHBOARD ===

$pageTitle = 'Database Management';
initDatabase();
$models = getModelsWithStatus();

// Calculate stats
$totalModels = count(array_filter($models, fn($m) => !$m['isOrphaned']));
$syncedCount = count(array_filter($models, fn($m) => $m['status'] === 'synced'));
$pendingCount = count(array_filter($models, fn($m) => $m['status'] === 'pending'));
$orphanedCount = count(array_filter($models, fn($m) => $m['status'] === 'orphaned'));

// Topbar actions
$topbarActions = '
    <a href="actions.php?action=sync_all" class="btn btn-primary" onclick="return confirmAction(\'Sync all models?\')">Sync All</a>
    <a href="actions.php?action=fresh" class="btn btn-danger" onclick="return confirmFreshMigration()">Fresh Migration</a>
';

include __DIR__ . '/includes/header.php';
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Models</div>
        <div class="stat-value"><?= $totalModels ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Synced</div>
        <div class="stat-value"><?= $syncedCount ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Changes</div>
        <div class="stat-value"><?= $pendingCount ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Orphaned Tables</div>
        <div class="stat-value <?= $orphanedCount > 0 ? 'danger' : '' ?>"><?= $orphanedCount ?></div>
    </div>
</div>

<!-- Models Table -->
<div class="card">
    <div class="card-header">
        <h2>All Models</h2>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Model</th>
                <th>Table Name</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($models)): ?>
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <div class="empty-icon">◆</div>
                            <div class="empty-title">No Models Found</div>
                            <div class="empty-text">Create model files in the models/ directory</div>
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
                                <span class="status-badge status-synced">✓ Synced</span>
                            <?php elseif ($model['status'] === 'orphaned'): ?>
                                <span class="status-badge status-orphaned">✗ Orphaned</span>
                            <?php else: ?>
                                <span class="status-badge status-pending">! Pending</span>
                                <?php if ($model['hasDestructive']): ?>
                                    <span class="status-badge status-destructive">⚠ Data Loss</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if (!$model['isOrphaned']): ?>
                                <a href="model.php?class=<?= urlencode($model['className']) ?>" class="btn btn-sm btn-secondary">View</a>
                                <a href="actions.php?action=sync&class=<?= urlencode($model['className']) ?>" 
                                   class="btn btn-sm btn-primary"
                                   onclick="return confirmAction('Sync model <?= e($model['shortName']) ?>?')">Sync</a>
                            <?php endif; ?>
                            <a href="actions.php?action=delete_table&table=<?= urlencode($model['tableName']) ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirmDeleteTable('<?= e($model['tableName']) ?>')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
