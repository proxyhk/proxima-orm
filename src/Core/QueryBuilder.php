<?php

namespace Proxima\Core;

use PDO;
use ReflectionClass;
use Proxima\Attributes\Table;
use Proxima\Attributes\Column;
use Proxima\Exceptions\DoesNotExist;

class QueryBuilder
{
    private string $modelClass;
    private string $tableName;
    private array $wheres = [];
    private array $bindings = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private array $orderBys = [];
    private array $selectColumns = ['*'];

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->tableName = $this->getTableName();
    }

    /**
     * WHERE clause ekler.
     * 
     * @param string $column Kolon adı
     * @param mixed $operator Operator veya değer (2 parametre kullanımında)
     * @param mixed|null $value Değer (3 parametre kullanımında)
     */
    public function where(string $column, $operator, $value = null): self
    {
        // 2 parametre: where('id', 5) -> where('id', '=', 5)
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $placeholder = 'param_' . count($this->bindings);
        
        $this->wheres[] = "$column $operator :$placeholder";
        $this->bindings[$placeholder] = $value;

        return $this;
    }

    /**
     * WHERE column > value
     */
    public function whereGreaterThan(string $column, $value): self
    {
        return $this->where($column, '>', $value);
    }

    /**
     * WHERE column < value
     */
    public function whereLessThan(string $column, $value): self
    {
        return $this->where($column, '<', $value);
    }

    /**
     * WHERE column >= value
     */
    public function whereGreaterThanOrEqual(string $column, $value): self
    {
        return $this->where($column, '>=', $value);
    }

    /**
     * WHERE column <= value
     */
    public function whereLessThanOrEqual(string $column, $value): self
    {
        return $this->where($column, '<=', $value);
    }

    /**
     * WHERE column != value
     */
    public function whereNotEqual(string $column, $value): self
    {
        return $this->where($column, '!=', $value);
    }

    /**
     * WHERE column IN (values)
     */
    public function whereIn(string $column, array $values): self
    {
        $placeholder = 'param_' . count($this->bindings);
        $placeholders = [];
        
        foreach ($values as $index => $value) {
            $key = $placeholder . '_' . $index;
            $placeholders[] = ':' . $key;
            $this->bindings[$key] = $value;
        }
        
        $this->wheres[] = "$column IN (" . implode(', ', $placeholders) . ")";
        
        return $this;
    }

    /**
     * WHERE column NOT IN (values)
     */
    public function whereNotIn(string $column, array $values): self
    {
        $placeholder = 'param_' . count($this->bindings);
        $placeholders = [];
        
        foreach ($values as $index => $value) {
            $key = $placeholder . '_' . $index;
            $placeholders[] = ':' . $key;
            $this->bindings[$key] = $value;
        }
        
        $this->wheres[] = "$column NOT IN (" . implode(', ', $placeholders) . ")";
        
        return $this;
    }

    /**
     * WHERE column IS NULL
     */
    public function whereNull(string $column): self
    {
        $this->wheres[] = "$column IS NULL";
        return $this;
    }

    /**
     * WHERE column IS NOT NULL
     */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = "$column IS NOT NULL";
        return $this;
    }

    /**
     * WHERE column LIKE pattern
     */
    public function whereLike(string $column, string $pattern): self
    {
        return $this->where($column, 'LIKE', $pattern);
    }

    /**
     * WHERE column BETWEEN min AND max
     */
    public function whereBetween(string $column, $min, $max): self
    {
        $placeholderMin = 'param_' . count($this->bindings);
        $placeholderMax = 'param_' . (count($this->bindings) + 1);
        
        $this->wheres[] = "$column BETWEEN :$placeholderMin AND :$placeholderMax";
        $this->bindings[$placeholderMin] = $min;
        $this->bindings[$placeholderMax] = $max;
        
        return $this;
    }

    /**
     * ORDER BY ekler.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        $this->orderBys[] = "$column $direction";

        return $this;
    }

    /**
     * LIMIT ekler.
     */
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }

    /**
     * OFFSET ekler.
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }

    /**
     * SELECT kolonları belirler.
     */
    public function select(array $columns): self
    {
        $this->selectColumns = $columns;
        return $this;
    }

    /**
     * Tüm sonuçları getirir (Model objeleri array'i).
     * 
     * @return array Model nesneleri dizisi
     */
    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($this->bindings);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Her satırı Model objesine dönüştür
        return array_map(function($row) {
            return $this->hydrate($row);
        }, $results);
    }

    /**
     * İlk sonucu getirir (tek Model objesi).
     * 
     * @return object|null Model nesnesi veya null
     */
    public function first(): ?object
    {
        $this->limit(1);
        $results = $this->get();
        
        return $results[0] ?? null;
    }

    /**
     * İlk sonucu getirir, bulamazsa exception fırlatır.
     * 
     * @throws DoesNotExist
     */
    public function firstOrFail(): object
    {
        $result = $this->first();
        
        if ($result === null) {
            throw new DoesNotExist("Record not found in {$this->tableName} table.");
        }
        
        return $result;
    }

    /**
     * Kayıt sayısını döndürür.
     */
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->tableName}";
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(" AND ", $this->wheres);
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($this->bindings);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['count'] ?? 0);
    }

    /**
     * SELECT sorgusunu oluşturur.
     */
    private function buildSelectQuery(): string
    {
        $columns = implode(', ', $this->selectColumns);
        $sql = "SELECT $columns FROM {$this->tableName}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(" AND ", $this->wheres);
        }

        if (!empty($this->orderBys)) {
            $sql .= " ORDER BY " . implode(", ", $this->orderBys);
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    /**
     * Veritabanı satırını Model objesine dönüştürür (hydration).
     */
    private function hydrate(array $data): object
    {
        $model = new $this->modelClass();
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getProperties() as $property) {
            $colAttr = $property->getAttributes(Column::class);
            
            if (!empty($colAttr)) {
                $propName = $property->getName();
                
                if (array_key_exists($propName, $data)) {
                    $value = $data[$propName];
                    
                    // Tip dönüşümü
                    $value = $this->castValue($value, $property->getType());
                    
                    $property->setValue($model, $value);
                }
            }
        }

        return $model;
    }

    /**
     * Değeri doğru tipe çevirir.
     */
    private function castValue($value, ?\ReflectionType $type)
    {
        if ($value === null || $type === null) {
            return $value;
        }

        $typeName = $type->getName();

        return match($typeName) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => (bool)$value,
            'string' => (string)$value,
            default => $value
        };
    }

    /**
     * Model sınıfından tablo adını alır.
     */
    private function getTableName(): string
    {
        $reflection = new ReflectionClass($this->modelClass);
        $attributes = $reflection->getAttributes(Table::class);
        
        if (empty($attributes)) {
            throw new \Exception("Error: #[Table] attribute not defined in {$this->modelClass} class.");
        }
        
        return $attributes[0]->newInstance()->name;
    }
}
