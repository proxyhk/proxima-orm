<?php
/**
 * Proxima Admin Panel - Professional Interface
 * Modern sidebar design with table management
 * 
 * Access: /admin/ (no token required, just username/password)
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

// Load settings
try {
    $settings = Settings::load($projectDir);
} catch (Exception $e) {
    http_response_code(500);
    die('<h1 style="text-align: center; margin-top: 100px; color: #ef4444;">Configuration Error</h1><p style="text-align: center; color: #94a3b8;">' . htmlspecialchars($e->getMessage()) . '</p>');
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (Settings::verifyAdmin($projectDir, $username, $password)) {
        $_SESSION['proxima_admin_authenticated'] = true;
        $_SESSION['proxima_admin_user'] = $username;
        $_SESSION['proxima_admin_login_time'] = time();
        $_SESSION['proxima_admin_project_dir'] = $projectDir; // Store project dir for API
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
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
        
        /* Data View Styles */
        .data-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: #18181b;
            border: 1px solid #27272a;
            border-bottom: none;
            margin-bottom: 0;
        }
        .data-info {
            font-size: 13px;
            color: #a1a1aa;
        }
        .data-count {
            font-weight: 600;
            color: #fafafa;
        }
        .data-search {
            flex: 0 0 550px;
            display: flex;
            gap: 8px;
        }
        .search-input {
            flex: 1;
            padding: 8px 14px;
            background: #09090b;
            border: 1px solid #27272a;
            color: #fafafa;
            font-size: 13px;
            font-family: inherit;
        }
        .search-input:focus {
            outline: none;
            border-color: #52525b;
        }
        .search-input::placeholder {
            color: #52525b;
        }
        .search-select {
            padding: 8px 12px;
            background: #09090b;
            border: 1px solid #27272a;
            color: #fafafa;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            min-width: 140px;
        }
        .search-select:focus {
            outline: none;
            border-color: #52525b;
        }
        .search-select option {
            background: #18181b;
            color: #fafafa;
        }
        .search-btn, .clear-btn {
            padding: 8px 16px;
            background: #27272a;
            border: none;
            color: #a1a1aa;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.1s;
        }
        .search-btn:hover, .clear-btn:hover {
            background: #3f3f46;
            color: #fafafa;
        }
        .clear-btn {
            padding: 8px 12px;
            color: #ef4444;
        }
        .clear-btn:hover {
            background: #3f3f46;
            color: #fca5a5;
        }
        
        .data-table-container {
            background: #18181b;
            border: 1px solid #27272a;
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 12px 24px;
            font-size: 11px;
            font-weight: 600;
            color: #52525b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #09090b;
            border-bottom: 1px solid #27272a;
            white-space: nowrap;
        }
        .data-table td {
            padding: 16px 24px;
            border-bottom: 1px solid #27272a;
            font-size: 13px;
            color: #fafafa;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .data-table tbody tr:hover {
            background: #27272a;
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: #18181b;
            border: 1px solid #27272a;
            border-top: none;
            margin-top: 0;
        }
        .pagination-btn {
            padding: 8px 16px;
            background: #27272a;
            border: none;
            color: #a1a1aa;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.1s;
        }
        .pagination-btn:hover:not(:disabled) {
            background: #3f3f46;
            color: #fafafa;
        }
        .pagination-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        /* Action Buttons */
        .action-btn {
            background: none;
            border: 1px solid #27272a;
            color: #a1a1aa;
            font-size: 16px;
            padding: 6px 10px;
            cursor: pointer;
            transition: all 0.1s;
            margin: 0 2px;
        }
        .action-btn:hover {
            border-color: #3f3f46;
            background: #27272a;
        }
        .view-btn:hover {
            color: #22d3ee;
            border-color: #22d3ee;
        }
        .edit-btn:hover {
            color: #a78bfa;
            border-color: #a78bfa;
        }
        .delete-btn:hover {
            color: #f87171;
            border-color: #f87171;
        }
        .pagination-btn:hover:not(:disabled) {
            background: #3f3f46;
            color: #fafafa;
        }
        .pagination-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        .pagination-info {
            font-size: 13px;
            color: #a1a1aa;
        }
        
        /* Create Button */
        .btn-create {
            padding: 8px 16px;
            background: #22d3ee;
            border: none;
            color: #09090b;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.1s;
            margin-right: 16px;
        }
        .btn-create:hover {
            background: #06b6d4;
        }
        
        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
        }
        .modal-content {
            position: relative;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            background: #18181b;
            border: 1px solid #27272a;
            display: flex;
            flex-direction: column;
            z-index: 1001;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid #27272a;
        }
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #fafafa;
        }
        .modal-close {
            background: none;
            border: none;
            color: #a1a1aa;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.1s;
        }
        .modal-close:hover {
            color: #fafafa;
            background: #27272a;
        }
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 24px;
            border-top: 1px solid #27272a;
            background: #09090b;
        }
        .btn-cancel {
            padding: 10px 20px;
            background: #27272a;
            border: none;
            color: #a1a1aa;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.1s;
        }
        .btn-cancel:hover {
            background: #3f3f46;
            color: #fafafa;
        }
        .btn-submit {
            padding: 10px 20px;
            background: #22d3ee;
            border: none;
            color: #09090b;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.1s;
        }
        .btn-submit:hover {
            background: #06b6d4;
        }
        
        /* Form Fields */
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #a1a1aa;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-label .required {
            color: #ef4444;
            margin-left: 4px;
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 10px 14px;
            background: #09090b;
            border: 1px solid #27272a;
            color: #fafafa;
            font-size: 14px;
            font-family: inherit;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #52525b;
        }
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-hint {
            font-size: 11px;
            color: #52525b;
            margin-top: 4px;
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
            <a href="?logout=1" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <div class="page-title" id="pageTitle">Database Management</div>
            <div class="topbar-actions">
                <button class="btn btn-primary" onclick="syncAllModels()" id="syncAllBtn">Sync All</button>
                <button class="btn btn-danger" onclick="freshMigration()" id="freshBtn">Fresh Migration</button>
                <button class="btn btn-primary" onclick="showDashboard()" id="backBtn" style="display: none;">← Back to Dashboard</button>
            </div>
        </div>
        
        <div class="content-area" id="contentArea">
            <!-- Dashboard View (default) -->
            <div id="dashboardView">
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
            
            <!-- Model Data View (hidden by default) -->
            <div id="modelDataView" style="display: none;">
                <div class="data-toolbar">
                    <div class="data-info">
                        <button class="btn-create" id="createBtn" onclick="openCreateModal()">✚ Create New</button>
                        <span class="data-count" id="dataCount">Loading...</span>
                    </div>
                    <div class="data-search">
                        <input type="text" placeholder="Search in table..." class="search-input" id="searchInput">
                        <select class="search-select" id="searchColumn">
                            <option value="all">All Columns</option>
                        </select>
                        <button class="search-btn" id="searchBtn" onclick="performSearch()">Search</button>
                        <button class="clear-btn" id="clearBtn" onclick="clearSearch()" style="display: none;">✕</button>
                    </div>
                </div>
                
                <div class="data-table-container">
                    <table class="data-table" id="dataTable">
                        <thead id="dataTableHead">
                            <tr>
                                <th>Loading...</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <tr>
                                <td class="loading">Loading records...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="pagination" id="pagination">
                    <button class="pagination-btn" id="prevBtn" disabled>← Previous</button>
                    <span class="pagination-info" id="paginationInfo">Page 1 of 1</span>
                    <button class="pagination-btn" id="nextBtn" disabled>Next →</button>
                </div>
            </div>
            
            <!-- Detail View -->
            <div id="detailView" style="display: none;">
                <div class="data-toolbar" style="justify-content: flex-start;">
                    <button class="btn btn-primary" onclick="backToModelData()">← Back to List</button>
                </div>
                
                <div class="data-table-container">
                    <table class="data-table" style="table-layout: fixed;">
                        <tbody id="detailTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Record Modal -->
    <div class="modal" id="createModal" style="display: none;">
        <div class="modal-overlay" onclick="closeCreateModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Create New Record</h2>
                <button class="modal-close" onclick="closeCreateModal()">✕</button>
            </div>
            <div class="modal-body">
                <form id="createForm">
                    <div id="formFields">
                        <div class="loading">Loading form...</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" form="createForm" class="btn-submit">Create Record</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-overlay" onclick="closeEditModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Record</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editForm" onsubmit="submitEditForm(event)"></form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" form="editForm" class="btn-submit">Update Record</button>
            </div>
        </div>
    </div>
    
    <script>
        // Session-based auth - no token needed in JavaScript
        let modelsData = [];
        let currentModel = null;
        let currentPage = 1;
        let perPage = 20;
        let allRecordsData = []; // Store all records for client-side search
        let allColumns = [];
        let modelSchema = null; // Cache schema for forms
        let currentRecordId = null; // Track current record for edit/view
        
        // Check URL params immediately to prevent flash
        const urlParams = new URLSearchParams(window.location.search);
        const hasModelInURL = urlParams.get('model') && urlParams.get('modelName');
        
        if (hasModelInURL) {
            // Hide dashboard immediately if we have model in URL
            document.addEventListener('DOMContentLoaded', () => {
                document.getElementById('dashboardView').style.display = 'none';
                document.getElementById('modelDataView').style.display = 'block';
            });
        }
        
        // Load models on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadModels();
            if (hasModelInURL) {
                restoreStateFromURL();
            }
        });
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', () => {
            const params = new URLSearchParams(window.location.search);
            const model = params.get('model');
            const modelName = params.get('modelName');
            const recordId = params.get('recordId');
            
            if (model && modelName && recordId) {
                // Navigate to record detail view
                currentModel = model;
                currentRecordId = parseInt(recordId);
                document.getElementById('pageTitle').textContent = modelName + ' - Record #' + recordId;
                document.getElementById('backBtn').style.display = 'inline-block';
                document.getElementById('syncAllBtn').style.display = 'none';
                document.getElementById('freshBtn').style.display = 'none';
                document.getElementById('dashboardView').style.display = 'none';
                document.getElementById('modelDataView').style.display = 'none';
                document.getElementById('detailView').style.display = 'block';
                // Load record data
                viewRecord(currentRecordId, false);
            } else if (model && modelName) {
                // Navigate to model data view
                currentModel = model;
                currentRecordId = null;
                currentPage = params.get('page') ? parseInt(params.get('page')) : 1;
                document.getElementById('pageTitle').textContent = modelName + ' Records';
                document.getElementById('backBtn').style.display = 'inline-block';
                document.getElementById('syncAllBtn').style.display = 'none';
                document.getElementById('freshBtn').style.display = 'none';
                document.getElementById('dashboardView').style.display = 'none';
                document.getElementById('modelDataView').style.display = 'block';
                document.getElementById('detailView').style.display = 'none';
                loadModelData();
            } else {
                // Navigate back to dashboard
                currentModel = null;
                currentRecordId = null;
                document.getElementById('pageTitle').textContent = 'Database Management';
                document.getElementById('backBtn').style.display = 'none';
                document.getElementById('syncAllBtn').style.display = 'inline-block';
                document.getElementById('freshBtn').style.display = 'inline-block';
                document.getElementById('dashboardView').style.display = 'block';
                document.getElementById('modelDataView').style.display = 'none';
                document.getElementById('detailView').style.display = 'none';
            }
        });
        
        // Restore state from URL parameters
        function restoreStateFromURL() {
            const params = new URLSearchParams(window.location.search);
            const model = params.get('model');
            const modelName = params.get('modelName');
            const page = params.get('page');
            
            if (model && modelName) {
                currentPage = page ? parseInt(page) : 1;
                // Update UI immediately
                document.getElementById('pageTitle').textContent = modelName + ' Records';
                document.getElementById('backBtn').style.display = 'inline-block';
                document.getElementById('syncAllBtn').style.display = 'none';
                document.getElementById('freshBtn').style.display = 'none';
                currentModel = model;
                // Load data
                loadModelData();
            }
        }
        
        // Update URL without page reload
        function updateURL(model = null, modelName = null, page = null, recordId = null) {
            const newParams = new URLSearchParams();
            
            if (model && modelName) {
                newParams.set('model', model);
                newParams.set('modelName', modelName);
                if (page) newParams.set('page', page);
                if (recordId) newParams.set('recordId', recordId);
            }
            
            const newURL = window.location.pathname + (newParams.toString() ? '?' + newParams.toString() : '');
            window.history.pushState({}, '', newURL);
        }
        
        async function loadModels() {
            try {
                const response = await fetch('api.php?action=getModels');
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
                // Skip orphaned models in sidebar (they don't have a class to load data)
                if (model.isOrphaned) return '';
                
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
                <div class="table-item" onclick="showModelData('${model.className}', '${model.shortName}')" style="cursor: pointer;">
                    <div class="table-icon">◆</div>
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
                    // Check if has destructive changes
                    let hasDestructive = false;
                    if (model.diff && model.diff.modify) {
                        hasDestructive = Object.values(model.diff.modify).some(change => change.destructive);
                    }
                    
                    if (hasDestructive) {
                        statusBadge = '<span class="status-badge status-pending">! Pending</span> <span class="status-badge" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">⚠ Data Loss</span>';
                    } else {
                        statusBadge = '<span class="status-badge status-pending">! Pending</span>';
                    }
                    
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
                    <td>
                        <div style="display: flex; gap: 6px;">${actionButtons}</div>
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
            // Find model data to check for destructive changes
            const modelData = modelsData.find(m => m.className === className);
            
            if (!modelData) {
                alert('Model not found');
                return;
            }
            
            // Check if there are destructive changes
            let hasDestructive = false;
            let destructiveWarnings = [];
            
            if (modelData.diff && modelData.diff.modify) {
                for (const [colName, change] of Object.entries(modelData.diff.modify)) {
                    if (change.destructive) {
                        hasDestructive = true;
                        const oldType = change.old.type;
                        const newType = change.new.type;
                        destructiveWarnings.push(`  • ${colName}: ${oldType} → ${newType}`);
                    }
                }
            }
            
            // Show warning if destructive
            let confirmMessage = `Sync model ${className}?`;
            
            if (hasDestructive) {
                confirmMessage = `⚠️ WARNING: DESTRUCTIVE CHANGES DETECTED\n\n` +
                    `The following column changes may cause DATA LOSS:\n\n` +
                    destructiveWarnings.join('\n') + `\n\n` +
                    `Examples:\n` +
                    `  - Type changes (string→integer): Data will be converted (may become 0)\n` +
                    `  - Length reduction (VARCHAR(100)→VARCHAR(50)): Data will be truncated\n` +
                    `  - nullable→NOT NULL: NULLs may cause errors\n\n` +
                    `Do you want to continue? Type "YES" to confirm:`;
                
                const confirmation = prompt(confirmMessage);
                if (confirmation !== 'YES') {
                    alert('Sync cancelled.');
                    return;
                }
            } else {
                if (!confirm(confirmMessage)) return;
            }
            
            try {
                const response = await fetch('api.php', {\r\n                    method: 'POST',\r\n                    headers: { 'Content-Type': 'application/json' },\r\n                    body: JSON.stringify({ action: 'sync', model: className })\r\n                });
                
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
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'syncAll' })
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
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'deleteTable', table: tableName })
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
        
        // Show dashboard view
        function showDashboard() {
            document.getElementById('dashboardView').style.display = 'block';
            document.getElementById('modelDataView').style.display = 'none';
            document.getElementById('detailView').style.display = 'none';
            document.getElementById('pageTitle').textContent = 'Database Management';
            document.getElementById('backBtn').style.display = 'none';
            document.getElementById('syncAllBtn').style.display = 'inline-block';
            document.getElementById('freshBtn').style.display = 'inline-block';
            currentModel = null;
            currentRecordId = null;
            updateURL(); // Clear URL params
        }
        
        // Show model data view
        async function showModelData(className, modelName, updateUrl = true) {
            currentModel = className;
            currentPage = 1;
            
            document.getElementById('pageTitle').textContent = modelName + ' Records';
            document.getElementById('backBtn').style.display = 'inline-block';
            document.getElementById('syncAllBtn').style.display = 'none';
            document.getElementById('freshBtn').style.display = 'none';
            document.getElementById('dashboardView').style.display = 'none';
            document.getElementById('modelDataView').style.display = 'block';
            
            if (updateUrl) {
                updateURL(className, modelName, currentPage);
            }
            
            await loadModelData();
        }
        
        // Load model data with pagination
        async function loadModelData() {
            if (!currentModel) return;
            
            try {
                document.getElementById('dataTableBody').innerHTML = '<tr><td colspan="100" class="loading">Loading records...</td></tr>';
                
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'getRecords', 
                        model: currentModel,
                        page: currentPage,
                        perPage: perPage
                    })
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Failed to load records');
                }
                
                // Store all data for search
                allRecordsData = result.data;
                allColumns = result.columns;
                
                // Populate column selector
                const columnSelect = document.getElementById('searchColumn');
                columnSelect.innerHTML = '<option value="all">All Columns</option>' + 
                    result.columns.map(col => `<option value="${col}">${col}</option>`).join('');
                
                displayModelData(result);
            } catch (error) {
                document.getElementById('dataTableBody').innerHTML = 
                    `<tr><td colspan="100" class="empty-state">
                        <div class="empty-icon">⚠️</div>
                        <div class="empty-title">Error</div>
                        <div class="empty-text">${error.message}</div>
                    </td></tr>`;
            }
        }
        
        // Search functionality
        function performSearch() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const selectedColumn = document.getElementById('searchColumn').value;
            
            if (!searchTerm) {
                // If empty, reload original data
                loadModelData();
                document.getElementById('clearBtn').style.display = 'none';
                return;
            }
            
            // Filter data client-side
            const filteredData = allRecordsData.filter(row => {
                if (selectedColumn === 'all') {
                    // Search in all columns
                    return allColumns.some(col => {
                        const value = row[col];
                        if (value === null || value === undefined) return false;
                        return String(value).toLowerCase().includes(searchTerm);
                    });
                } else {
                    // Search in specific column only
                    const value = row[selectedColumn];
                    if (value === null || value === undefined) return false;
                    return String(value).toLowerCase().includes(searchTerm);
                }
            });
            
            // Display filtered results
            displayModelData({
                data: filteredData,
                columns: allColumns,
                pagination: {
                    page: 1,
                    perPage: filteredData.length,
                    total: filteredData.length,
                    totalPages: 1,
                    hasNext: false,
                    hasPrev: false
                }
            });
            
            // Show clear button
            document.getElementById('clearBtn').style.display = 'inline-block';
            
            // Update count text
            const columnText = selectedColumn === 'all' ? 'all columns' : `column "${selectedColumn}"`;
            document.getElementById('dataCount').textContent = 
                `${filteredData.length} record${filteredData.length !== 1 ? 's' : ''} found in ${columnText}`;
        }
        
        // Clear search
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('searchColumn').value = 'all';
            document.getElementById('clearBtn').style.display = 'none';
            loadModelData();
        }
        
        // Add Enter key listener to search input
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('searchInput')?.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
        });
        
        // Display model data in table
        function displayModelData(result) {
            const { data, columns, pagination } = result;
            
            // Update data count
            document.getElementById('dataCount').textContent = 
                `${pagination.total} record${pagination.total !== 1 ? 's' : ''}`;
            
            // Update table header - add Actions column
            const thead = document.getElementById('dataTableHead');
            thead.innerHTML = '<tr>' + 
                columns.map(col => `<th>${col}</th>`).join('') + 
                '<th style="width: 140px; text-align: center;">Actions</th>' +
                '</tr>';
            
            // Update table body
            const tbody = document.getElementById('dataTableBody');
            
            if (data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${columns.length + 1}" class="empty-state">
                            <div class="empty-icon">📭</div>
                            <div class="empty-title">No Records Found</div>
                            <div class="empty-text">This table is empty</div>
                        </td>
                    </tr>
                `;
            } else {
                tbody.innerHTML = data.map(row => {
                    // Get primary key (usually 'id')
                    const recordId = row.id || row[columns[0]];
                    
                    return '<tr>' + columns.map(col => {
                        let value = row[col];
                        let displayValue = '';
                        let titleValue = '';
                        
                        // Format value
                        if (value === null || value === undefined) {
                            displayValue = '<span style="color: #52525b; font-style: italic;">NULL</span>';
                            titleValue = 'NULL';
                        } else if (typeof value === 'boolean') {
                            displayValue = value ? 'true' : 'false';
                            titleValue = displayValue;
                        } else {
                            // Escape HTML to prevent XSS
                            const escaped = String(value)
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#039;');
                            displayValue = escaped;
                            titleValue = escaped;
                        }
                        
                        return `<td title="${titleValue}">${displayValue}</td>`;
                    }).join('') + 
                    `<td style="text-align: center;">
                        <button class="action-btn view-btn" onclick="viewRecord(${recordId})" title="View Details">👁️</button>
                        <button class="action-btn edit-btn" onclick="openEditModal(${recordId})" title="Edit">✏️</button>
                        <button class="action-btn delete-btn" onclick="deleteRecord(${recordId})" title="Delete">🗑️</button>
                    </td>` +
                    '</tr>';
                }).join('');
            }
            
            // Update pagination
            document.getElementById('paginationInfo').textContent = 
                `Page ${pagination.page} of ${pagination.totalPages}`;
            
            document.getElementById('prevBtn').disabled = !pagination.hasPrev;
            document.getElementById('nextBtn').disabled = !pagination.hasNext;
        }
        
        // Pagination handlers
        document.getElementById('prevBtn')?.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                loadModelData();
                // Update URL with new page
                const params = new URLSearchParams(window.location.search);
                const model = params.get('model');
                const modelName = params.get('modelName');
                if (model && modelName) {
                    updateURL(model, modelName, currentPage);
                }
            }
        });
        
        document.getElementById('nextBtn')?.addEventListener('click', () => {
            currentPage++;
            loadModelData();
            // Update URL with new page
            const params = new URLSearchParams(window.location.search);
            const model = params.get('model');
            const modelName = params.get('modelName');
            if (model && modelName) {
                updateURL(model, modelName, currentPage);
            }
        });
        
        async function freshMigration() {
            const confirmed = prompt('⚠️ WARNING: This will DROP all tables and recreate them. All data will be lost!\\n\\nType "DELETE ALL DATA" to confirm:');
            
            if (confirmed !== 'DELETE ALL DATA') {
                alert('Fresh migration cancelled.');
                return;
            }
            
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'fresh' })
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
        
        // Create Record Modal Functions
        async function openCreateModal() {
            if (!currentModel) return;
            
            document.getElementById('createModal').style.display = 'flex';
            document.getElementById('formFields').innerHTML = '<div class="loading">Loading form...</div>';
            
            try {
                // Get model schema
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'getModelSchema', 
                        model: currentModel
                    })
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Failed to load schema');
                }
                
                modelSchema = result.schema;
                buildCreateForm(modelSchema);
            } catch (error) {
                document.getElementById('formFields').innerHTML = 
                    `<div class="empty-state">
                        <div class="empty-icon">⚠️</div>
                        <div class="empty-title">Error</div>
                        <div class="empty-text">${error.message}</div>
                    </div>`;
            }
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
            document.getElementById('createForm').reset();
        }
        
        function buildCreateForm(schema) {
            const formFields = document.getElementById('formFields');
            let html = '';
            
            for (const [fieldName, config] of Object.entries(schema)) {
                // Skip auto-increment primary keys
                if (config.autoIncrement && config.primaryKey) {
                    continue;
                }
                
                const required = !config.nullable ? 'required' : '';
                const requiredMark = !config.nullable ? '<span class="required">*</span>' : '';
                
                let inputHtml = '';
                let hint = '';
                
                // Build hint
                if (config.default !== null) {
                    hint = `Default: ${config.default}`;
                }
                
                // Generate input based on type
                switch (config.type) {
                    case 'text':
                        inputHtml = `<textarea class="form-textarea" name="${fieldName}" ${required} placeholder="Enter ${fieldName}"></textarea>`;
                        break;
                    
                    case 'boolean':
                        inputHtml = `
                            <select class="form-select" name="${fieldName}" ${required}>
                                <option value="">-- Select --</option>
                                <option value="1">True</option>
                                <option value="0">False</option>
                            </select>`;
                        break;
                    
                    case 'integer':
                        inputHtml = `<input type="number" class="form-input" name="${fieldName}" ${required} placeholder="Enter ${fieldName}">`;
                        hint += config.length ? ` (Max: ${config.length})` : '';
                        break;
                    
                    case 'decimal':
                        inputHtml = `<input type="number" step="0.01" class="form-input" name="${fieldName}" ${required} placeholder="Enter ${fieldName}">`;
                        hint += ` (${config.length},${config.scale})`;
                        break;
                    
                    case 'datetime':
                        inputHtml = `<input type="datetime-local" class="form-input" name="${fieldName}" ${required}>`;
                        break;
                    
                    case 'string':
                    default:
                        inputHtml = `<input type="text" class="form-input" name="${fieldName}" ${required} maxlength="${config.length}" placeholder="Enter ${fieldName}">`;
                        hint += ` (Max length: ${config.length})`;
                        break;
                }
                
                html += `
                    <div class="form-group">
                        <label class="form-label">${fieldName}${requiredMark}</label>
                        ${inputHtml}
                        ${hint ? `<div class="form-hint">${hint}</div>` : ''}
                    </div>
                `;
            }
            
            formFields.innerHTML = html;
        }
        
        // Handle form submission
        document.getElementById('createForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = {};
            
            // Convert FormData to object
            for (const [key, value] of formData.entries()) {
                data[key] = value || null;
            }
            
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'createRecord', 
                        model: currentModel,
                        data: data
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✓ Record created successfully!');
                    closeCreateModal();
                    loadModelData(); // Reload data
                } else {
                    alert('Error: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });
        
        // Edit Record Functions
        async function openEditModal(recordId) {
            if (!currentModel || !recordId) return;
            
            currentRecordId = recordId;
            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('editForm').innerHTML = '<div class="loading">Loading record...</div>';
            
            try {
                // Get record data
                const recordResponse = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'getRecord', 
                        model: currentModel,
                        id: recordId
                    })
                });
                
                const recordResult = await recordResponse.json();
                
                if (!recordResult.success) {
                    throw new Error(recordResult.error || 'Failed to load record');
                }
                
                // Get schema if not cached
                if (!modelSchema) {
                    const schemaResponse = await fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            action: 'getModelSchema', 
                            model: currentModel
                        })
                    });
                    
                    const schemaResult = await schemaResponse.json();
                    
                    if (!schemaResult.success) {
                        throw new Error(schemaResult.error || 'Failed to load schema');
                    }
                    
                    modelSchema = schemaResult.schema;
                }
                
                buildEditForm(modelSchema, recordResult.data);
            } catch (error) {
                document.getElementById('editForm').innerHTML = 
                    `<div class="empty-state">
                        <div class="empty-icon">⚠️</div>
                        <div class="empty-title">Error</div>
                        <div class="empty-text">${error.message}</div>
                    </div>`;
            }
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editForm').innerHTML = '';
            currentRecordId = null;
        }
        
        function buildEditForm(schema, recordData) {
            const editForm = document.getElementById('editForm');
            let html = '';
            
            for (const [fieldName, config] of Object.entries(schema)) {
                // Skip auto-increment primary keys but show other primary keys as readonly
                if (config.primaryKey && config.autoIncrement) {
                    continue;
                }
                
                const value = recordData[fieldName];
                const required = config.nullable === false && !config.primaryKey;
                const requiredMark = required ? ' <span class="required">*</span>' : '';
                const readonly = config.primaryKey ? 'readonly style="background: #27272a; cursor: not-allowed;"' : '';
                
                let inputHtml = '';
                let hint = '';
                
                // Helper function to safely format value (preserves 0 and empty string)
                const safeValue = (value !== null && value !== undefined) ? value : '';
                
                // Generate appropriate input based on type
                if (config.type === 'text') {
                    inputHtml = `<textarea class="form-input" name="${fieldName}" rows="4" ${readonly}>${safeValue}</textarea>`;
                } else if (config.type === 'integer' || config.type === 'bigint') {
                    inputHtml = `<input type="number" class="form-input" name="${fieldName}" value="${safeValue}" step="1" ${readonly}>`;
                } else if (config.type === 'float' || config.type === 'decimal' || config.type === 'double') {
                    inputHtml = `<input type="number" class="form-input" name="${fieldName}" value="${safeValue}" step="any" ${readonly}>`;
                } else if (config.type === 'boolean') {
                    const checked = value ? 'checked' : '';
                    inputHtml = `
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="${fieldName}" value="1" ${checked} ${readonly}>
                            <span style="color: #a1a1aa;">Enable</span>
                        </label>
                    `;
                } else if (config.type === 'date') {
                    const dateValue = value ? value.split(' ')[0] : '';
                    inputHtml = `<input type="date" class="form-input" name="${fieldName}" value="${dateValue}" ${readonly}>`;
                } else if (config.type === 'datetime' || config.type === 'timestamp') {
                    const datetimeValue = value ? value.replace(' ', 'T').substring(0, 16) : '';
                    inputHtml = `<input type="datetime-local" class="form-input" name="${fieldName}" value="${datetimeValue}" ${readonly}>`;
                } else {
                    // Default: string/varchar
                    inputHtml = `<input type="text" class="form-input" name="${fieldName}" value="${safeValue}" ${readonly}>`;
                    if (config.length) {
                        hint = `Maximum ${config.length} characters`;
                    }
                }
                
                html += `
                    <div class="form-group">
                        <label class="form-label">${fieldName}${requiredMark}</label>
                        ${inputHtml}
                        ${hint ? `<div class="form-hint">${hint}</div>` : ''}
                    </div>
                `;
            }
            
            editForm.innerHTML = html;
        }
        
        // Handle edit form submission
        async function submitEditForm(e) {
            e.preventDefault();
            
            if (!currentRecordId) {
                alert('Error: No record ID');
                return;
            }
            
            const formData = new FormData(e.target);
            const data = {};
            
            // Convert FormData to object
            for (const [key, value] of formData.entries()) {
                data[key] = value || null;
            }
            
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'updateRecord', 
                        model: currentModel,
                        id: currentRecordId,
                        data: data
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✓ Record updated successfully!');
                    closeEditModal();
                    loadModelData(); // Reload data
                } else {
                    alert('Error: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        // Delete Record Function
        async function deleteRecord(recordId) {
            if (!currentModel || !recordId) return;
            
            if (!confirm(`Are you sure you want to delete this record?\n\nThis action cannot be undone.`)) {
                return;
            }
            
            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'deleteRecord', 
                        model: currentModel,
                        id: recordId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✓ Record deleted successfully!');
                    loadModelData(); // Reload data
                } else {
                    alert('Error: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        // View Record Detail
        async function viewRecord(recordId, updateUrl = true) {
            if (!currentModel || !recordId) return;
            
            currentRecordId = recordId;
            
            // Get model name from current page title or modelsData
            const modelData = modelsData.find(m => m.className === currentModel);
            const modelName = modelData ? modelData.shortName : currentModel.split('\\').pop();
            
            // Update page title
            document.getElementById('pageTitle').textContent = modelName + ' - Record #' + recordId;
            
            // Hide model data view, show detail view
            document.getElementById('dashboardView').style.display = 'none';
            document.getElementById('modelDataView').style.display = 'none';
            document.getElementById('detailView').style.display = 'block';
            document.getElementById('detailTableBody').innerHTML = '<tr><td colspan="2" class="loading">Loading record...</td></tr>';
            
            // Update URL with recordId
            if (updateUrl) {
                updateURL(currentModel, modelName, currentPage, recordId);
            }
            
            try {
                // Get record data
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'getRecord', 
                        model: currentModel,
                        id: recordId
                    })
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Failed to load record');
                }
                
                displayRecordDetail(result.data);
            } catch (error) {
                document.getElementById('detailTableBody').innerHTML = 
                    `<tr><td colspan="2" class="empty-state">
                        <div class="empty-icon">⚠️</div>
                        <div class="empty-title">Error</div>
                        <div class="empty-text">${error.message}</div>
                    </td></tr>`;
            }
        }
        
        function displayRecordDetail(data) {
            const tbody = document.getElementById('detailTableBody');
            
            let html = '';
            for (const [key, value] of Object.entries(data)) {
                let displayValue = '';
                
                if (value === null || value === undefined) {
                    displayValue = '<span style="color: #52525b; font-style: italic;">NULL</span>';
                } else if (typeof value === 'boolean') {
                    displayValue = value ? 'true' : 'false';
                } else {
                    // Escape HTML
                    displayValue = String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }
                
                html += `
                    <tr>
                        <td style="font-weight: 600; color: #a1a1aa; width: 200px; background: #09090b;">${key}</td>
                        <td style="word-break: break-all; white-space: pre-wrap;">${displayValue}</td>
                    </tr>
                `;
            }
            
            tbody.innerHTML = html;
        }
        
        function backToModelData() {
            // Get model name from modelsData
            const modelData = modelsData.find(m => m.className === currentModel);
            const modelName = modelData ? modelData.shortName : currentModel.split('\\').pop();
            
            // Update page title
            document.getElementById('pageTitle').textContent = modelName + ' Records';
            
            // Hide detail view, show model data view
            document.getElementById('dashboardView').style.display = 'none';
            document.getElementById('detailView').style.display = 'none';
            document.getElementById('modelDataView').style.display = 'block';
            
            // Clear recordId and update URL
            currentRecordId = null;
            updateURL(currentModel, modelName, currentPage);
        }
    </script>
</body>
</html>
