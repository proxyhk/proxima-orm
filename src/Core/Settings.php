<?php

namespace Proxima\Core;

/**
 * Settings - Manages proxima.settings.php configuration file
 * Handles loading, creating and validating project settings
 */
class Settings
{
    /**
     * Load settings from proxima.settings.php
     * 
     * @param string $projectDir Project root directory
     * @return array Configuration array with project_dir and database settings
     * @throws \RuntimeException If settings file doesn't exist or is invalid
     */
    public static function load(string $projectDir): array
    {
        $settingsFile = rtrim($projectDir, '/\\') . DIRECTORY_SEPARATOR . 'proxima.settings.php';
        
        if (!file_exists($settingsFile)) {
            throw new \RuntimeException("Settings file not found: {$settingsFile}");
        }
        
        $settings = require $settingsFile;
        
        if (!is_array($settings)) {
            throw new \RuntimeException("Invalid settings file format. Must return an array.");
        }
        
        // Validate required keys
        if (!isset($settings['project_dir'])) {
            throw new \RuntimeException("Settings must contain 'project_dir' key");
        }
        
        if (!isset($settings['database'])) {
            throw new \RuntimeException("Settings must contain 'database' key");
        }
        
        $db = $settings['database'];
        $required = ['host', 'dbname', 'user', 'password'];
        
        foreach ($required as $key) {
            if (!isset($db[$key])) {
                throw new \RuntimeException("Database settings must contain '{$key}' key");
            }
        }
        
        return $settings;
    }
    
    /**
     * Create a new proxima.settings.php file
     * 
     * @param string $projectDir Project root directory
     * @param array $config Configuration array
     * @return bool True on success
     * @throws \RuntimeException If file cannot be written
     */
    public static function create(string $projectDir, array $config): bool
    {
        $settingsFile = rtrim($projectDir, '/\\') . DIRECTORY_SEPARATOR . 'proxima.settings.php';
        
        // Validate config
        if (!isset($config['project_dir']) || !isset($config['database'])) {
            throw new \RuntimeException("Config must contain 'project_dir' and 'database' keys");
        }
        
        $db = $config['database'];
        $required = ['host', 'dbname', 'user', 'password'];
        
        foreach ($required as $key) {
            if (!isset($db[$key])) {
                throw new \RuntimeException("Database config must contain '{$key}' key");
            }
        }
        
        // Generate file content
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= " * Proxima Settings\n";
        $content .= " * Auto-generated configuration file\n";
        $content .= " */\n\n";
        $content .= "return [\n";
        $content .= "    'project_dir' => " . var_export($config['project_dir'], true) . ",\n\n";
        $content .= "    'database' => [\n";
        $content .= "        'host' => " . var_export($db['host'], true) . ",\n";
        $content .= "        'dbname' => " . var_export($db['dbname'], true) . ",\n";
        $content .= "        'user' => " . var_export($db['user'], true) . ",\n";
        $content .= "        'password' => " . var_export($db['password'], true) . ",\n";
        
        // Optional charset
        if (isset($db['charset'])) {
            $content .= "        'charset' => " . var_export($db['charset'], true) . ",\n";
        } else {
            $content .= "        'charset' => 'utf8mb4',\n";
        }
        
        $content .= "    ],\n\n";
        
        // Admin credentials (optional)
        if (isset($config['admin'])) {
            $admin = $config['admin'];
            $content .= "    'admin' => [\n";
            $content .= "        'token' => " . var_export($admin['token'], true) . ",\n";
            $content .= "        'username' => " . var_export($admin['username'], true) . ",\n";
            $content .= "        'password_hash' => " . var_export($admin['password_hash'], true) . ",\n";
            $content .= "    ],\n\n";
        }
        
        // Security flags
        $content .= "    'security' => [\n";
        $content .= "        'setup_completed' => " . var_export($config['security']['setup_completed'] ?? true, true) . ",\n";
        $content .= "        'development_mode' => " . var_export($config['security']['development_mode'] ?? false, true) . ",\n";
        $content .= "    ],\n";
        
        $content .= "];\n";
        
        $result = file_put_contents($settingsFile, $content);
        
        if ($result === false) {
            throw new \RuntimeException("Failed to write settings file: {$settingsFile}");
        }
        
        return true;
    }
    
    /**
     * Check if proxima.settings.php exists
     * 
     * @param string $projectDir Project root directory
     * @return bool True if settings file exists
     */
    public static function exists(string $projectDir): bool
    {
        $settingsFile = rtrim($projectDir, '/\\') . DIRECTORY_SEPARATOR . 'proxima.settings.php';
        return file_exists($settingsFile);
    }
    
    /**
     * Get the path to settings file
     * 
     * @param string $projectDir Project root directory
     * @return string Full path to proxima.settings.php
     */
    public static function getPath(string $projectDir): string
    {
        return rtrim($projectDir, '/\\') . DIRECTORY_SEPARATOR . 'proxima.settings.php';
    }
    
    /**
     * Generate a random secure token
     * 
     * @param int $length Token length (default 32)
     * @return string Random token
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Verify admin credentials
     * 
     * @param string $projectDir Project root directory
     * @param string $username Username
     * @param string $password Password (plain text)
     * @return bool True if credentials are valid
     */
    public static function verifyAdmin(string $projectDir, string $username, string $password): bool
    {
        try {
            $settings = self::load($projectDir);
            
            if (!isset($settings['admin'])) {
                return false;
            }
            
            $admin = $settings['admin'];
            
            if ($admin['username'] !== $username) {
                return false;
            }
            
            return password_verify($password, $admin['password_hash']);
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
