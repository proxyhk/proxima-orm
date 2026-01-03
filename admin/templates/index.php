<?php
/**
 * Proxima Admin Panel - Professional Interface
 * Modern sidebar design with table management
 */

// Session lifetime: 30 days (BEFORE session_start!)
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);

session_start();

// Load Composer autoload (we're in user project: admin/)
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require $path;
        break;
    }
}

use Proxima\Core\Settings;
use Proxima\Core\Database;
use Proxima\Core\ModelDiscovery;

// Get project directory (1 level up: admin -> project)
$projectDir = dirname(__DIR__);

// Verify token from URL parameter or session
$currentToken = $_GET['token'] ?? $_SESSION['proxima_admin_token'] ?? null;

if (!$currentToken) {
    http_response_code(403);
    die('<h1 style="text-align: center; margin-top: 100px; color: #ef4444;">Access Denied - Token Required</h1><p style="text-align: center; color: #94a3b8;">Please use the correct URL with token parameter.</p>');
}

try {
    $settings = Settings::load($projectDir);
    
    // Check if token matches
    if (!isset($settings['admin']['token']) || $settings['admin']['token'] !== $currentToken) {
        http_response_code(403);
        die('<h1 style="text-align: center; margin-top: 100px; color: #ef4444;">Access Denied - Invalid Token</h1>');
    }
} catch (Exception $e) {
    http_response_code(500);
    die('<h1 style="text-align: center; margin-top: 100px; color: #ef4444;">Configuration Error</h1>');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (Settings::verifyAdmin($projectDir, $username, $password)) {
        $_SESSION['proxima_admin_authenticated'] = true;
        $_SESSION['proxima_admin_user'] = $username;
        $_SESSION['proxima_admin_login_time'] = time();
        $_SESSION['proxima_admin_token'] = $currentToken; // Store token in session
        header('Location: ' . $_SERVER['PHP_SELF'] . '?token=' . urlencode($currentToken));
        exit;
    } else {
        $loginError = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF'] . '?token=' . urlencode($currentToken));
    exit;
}

// Check authentication
$isAuthenticated = isset($_SESSION['proxima_admin_authenticated']) && $_SESSION['proxima_admin_authenticated'] === true;

// Check session age (30 days)
if ($isAuthenticated && isset($_SESSION['proxima_admin_login_time'])) {
    $sessionAge = time() - $_SESSION['proxima_admin_login_time'];
    $maxAge = 60 * 60 * 24 * 30;
    
    if ($sessionAge > $maxAge) {
        session_destroy();
        $isAuthenticated = false;
        $loginError = 'Session expired. Please login again.';
    }
}

// Show login form if not authenticated
if (!$isAuthenticated): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Proxima Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #09090b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .login-box {
            background: #18181b;
            border: 1px solid #27272a;
            width: 400px;
            max-width: 100%;
        }
        .header {
            padding: 32px 32px 24px;
            border-bottom: 1px solid #27272a;
        }
        .brand {
            font-size: 13px;
            font-weight: 600;
            color: #71717a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .brand span { color: #22d3ee; }
        h1 { font-size: 20px; font-weight: 600; color: #fafafa; }
        .error {
            background: rgba(239, 68, 68, 0.1);
            border-left: 3px solid #ef4444;
            padding: 12px 16px;
            margin: 24px 32px 0;
            color: #fca5a5;
            font-size: 13px;
        }
        .content { padding: 24px 32px; }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #71717a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            background: #09090b;
            border: 1px solid #27272a;
            color: #fafafa;
            font-size: 14px;
            font-family: inherit;
        }
        input:focus { outline: none; border-color: #52525b; }
        input::placeholder { color: #52525b; }
        .footer {
            padding: 24px 32px;
            background: #09090b;
            border-top: 1px solid #27272a;
        }
        .btn-login {
            width: 100%;
            background: #fafafa;
            color: #09090b;
            border: none;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.15s;
        }
        .btn-login:hover { background: #e4e4e7; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="header">
            <div class="brand"><span>◆</span> Proxima</div>
            <h1>Admin Login</h1>
        </div>
        
        <?php if (isset($loginError)): ?>
            <div class="error"><?php echo htmlspecialchars($loginError); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="content">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
            </div>
            
            <div class="footer">
                <button type="submit" name="login" class="btn-login">Sign In →</button>
            </div>
        </form>
    </div>
</body>
</html>
<?php exit; endif; ?>

<?php
// Load settings and initialize
try {
    $settings = Settings::load($projectDir);
    Database::connect($settings['database']);
    $models = ModelDiscovery::loadFromModelsDirectory($settings['project_dir']);
} catch (Exception $e) {
    die('Configuration error: ' . $e->getMessage());
}

$currentUser = $_SESSION['proxima_admin_user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proxima Admin - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #09090b;
            color: #fafafa;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: #18181b;
            border-right: 1px solid #27272a;
            overflow-y: auto;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid #27272a;
        }
        .sidebar-logo {
            font-size: 13px;
            font-weight: 600;
            color: #71717a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .sidebar-logo span { color: #22d3ee; }
        .sidebar-subtitle {
            font-size: 16px;
            color: #fafafa;
            font-weight: 600;
        }
        
        .sidebar-section {
            padding: 20px 12px;
            flex: 1;
        }
        .section-title {
            font-size: 11px;
            font-weight: 600;
            color: #52525b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 12px 12px;
        }
        .table-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            margin-bottom: 2px;
            cursor: pointer;
            transition: background 0.1s;
            color: #a1a1aa;
            text-decoration: none;
        }
        .table-item:hover {
            background: #27272a;
            color: #fafafa;
        }
        .table-item.active {
            background: #27272a;
            color: #fafafa;
        }
        .table-icon {
            width: 28px;
            height: 28px;
            background: #27272a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .table-info {
            flex: 1;
        }
        .table-name {
            font-size: 13px;
            font-weight: 500;
        }
        .table-meta {
            font-size: 11px;
            color: #52525b;
            font-family: 'SF Mono', Monaco, monospace;
        }
        .table-badge {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            font-size: 10px;
            font-weight: 600;
            padding: 3px 6px;
        }
        .table-badge.pending {
            background: rgba(251, 146, 60, 0.1);
            color: #f97316;
        }
        
        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid #27272a;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            background: #27272a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: #fafafa;
        }
        .user-details {
            flex: 1;
        }
        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: #fafafa;
        }
        .user-role {
            font-size: 11px;
            color: #52525b;
        }
        .btn-logout {
            display: block;
            width: 100%;
            background: #27272a;
            border: none;
            color: #a1a1aa;
            padding: 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background 0.1s;
        }
        .btn-logout:hover {
            background: #3f3f46;
            color: #ef4444;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #09090b;
        }
        .topbar {
            background: #18181b;
            border-bottom: 1px solid #27272a;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .page-title {
            font-size: 16px;
            font-weight: 600;
            color: #fafafa;
        }
        .topbar-actions {
            display: flex;
            gap: 8px;
        }
        .btn {
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-family: inherit;
            transition: background 0.1s;
        }
        .btn-primary {
            background: #fafafa;
            color: #09090b;
        }
        .btn-primary:hover {
            background: #e4e4e7;
        }
        .btn-danger {
            background: #27272a;
            color: #ef4444;
        }
        .btn-danger:hover {
            background: #3f3f46;
        }
        
        .content-area {
            padding: 32px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: #18181b;
            border: 1px solid #27272a;
            padding: 20px;
        }
        .stat-label {
            font-size: 11px;
            color: #52525b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #fafafa;
        }
        
        .models-list {
            background: #18181b;
            border: 1px solid #27272a;
        }
        .models-header {
            padding: 20px 24px;
            border-bottom: 1px solid #27272a;
        }
        .models-header h2 {
            font-size: 14px;
            font-weight: 600;
            color: #fafafa;
        }
        .models-table {
            width: 100%;
            border-collapse: collapse;
        }
        .models-table th {
            text-align: left;
            padding: 12px 24px;
            font-size: 11px;
            font-weight: 600;
            color: #52525b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #09090b;
            border-bottom: 1px solid #27272a;
        }
        .models-table td {
            padding: 16px 24px;
            border-bottom: 1px solid #27272a;
        }
        .models-table tr:last-child td {
            border-bottom: none;
        }
        .models-table tr:hover {
            background: #27272a;
        }
        .model-name-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .model-icon-big {
            width: 32px;
            height: 32px;
            background: #27272a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .model-info-cell {
            flex: 1;
        }
        .model-name-text {
            font-size: 13px;
            font-weight: 600;
            color: #fafafa;
        }
        .model-table-text {
            font-size: 12px;
            color: #52525b;
            font-family: 'SF Mono', Monaco, monospace;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-synced {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }
        .status-pending {
            background: rgba(251, 146, 60, 0.1);
            color: #f97316;
        }
        .status-orphaned {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .table-badge.orphaned {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .orphaned-row {
            background: rgba(239, 68, 68, 0.05);
        }
        .btn-delete {
            color: #ef4444 !important;
        }
        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.2) !important;
        }
        .btn-table-action {
            padding: 6px 12px;
            background: #27272a;
            border: none;
            color: #a1a1aa;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.1s;
        }
        .btn-table-action:hover {
            background: #3f3f46;
            color: #fafafa;
        }
        
        .loading {
            text-align: center;
            padding: 60px;
            color: #52525b;
        }
        .empty-state {
            text-align: center;
            padding: 60px 32px;
        }
        .empty-icon {
            font-size: 32px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        .empty-title {
            font-size: 14px;
            font-weight: 600;
            color: #71717a;
            margin-bottom: 4px;
        }
        .empty-text {
            font-size: 13px;
            color: #52525b;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><span>◆</span> Proxima</div>
            <div class="sidebar-subtitle">Admin Panel</div>
        </div>
        
        <div class="sidebar-section">
            <div class="section-title">Models</div>
            <div id="sidebarModels">
                <div class="loading">Loading models...</div>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($currentUser, 0, 1)); ?></div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($currentUser); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
            <a href="?logout=1&token=<?php echo urlencode($currentToken); ?>" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title">Database Management</div>
            <div class="topbar-actions">
                <button class="btn btn-primary" onclick="syncAllModels()">Sync All</button>
                <button class="btn btn-danger" onclick="freshMigration()">Fresh Migration</button>
            </div>
        </div>
        
        <div class="content-area">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Models</div>
                    <div class="stat-value" id="totalModels">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Synced</div>
                    <div class="stat-value" id="syncedCount">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Changes</div>
                    <div class="stat-value" id="pendingCount">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Orphaned Tables</div>
                    <div class="stat-value" id="orphanedCount" style="color: #ef4444;">-</div>
                </div>
            </div>
            
            <div class="models-list">
                <div class="models-header">
                    <h2>All Models</h2>
                </div>
                <table class="models-table">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Table Name</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="modelsTableBody">
                        <tr>
                            <td colspan="4" class="loading">Loading models...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        const ADMIN_TOKEN = '<?php echo htmlspecialchars($currentToken); ?>';
        let modelsData = [];
        
        // Load models on page load
        document.addEventListener('DOMContentLoaded', loadModels);
        
        async function loadModels() {
            try {
                const response = await fetch('api.php?action=getModels&token=' + encodeURIComponent(ADMIN_TOKEN));
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to load models');
                }
                
                modelsData = data.models;
                displayModels(modelsData);
                displaySidebar(modelsData);
                updateStats(modelsData);
            } catch (error) {
                document.getElementById('modelsTableBody').innerHTML = 
                    `<tr><td colspan="4" class="empty-state">
                        <div class="empty-icon">⚠️</div>
                        <div class="empty-title">Error</div>
                        <div class="empty-text">${error.message}</div>
                    </td></tr>`;
            }
        }
        
        function displaySidebar(models) {
            const sidebar = document.getElementById('sidebarModels');
            
            if (models.length === 0) {
                sidebar.innerHTML = '<div style="padding: 20px; text-align: center; color: #64748b;">No models found</div>';
                return;
            }
            
            sidebar.innerHTML = models.map(model => {
                let badgeClass = '';
                let badgeText = '';
                
                if (model.status === 'synced') {
                    badgeClass = '';
                    badgeText = '✓';
                } else if (model.status === 'orphaned') {
                    badgeClass = 'orphaned';
                    badgeText = '✗';
                } else {
                    badgeClass = 'pending';
                    badgeText = '!';
                }
                
                return `
                <div class="table-item">
                    <div class="table-icon">${model.isOrphaned ? '⚠' : '◆'}</div>
                    <div class="table-info">
                        <div class="table-name">${model.shortName}</div>
                        <div class="table-meta">${model.tableName}</div>
                    </div>
                    <div class="table-badge ${badgeClass}">
                        ${badgeText}
                    </div>
                </div>
            `;
            }).join('');
        }
        
        function displayModels(models) {
            const tbody = document.getElementById('modelsTableBody');
            
            if (models.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="empty-state">
                            <div class="empty-icon">◆</div>
                            <div class="empty-title">No Models Found</div>
                            <div class="empty-text">Create model files in the models/ directory</div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = models.map(model => {
                let statusBadge = '';
                let actionButtons = '';
                
                if (model.status === 'orphaned') {
                    statusBadge = '<span class="status-badge status-orphaned">✗ Orphaned</span>';
                    actionButtons = `<button class="btn-table-action btn-delete" onclick="deleteTable('${model.tableName}')">Delete</button>`;
                } else if (model.status === 'synced') {
                    statusBadge = '<span class="status-badge status-synced">✓ Synced</span>';
                    actionButtons = `
                        <button class="btn-table-action" onclick="syncModel('${model.className}')">Sync</button>
                        <button class="btn-table-action btn-delete" onclick="deleteTable('${model.tableName}')">Delete</button>
                    `;
                } else {
                    statusBadge = '<span class="status-badge status-pending">! Pending</span>';
                    actionButtons = `
                        <button class="btn-table-action" onclick="syncModel('${model.className}')">Sync</button>
                        <button class="btn-table-action btn-delete" onclick="deleteTable('${model.tableName}')">Delete</button>
                    `;
                }
                
                return `
                <tr class="${model.isOrphaned ? 'orphaned-row' : ''}">
                    <td>
                        <div class="model-name-cell">
                            <div class="model-icon-big">${model.isOrphaned ? '⚠' : '◆'}</div>
                            <div class="model-info-cell">
                                <div class="model-name-text">${model.shortName}${model.isOrphaned ? ' <small style="color:#ef4444">(no model file)</small>' : ''}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="model-table-text">${model.tableName}</span>
                    </td>
                    <td>
                        ${statusBadge}
                    </td>
                    <td style="display: flex; gap: 6px;">
                        ${actionButtons}
                    </td>
                </tr>
            `;
            }).join('');
        }
        
        function updateStats(models) {
            const syncedCount = models.filter(m => m.status === 'synced').length;
            const orphanedCount = models.filter(m => m.status === 'orphaned').length;
            const pendingCount = models.filter(m => m.status === 'pending').length;
            const totalModels = models.length - orphanedCount; // Don't count orphaned in total
            
            document.getElementById('totalModels').textContent = totalModels;
            document.getElementById('syncedCount').textContent = syncedCount;
            document.getElementById('pendingCount').textContent = pendingCount;
            document.getElementById('orphanedCount').textContent = orphanedCount;
        }
        
        async function syncModel(className) {
            if (!confirm(`Sync model ${className}?`)) return;
            
            try {
                const response = await fetch('api.php?token=' + encodeURIComponent(ADMIN_TOKEN), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'sync', model: className, token: ADMIN_TOKEN })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✓ Model synced successfully!');
                    loadModels();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function syncAllModels() {
            if (!confirm('Sync all models? This may take a while.')) return;
            
            try {
                const response = await fetch('api.php?token=' + encodeURIComponent(ADMIN_TOKEN), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'syncAll', token: ADMIN_TOKEN })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✓ All models synced successfully!');
                    loadModels();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function deleteTable(tableName) {
            if (!confirm(`Delete table "${tableName}"? This will permanently remove the table and all its data.`)) return;
            
            try {
                const response = await fetch('api.php?token=' + encodeURIComponent(ADMIN_TOKEN), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'deleteTable', table: tableName, token: ADMIN_TOKEN })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✓ Table deleted successfully!');
                    loadModels();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function freshMigration() {
            const confirmed = prompt('⚠️ WARNING: This will DROP all tables and recreate them. All data will be lost!\\n\\nType "DELETE ALL DATA" to confirm:');
            
            if (confirmed !== 'DELETE ALL DATA') {
                alert('Fresh migration cancelled.');
                return;
            }
            
            try {
                const response = await fetch('api.php?token=' + encodeURIComponent(ADMIN_TOKEN), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'fresh', token: ADMIN_TOKEN })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✓ Fresh migration completed!');
                    loadModels();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>
