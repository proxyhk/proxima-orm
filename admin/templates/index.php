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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(51, 65, 85, 0.5);
            border-radius: 20px;
            padding: 48px;
            width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .logo { text-align: center; margin-bottom: 32px; }
        .logo h1 { font-size: 28px; color: #818cf8; margin-bottom: 8px; font-weight: 700; }
        .logo p { color: #94a3b8; font-size: 14px; font-weight: 400; }
        .error { background: rgba(239, 68, 68, 0.1); border: 1px solid #dc2626; border-radius: 12px; padding: 14px; margin-bottom: 24px; color: #fca5a5; font-size: 14px; }
        .form-group { margin-bottom: 24px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px; letter-spacing: 0.3px; }
        input { width: 100%; padding: 14px 16px; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(51, 65, 85, 0.8); border-radius: 10px; color: #e2e8f0; font-size: 14px; transition: all 0.2s; font-family: 'Inter', sans-serif; }
        input:focus { outline: none; border-color: #818cf8; background: rgba(15, 23, 42, 0.9); }
        .btn-login { width: 100%; background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%); color: white; border: none; padding: 14px; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-top: 8px; }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(129, 140, 248, 0.4); }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">
            <h1>⚡ Proxima</h1>
            <p>Admin Panel</p>
        </div>
        
        <?php if (isset($loginError)): ?>
            <div class="error">⚠️ <?php echo htmlspecialchars($loginError); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>USERNAME</label>
                <input type="text" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label>PASSWORD</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit" name="login" class="btn-login">
                Sign In →
            </button>
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
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            border-right: 1px solid #334155;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 1px solid #334155;
        }
        .sidebar-logo {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #818cf8 0%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        .sidebar-subtitle {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
        
        .sidebar-section {
            padding: 24px 16px 16px;
        }
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 12px 12px;
        }
        .table-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 4px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            color: #cbd5e1;
            text-decoration: none;
        }
        .table-item:hover {
            background: rgba(99, 102, 241, 0.1);
            color: #818cf8;
        }
        .table-item.active {
            background: rgba(129, 140, 248, 0.15);
            color: #818cf8;
            font-weight: 600;
        }
        .table-icon {
            width: 32px;
            height: 32px;
            background: rgba(129, 140, 248, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .table-info {
            flex: 1;
        }
        .table-name {
            font-size: 14px;
            font-weight: 500;
        }
        .table-meta {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }
        .table-badge {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
            font-size: 10px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
        }
        .table-badge.pending {
            background: rgba(251, 146, 60, 0.1);
            color: #fb923c;
        }
        
        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid #334155;
            margin-top: auto;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #818cf8, #a855f7);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }
        .user-details {
            flex: 1;
        }
        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #f1f5f9;
        }
        .user-role {
            font-size: 12px;
            color: #64748b;
        }
        .btn-logout {
            width: 100%;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-logout:hover {
            background: rgba(239, 68, 68, 0.2);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
        }
        .topbar {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #334155;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #f1f5f9;
        }
        .topbar-actions {
            display: flex;
            gap: 12px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #818cf8, #6366f1);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(129, 140, 248, 0.4);
        }
        .btn-danger {
            background: linear-gradient(135deg, #f87171, #dc2626);
            color: white;
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(248, 113, 113, 0.4);
        }
        
        .content-area {
            padding: 40px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 24px;
        }
        .stat-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #f1f5f9;
        }
        
        .models-list {
            background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
            border: 1px solid #334155;
            border-radius: 16px;
            overflow: hidden;
        }
        .models-header {
            padding: 24px 32px;
            border-bottom: 1px solid #334155;
        }
        .models-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #f1f5f9;
        }
        .models-table {
            width: 100%;
        }
        .models-table th {
            text-align: left;
            padding: 16px 32px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #334155;
        }
        .models-table td {
            padding: 20px 32px;
            border-bottom: 1px solid rgba(51, 65, 85, 0.5);
        }
        .models-table tr:last-child td {
            border-bottom: none;
        }
        .models-table tr:hover {
            background: rgba(129, 140, 248, 0.05);
        }
        .model-name-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .model-icon-big {
            width: 40px;
            height: 40px;
            background: rgba(129, 140, 248, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .model-info-cell {
            flex: 1;
        }
        .model-name-text {
            font-size: 15px;
            font-weight: 600;
            color: #f1f5f9;
        }
        .model-table-text {
            font-size: 13px;
            color: #64748b;
            font-family: 'Monaco', monospace;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-synced {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
        }
        .status-pending {
            background: rgba(251, 146, 60, 0.1);
            color: #fb923c;
        }
        .btn-table-action {
            padding: 8px 16px;
            background: rgba(129, 140, 248, 0.1);
            border: 1px solid rgba(129, 140, 248, 0.2);
            color: #818cf8;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-table-action:hover {
            background: rgba(129, 140, 248, 0.2);
        }
        
        .loading {
            text-align: center;
            padding: 60px;
            color: #64748b;
        }
        .empty-state {
            text-align: center;
            padding: 80px 40px;
        }
        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }
        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .empty-text {
            font-size: 14px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">⚡ Proxima</div>
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
                <button class="btn btn-primary" onclick="syncAllModels()">🔄 Sync All</button>
                <button class="btn btn-danger" onclick="freshMigration()">⚠️ Fresh Migration</button>
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
            
            sidebar.innerHTML = models.map(model => `
                <div class="table-item">
                    <div class="table-icon">📦</div>
                    <div class="table-info">
                        <div class="table-name">${model.shortName}</div>
                        <div class="table-meta">${model.tableName}</div>
                    </div>
                    <div class="table-badge ${model.status === 'synced' ? '' : 'pending'}">
                        ${model.status === 'synced' ? '✓' : '!'}
                    </div>
                </div>
            `).join('');
        }
        
        function displayModels(models) {
            const tbody = document.getElementById('modelsTableBody');
            
            if (models.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="empty-state">
                            <div class="empty-icon">📦</div>
                            <div class="empty-title">No Models Found</div>
                            <div class="empty-text">Create model files in the models/ directory</div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = models.map(model => `
                <tr>
                    <td>
                        <div class="model-name-cell">
                            <div class="model-icon-big">📦</div>
                            <div class="model-info-cell">
                                <div class="model-name-text">${model.shortName}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="model-table-text">${model.tableName}</span>
                    </td>
                    <td>
                        <span class="status-badge ${model.status === 'synced' ? 'status-synced' : 'status-pending'}">
                            ${model.status === 'synced' ? '✓ Synced' : '! Pending'}
                        </span>
                    </td>
                    <td>
                        <button class="btn-table-action" onclick="syncModel('${model.className}')">
                            Sync
                        </button>
                    </td>
                </tr>
            `).join('');
        }
        
        function updateStats(models) {
            const syncedCount = models.filter(m => m.status === 'synced').length;
            document.getElementById('totalModels').textContent = models.length;
            document.getElementById('syncedCount').textContent = syncedCount;
            document.getElementById('pendingCount').textContent = models.length - syncedCount;
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
