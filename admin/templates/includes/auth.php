<?php
/**
 * Proxima Admin Panel - Authentication Helper
 * 
 * Include this file at the top of every admin page
 * Handles session management and authentication checks
 */

// Session lifetime: 30 days (BEFORE session_start!)
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Composer autoload
$autoloadPaths = [
    __DIR__ . '/../../vendor/autoload.php',  // admin/includes -> project/vendor
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

use Proxima\Core\Settings;
use Proxima\Core\Database;
use Proxima\Core\ModelDiscovery;

// Get project directory (2 levels up: admin/includes -> admin -> project)
$projectDir = dirname(dirname(__DIR__));

// Load settings
try {
    $settings = Settings::load($projectDir);
} catch (Exception $e) {
    http_response_code(500);
    die('<div style="font-family: system-ui; text-align: center; margin-top: 100px;">
        <h1 style="color: #ef4444;">Configuration Error</h1>
        <p style="color: #94a3b8;">' . htmlspecialchars($e->getMessage()) . '</p>
    </div>');
}

/**
 * Check if user is authenticated
 */
function isAuthenticated(): bool
{
    if (!isset($_SESSION['proxima_admin_authenticated']) || $_SESSION['proxima_admin_authenticated'] !== true) {
        return false;
    }
    
    // Check session age (30 days)
    if (isset($_SESSION['proxima_admin_login_time'])) {
        $sessionAge = time() - $_SESSION['proxima_admin_login_time'];
        $maxAge = 60 * 60 * 24 * 30;
        
        if ($sessionAge > $maxAge) {
            session_destroy();
            return false;
        }
    }
    
    return true;
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth(): void
{
    if (!isAuthenticated()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Get current admin username
 */
function getCurrentUser(): ?string
{
    return $_SESSION['proxima_admin_user'] ?? null;
}

/**
 * Initialize database connection and load models
 */
function initDatabase(): void
{
    global $settings;
    
    Database::connect($settings['database']);
    ModelDiscovery::loadFromModelsDirectory($settings['project_dir']);
}

/**
 * Verify admin login credentials
 */
function verifyLogin(string $username, string $password): bool
{
    global $projectDir;
    return Settings::verifyAdmin($projectDir, $username, $password);
}

/**
 * Login user
 */
function loginUser(string $username): void
{
    global $projectDir;
    
    $_SESSION['proxima_admin_authenticated'] = true;
    $_SESSION['proxima_admin_user'] = $username;
    $_SESSION['proxima_admin_login_time'] = time();
    $_SESSION['proxima_admin_project_dir'] = $projectDir;
}

/**
 * Logout user
 */
function logoutUser(): void
{
    session_destroy();
}

/**
 * Flash message helper - set message
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Flash message helper - get and clear message
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
