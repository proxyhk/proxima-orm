<?php

namespace Proxima\Core;

use ReflectionClass;
use Proxima\Attributes\Table;
use Proxima\Attributes\Column;

class Schema
{
    // Model sınıfını alır ve veritabanında tablosunu oluşturur
    public static function create(string $modelClass): void
    {
        // 1. REFLECTION: Sınıfın röntgenini çekelim
        $reflection = new ReflectionClass($modelClass);

        // 2. TABLO ADINI BUL: #[Table] etiketini oku
        $tableAttributes = $reflection->getAttributes(Table::class);
        
        if (empty($tableAttributes)) {
            die("Hata: $modelClass sınıfında #[Table] etiketi tanımlanmamış!");
        }

        // Table(name: 'users') kısmındaki 'users'ı alıyoruz
        $tableName = $tableAttributes[0]->newInstance()->name;

        // 3. KOLONLARI BUL: Sınıfın içindeki özellikleri (properties) gez
        $columnsSql = [];
        $primaryKey = null;

        foreach ($reflection->getProperties() as $property) {
            // Her özelliğin tepesindeki #[Column] etiketine bak
            $colAttributes = $property->getAttributes(Column::class);

            if (!empty($colAttributes)) {
                // Etiketi başlat (new Column(...) yapmış gibi olur)
                $column = $colAttributes[0]->newInstance();
                $colName = $property->getName();

                // SQL parçasını oluştur: "username VARCHAR(150)" gibi
                $sqlPart = "$colName " . self::mapTypeToSql($column->type, $column->length, $column->scale);

                // Ayarları ekle
                if (!$column->nullable) {
                    $sqlPart .= " NOT NULL";
                }
                
                if ($column->unique) {
                    $sqlPart .= " UNIQUE";
                }

                if ($column->autoIncrement) {
                    $sqlPart .= " AUTO_INCREMENT";
                }

                if ($column->primaryKey) {
                    $primaryKey = $colName;
                }

                $columnsSql[] = $sqlPart;
            }
        }

        // Primary Key varsa SQL'in sonuna ekle
        if ($primaryKey) {
            $columnsSql[] = "PRIMARY KEY ($primaryKey)";
        }

        // 4. SQL'İ BİRLEŞTİR
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (" . implode(", ", $columnsSql) . ") ENGINE=InnoDB;";

        // 5. ÇALIŞTIR
        Database::getConnection()->exec($sql);
        
        // Silent mode - no echo for API usage
    }

    // Basit bir tip dönüşüm haritası
    private static function mapTypeToSql(string $type, int $length, int $scale = 0): string
    {
        return match ($type) {
            'string'  => "VARCHAR($length)",
            'integer' => "INT",
            'boolean' => "TINYINT(1)", // SQL'de boolean yoktur, 0-1 vardır
            'text'    => "TEXT",
            'datetime'=> "DATETIME",
            'decimal' => "DECIMAL($length,$scale)",
            default   => "VARCHAR(255)"
        };
    }

    /**
     * Inspect existing table structure from database
     * 
     * @param string $tableName Table name to inspect
     * @return array|null Array of column definitions or null if table doesn't exist
     */
    public static function inspect(string $tableName): ?array
    {
        $db = Database::getConnection();
        $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
        
        $sql = "SELECT 
                    COLUMN_NAME as name,
                    DATA_TYPE as type,
                    CHARACTER_MAXIMUM_LENGTH as length,
                    NUMERIC_PRECISION as numeric_precision,
                    NUMERIC_SCALE as numeric_scale,
                    IS_NULLABLE as nullable,
                    COLUMN_KEY as `key`,
                    EXTRA as extra,
                    COLUMN_DEFAULT as `default`
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = :dbName
                AND TABLE_NAME = :tableName
                ORDER BY ORDINAL_POSITION";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['dbName' => $dbName, 'tableName' => $tableName]);
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($columns)) {
            return null; // Table doesn't exist
        }
        
        $structure = [];
        foreach ($columns as $col) {
            $structure[$col['name']] = [
                'type' => $col['type'],
                'length' => $col['length'] ?? $col['numeric_precision'],
                'scale' => $col['numeric_scale'] ?? 0,
                'nullable' => $col['nullable'] === 'YES',
                'primaryKey' => $col['key'] === 'PRI',
                'unique' => $col['key'] === 'UNI',
                'autoIncrement' => str_contains($col['extra'], 'auto_increment'),
                'default' => $col['default']
            ];
        }
        
        return $structure;
    }

    /**
     * Get column definitions from Model class
     * 
     * @param string $modelClass Model class name
     * @return array Array of column definitions
     */
    private static function getModelColumns(string $modelClass): array
    {
        $reflection = new ReflectionClass($modelClass);
        $columns = [];
        
        foreach ($reflection->getProperties() as $property) {
            $colAttributes = $property->getAttributes(Column::class);
            
            if (!empty($colAttributes)) {
                $column = $colAttributes[0]->newInstance();
                $colName = $property->getName();
                
                $columns[$colName] = [
                    'type' => $column->type,
                    'length' => $column->length,
                    'scale' => $column->scale,
                    'nullable' => $column->nullable,
                    'primaryKey' => $column->primaryKey,
                    'unique' => $column->unique,
                    'autoIncrement' => $column->autoIncrement
                ];
            }
        }
        
        return $columns;
    }

    /**
     * Compare model definition with database structure
     * 
     * @param string $modelClass Model class name
     * @return array Differences array with 'add', 'modify', 'drop' keys
     */
    public static function diff(string $modelClass): array
    {
        // Get table name
        $reflection = new ReflectionClass($modelClass);
        $tableAttributes = $reflection->getAttributes(Table::class);
        
        if (empty($tableAttributes)) {
            throw new \Exception("Model $modelClass doesn't have #[Table] attribute");
        }
        
        $tableName = $tableAttributes[0]->newInstance()->name;
        
        // Get current database structure
        $dbStructure = self::inspect($tableName);
        
        // Get model structure
        $modelStructure = self::getModelColumns($modelClass);
        
        $diff = [
            'add' => [],
            'modify' => [],
            'drop' => [],
            'tableExists' => $dbStructure !== null
        ];
        
        // If table doesn't exist, all columns need to be added
        if ($dbStructure === null) {
            $diff['add'] = $modelStructure;
            return $diff;
        }
        
        // Find columns to add or modify
        foreach ($modelStructure as $colName => $modelCol) {
            if (!isset($dbStructure[$colName])) {
                // Column doesn't exist in DB
                $diff['add'][$colName] = $modelCol;
            } else {
                // Column exists, check if it needs modification
                $dbCol = $dbStructure[$colName];
                
                // Normalize types for comparison
                $modelType = self::normalizeType($modelCol['type']);
                $dbType = self::normalizeType($dbCol['type']);
                
                // Only check length for string and decimal types
                $needsLengthCheck = in_array($modelType, ['string', 'decimal']);
                $lengthDiff = $needsLengthCheck && ($modelCol['length'] != $dbCol['length']);
                $scaleDiff = $needsLengthCheck && ($modelCol['scale'] ?? 0) != ($dbCol['scale'] ?? 0);
                
                if ($modelType !== $dbType || 
                    $lengthDiff ||
                    $scaleDiff ||
                    $modelCol['nullable'] !== $dbCol['nullable']) {
                    $diff['modify'][$colName] = [
                        'old' => $dbCol,
                        'new' => $modelCol
                    ];
                }
            }
        }
        
        // Find columns to drop (exist in DB but not in model)
        foreach ($dbStructure as $colName => $dbCol) {
            if (!isset($modelStructure[$colName])) {
                $diff['drop'][] = $colName;
            }
        }
        
        return $diff;
    }

    /**
     * Normalize database type to model type
     */
    private static function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'varchar', 'char' => 'string',
            'int', 'bigint', 'smallint', 'tinyint', 'integer' => 'integer',
            'text', 'longtext', 'mediumtext' => 'text',
            'datetime', 'timestamp', 'date' => 'datetime',
            'decimal', 'numeric' => 'decimal',
            'boolean' => 'integer', // boolean is stored as tinyint in MySQL
            default => $type
        };
    }

    /**
     * Sync model with database (CREATE or ALTER)
     * 
     * @param string $modelClass Model class name
     * @return array Result with SQL statements and success status
     */
    public static function sync(string $modelClass): array
    {
        $diff = self::diff($modelClass);
        $reflection = new ReflectionClass($modelClass);
        $tableAttributes = $reflection->getAttributes(Table::class);
        $tableName = $tableAttributes[0]->newInstance()->name;
        
        $result = [
            'table' => $tableName,
            'sql' => [],
            'success' => true,
            'message' => ''
        ];
        
        // If table doesn't exist, create it
        if (!$diff['tableExists']) {
            try {
                self::create($modelClass);
                $result['message'] = "Table '$tableName' created successfully";
                $result['sql'][] = "CREATE TABLE $tableName";
                return $result;
            } catch (\Exception $e) {
                $result['success'] = false;
                $result['message'] = "Failed to create table: " . $e->getMessage();
                return $result;
            }
        }
        
        $db = Database::getConnection();
        $alterStatements = [];
        
        // Add new columns
        foreach ($diff['add'] as $colName => $colDef) {
            $sqlType = self::mapTypeToSql($colDef['type'], $colDef['length'], $colDef['scale'] ?? 0);
            $sql = "ALTER TABLE $tableName ADD COLUMN $colName $sqlType";
            
            if (!$colDef['nullable']) {
                $sql .= " NOT NULL";
            }
            
            if ($colDef['unique']) {
                $sql .= " UNIQUE";
            }
            
            $alterStatements[] = $sql;
        }
        
        // Modify existing columns
        foreach ($diff['modify'] as $colName => $changes) {
            $colDef = $changes['new'];
            $sqlType = self::mapTypeToSql($colDef['type'], $colDef['length'], $colDef['scale'] ?? 0);
            $sql = "ALTER TABLE $tableName MODIFY COLUMN $colName $sqlType";
            
            if (!$colDef['nullable']) {
                $sql .= " NOT NULL";
            }
            
            if ($colDef['unique']) {
                $sql .= " UNIQUE";
            }
            
            $alterStatements[] = $sql;
        }
        
        // Execute ALTER statements
        try {
            foreach ($alterStatements as $sql) {
                $db->exec($sql);
                $result['sql'][] = $sql;
            }
            
            $addCount = count($diff['add']);
            $modifyCount = count($diff['modify']);
            $dropCount = count($diff['drop']);
            
            if ($addCount + $modifyCount + $dropCount === 0) {
                $result['message'] = "Table '$tableName' is already up to date";
            } else {
                $parts = [];
                if ($addCount > 0) $parts[] = "$addCount column(s) added";
                if ($modifyCount > 0) $parts[] = "$modifyCount column(s) modified";
                if ($dropCount > 0) $parts[] = "$dropCount column(s) need manual drop";
                
                $result['message'] = "Table '$tableName' synced: " . implode(', ', $parts);
            }
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['message'] = "Failed to sync table: " . $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Sync all discovered models
     * 
     * @return array Results for all models
     */
    public static function syncAll(): array
    {
        $models = ModelDiscovery::getModels();
        $results = [];
        
        foreach ($models as $modelClass) {
            $results[$modelClass] = self::sync($modelClass);
        }
        
        return $results;
    }
}