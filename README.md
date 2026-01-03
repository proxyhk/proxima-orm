# Proxima ORM

Django-inspired Active Record ORM for PHP 8+ with automatic schema synchronization.

## Installation

```bash
composer require proxima/orm
```

## Quick Setup

### Web Setup (No SSH Required)

Access the setup wizard directly from your browser:

```
http://yoursite.com/vendor/proxima/orm/admin/setup.php
```

Follow the 3-step wizard:
1. **Project Directory** - Select your project root
2. **Database** - Enter MySQL credentials
3. **Admin User** - Create admin credentials

⚠️ **Security**: After setup, either delete or restrict access to `vendor/proxima/orm/admin/setup.php`

### CLI Setup (SSH Available)

```bash
php vendor/bin/proxima setup
```

## Usage

### 1. Create Models

Create models in `models/` directory:

```php
<?php

use Proxima\Model;
use Proxima\Attributes\{Table, Column};

#[Table(name: 'users')]
class User extends Model
{
    #[Column(type: 'integer', primaryKey: true, autoIncrement: true)]
    public int $id;
    
    #[Column(type: 'string', length: 100)]
    public string $name;
    
    #[Column(type: 'string', length: 150, unique: true)]
    public string $email;
    
    #[Column(type: 'datetime')]
    public string $created_at;
}
```

### 2. Sync Database

```bash
# Sync all models
php vendor/bin/proxima migrate:sync

# Sync specific model
php vendor/bin/proxima migrate:sync User

# Check status
php vendor/bin/proxima migrate:status

# Fresh migration (⚠️ drops all tables)
php vendor/bin/proxima migrate:fresh
```

### 3. Use Models

```php
// Create
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// Find
$user = User::find(1);
$users = User::all();

// Query
$users = User::where('email', 'LIKE', '%@example.com')->get();

// Update
$user->name = 'Jane Doe';
$user->save();

// Delete
$user->delete();
```

### 4. Admin Panel

Access the admin panel (token shown after setup):

```
http://yoursite.com/admin/index.php?token=YOUR_TOKEN
```

Features:
- View all models and tables
- Sync individual models
- Bulk sync all models
- Fresh migration
- Real-time schema status

## Column Types

| Type | SQL | PHP Type |
|------|-----|----------|
| `string` | VARCHAR | string |
| `integer` | INT | int |
| `decimal` | DECIMAL | float |
| `boolean` | TINYINT(1) | bool |
| `text` | TEXT | string |
| `datetime` | DATETIME | string |

### Column Attributes

```php
#[Column(
    type: 'string',           // Required: column type
    length: 255,              // Optional: for string/decimal (default: 255)
    scale: 2,                 // Optional: decimal places (default: 0)
    primaryKey: false,        // Optional: primary key (default: false)
    autoIncrement: false,     // Optional: auto increment (default: false)
    nullable: false,          // Optional: allow NULL (default: false)
    unique: false             // Optional: unique constraint (default: false)
)]
```

## Configuration

After setup, `proxima.settings.php` is created in your project root:

```php
<?php

return [
    'project_dir' => __DIR__,
    
    'database' => [
        'host' => 'localhost',
        'dbname' => 'your_database',
        'user' => 'your_user',
        'password' => 'your_password',
        'charset' => 'utf8mb4',
    ],
    
    'admin' => [
        'token' => 'generated_token_here',
        'username' => 'admin',
        'password_hash' => '$2y$10$...',
    ],
];
```

## CLI Commands

```bash
# Setup wizard
php vendor/bin/proxima setup

# Migration commands
php vendor/bin/proxima migrate:sync [ModelName]
php vendor/bin/proxima migrate:status
php vendor/bin/proxima migrate:fresh
php vendor/bin/proxima migrate:help
```

## Features

✅ **Django-inspired** - Active Record pattern  
✅ **PHP 8+ Attributes** - Modern attribute-based configuration  
✅ **Auto Schema Sync** - Automatic database synchronization  
✅ **Professional Admin Panel** - Web-based management  
✅ **CLI Tools** - Command-line migration tools  
✅ **Token Security** - Secure admin access  
✅ **Zero Config** - Convention over configuration  
✅ **Shared Hosting Friendly** - Works without SSH  

## Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.2+
- PDO Extension
- Composer

## Project Structure

```
your-project/
├── models/                    # Your model classes
│   └── User.php
├── admin/                     # Generated admin panel
│   ├── index.php
│   └── api.php
├── vendor/
│   └── proxima/orm/
│       ├── admin/setup.php    # Setup wizard
│       └── bin/proxima        # CLI tool
├── proxima.settings.php       # Generated config
└── composer.json
```

## License

MIT License

## Support

- Documentation: [https://github.com/proxima/orm](https://github.com/proxima/orm)
- Issues: [https://github.com/proxima/orm/issues](https://github.com/proxima/orm/issues)
