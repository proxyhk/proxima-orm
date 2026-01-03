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
if ($projectDir && Settings::exists($projectDir)) {
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
                    <title>Setup Already Completed</title>
                    <style>
                        body { font-family: Arial, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                        .message { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 48px; max-width: 500px; text-align: center; }
                        h1 { color: #f87171; margin-bottom: 16px; }
                        p { color: #94a3b8; line-height: 1.6; margin-bottom: 24px; }
                        .info { background: rgba(99, 102, 241, 0.1); border: 1px solid #6366f1; border-radius: 8px; padding: 16px; color: #a5b4fc; font-size: 14px; margin-top: 24px; }
                    </style>
                </head>
                <body>
                    <div class="message">
                        <h1>⚠️ Setup Already Completed</h1>
                        <p>Proxima has already been configured for this project.</p>
                        <p>If you need to reconfigure, set <code>development_mode</code> to <code>true</code> in your <code>proxima.settings.php</code> file.</p>
                        <div class="info">
                            <strong>Security Note:</strong> This restriction prevents unauthorized access to the setup wizard.
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
            
            // Copy admin files directly to admin/ folder (no token subfolder)
            copy(__DIR__ . '/templates/index.php', $adminDir . '/index.php');
            copy(__DIR__ . '/templates/api.php', $adminDir . '/api.php');
            
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
    
    // Admin URL is in USER PROJECT: admin/index.php?token=xxx
    $adminUrl = "http://{$_SERVER['HTTP_HOST']}/{$projectName}/admin/index.php?token={$token}";
    
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
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 48px;
            max-width: 650px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        .icon { width: 80px; height: 80px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; margin: 0 auto 24px; display: flex; align-items: center; justify-content: center; font-size: 48px; box-shadow: 0 0 30px rgba(16,185,129,0.4); }
        h1 { font-size: 28px; color: #f1f5f9; text-align: center; margin-bottom: 8px; }
        .sub { text-align: center; color: #94a3b8; margin-bottom: 32px; }
        .box { background: #0f172a; border: 1px solid #1e293b; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .label { font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 8px; }
        .value { font-family: Monaco, monospace; font-size: 13px; color: #60a5fa; word-break: break-all; }
        .warning { background: rgba(251,146,60,0.1); border-left: 4px solid #fb923c; padding: 16px; border-radius: 8px; margin: 24px 0; color: #cbd5e1; font-size: 14px; }
        .btn { display: block; width: 100%; background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; padding: 14px; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; text-decoration: none; text-align: center; transition: transform 0.2s; }
        .btn:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1>Setup Completed</h1>
        <p class="sub">Your admin panel is ready</p>
        
        <div class="box">
            <div class="label">Admin Panel URL</div>
            <div class="value"><?php echo htmlspecialchars($adminUrl); ?></div>
        </div>
        
        <div class="box">
            <div class="label">Username</div>
            <div class="value"><?php echo htmlspecialchars($adminUser); ?></div>
        </div>
        
        <div class="warning">
            ⚠️ <strong>Important:</strong> Save this URL securely. The token cannot be recovered if lost.
        </div>
        
        <div class="warning" style="background: rgba(34, 197, 94, 0.1); border-left-color: #22c55e; margin-top: 16px;">
            ✓ <strong>Setup Protected:</strong> This setup wizard is now locked and cannot be accessed again. To re-enable for development, set <code>development_mode</code> to <code>true</code> in your settings file.
        </div>
        
        <a href="<?php echo htmlspecialchars($adminUrl); ?>" class="btn">Open Admin Panel →</a>
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
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; color: #e2e8f0; }
        .wizard { background: #1e293b; border: 1px solid #334155; border-radius: 20px; padding: 48px; max-width: 700px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
        .logo { text-align: center; margin-bottom: 40px; }
        .logo h1 { font-size: 32px; background: linear-gradient(135deg, #6366f1, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 8px; }
        .logo p { color: #94a3b8; font-size: 14px; }
        .steps { display: flex; justify-content: space-between; margin-bottom: 40px; position: relative; }
        .steps:before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 2px; background: #334155; z-index: 0; }
        .step { flex: 1; text-align: center; position: relative; z-index: 1; }
        .step-circle { width: 40px; height: 40px; border-radius: 50%; background: #334155; color: #64748b; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-weight: 700; font-size: 16px; }
        .step.active .step-circle { background: linear-gradient(135deg, #6366f1, #a855f7); color: white; box-shadow: 0 0 20px rgba(99,102,241,0.5); }
        .step.complete .step-circle { background: #10b981; color: white; }
        .step-label { font-size: 12px; color: #64748b; font-weight: 500; }
        .step.active .step-label { color: #a5b4fc; }
        .form-group { margin-bottom: 24px; }
        label { display: block; font-size: 14px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px; }
        input { width: 100%; padding: 12px 16px; background: #0f172a; border: 1px solid #334155; border-radius: 10px; color: #e2e8f0; font-size: 14px; transition: border 0.2s; }
        input:focus { outline: none; border-color: #6366f1; }
        .browser { background: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 16px; max-height: 400px; overflow-y: auto; margin-bottom: 16px; }
        .current-path { background: #1e293b; border: 1px solid #334155; padding: 12px 16px; border-radius: 8px; margin-bottom: 12px; font-family: Monaco, monospace; font-size: 13px; color: #60a5fa; display: flex; align-items: center; gap: 8px; }
        .folder { padding: 12px 14px; background: #1e293b; border: 1px solid #334155; border-radius: 8px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 12px; }
        .folder:hover { background: #334155; border-color: #6366f1; transform: translateX(4px); }
        .actions { display: flex; gap: 12px; margin-top: 32px; }
        .btn { flex: 1; padding: 14px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(99,102,241,0.3); }
        .btn-secondary { background: #334155; color: #cbd5e1; }
        .btn-secondary:hover { background: #475569; }
        .error { background: rgba(239,68,68,0.1); border: 1px solid #dc2626; border-radius: 10px; padding: 14px; color: #fca5a5; font-size: 14px; margin-bottom: 20px; }
        .help { font-size: 13px; color: #64748b; margin-top: 6px; }
    </style>
</head>
<body>
    <div class="wizard">
        <div class="logo">
            <h1>⚡ Proxima</h1>
            <p>Admin Panel Setup Wizard</p>
        </div>
        
        <div class="steps">
            <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'complete' : 'active') : ''; ?>">
                <div class="step-circle">1</div>
                <div class="step-label">Project</div>
            </div>
            <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'complete' : 'active') : ''; ?>">
                <div class="step-circle">2</div>
                <div class="step-label">Database</div>
            </div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                <div class="step-circle">3</div>
                <div class="step-label">Admin</div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="step" value="<?php echo $step; ?>">
            
            <?php if ($step == 1): ?>
                <h2 style="font-size: 20px; margin-bottom: 24px; color: #f1f5f9;">Select Project Directory</h2>
                
                <div class="form-group">
                    <label>Browse Directories (Click folder to enter)</label>
                    <div class="browser" id="browser">
                        <div id="folders">Loading...</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Selected Project Directory</label>
                    <input type="text" name="project_dir" id="selectedDir" readonly required>
                    <div class="help">This is where your models/ folder will be created</div>
                </div>
                
                <div class="actions">
                    <button type="submit" class="btn btn-primary">Next Step →</button>
                </div>
                
            <?php elseif ($step == 2): ?>
                <h2 style="font-size: 20px; margin-bottom: 24px; color: #f1f5f9;">Database Configuration</h2>
                
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
                    <input type="password" name="db_password" placeholder="Leave empty if no password">
                </div>
                
                <div class="actions">
                    <button type="button" class="btn btn-secondary" onclick="location.href='setup.php?step=1'">← Back</button>
                    <button type="submit" class="btn btn-primary">Next Step →</button>
                </div>
                
            <?php elseif ($step == 3): ?>
                <h2 style="font-size: 20px; margin-bottom: 24px; color: #f1f5f9;">Admin Credentials</h2>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="admin_username" placeholder="admin" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="admin_password" placeholder="Enter secure password" required>
                    <div class="help">Minimum 6 characters</div>
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
                    document.getElementById('folders').innerHTML = '<div style="color: #ef4444;">' + data.error + '</div>';
                    return;
                }
                
                currentPath = data.current;
                
                // Update selected directory with actual path from server
                document.getElementById('selectedDir').value = data.current;
                
                let html = '';
                
                // Current path display
                html += `<div class="current-path">
                    <span style="color: #64748b;">📂</span>
                    <span>${data.current}</span>
                </div>`;
                
                // Folders
                if (data.items.length === 0) {
                    html += '<div style="color: #64748b; padding: 20px; text-align: center;">No subdirectories found</div>';
                } else {
                    data.items.forEach(item => {
                        const icon = item.isParent ? '⬆️' : '📁';
                        const label = item.isParent ? 'Go Up (Parent Directory)' : item.name;
                        html += `<div class="folder" data-path="${item.path}" onclick="loadDirectory(this.dataset.path)">
                            <span style="font-size: 18px;">${icon}</span> 
                            <span style="flex: 1;">${label}</span>
                            <span style="font-size: 12px; color: #64748b;">→</span>
                        </div>`;
                    });
                }
                
                document.getElementById('folders').innerHTML = html;
            } catch (error) {
                document.getElementById('folders').innerHTML = '<div style="color: #ef4444; padding: 20px; text-align: center;">Error loading directory: ' + error.message + '</div>';
            }
        }
        
        // Load initial directory (auto-detect based on server)
        loadDirectory(null);
    </script>
    <?php endif; ?>
</body>
</html>
