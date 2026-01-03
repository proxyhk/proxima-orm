<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.0+-8892BF?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.0+">
  <img src="https://img.shields.io/badge/License-MIT-blue?style=for-the-badge" alt="License">
  <img src="https://img.shields.io/badge/ORM-Active_Record-6366f1?style=for-the-badge" alt="Active Record">
  <img src="https://img.shields.io/badge/Type-Safe-10b981?style=for-the-badge" alt="Type Safe">
</p>

<h1 align="center">⚡ Proxima ORM</h1>

<p align="center">
  <b>Modern, Django-inspired Active Record ORM for PHP 8+</b><br>
  <sub>Automatic schema synchronization • Professional admin panel • Zero configuration migrations</sub>
</p>

<p align="center">
  <a href="#-features">Features</a> •
  <a href="#-installation">Installation</a> •
  <a href="#-quick-start">Quick Start</a> •
  <a href="#-documentation">Documentation</a> •
  <a href="#-admin-panel">Admin Panel</a> •
  <a href="#-cli-commands">CLI</a>
</p>

---

## ✨ Features

<table>
<tr>
<td width="50%">

### 🎯 Core Features
- **PHP 8 Attributes** - Define models with modern syntax
- **Active Record Pattern** - Intuitive, object-oriented database operations
- **Auto Schema Sync** - Automatic table creation and synchronization
- **Type Safety** - Full PHP type hints support
- **Zero Configuration** - Convention over configuration

</td>
<td width="50%">

### 🛠️ Advanced Features
- **Professional Admin Panel** - Django-like admin interface
- **CLI Migration Tool** - Powerful command-line migrations
- **Query Builder** - Fluent, chainable query interface
- **Exception Handling** - Django-style error management
- **Model Discovery** - Auto-detection of model classes

</td>
</tr>
</table>

---

## 📦 Installation

```bash
composer require proxima/orm
```

### Requirements
- PHP 8.0 or higher
- PDO extension
- MySQL database

---

## 🚀 Quick Start

### 1️⃣ Connect to Database

```php
<?php
require 'vendor/autoload.php';

use Proxima\Core\Database;

Database::connect([
    'host'   => 'localhost',
    'dbname' => 'myapp',
    'user'   => 'root',
    'password' => ''
]);
```

### 2️⃣ Define Your Model

```php
<?php

use Proxima\Model;
use Proxima\Attributes\Table;
use Proxima\Attributes\Column;

#[Table(name: 'users')]
class User extends Model
{
    #[Column(type: 'integer', primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(type: 'string', length: 100)]
    public string $username;

    #[Column(type: 'string', length: 150, unique: true)]
    public string $email;

    #[Column(type: 'boolean')]
    public bool $isActive;

    #[Column(type: 'text', nullable: true)]
    public ?string $bio;

    #[Column(type: 'decimal', length: 10, scale: 2)]
    public float $balance;
}
```

### 3️⃣ Create Table & Start Using

```php
use Proxima\Core\Schema;

// Create table from model (auto-generates SQL)
Schema::create(User::class);

// Create new user
$user = new User();
$user->username = "john_doe";
$user->email = "john@example.com";
$user->isActive = true;
$user->balance = 100.50;
$user->save();

echo "Created user with ID: " . $user->id;
```

---

## 📖 Documentation

### 🔍 Reading Records

```php
// Find by ID
$user = User::find(1);

// Find or throw exception
$user = User::findOrFail(1);

// Get all records
$users = User::all();

// Get first result
$user = User::where('email', 'john@example.com')->first();
```

### ✏️ Updating Records

```php
$user = User::find(1);
$user->username = "john_updated";
$user->save();  // Automatically does UPDATE
```

### 🗑️ Deleting Records

```php
$user = User::find(1);
$user->delete();
```

### 🔎 Query Builder

#### Basic Where Clauses

```php
// Simple equality
$users = User::where('isActive', true)->get();

// With operators
$users = User::where('id', '>', 10)->get();
$users = User::where('balance', '>=', 100)->get();

// Multiple conditions (AND)
$users = User::where('isActive', true)
    ->where('balance', '>', 50)
    ->get();
```

#### Helper Methods

```php
// Comparison helpers
$users = User::whereGreaterThan('id', 10)->get();
$users = User::whereLessThan('balance', 100)->get();
$users = User::whereGreaterThanOrEqual('score', 80)->get();
$users = User::whereLessThanOrEqual('price', 50)->get();
$users = User::whereNotEqual('role', 'admin')->get();

// IN / NOT IN
$users = User::whereIn('id', [1, 3, 5, 7])->get();
$users = User::whereNotIn('status', ['banned', 'suspended'])->get();

// LIKE patterns
$users = User::whereLike('username', '%john%')->get();
$users = User::whereLike('email', '%@gmail.com')->get();

// BETWEEN
$users = User::whereBetween('balance', 100, 1000)->get();

// NULL checks
$users = User::whereNull('deleted_at')->get();
$users = User::whereNotNull('email_verified_at')->get();
```

#### Ordering & Limiting

```php
$users = User::query()
    ->orderBy('username', 'ASC')
    ->limit(10)
    ->offset(20)  // Pagination
    ->get();
```

#### Aggregate Functions

```php
$total = User::query()->count();
$activeUsers = User::where('isActive', true)->count();
```

---

## 🎨 Column Types

| Type | SQL Type | PHP Type | Description |
|------|----------|----------|-------------|
| `string` | `VARCHAR(length)` | `string` | Variable-length string |
| `integer` | `INT` | `int` | Integer values |
| `boolean` | `TINYINT(1)` | `bool` | True/False values |
| `text` | `TEXT` | `string` | Long text content |
| `datetime` | `DATETIME` | `string` | Date and time |
| `decimal` | `DECIMAL(length, scale)` | `float` | Precise decimal values |

### Column Options

| Option | Type | Description |
|--------|------|-------------|
| `type` | `string` | Column type (required) |
| `length` | `int` | Max length (default: 255) |
| `scale` | `int` | Decimal places (default: 0) |
| `primaryKey` | `bool` | Mark as primary key |
| `autoIncrement` | `bool` | Auto-increment for integers |
| `nullable` | `bool` | Allow NULL values |
| `unique` | `bool` | Unique constraint |

---

## 🔄 Schema Synchronization

Proxima automatically detects changes in your models and syncs with the database:

```php
use Proxima\Core\Schema;

// Sync a single model (creates or alters table)
$result = Schema::sync(User::class);

// Check difference between model and database
$diff = Schema::diff(User::class);

// Sync all discovered models
$results = Schema::syncAll();
```

---

## ⚠️ Exception Handling

Proxima provides Django-style exceptions for better error management:

### IntegrityError

Thrown when database constraints are violated (unique, not null, foreign key):

```php
use Proxima\Exceptions\IntegrityError;

try {
    $user = new User();
    $user->email = "existing@example.com";  // Duplicate!
    $user->save();
} catch (IntegrityError $e) {
    echo $e->getMessage();
    // "Integrity constraint violation (INSERT): Value 'existing@example.com' already exists (Field: email)."
}
```

### DoesNotExist

Thrown when a record is not found:

```php
use Proxima\Exceptions\DoesNotExist;

try {
    $user = User::findOrFail(999);
} catch (DoesNotExist $e) {
    echo $e->getMessage();
    // "Record with ID=999 not found."
}
```

---

## 🖥️ Admin Panel

Proxima includes a professional Django-like admin panel with setup wizard.

### Setup

1. After installing via Composer, visit:
   ```
   http://yoursite.com/vendor/proxima/orm/admin/setup.php
   ```

2. Follow the 3-step wizard:
   - **Step 1:** Select your project directory
   - **Step 2:** Configure database connection
   - **Step 3:** Create admin credentials

3. Access your admin panel:
   ```
   http://yoursite.com/yourproject/admin/index.php?token=YOUR_TOKEN
   ```

### Features

- 📊 **Model Management** - View and manage all your models
- 🔄 **Migration Interface** - Sync schemas with one click
- 🔒 **Secure Access** - Token-based authentication
- 🎨 **Modern UI** - Clean, professional dark theme

---

## 🔧 CLI Commands

Proxima provides powerful CLI tools for migrations:

```bash
# Show help
php proxima migrate:help

# Sync all models
php proxima migrate:sync

# Sync specific model
php proxima migrate:sync User

# Show migration status
php proxima migrate:status

# Fresh migration (DROP and recreate all tables)
php proxima migrate:fresh --force
```

### proxima.settings.php

The CLI requires a settings file in your project root:

```php
<?php
// proxima.settings.php

return [
    'project_dir' => '/path/to/your/project',
    
    'database' => [
        'host' => 'localhost',
        'dbname' => 'your_database',
        'user' => 'your_username',
        'password' => 'your_password',
        'charset' => 'utf8mb4',
    ],
    
    'admin' => [
        'token' => 'your_secure_token',
        'username' => 'admin',
        'password_hash' => 'hashed_password',
    ],
    
    'security' => [
        'setup_completed' => true,
        'development_mode' => false,
    ],
];
```

---

## 📁 Project Structure

```
proxima-orm/
├── bin/
│   └── proxima              # CLI entry point
├── src/
│   ├── Attributes/
│   │   ├── Column.php       # Column attribute
│   │   └── Table.php        # Table attribute
│   ├── Console/
│   │   └── MigrateCommand.php  # CLI migration commands
│   ├── Core/
│   │   ├── Database.php     # Database connection
│   │   ├── ModelDiscovery.php  # Auto model detection
│   │   ├── QueryBuilder.php # Fluent query builder
│   │   ├── Schema.php       # Schema management
│   │   └── Settings.php     # Settings management
│   ├── Exceptions/
│   │   ├── DoesNotExist.php
│   │   ├── IntegrityError.php
│   │   └── ProximaException.php
│   └── Model.php            # Base model class
├── admin/
│   ├── setup.php            # Setup wizard
│   └── templates/           # Admin panel templates
├── composer.json
├── LICENSE
└── README.md
```

---

## 🤝 Complete Example

```php
<?php
require 'vendor/autoload.php';

use Proxima\Core\Database;
use Proxima\Core\Schema;
use Proxima\Model;
use Proxima\Attributes\{Table, Column};
use Proxima\Exceptions\{IntegrityError, DoesNotExist};

// 1. Define Model
#[Table(name: 'products')]
class Product extends Model
{
    #[Column(type: 'integer', primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(type: 'string', length: 200)]
    public string $name;

    #[Column(type: 'decimal', length: 10, scale: 2)]
    public float $price;

    #[Column(type: 'integer')]
    public int $stock;

    #[Column(type: 'boolean')]
    public bool $isActive;
}

// 2. Connect
Database::connect([
    'host' => 'localhost',
    'dbname' => 'shop',
    'user' => 'root',
    'password' => ''
]);

// 3. Create table
Schema::sync(Product::class);

// 4. CRUD Operations
try {
    // Create
    $product = new Product();
    $product->name = "Laptop Pro";
    $product->price = 1299.99;
    $product->stock = 50;
    $product->isActive = true;
    $product->save();
    
    echo "Created product ID: {$product->id}\n";
    
    // Read
    $laptop = Product::find($product->id);
    
    // Update
    $laptop->price = 1199.99;
    $laptop->save();
    
    // Query
    $affordable = Product::whereLessThan('price', 500)
        ->where('isActive', true)
        ->orderBy('price', 'ASC')
        ->limit(10)
        ->get();
    
    // Count
    $total = Product::query()->count();
    echo "Total products: $total\n";
    
    // Delete
    $product->delete();
    
} catch (IntegrityError $e) {
    echo "Constraint error: " . $e->getMessage();
} catch (DoesNotExist $e) {
    echo "Not found: " . $e->getMessage();
}
```

---

## 📄 License

This project is licensed under the **MIT License** - see the [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Author

**Hakan Enes Karatopak**
- 📧 Email: hello@eneskaratopak.com
- 🌐 Website: [getproxima.org](https://getproxima.org)

---

<p align="center">
  Made with ❤️ for the PHP community
</p>

<p align="center">
  <a href="https://getproxima.org">
    <img src="https://img.shields.io/badge/Documentation-getproxima.org-6366f1?style=for-the-badge" alt="Documentation">
  </a>
</p>
