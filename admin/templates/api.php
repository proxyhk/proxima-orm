<?php
/**
 * Proxima Admin Panel - API Endpoints
 * Handles AJAX requests from admin panel
 * 
 * Authentication: Session-based (no token required)
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
    // Check session authentication
    if (!isset($_SESSION['proxima_admin_authenticated']) || $_SESSION['proxima_admin_authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated. Please login.']);
        exit;
    }
    
    // Get project directory (1 level up: admin -> project)
    $projectDir = dirname(__DIR__);
    
    // Load settings
    $settings = Settings::load($projectDir);
    
    // Connect to database and load models
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
            $modelTableNames = [];
            
            foreach ($models as $className => $info) {
                // Check if model is synced
                $diff = Schema::diff($className);
                $isSynced = empty($diff['add']) && empty($diff['modify']) && empty($diff['drop']);
                
                $modelTableNames[] = $info['tableName'];
                
                $result[] = [
                    'className' => $className,
                    'shortName' => $info['shortName'],
                    'tableName' => $info['tableName'],
                    'status' => $isSynced ? 'synced' : 'pending',
                    'diff' => $diff,
                    'isOrphaned' => false,
                ];
            }
            
            // Check for orphaned tables (tables in DB but no model file)
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SHOW TABLES");
            $allTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            foreach ($allTables as $tableName) {
                if (!in_array($tableName, $modelTableNames)) {
                    // This table exists in DB but has no model - it's orphaned
                    $result[] = [
                        'className' => null,
                        'shortName' => $tableName,
                        'tableName' => $tableName,
                        'status' => 'orphaned',
                        'diff' => [],
                        'isOrphaned' => true,
                    ];
                }
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
                    Database::getConnection()->exec("DROP TABLE IF EXISTS `{$tableName}`");
                }
            }
            
            // Recreate all tables
            Schema::syncAll();
            
            echo json_encode([
                'success' => true,
                'message' => 'Fresh migration completed',
            ]);
            break;
            
        case 'deleteTable':
            $tableName = $input['table'] ?? null;
            
            if (!$tableName) {
                throw new Exception('Table name required');
            }
            
            // Sanitize table name to prevent SQL injection
            $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
            
            Database::getConnection()->exec("DROP TABLE IF EXISTS `{$tableName}`");
            
            echo json_encode([
                'success' => true,
                'message' => "Table '{$tableName}' deleted successfully",
            ]);
            break;
            
        case 'getRecords':
            $modelClass = $input['model'] ?? null;
            $page = max(1, (int)($input['page'] ?? 1));
            $perPage = max(1, min(100, (int)($input['perPage'] ?? 20)));
            
            if (!$modelClass) {
                throw new Exception('Model class name required');
            }
            
            if (!class_exists($modelClass)) {
                throw new Exception("Model class '$modelClass' not found");
            }
            
            // Calculate offset
            $offset = ($page - 1) * $perPage;
            
            // Get total count
            $total = $modelClass::query()->count();
            
            // Get records with pagination
            $records = $modelClass::query()
                ->limit($perPage)
                ->offset($offset)
                ->get();
            
            // Get column names from first record or model reflection
            $columns = [];
            if (!empty($records)) {
                $columns = array_keys(get_object_vars($records[0]));
            } else {
                // If no records, get columns from model reflection
                $reflection = new ReflectionClass($modelClass);
                foreach ($reflection->getProperties() as $property) {
                    $colAttributes = $property->getAttributes(\Proxima\Attributes\Column::class);
                    if (!empty($colAttributes)) {
                        $columns[] = $property->getName();
                    }
                }
            }
            
            // Convert records to arrays
            $data = [];
            foreach ($records as $record) {
                $row = [];
                foreach ($columns as $col) {
                    $row[$col] = $record->$col ?? null;
                }
                $data[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'columns' => $columns,
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => ceil($total / $perPage),
                    'hasNext' => $page < ceil($total / $perPage),
                    'hasPrev' => $page > 1,
                ],
            ]);
            break;
            
        case 'getModelSchema':
            $modelClass = $input['model'] ?? null;
            
            if (!$modelClass) {
                throw new Exception('Model class name required');
            }
            
            if (!class_exists($modelClass)) {
                throw new Exception("Model class '$modelClass' not found");
            }
            
            // Get model schema using reflection
            $reflection = new ReflectionClass($modelClass);
            $schema = [];
            
            foreach ($reflection->getProperties() as $property) {
                $colAttributes = $property->getAttributes(\Proxima\Attributes\Column::class);
                
                if (!empty($colAttributes)) {
                    $column = $colAttributes[0]->newInstance();
                    $colName = $property->getName();
                    
                    $schema[$colName] = [
                        'type' => $column->type,
                        'length' => $column->length,
                        'scale' => $column->scale,
                        'nullable' => $column->nullable,
                        'primaryKey' => $column->primaryKey,
                        'autoIncrement' => $column->autoIncrement,
                        'unique' => $column->unique,
                        'default' => $column->default,
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'schema' => $schema,
            ]);
            break;
            
        case 'createRecord':
            $modelClass = $input['model'] ?? null;
            $data = $input['data'] ?? null;
            
            if (!$modelClass) {
                throw new Exception('Model class name required');
            }
            
            if (!$data || !is_array($data)) {
                throw new Exception('Record data required');
            }
            
            if (!class_exists($modelClass)) {
                throw new Exception("Model class '$modelClass' not found");
            }
            
            // Create new model instance
            $record = new $modelClass();
            
            // Set properties
            foreach ($data as $key => $value) {
                if (property_exists($record, $key)) {
                    // Handle empty values for nullable fields
                    if ($value === '' || $value === null) {
                        $record->$key = null;
                    } else {
                        $record->$key = $value;
                    }
                }
            }
            
            // Save record
            $record->save();
            
            echo json_encode([
                'success' => true,
                'message' => 'Record created successfully',
                'id' => $record->id ?? null,
            ]);
            break;
            
        case 'updateRecord':
            $modelClass = $input['model'] ?? null;
            $id = $input['id'] ?? null;
            $data = $input['data'] ?? null;
            
            if (!$modelClass) {
                throw new Exception('Model class name required');
            }
            
            if (!$id) {
                throw new Exception('Record ID required');
            }
            
            if (!$data || !is_array($data)) {
                throw new Exception('Record data required');
            }
            
            if (!class_exists($modelClass)) {
                throw new Exception("Model class '$modelClass' not found");
            }
            
            // Find existing record
            $record = $modelClass::find($id);
            
            if (!$record) {
                throw new Exception("Record with ID=$id not found");
            }
            
            // Update properties
            foreach ($data as $key => $value) {
                if (property_exists($record, $key)) {
                    // Skip primary key
                    $reflection = new ReflectionClass($modelClass);
                    $property = $reflection->getProperty($key);
                    $colAttributes = $property->getAttributes(\Proxima\Attributes\Column::class);
                    
                    if (!empty($colAttributes)) {
                        $column = $colAttributes[0]->newInstance();
                        if ($column->primaryKey) {
                            continue; // Skip primary key
                        }
                    }
                    
                    // Handle empty values for nullable fields
                    if ($value === '' || $value === null) {
                        $record->$key = null;
                    } else {
                        $record->$key = $value;
                    }
                }
            }
            
            // Save record
            $record->save();
            
            echo json_encode([
                'success' => true,
                'message' => 'Record updated successfully',
            ]);
            break;
            
        case 'deleteRecord':
            $modelClass = $input['model'] ?? null;
            $id = $input['id'] ?? null;
            
            if (!$modelClass) {
                throw new Exception('Model class name required');
            }
            
            if (!$id) {
                throw new Exception('Record ID required');
            }
            
            if (!class_exists($modelClass)) {
                throw new Exception("Model class '$modelClass' not found");
            }
            
            // Find and delete record
            $record = $modelClass::find($id);
            
            if (!$record) {
                throw new Exception("Record with ID=$id not found");
            }
            
            $record->delete();
            
            echo json_encode([
                'success' => true,
                'message' => 'Record deleted successfully',
            ]);
            break;
            
        case 'getRecord':
            $modelClass = $input['model'] ?? null;
            $id = $input['id'] ?? null;
            
            if (!$modelClass) {
                throw new Exception('Model class name required');
            }
            
            if (!$id) {
                throw new Exception('Record ID required');
            }
            
            if (!class_exists($modelClass)) {
                throw new Exception("Model class '$modelClass' not found");
            }
            
            // Find record
            $record = $modelClass::find($id);
            
            if (!$record) {
                throw new Exception("Record with ID=$id not found");
            }
            
            // Get all properties
            $data = [];
            $reflection = new ReflectionClass($modelClass);
            foreach ($reflection->getProperties() as $property) {
                $colAttributes = $property->getAttributes(\Proxima\Attributes\Column::class);
                if (!empty($colAttributes)) {
                    $propName = $property->getName();
                    $data[$propName] = $record->$propName ?? null;
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $data,
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
