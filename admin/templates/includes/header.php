<?php
/**
 * Proxima Admin Panel - Header Template
 * 
 * Includes HTML head, sidebar navigation
 * 
 * Required variables:
 * - $pageTitle: string - Page title for <title> tag and topbar
 * - $currentPage: string - Current page identifier for sidebar highlighting
 */

// Include version management
require_once __DIR__ . '/../version.php';

$currentUser = getCurrentUser();
$flash = getFlash();

// Check for updates
$versionInfo = getVersionInfo();
$updateAvailable = $versionInfo['updateAvailable'];

// Get models for sidebar
initDatabase();
$sidebarModels = getModelsWithStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin') ?> - Proxima Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-logo"><span>◆</span> Proxima</a>
            <div class="sidebar-subtitle">Admin Panel</div>
        </div>
        
        <div class="sidebar-section">
            <div class="section-title">Models</div>
            <div class="sidebar-models">
                <?php if (empty($sidebarModels)): ?>
                    <div class="sidebar-empty">No models found</div>
                <?php else: ?>
                    <?php foreach ($sidebarModels as $model): ?>
                        <?php if ($model['isOrphaned']) continue; // Skip orphaned in sidebar ?>
                        <?php 
                        $isActive = isset($currentModel) && $currentModel === $model['className'];
                        $badgeClass = $model['status'] === 'synced' ? '' : 'pending';
                        $badgeText = $model['status'] === 'synced' ? '✓' : '!';
                        ?>
                        <a href="model.php?class=<?= urlencode($model['className']) ?>" 
                           class="table-item <?= $isActive ? 'active' : '' ?>">
                            <div class="table-icon">◆</div>
                            <div class="table-info">
                                <div class="table-name"><?= e($model['shortName']) ?></div>
                                <div class="table-meta"><?= e($model['tableName']) ?></div>
                            </div>
                            <div class="table-badge <?= $badgeClass ?>"><?= $badgeText ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <?php if ($updateAvailable): ?>
                <a href="actions.php?action=update_admin" class="btn-update" onclick="return confirm('Update admin panel to version <?= e($versionInfo['vendor']) ?>?')">
                    ⬆ Update Available (v<?= e($versionInfo['vendor']) ?>)
                </a>
            <?php endif; ?>
            
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($currentUser ?? 'A', 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= e($currentUser) ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
            <div class="sidebar-version">v<?= PROXIMA_ADMIN_VERSION ?></div>
            <a href="index.php?logout=1" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <?php if (isset($showBackButton) && $showBackButton): ?>
                    <a href="<?= e($backUrl ?? 'index.php') ?>" class="btn btn-secondary">← Back</a>
                <?php endif; ?>
                <h1 class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
            </div>
            <div class="topbar-actions">
                <?php if (isset($topbarActions)): ?>
                    <?= $topbarActions ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>">
                <?= e($flash['message']) ?>
            </div>
        <?php endif; ?>
        
        <div class="content-area">
