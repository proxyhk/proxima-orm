<?php
/**
 * Proxima Admin Panel - API Endpoints
 * Handles AJAX requests from admin panel
 */

// Session lifetime: 30 days (BEFORE session_start!)
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 30);

session_start();

// Load Composer autoload (we're in user project: admin/)
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',  // 1 level up to project/vendor/
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
use Proxima\Core\Schema;

// Catch all errors and return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Error: $errstr in $errfile:$errline"]);
    exit;
});

header('Content-Type: application/json');

try {
    // Get project directory (1 level up: admin -> project)
    $projectDir = dirname(__DIR__);
    
    // Load settings for token verification
    $settings = Settings::load($projectDir);
    
    // Verify token from request
    $requestToken = $_GET['token'] ?? $_POST['token'] ?? null;
    
    // Also check JSON body for token
    if (!$requestToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $requestToken = $input['token'] ?? null;
    }
    
    if (!$requestToken || $requestToken !== $settings['admin']['token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
    
    // Load settings
    Database::connect($settings['database']);
    ModelDiscovery::loadFromModelsDirectory($settings['project_dir']);
    
    // Get action
    $action = $_GET['action'] ?? ($_POST['action'] ?? null);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $action;
    }
    
    switch ($action) {
        case 'getModels':
            $models = ModelDiscovery::getModelsWithInfo();
            $result = [];
            
            foreach ($models as $className => $info) {
                // Check if model is synced
                $diff = Schema::diff($className);
                $isSynced = empty($diff['add']) && empty($diff['modify']) && empty($diff['drop']);
                
                $result[] = [
                    'className' => $className,
                    'shortName' => $info['shortName'],
                    'tableName' => $info['tableName'],
                    'status' => $isSynced ? 'synced' : 'pending',
                    'diff' => $diff,
                ];
            }
            
            echo json_encode([
                'success' => true,
                'models' => $result,
            ]);
            break;
            
        case 'sync':
            $modelClass = $input['model'] ?? null;
            
            if (!$modelClass) {
                throw new Exception('Model class name required');
            }
            
            Schema::sync($modelClass);
            
            echo json_encode([
                'success' => true,
                'message' => 'Model synced successfully',
            ]);
            break;
            
        case 'syncAll':
            Schema::syncAll();
            
            echo json_encode([
                'success' => true,
                'message' => 'All models synced successfully',
            ]);
            break;
            
        case 'fresh':
            $models = ModelDiscovery::getModels();
            
            // Drop all tables
            foreach ($models as $modelClass) {
                $reflection = new ReflectionClass($modelClass);
                $tableAttr = $reflection->getAttributes(\Proxima\Attributes\Table::class)[0] ?? null;
                
                if ($tableAttr) {
                    $tableName = $tableAttr->newInstance()->name;
                    Database::getInstance()->exec("DROP TABLE IF EXISTS `{$tableName}`");
                }
            }
            
            // Recreate all tables
            Schema::syncAll();
            
            echo json_encode([
                'success' => true,
                'message' => 'Fresh migration completed',
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
