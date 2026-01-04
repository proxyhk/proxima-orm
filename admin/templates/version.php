<?php
/**
 * Proxima Admin Panel - Version Information
 * 
 * This file contains version info for auto-update functionality
 */

// Current admin panel version
define('PROXIMA_ADMIN_VERSION', '1.0.7');

// List of files to copy during update
define('PROXIMA_ADMIN_FILES', [
    // Main PHP files
    'index.php',
    'model.php',
    'record.php',
    'create.php',
    'edit.php',
    'actions.php',
    'version.php',
    
    // Include files
    'includes/auth.php',
    'includes/functions.php',
    'includes/header.php',
    'includes/footer.php',
    
    // Asset files
    'assets/style.css',
    'assets/app.js',
]);

/**
 * Get vendor (source) admin path
 */
function getVendorAdminPath(): ?string
{
    // Look for proxima-orm in vendor directory
    $vendorPaths = [
        dirname(__DIR__) . '/vendor/proxima/orm/admin/templates',      // Composer install (../vendor)
        dirname(__DIR__, 2) . '/proxima-orm/admin/templates',          // Local development (../../proxima-orm)
    ];
    
    foreach ($vendorPaths as $path) {
        if (is_dir($path) && file_exists($path . '/version.php')) {
            return $path;
        }
    }
    
    return null;
}

/**
 * Get vendor version
 */
function getVendorVersion(): ?string
{
    $vendorPath = getVendorAdminPath();
    
    if (!$vendorPath) {
        return null;
    }
    
    $versionFile = $vendorPath . '/version.php';
    
    if (!file_exists($versionFile)) {
        return null;
    }
    
    // Read version from vendor file
    $content = file_get_contents($versionFile);
    
    if (preg_match("/define\('PROXIMA_ADMIN_VERSION',\s*'([^']+)'\)/", $content, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Check if update is available
 */
function isUpdateAvailable(): bool
{
    $vendorVersion = getVendorVersion();
    
    if (!$vendorVersion) {
        return false;
    }
    
    return version_compare($vendorVersion, PROXIMA_ADMIN_VERSION, '>');
}

/**
 * Get current and vendor versions
 */
function getVersionInfo(): array
{
    return [
        'current' => PROXIMA_ADMIN_VERSION,
        'vendor' => getVendorVersion(),
        'updateAvailable' => isUpdateAvailable(),
    ];
}

/**
 * Perform update - copy all files from vendor to current admin directory
 */
function performUpdate(): array
{
    $vendorPath = getVendorAdminPath();
    
    if (!$vendorPath) {
        return [
            'success' => false,
            'message' => 'Vendor admin path not found',
        ];
    }
    
    $adminPath = __DIR__;  // Fixed: Current directory is already the admin directory
    $errors = [];
    $updated = [];
    
    foreach (PROXIMA_ADMIN_FILES as $file) {
        $sourcePath = $vendorPath . '/' . $file;
        $destPath = $adminPath . '/' . $file;
        
        if (!file_exists($sourcePath)) {
            $errors[] = "Source file not found: $file";
            continue;
        }
        
        // Ensure destination directory exists
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        
        // Copy file
        if (copy($sourcePath, $destPath)) {
            $updated[] = $file;
        } else {
            $errors[] = "Failed to copy: $file";
        }
    }
    
    if (empty($errors)) {
        return [
            'success' => true,
            'message' => 'Update completed successfully! ' . count($updated) . ' files updated.',
            'files' => $updated,
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Update completed with errors',
        'files' => $updated,
        'errors' => $errors,
    ];
}
