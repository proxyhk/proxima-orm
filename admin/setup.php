<?php
/**
 * Proxima Admin Panel - Setup Wizard
 * 
 * Access via: http://yoursite.com/vendor/proxima/orm/admin/setup.php
 * After composer require proxima/orm
 */

session_start();

// Try composer autoload first (when installed via composer)
$autoloadPaths = [
    __DIR__ . '/../../../autoload.php',  // vendor/proxima/orm/admin -> vendor/autoload.php
    __DIR__ . '/../vendor/autoload.php', // Development mode
];

$autoloadFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require $path;
        $autoloadFound = true;
        break;
    }
}

// Fallback to manual loading if composer not found
if (!$autoloadFound) {
    spl_autoload_register(function ($class) {
        $prefix = 'Proxima\\';
        $base_dir = __DIR__ . '/../src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
}

use Proxima\Core\Settings;

// Detect project directory intelligently
// Try multiple methods to find project root
$projectDir = null;

// Method 1: Check if we're in vendor/proxima/orm/admin (composer installation)
$vendorPath = dirname(dirname(dirname(dirname(__DIR__)))); // 4 levels up
if (file_exists($vendorPath . '/composer.json')) {
    $projectDir = $vendorPath;
}

// Method 2: Use document root
if (!$projectDir && isset($_SERVER['DOCUMENT_ROOT'])) {
    // Get relative path from URL
    $scriptPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $parts = explode('/vendor/', $scriptPath);
    if (count($parts) >= 2) {
        $projectDir = $_SERVER['DOCUMENT_ROOT'] . $parts[0];
    }
}

// Method 3: Manual selection will happen in step 1
// If we can't detect, user will select in the wizard

// Check if setup is already completed
// But allow showing complete page if we just finished setup
$justCompleted = (isset($_GET['step']) && $_GET['step'] === 'complete' && isset($_SESSION['setup_complete']));

if ($projectDir && Settings::exists($projectDir) && !$justCompleted) {
    try {
        $settings = Settings::load($projectDir);
        
        // Check if setup is already completed
        if (isset($settings['security']['setup_completed']) && $settings['security']['setup_completed'] === true) {
            // Allow access only in development mode
            $isDevelopment = $settings['security']['development_mode'] ?? false;
            
            if (!$isDevelopment) {
                http_response_code(403);
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Setup Already Completed - Proxima</title>
                    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body { 
                            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
                            background: #09090b; 
                            color: #fafafa; 
                            display: flex; 
                            align-items: center; 
                            justify-content: center; 
                            min-height: 100vh; 
                            padding: 24px;
                        }
                        .container { 
                            background: #18181b; 
                            border: 1px solid #27272a; 
                            max-width: 480px; 
                            width: 100%;
                        }
                        .header {
                            padding: 32px 32px 24px;
                            border-bottom: 1px solid #27272a;
                        }
                        .status {
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            padding: 6px 12px;
                            background: rgba(239, 68, 68, 0.1);
                            border: 1px solid #dc2626;
                            font-size: 12px;
                            font-weight: 600;
                            color: #ef4444;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            margin-bottom: 16px;
                        }
                        h1 { 
                            font-size: 20px; 
                            font-weight: 600; 
                            color: #fafafa;
                            margin-bottom: 8px;
                        }
                        .desc { 
                            color: #a1a1aa; 
                            font-size: 14px; 
                            line-height: 1.6;
                        }
                        .content {
                            padding: 24px 32px;
                        }
                        .info-box {
                            background: #09090b;
                            border: 1px solid #27272a;
                            padding: 16px;
                            margin-bottom: 16px;
                        }
                        .info-box strong {
                            display: block;
                            font-size: 11px;
                            font-weight: 600;
                            color: #71717a;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            margin-bottom: 8px;
                        }
                        .info-box code {
                            font-family: 'SF Mono', Monaco, monospace;
                            font-size: 13px;
                            color: #22d3ee;
                            background: none;
                        }
                        .footer {
                            padding: 16px 32px;
                            background: #09090b;
                            border-top: 1px solid #27272a;
                        }
                        .note {
                            display: flex;
                            gap: 10px;
                            font-size: 12px;
                            color: #71717a;
                        }
                        .note-icon {
                            color: #3b82f6;
                            flex-shrink: 0;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <div class="status">● Locked</div>
                            <h1>Setup Already Completed</h1>
                            <p class="desc">Proxima has been configured for this project. The setup wizard is now locked for security.</p>
                        </div>
                        <div class="content">
                            <div class="info-box">
                                <strong>To reconfigure</strong>
                                Set <code>development_mode</code> to <code>true</code> in your <code>proxima.settings.php</code> file.
                            </div>
                        </div>
                        <div class="footer">
                            <div class="note">
                                <span class="note-icon">ℹ</span>
                                <span>This restriction prevents unauthorized access to the setup wizard in production.</span>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
                <?php
                exit;
            }
        }
    } catch (\Exception $e) {
        // Settings file exists but invalid - allow setup to continue
    }
}

// Reset setup if accessing without step parameter
if (!isset($_GET['step']) && !isset($_POST['step'])) {
    unset($_SESSION['setup_step']);
    unset($_SESSION['setup_data']);
    unset($_SESSION['setup_complete']);
    unset($_SESSION['admin_token']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_project_dir']);
}

// AJAX: Directory Browser
if (isset($_GET['action']) && $_GET['action'] === 'browse') {
    $path = $_GET['path'] ?? null;
    
    // Auto-detect starting path if not provided
    if (!$path) {
        // Try to detect project root intelligently
        if ($projectDir && is_dir($projectDir)) {
            $path = $projectDir;
        } elseif (isset($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT'])) {
            $path = $_SERVER['DOCUMENT_ROOT'];
        } else {
            // Platform-specific defaults
            $path = (DIRECTORY_SEPARATOR === '\\') ? 'C:\\' : '/';
        }
    }
    
    // Validate and normalize path
    $path = realpath($path);
    if (!$path || !is_dir($path)) {
        echo json_encode(['error' => 'Invalid directory']);
        exit;
    }
    
    $items = [];
    
    // Get parent directory link (except for root)
    $parentDir = dirname($path);
    if ($parentDir !== $path && is_readable($parentDir)) {
        $items[] = ['name' => '..', 'path' => $parentDir, 'isParent' => true];
    }
    
    // Read directories
    try {
        $dirs = @glob($path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        if ($dirs === false) {
            $dirs = [];
        }
        
        foreach ($dirs as $dir) {
            if (is_readable($dir)) {
                $items[] = ['name' => basename($dir), 'path' => $dir, 'isParent' => false];
            }
        }
    } catch (Exception $e) {
        // Ignore permission errors
    }
    
    header('Content-Type: application/json');
    echo json_encode(['items' => $items, 'current' => $path]);
    exit;
}

// Initialize
$step = $_GET['step'] ?? $_SESSION['setup_step'] ?? 1;
$_SESSION['setup_step'] = $step;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $currentStep = $_POST['step'] ?? 1;
        
        if ($currentStep == 1) {
            $projectDir = $_POST['project_dir'] ?? '';
            if (empty($projectDir) || !is_dir($projectDir)) {
                throw new Exception('Invalid project directory');
            }
            $_SESSION['setup_data']['project_dir'] = $projectDir;
            $_SESSION['setup_step'] = 2;
            header('Location: setup.php?step=2');
            exit;
        }
        
        if ($currentStep == 2) {
            $db = [
                'host' => $_POST['db_host'] ?? '',
                'dbname' => $_POST['db_name'] ?? '',
                'user' => $_POST['db_user'] ?? '',
                'password' => $_POST['db_password'] ?? '',
                'charset' => 'utf8mb4',
            ];
            if (empty($db['host']) || empty($db['dbname']) || empty($db['user'])) {
                throw new Exception('All database fields are required');
            }
            $_SESSION['setup_data']['database'] = $db;
            $_SESSION['setup_step'] = 3;
            header('Location: setup.php?step=3');
            exit;
        }
        
        if ($currentStep == 3) {
            $adminUser = $_POST['admin_username'] ?? '';
            $adminPass = $_POST['admin_password'] ?? '';
            
            if (empty($adminUser) || empty($adminPass)) {
                throw new Exception('All fields required');
            }
            if (strlen($adminPass) < 6) {
                throw new Exception('Password must be at least 6 characters');
            }
            
            $projectDir = $_SESSION['setup_data']['project_dir'];
            if (Settings::exists($projectDir)) {
                throw new Exception('Settings already exist');
            }
            
            // Normalize path separators for cross-platform compatibility
            $projectDir = str_replace('\\', '/', $projectDir);
            
            // Create models/ directory
            $modelsDir = rtrim($projectDir, '/') . '/models';
            if (!is_dir($modelsDir)) {
                mkdir($modelsDir, 0755, true);
            }
            
            // Create admin/ directory in USER PROJECT
            $adminDir = rtrim($projectDir, '/') . '/admin';
            if (!is_dir($adminDir)) {
                mkdir($adminDir, 0755, true);
            }
            
            // Generate admin token
            $token = Settings::generateToken(32);
            
            // Create settings
            $config = [
                'project_dir' => $projectDir,
                'database' => $_SESSION['setup_data']['database'],
                'admin' => [
                    'token' => $token,
                    'username' => $adminUser,
                    'password_hash' => password_hash($adminPass, PASSWORD_DEFAULT),
                ],
                'security' => [
                    'setup_completed' => true,
                    'development_mode' => false,
                ],
            ];
            Settings::create($projectDir, $config);
            
            // Copy admin files to admin/ folder
            $templatesDir = __DIR__ . '/templates';
            
            // Create subdirectories
            $includesDir = $adminDir . '/includes';
            $assetsDir = $adminDir . '/assets';
            if (!is_dir($includesDir)) {
                mkdir($includesDir, 0755, true);
            }
            if (!is_dir($assetsDir)) {
                mkdir($assetsDir, 0755, true);
            }
            
            // Copy main PHP files
            copy($templatesDir . '/index.php', $adminDir . '/index.php');
            copy($templatesDir . '/model.php', $adminDir . '/model.php');
            copy($templatesDir . '/record.php', $adminDir . '/record.php');
            copy($templatesDir . '/create.php', $adminDir . '/create.php');
            copy($templatesDir . '/edit.php', $adminDir . '/edit.php');
            copy($templatesDir . '/actions.php', $adminDir . '/actions.php');
            
            // Copy includes
            copy($templatesDir . '/includes/auth.php', $includesDir . '/auth.php');
            copy($templatesDir . '/includes/functions.php', $includesDir . '/functions.php');
            copy($templatesDir . '/includes/header.php', $includesDir . '/header.php');
            copy($templatesDir . '/includes/footer.php', $includesDir . '/footer.php');
            
            // Copy assets
            copy($templatesDir . '/assets/style.css', $assetsDir . '/style.css');
            copy($templatesDir . '/assets/app.js', $assetsDir . '/app.js');
            
            $_SESSION['setup_complete'] = true;
            $_SESSION['admin_token'] = $token;
            $_SESSION['admin_username'] = $adminUser;
            $_SESSION['admin_project_dir'] = $projectDir;
            
            header('Location: setup.php?step=complete');
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Completion page
if ($step === 'complete' && isset($_SESSION['setup_complete'])):
    $token = $_SESSION['admin_token'];
    $adminUser = $_SESSION['admin_username'];
    $projectDir = $_SESSION['admin_project_dir'];
    $projectName = basename($projectDir);
    
    // Admin URL is now simple - no token needed
    $adminUrl = "http://{$_SERVER['HTTP_HOST']}/{$projectName}/admin/";
    
    session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Complete - Proxima</title>
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
            color: #fafafa;
        }
        .container {
            background: #18181b;
            border: 1px solid #27272a;
            max-width: 560px;
            width: 100%;
        }
        .header {
            padding: 32px 32px 24px;
            border-bottom: 1px solid #27272a;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid #22c55e;
            font-size: 12px;
            font-weight: 600;
            color: #22c55e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }
        h1 { 
            font-size: 24px; 
            font-weight: 600; 
            color: #fafafa;
            margin-bottom: 8px;
        }
        .desc { 
            color: #a1a1aa; 
            font-size: 14px;
        }
        .content {
            padding: 24px 32px;
        }
        .field {
            background: #09090b;
            border: 1px solid #27272a;
            padding: 16px;
            margin-bottom: 12px;
        }
        .field-label {
            font-size: 11px;
            font-weight: 600;
            color: #71717a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .field-value {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 13px;
            color: #22d3ee;
            word-break: break-all;
            line-height: 1.5;
        }
        .alerts {
            padding: 0 32px 24px;
        }
        .alert {
            padding: 14px 16px;
            margin-bottom: 8px;
            font-size: 13px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .alert-warning {
            background: rgba(251, 146, 60, 0.08);
            border-left: 3px solid #f97316;
            color: #fed7aa;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.08);
            border-left: 3px solid #22c55e;
            color: #bbf7d0;
        }
        .alert-icon {
            flex-shrink: 0;
            font-size: 14px;
        }
        .alert code {
            font-family: 'SF Mono', Monaco, monospace;
            color: #22d3ee;
            font-size: 12px;
        }
        .footer {
            padding: 24px 32px;
            background: #09090b;
            border-top: 1px solid #27272a;
        }
        .btn {
            display: block;
            width: 100%;
            background: #fafafa;
            color: #09090b;
            padding: 14px 24px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background 0.15s;
        }
        .btn:hover {
            background: #e4e4e7;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="status">● Complete</div>
            <h1>Setup Completed</h1>
            <p class="desc">Your Proxima admin panel is ready to use.</p>
        </div>
        
        <div class="content">
            <div class="field">
                <div class="field-label">Admin Panel URL</div>
                <div class="field-value"><?php echo htmlspecialchars($adminUrl); ?></div>
            </div>
            
            <div class="field">
                <div class="field-label">Username</div>
                <div class="field-value"><?php echo htmlspecialchars($adminUser); ?></div>
            </div>
        </div>
        
        <div class="alerts">
            <div class="alert alert-warning">
                <span class="alert-icon">!</span>
                <span><strong>Save this URL securely.</strong> The access token cannot be recovered if lost.</span>
            </div>
            <div class="alert alert-success">
                <span class="alert-icon">✓</span>
                <span>Setup wizard is now locked. Set <code>development_mode: true</code> in settings to reconfigure.</span>
            </div>
        </div>
        
        <div class="footer">
            <a href="<?php echo htmlspecialchars($adminUrl); ?>" class="btn">Open Admin Panel →</a>
        </div>
    </div>
</body>
</html>
<?php exit; endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Wizard - Proxima</title>
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
            color: #fafafa; 
        }
        .wizard { background: #18181b; border: 1px solid #27272a; max-width: 600px; width: 100%; }
        .logo { padding: 32px 32px 24px; border-bottom: 1px solid #27272a; }
        .logo h1 { font-size: 13px; font-weight: 600; color: #71717a; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
        .logo h1 span { color: #22d3ee; }
        .logo p { font-size: 20px; font-weight: 600; color: #fafafa; }
        .steps { display: flex; padding: 0 32px; gap: 8px; background: #09090b; border-bottom: 1px solid #27272a; padding-top: 16px; padding-bottom: 16px; }
        .step { flex: 1; display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #18181b; border: 1px solid #27272a; }
        .step.active { border-color: #fafafa; }
        .step.complete { border-color: #22c55e; }
        .step-circle { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #52525b; border: 1px solid #3f3f46; }
        .step.active .step-circle { background: #fafafa; color: #09090b; border-color: #fafafa; }
        .step.complete .step-circle { background: #22c55e; color: #fff; border-color: #22c55e; }
        .step-label { font-size: 13px; font-weight: 500; color: #52525b; }
        .step.active .step-label { color: #fafafa; }
        .step.complete .step-label { color: #22c55e; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 12px; font-weight: 600; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        input { width: 100%; padding: 12px 14px; background: #09090b; border: 1px solid #27272a; color: #fafafa; font-size: 14px; font-family: inherit; }
        input:focus { outline: none; border-color: #52525b; }
        input::placeholder { color: #52525b; }
        .browser { background: #09090b; border: 1px solid #27272a; max-height: 320px; overflow-y: auto; }
        .current-path { padding: 12px 14px; background: #18181b; border-bottom: 1px solid #27272a; font-family: 'SF Mono', Monaco, monospace; font-size: 12px; color: #22d3ee; display: flex; align-items: center; gap: 10px; }
        .folder { padding: 12px 14px; border-bottom: 1px solid #27272a; cursor: pointer; transition: background 0.1s; display: flex; align-items: center; justify-content: flex-start; gap: 12px; }
        .folder:last-child { border-bottom: none; }
        .folder:hover { background: #27272a; }
        .actions { display: flex; gap: 12px; padding: 24px 32px; background: #09090b; border-top: 1px solid #27272a; }
        .btn { flex: 1; padding: 12px 20px; border: none; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; }
        .btn-primary { background: #fafafa; color: #09090b; }
        .btn-primary:hover { background: #e4e4e7; }
        .btn-secondary { background: #27272a; color: #a1a1aa; }
        .btn-secondary:hover { background: #3f3f46; }
        .error { background: rgba(239,68,68,0.1); border-left: 3px solid #ef4444; padding: 12px 14px; color: #fca5a5; font-size: 13px; margin: 24px 32px 0; }
        .help { font-size: 12px; color: #52525b; margin-top: 8px; }
        .content { padding: 32px; }
        .section-title { font-size: 15px; font-weight: 600; color: #fafafa; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="wizard">
        <div class="logo">
            <h1><span>◆</span> Proxima</h1>
            <p>Setup Wizard</p>
        </div>
        
        <div class="steps">
            <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'complete' : 'active') : ''; ?>">
                <div class="step-circle"><?php echo $step > 1 ? '✓' : '1'; ?></div>
                <div class="step-label">Project</div>
            </div>
            <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'complete' : 'active') : ''; ?>">
                <div class="step-circle"><?php echo $step > 2 ? '✓' : '2'; ?></div>
                <div class="step-label">Database</div>
            </div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                <div class="step-circle">3</div>
                <div class="step-label">Admin</div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="step" value="<?php echo $step; ?>">
            
            <?php if ($step == 1): ?>
                <div class="content">
                    <div class="section-title">Select Project Directory</div>
                    
                    <div class="form-group">
                        <label>Browse Directories</label>
                        <div class="browser" id="browser">
                            <div id="folders" style="padding: 20px; text-align: center; color: #52525b;">Loading...</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Selected Directory</label>
                        <input type="text" name="project_dir" id="selectedDir" readonly required>
                        <div class="help">This is where your models/ and admin/ folders will be created</div>
                    </div>
                </div>
                
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Continue →</button>
                </div>
                
            <?php elseif ($step == 2): ?>
                <div class="content">
                    <div class="section-title">Database Configuration</div>
                    
                    <div class="form-group">
                        <label>Host</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Database Name</label>
                        <input type="text" name="db_name" placeholder="my_database" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="db_user" value="root" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="db_password" placeholder="Leave empty if none">
                    </div>
                </div>
                
                <div class="actions">
                    <button type="button" class="btn btn-secondary" onclick="location.href='setup.php?step=1'">← Back</button>
                    <button type="submit" class="btn btn-primary">Continue →</button>
                </div>
                
            <?php elseif ($step == 3): ?>
                <div class="content">
                    <div class="section-title">Admin Credentials</div>
                    
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="admin_username" placeholder="admin" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="admin_password" placeholder="Secure password" required>
                        <div class="help">Minimum 6 characters</div>
                    </div>
                </div>
                
                <div class="actions">
                    <button type="button" class="btn btn-secondary" onclick="location.href='setup.php?step=2'">← Back</button>
                    <button type="submit" class="btn btn-primary">Complete Setup</button>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if ($step == 1): ?>
    <script>
        let currentPath = null;
        
        async function loadDirectory(path) {
            currentPath = path;
            
            // Auto-select current directory
            document.getElementById('selectedDir').value = path;
            
            try {
                const url = path ? `setup.php?action=browse&path=${encodeURIComponent(path)}` : 'setup.php?action=browse';
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.error) {
                    document.getElementById('folders').innerHTML = '<div style="padding: 20px; text-align: center; color: #ef4444;">' + data.error + '</div>';
                    return;
                }
                
                currentPath = data.current;
                
                // Update selected directory with actual path from server
                document.getElementById('selectedDir').value = data.current;
                
                let html = '';
                
                // Current path display
                html += `<div class="current-path">
                    <span style="font-size: 14px;">📂</span>
                    <span style="text-align: left;">${data.current}</span>
                </div>`;
                
                // Folders
                if (data.items.length === 0) {
                    html += '<div style="padding: 20px; text-align: center; color: #52525b;">No subdirectories</div>';
                } else {
                    data.items.forEach(item => {
                        const icon = item.isParent ? '📂' : '📁';
                        const label = item.isParent ? '.. (Parent)' : item.name;
                        html += `<div class="folder" data-path="${item.path}" onclick="loadDirectory(this.dataset.path)">
                            <span style="font-size: 16px; flex-shrink: 0;">${icon}</span>
                            <span style="flex: 1; text-align: left;">${label}</span>
                            <span style="color: #3f3f46; flex-shrink: 0;">→</span>
                        </div>`;
                    });
                }
                
                document.getElementById('folders').innerHTML = html;
            } catch (error) {
                document.getElementById('folders').innerHTML = '<div style="padding: 20px; text-align: center; color: #ef4444;">Error: ' + error.message + '</div>';
            }
        }
        
        // Load initial directory (auto-detect based on server)
        loadDirectory(null);
    </script>
    <?php endif; ?>
</body>
</html>
