<?php
/**
 * Proxima Admin Panel - Helper Functions
 * 
 * Common utility functions used across admin pages
 */

use Proxima\Core\Schema;
use Proxima\Core\ModelDiscovery;
use Proxima\Core\Database;

/**
 * Get all models with their info and sync status
 */
function getModelsWithStatus(): array
{
    $models = ModelDiscovery::getModelsWithInfo();
    $result = [];
    $modelTableNames = [];
    
    foreach ($models as $className => $info) {
        $diff = Schema::diff($className);
        $isSynced = empty($diff['add']) && empty($diff['modify']) && empty($diff['drop']);
        
        $modelTableNames[] = $info['tableName'];
        
        // Check for destructive changes
        $hasDestructive = false;
        if (!empty($diff['modify'])) {
            foreach ($diff['modify'] as $change) {
                if ($change['destructive'] ?? false) {
                    $hasDestructive = true;
                    break;
                }
            }
        }
        
        $result[] = [
            'className' => $className,
            'shortName' => $info['shortName'],
            'tableName' => $info['tableName'],
            'status' => $isSynced ? 'synced' : 'pending',
            'diff' => $diff,
            'hasDestructive' => $hasDestructive,
            'isOrphaned' => false,
        ];
    }
    
    // Check for orphaned tables
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    
    foreach ($allTables as $tableName) {
        if (!in_array($tableName, $modelTableNames)) {
            $result[] = [
                'className' => null,
                'shortName' => $tableName,
                'tableName' => $tableName,
                'status' => 'orphaned',
                'diff' => [],
                'hasDestructive' => false,
                'isOrphaned' => true,
            ];
        }
    }
    
    return $result;
}

/**
 * Get model info by class name
 */
function getModelByClass(string $className): ?array
{
    $models = getModelsWithStatus();
    foreach ($models as $model) {
        if ($model['className'] === $className) {
            return $model;
        }
    }
    return null;
}

/**
 * Get model schema (column definitions)
 */
function getModelSchema(string $modelClass): array
{
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
    
    return $schema;
}

/**
 * Get records with pagination
 */
function getRecords(string $modelClass, int $page = 1, int $perPage = 20): array
{
    $offset = ($page - 1) * $perPage;
    $total = $modelClass::query()->count();
    
    $records = $modelClass::query()
        ->limit($perPage)
        ->offset($offset)
        ->get();
    
    // Get column names
    $columns = [];
    if (!empty($records)) {
        $columns = array_keys(get_object_vars($records[0]));
    } else {
        $schema = getModelSchema($modelClass);
        $columns = array_keys($schema);
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
    
    return [
        'data' => $data,
        'columns' => $columns,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => (int) ceil($total / $perPage),
            'hasNext' => $page < ceil($total / $perPage),
            'hasPrev' => $page > 1,
        ],
    ];
}

/**
 * Get single record by ID
 */
function getRecord(string $modelClass, int $id): ?array
{
    $record = $modelClass::find($id);
    
    if (!$record) {
        return null;
    }
    
    $schema = getModelSchema($modelClass);
    $data = [];
    
    foreach (array_keys($schema) as $col) {
        $data[$col] = $record->$col ?? null;
    }
    
    return $data;
}

/**
 * Create a new record
 */
function createRecord(string $modelClass, array $data): int
{
    $record = new $modelClass();
    
    foreach ($data as $key => $value) {
        if (property_exists($record, $key)) {
            $record->$key = ($value === '' || $value === null) ? null : $value;
        }
    }
    
    $record->save();
    
    return $record->id ?? 0;
}

/**
 * Update a record
 */
function updateRecord(string $modelClass, int $id, array $data): bool
{
    $record = $modelClass::find($id);
    
    if (!$record) {
        throw new Exception("Record with ID=$id not found");
    }
    
    $schema = getModelSchema($modelClass);
    
    foreach ($data as $key => $value) {
        if (property_exists($record, $key)) {
            // Skip primary key
            if (isset($schema[$key]['primaryKey']) && $schema[$key]['primaryKey']) {
                continue;
            }
            $record->$key = ($value === '' || $value === null) ? null : $value;
        }
    }
    
    $record->save();
    
    return true;
}

/**
 * Delete a record
 */
function deleteRecord(string $modelClass, int $id): bool
{
    $record = $modelClass::find($id);
    
    if (!$record) {
        throw new Exception("Record with ID=$id not found");
    }
    
    $record->delete();
    
    return true;
}

/**
 * Sync a model
 */
function syncModel(string $modelClass): array
{
    return Schema::sync($modelClass);
}

/**
 * Sync all models
 */
function syncAllModels(): array
{
    return Schema::syncAll();
}

/**
 * Delete a table
 */
function deleteTable(string $tableName): bool
{
    // Sanitize table name
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    Database::getConnection()->exec("DROP TABLE IF EXISTS `{$tableName}`");
    return true;
}

/**
 * Fresh migration - drop all tables and recreate
 */
function freshMigration(): bool
{
    $models = ModelDiscovery::getModels();
    $pdo = Database::getConnection();
    
    foreach ($models as $modelClass) {
        $reflection = new ReflectionClass($modelClass);
        $tableAttr = $reflection->getAttributes(\Proxima\Attributes\Table::class)[0] ?? null;
        
        if ($tableAttr) {
            $tableName = $tableAttr->newInstance()->name;
            $pdo->exec("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }
    
    Schema::syncAll();
    
    return true;
}

/**
 * Escape HTML for safe output
 */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format value for display in tables
 */
function formatValue(mixed $value): string
{
    if ($value === null) {
        return '<span class="null-value">NULL</span>';
    }
    
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    
    return e($value);
}

/**
 * Generate CSRF token
 */
function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF hidden input
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}
