<?php

namespace Proxima;

use ReflectionClass;
use Proxima\Core\Database;
use Proxima\Core\QueryBuilder;
use Proxima\Attributes\Table;
use Proxima\Attributes\Column;
use Proxima\Exceptions\IntegrityError;
use Proxima\Exceptions\DoesNotExist;
use PDOException;
use Exception;

abstract class Model
{
    /**
     * Yeni bir QueryBuilder instance'ı oluşturur.
     * 
     * @return QueryBuilder
     */
    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class);
    }

    /**
     * ID'ye göre tek kayıt getirir.
     * 
     * @param int $id Primary key değeri
     * @return static|null Model instance veya null
     */
    public static function find(int $id): ?static
    {
        return static::query()
            ->where(static::getPrimaryKeyName(), $id)
            ->first();
    }

    /**
     * ID'ye göre tek kayıt getirir, bulamazsa exception fırlatır.
     * 
     * @param int $id Primary key değeri
     * @return static Model instance
     * @throws DoesNotExist
     */
    public static function findOrFail(int $id): static
    {
        $result = static::find($id);
        
        if ($result === null) {
            throw new DoesNotExist("Record with ID=$id not found.");
        }
        
        return $result;
    }

    /**
     * Tüm kayıtları getirir.
     * 
     * @return array Model instance'ları dizisi
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * WHERE koşulu ile sorgu başlatır.
     * 
     * @param string $column Kolon adı
     * @param mixed $operator Operator veya değer
     * @param mixed|null $value Değer (3 parametre kullanımında)
     * @return QueryBuilder
     */
    public static function where(string $column, $operator, $value = null): QueryBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    /**
     * Tanımlanmamış static metodları QueryBuilder'a yönlendirir.
     * 
     * Bu sayede User::whereGreaterThan() gibi helper metodlar çalışır.
     */
    public static function __callStatic(string $method, array $arguments)
    {
        $query = static::query();
        
        // Eğer QueryBuilder'da bu metod varsa çağır
        if (method_exists($query, $method)) {
            return $query->$method(...$arguments);
        }
        
        throw new \BadMethodCallException("Method {$method} does not exist on " . static::class);
    }

    /**
     * Primary key kolonunun adını döndürür.
     */
    private static function getPrimaryKeyName(): string
    {
        $reflection = new ReflectionClass(static::class);
        
        foreach ($reflection->getProperties() as $property) {
            $colAttr = $property->getAttributes(Column::class);
            
            if (!empty($colAttr)) {
                $columnConfig = $colAttr[0]->newInstance();
                
                if ($columnConfig->primaryKey) {
                    return $property->getName();
                }
            }
        }
        
        return 'id'; // Default fallback
    }

    /**
     * Modeli veritabanına kaydeder veya günceller (Active Record Pattern).
     * 
     * Eğer model ID'ye sahipse (ve initialized ise) UPDATE yapar,
     * ID yoksa veya boşsa INSERT yapar.
     * 
     * @return bool Başarılıysa true, başarısızsa false
     * @throws IntegrityError Eğer veritabanı kısıtlaması (unique, not null vb.) ihlal edilirse.
     * @throws PDOException Diğer veritabanı hataları için.
     * @throws Exception Eğer sınıfta #[Table] attribute yoksa veya gerekli alanlar eksikse.
     */
    public function save(): bool
    {
        // 1. Reflection ile model bilgilerini al
        $reflection = new ReflectionClass($this);

        // 2. Tablo adını al
        $tableName = $this->getTableName($reflection);

        // 3. Primary Key ve Auto Increment bilgilerini topla
        $primaryKeyInfo = $this->getPrimaryKeyInfo($reflection);

        // 4. INSERT mi UPDATE mi karar ver
        $isUpdate = false;
        
        if ($primaryKeyInfo) {
            $pkProperty = $primaryKeyInfo['property'];
            
            if ($pkProperty->isInitialized($this)) {
                $pkValue = $pkProperty->getValue($this);
                
                // ID varsa ve 0'dan büyükse UPDATE yap
                if (!empty($pkValue)) {
                    $isUpdate = true;
                }
            }
        }

        // 5. INSERT veya UPDATE yap
        if ($isUpdate) {
            return $this->performUpdate($reflection, $tableName, $primaryKeyInfo);
        } else {
            return $this->performInsert($reflection, $tableName, $primaryKeyInfo);
        }
    }

    /**
     * Modeli veritabanından siler.
     * 
     * @return bool Başarılıysa true
     * @throws Exception Eğer model ID'ye sahip değilse
     */
    public function delete(): bool
    {
        $reflection = new ReflectionClass($this);
        $tableName = $this->getTableName($reflection);
        $primaryKeyInfo = $this->getPrimaryKeyInfo($reflection);

        if (!$primaryKeyInfo) {
            throw new Exception("Error: Cannot delete record without primary key.");
        }

        $pkProperty = $primaryKeyInfo['property'];

        if (!$pkProperty->isInitialized($this)) {
            throw new Exception("Error: Cannot delete record without ID.");
        }

        $pkName = $pkProperty->getName();
        $pkValue = $pkProperty->getValue($this);

        if (empty($pkValue)) {
            throw new Exception("Error: Cannot delete record with empty ID.");
        }

        $sql = "DELETE FROM $tableName WHERE $pkName = :id";

        try {
            $stmt = Database::getConnection()->prepare($sql);
            $result = $stmt->execute(['id' => $pkValue]);

            $affectedRows = $stmt->rowCount();

            if ($result && $affectedRows === 0) {
                throw new Exception("Warning: Record with ID=$pkValue not found.");
            }

            return $result;
        } catch (PDOException $e) {
            $this->handleDbError($e, 'DELETE');
            return false;
        }
    }

    /**
     * INSERT işlemini gerçekleştirir.
     */
    private function performInsert(ReflectionClass $reflection, string $tableName, ?array $primaryKeyInfo): bool
    {
        // Verileri topla
        $data = $this->collectDataForSave($reflection, false);

        if (empty($data)) {
            throw new Exception("Error: No data to save! At least one field must be set.");
        }

        // SQL oluştur: INSERT INTO users (username, email) VALUES (:username, :email)
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        $sql = "INSERT INTO $tableName ($columns) VALUES ($placeholders)";

        try {
            $stmt = Database::getConnection()->prepare($sql);
            $result = $stmt->execute($data);
            
            if ($result) {
                // Auto increment ID'yi modele geri ata
                $this->assignLastInsertId($reflection, $primaryKeyInfo);
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->handleDbError($e, 'INSERT');
            return false;
        }
    }

    /**
     * UPDATE işlemini gerçekleştirir.
     */
    private function performUpdate(ReflectionClass $reflection, string $tableName, array $primaryKeyInfo): bool
    {
        // Verileri topla (Primary key hariç)
        $data = $this->collectDataForSave($reflection, true);

        if (empty($data)) {
            throw new Exception("Error: No data to update! At least one field must be modified.");
        }

        // Primary key değerini al
        $pkProperty = $primaryKeyInfo['property'];
        $pkName = $pkProperty->getName();
        $pkValue = $pkProperty->getValue($this);

        // SQL oluştur: UPDATE users SET username = :username, email = :email WHERE id = :id
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "$column = :$column";
        }
        
        $sql = "UPDATE $tableName SET " . implode(", ", $setParts) . " WHERE $pkName = :pk_value";
        
        // WHERE için primary key'i ekle
        $data['pk_value'] = $pkValue;

        try {
            $stmt = Database::getConnection()->prepare($sql);
            $result = $stmt->execute($data);
            
            // UPDATE işleminde etkilenen satır sayısını kontrol et
            $affectedRows = $stmt->rowCount();
            
            if ($result && $affectedRows === 0) {
                // Warning: Record not found or no changes
                throw new Exception("Warning: Record with ID=$pkValue not found or no changes made.");
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->handleDbError($e, 'UPDATE');
            return false;
        }
    }

    /**
     * Kaydedilecek verileri toplar.
     */
    private function collectDataForSave(ReflectionClass $reflection, bool $excludePrimaryKey): array
    {
        $data = [];
        
        foreach ($reflection->getProperties() as $property) {
            $colAttr = $property->getAttributes(Column::class);
            
            if (empty($colAttr)) {
                continue;
            }

            $columnConfig = $colAttr[0]->newInstance();
            $propName = $property->getName();

            // Initialized değilse atla
            if (!$property->isInitialized($this)) {
                continue;
            }
            
            $value = $property->getValue($this);

            // UPDATE işleminde primary key'i atla
            if ($excludePrimaryKey && $columnConfig->primaryKey) {
                continue;
            }

            // INSERT işleminde auto increment boşsa atla
            if (!$excludePrimaryKey && $columnConfig->autoIncrement && empty($value)) {
                continue;
            }

            $data[$propName] = $value;
        }

        return $data;
    }

    /**
     * Son eklenen ID'yi modele atar.
     */
    private function assignLastInsertId(ReflectionClass $reflection, ?array $primaryKeyInfo): void
    {
        if (!$primaryKeyInfo) {
            return;
        }

        $lastId = Database::getConnection()->lastInsertId();
        
        if ($lastId) {
            $primaryKeyInfo['property']->setValue($this, (int)$lastId);
        }
    }

    /**
     * Tablo adını döndürür.
     */
    private function getTableName(ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(Table::class);
        
        if (empty($attributes)) {
            throw new Exception("Error: #[Table] attribute not defined in " . get_class($this) . " class.");
        }
        
        return $attributes[0]->newInstance()->name;
    }

    /**
     * Primary key bilgilerini döndürür.
     */
    private function getPrimaryKeyInfo(ReflectionClass $reflection): ?array
    {
        foreach ($reflection->getProperties() as $property) {
            $colAttr = $property->getAttributes(Column::class);
            
            if (!empty($colAttr)) {
                $columnConfig = $colAttr[0]->newInstance();
                
                if ($columnConfig->primaryKey) {
                    return [
                        'property' => $property,
                        'config' => $columnConfig
                    ];
                }
            }
        }
        
        return null;
    }

    /**
     * Veritabanı hatalarını Django benzeri Exception sınıflarına çevirir.
     * 
     * @param PDOException $e Yakalanan PDO hatası
     * @param string $operation Yapılan işlem (INSERT, UPDATE, DELETE vb.)
     */
    protected function handleDbError(PDOException $e, string $operation = 'UNKNOWN'): void
    {
        $errorCode = $e->getCode();
        $errorInfo = $e->errorInfo ?? [];
        $sqlState = $errorInfo[0] ?? $errorCode;
        $message = $e->getMessage();

        // SQLSTATE 23000: Integrity Constraint Violation
        if ($sqlState == '23000' || $errorCode == '23000') {
            // Build detailed error message
            $userMessage = "Integrity constraint violation ($operation): ";
            
            // Duplicate entry error
            if (stripos($message, 'Duplicate entry') !== false) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $message, $matches);
                if (!empty($matches)) {
                    $userMessage .= "Value '{$matches[1]}' already exists (Field: {$matches[2]}).";
                } else {
                    $userMessage .= "This record already exists.";
                }
            }
            // NOT NULL violation
            elseif (stripos($message, "cannot be null") !== false) {
                preg_match("/Column '(.+?)' cannot be null/", $message, $matches);
                if (!empty($matches)) {
                    $userMessage .= "Field '{$matches[1]}' cannot be null.";
                } else {
                    $userMessage .= "A required field was left empty.";
                }
            }
            // Foreign key violation
            elseif (stripos($message, 'foreign key constraint') !== false) {
                $userMessage .= "Related record not found or deletion prevented by foreign key constraint.";
            }
            else {
                $userMessage .= "Database constraint violated.";
            }
            
            throw new IntegrityError($userMessage);
        }

        // SQLSTATE 42S02: Table not found
        if ($sqlState == '42S02') {
            throw new Exception("Database Error ($operation): Table not found. Have you run migrations?");
        }

        // SQLSTATE 42S22: Column not found
        if ($sqlState == '42S22') {
            preg_match("/Unknown column '(.+?)'/", $message, $matches);
            $columnName = $matches[1] ?? 'unknown';
            throw new Exception("Database Error ($operation): Column '$columnName' not found in table. Is the schema up to date?");
        }

        // All other errors
        throw new Exception("Database Error ($operation): " . $message);
    }
}