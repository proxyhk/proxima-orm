<?php

namespace Proxima\Console;

use Proxima\Core\Schema;
use Proxima\Core\ModelDiscovery;
use Proxima\Core\Database;

/**
 * MigrateCommand - Handles CLI migration commands
 */
class MigrateCommand
{
    private const COLORS = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'reset' => "\033[0m"
    ];

    /**
     * Display help information
     */
    public static function help(): void
    {
        self::output("
╔═══════════════════════════════════════════════════════════╗
║              Proxima ORM - Migration Tool                 ║
╚═══════════════════════════════════════════════════════════╝

Usage: php proxima migrate:<command> [options]

Commands:
  sync [Model]    Sync database with model(s)
                  - Without argument: syncs all models
                  - With argument: syncs specific model
                  
  status          Show migration status for all models
  
  fresh           Drop all tables and recreate (⚠️  DANGEROUS!)
  
  help            Show this help message

Examples:
  php proxima migrate:sync           # Sync all models
  php proxima migrate:sync User      # Sync only User model
  php proxima migrate:status         # Show status
  php proxima migrate:fresh          # Fresh migration

", 'cyan');
    }

    /**
     * Sync command - Create or alter tables
     */
    public static function sync(?string $modelName = null): void
    {
        self::output("\n🔄 Proxima Migration - Sync\n", 'cyan');
        self::output("═══════════════════════════════════\n\n");

        // Discover models
        $models = ModelDiscovery::getModelsWithInfo();

        if (empty($models)) {
            self::output("⚠️  No models found!\n", 'yellow');
            return;
        }

        // If specific model requested
        if ($modelName) {
            $found = false;
            foreach ($models as $modelClass => $info) {
                if ($info['shortName'] === $modelName || $modelClass === $modelName) {
                    $found = true;
                    self::syncModel($modelClass, $info);
                    break;
                }
            }

            if (!$found) {
                self::output("❌ Model '$modelName' not found!\n", 'red');
                self::output("\nAvailable models:\n", 'yellow');
                foreach ($models as $info) {
                    self::output("  - " . $info['shortName'] . "\n");
                }
            }
        } else {
            // Sync all models
            foreach ($models as $modelClass => $info) {
                self::syncModel($modelClass, $info);
            }
        }

        self::output("\n✅ Migration completed!\n\n", 'green');
    }

    /**
     * Sync a single model
     */
    private static function syncModel(string $modelClass, array $info): void
    {
        self::output("📦 {$info['shortName']} → {$info['tableName']}\n", 'blue');

        try {
            $result = Schema::sync($modelClass);

            if ($result['success']) {
                self::output("   ✓ " . $result['message'] . "\n", 'green');

                if (!empty($result['sql'])) {
                    foreach ($result['sql'] as $sql) {
                        self::output("     SQL: " . self::truncate($sql, 60) . "\n", 'reset');
                    }
                }
            } else {
                self::output("   ✗ " . $result['message'] . "\n", 'red');
            }
        } catch (\Exception $e) {
            self::output("   ✗ Error: " . $e->getMessage() . "\n", 'red');
        }

        self::output("\n");
    }

    /**
     * Status command - Show current state
     */
    public static function status(): void
    {
        self::output("\n📊 Proxima Migration - Status\n", 'cyan');
        self::output("═══════════════════════════════════\n\n");

        $models = ModelDiscovery::getModelsWithInfo();

        if (empty($models)) {
            self::output("⚠️  No models found!\n", 'yellow');
            return;
        }

        foreach ($models as $modelClass => $info) {
            self::output("📦 {$info['shortName']} → {$info['tableName']}\n", 'blue');

            try {
                $diff = Schema::diff($modelClass);

                if (!$diff['tableExists']) {
                    self::output("   ⚠️  Table not created\n", 'yellow');
                } else {
                    $addCount = count($diff['add']);
                    $modifyCount = count($diff['modify']);
                    $dropCount = count($diff['drop']);

                    if ($addCount + $modifyCount + $dropCount === 0) {
                        self::output("   ✓ Up to date\n", 'green');
                    } else {
                        if ($addCount > 0) {
                            self::output("   + $addCount column(s) to add\n", 'yellow');
                        }
                        if ($modifyCount > 0) {
                            self::output("   ~ $modifyCount column(s) to modify\n", 'yellow');
                        }
                        if ($dropCount > 0) {
                            self::output("   - $dropCount column(s) to drop (manual)\n", 'magenta');
                        }
                    }
                }
            } catch (\Exception $e) {
                self::output("   ✗ Error: " . $e->getMessage() . "\n", 'red');
            }

            self::output("\n");
        }
    }

    /**
     * Fresh command - Drop and recreate all tables
     */
    public static function fresh(): void
    {
        self::output("\n⚠️  Proxima Migration - Fresh\n", 'yellow');
        self::output("═══════════════════════════════════\n\n");
        self::output("🚨 WARNING: This will DROP all tables and recreate them!\n", 'red');
        self::output("   All data will be LOST!\n\n", 'red');

        // In CLI, we can't easily prompt, so just inform
        self::output("To confirm, run with --force flag:\n", 'yellow');
        self::output("  php proxima migrate:fresh --force\n\n");

        // Check if --force is in arguments
        global $argv;
        if (!in_array('--force', $argv ?? [])) {
            self::output("❌ Aborted (no --force flag)\n\n", 'red');
            return;
        }

        self::output("🔄 Dropping and recreating tables...\n\n", 'cyan');

        $db = Database::getConnection();
        $models = ModelDiscovery::getModelsWithInfo();

        foreach ($models as $modelClass => $info) {
            try {
                // Drop table
                $db->exec("DROP TABLE IF EXISTS {$info['tableName']}");
                self::output("   ✓ Dropped: {$info['tableName']}\n", 'green');

                // Recreate
                Schema::create($modelClass);
                self::output("   ✓ Created: {$info['tableName']}\n", 'green');
            } catch (\Exception $e) {
                self::output("   ✗ Error with {$info['tableName']}: " . $e->getMessage() . "\n", 'red');
            }
        }

        self::output("\n✅ Fresh migration completed!\n\n", 'green');
    }

    /**
     * Output colored text
     */
    private static function output(string $text, string $color = 'reset'): void
    {
        $colorCode = self::COLORS[$color] ?? self::COLORS['reset'];
        echo $colorCode . $text . self::COLORS['reset'];
    }

    /**
     * Truncate long text
     */
    private static function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }
}
