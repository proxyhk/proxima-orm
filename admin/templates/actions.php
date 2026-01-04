<?php
/**
 * Proxima Admin Panel - Action Handler
 * 
 * Handles all POST actions and redirects back with flash messages
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();
initDatabase();

// Get action from POST or GET
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if (!$action) {
    setFlash('error', 'No action specified');
    header('Location: index.php');
    exit;
}

try {
    switch ($action) {
        // ===== SYNC SINGLE MODEL =====
        case 'sync':
            $class = $_GET['class'] ?? $_POST['class'] ?? null;
            
            if (!$class || !class_exists($class)) {
                throw new Exception('Invalid model class');
            }
            
            $result = syncModel($class);
            
            if ($result['success']) {
                setFlash('success', 'Model synced successfully!');
            } else {
                setFlash('error', 'Sync failed: ' . ($result['message'] ?? 'Unknown error'));
            }
            
            header('Location: index.php');
            exit;
        
        // ===== SYNC ALL MODELS =====
        case 'sync_all':
            $result = syncAllModels();
            setFlash('success', 'All models synced successfully!');
            header('Location: index.php');
            exit;
        
        // ===== FRESH MIGRATION =====
        case 'fresh':
            freshMigration();
            setFlash('success', 'Fresh migration completed! All tables recreated.');
            header('Location: index.php');
            exit;
        
        // ===== DELETE TABLE =====
        case 'delete_table':
            $table = $_GET['table'] ?? $_POST['table'] ?? null;
            
            if (!$table) {
                throw new Exception('Table name is required');
            }
            
            deleteTable($table);
            setFlash('success', 'Table "' . $table . '" deleted successfully!');
            header('Location: index.php');
            exit;
        
        // ===== CREATE RECORD =====
        case 'create_record':
            // Verify CSRF
            if (!verifyCsrf()) {
                throw new Exception('Invalid security token. Please try again.');
            }
            
            $class = $_POST['class'] ?? null;
            
            if (!$class || !class_exists($class)) {
                throw new Exception('Invalid model class');
            }
            
            // Get schema to check for image fields
            $schema = getModelSchema($class);
            
            // Get form data (exclude action, class, csrf_token)
            $data = $_POST;
            unset($data['action'], $data['class'], $data['csrf_token']);
            
            // Handle image uploads
            foreach ($schema as $fieldName => $config) {
                if (!empty($config['isImage']) && isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                    $reflection = new \ReflectionClass($class);
                    $modelName = $reflection->getShortName();
                    $imagePath = uploadImage($_FILES[$fieldName], $modelName);
                    $data[$fieldName] = $imagePath;
                }
            }
            
            $id = createRecord($class, $data);
            
            setFlash('success', 'Record created successfully!');
            header('Location: model.php?class=' . urlencode($class));
            exit;
        
        // ===== UPDATE RECORD =====
        case 'update_record':
            // Verify CSRF
            if (!verifyCsrf()) {
                throw new Exception('Invalid security token. Please try again.');
            }
            
            $class = $_POST['class'] ?? null;
            $id = $_POST['id'] ?? null;
            
            if (!$class || !class_exists($class)) {
                throw new Exception('Invalid model class');
            }
            
            if (!$id) {
                throw new Exception('Record ID is required');
            }
            
            // Get schema to check for image fields
            $schema = getModelSchema($class);
            $oldRecord = getRecord($class, (int) $id);
            
            // Get form data (exclude action, class, id, csrf_token)
            $data = $_POST;
            unset($data['action'], $data['class'], $data['id'], $data['csrf_token']);
            
            // Handle image uploads and delete old images
            foreach ($schema as $fieldName => $config) {
                if (!empty($config['isImage']) && isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                    // Delete old image if exists
                    if (!empty($oldRecord[$fieldName])) {
                        deleteImage($oldRecord[$fieldName]);
                    }
                    
                    // Upload new image
                    $reflection = new \ReflectionClass($class);
                    $modelName = $reflection->getShortName();
                    $imagePath = uploadImage($_FILES[$fieldName], $modelName);
                    $data[$fieldName] = $imagePath;
                }
            }
            
            updateRecord($class, (int) $id, $data);
            
            setFlash('success', 'Record updated successfully!');
            header('Location: record.php?class=' . urlencode($class) . '&id=' . $id);
            exit;
        
        // ===== DELETE RECORD =====
        case 'delete_record':
            $class = $_GET['class'] ?? $_POST['class'] ?? null;
            $id = $_GET['id'] ?? $_POST['id'] ?? null;
            
            if (!$class || !class_exists($class)) {
                throw new Exception('Invalid model class');
            }
            
            if (!$id) {
                throw new Exception('Record ID is required');
            }
            
            deleteRecord($class, (int) $id);
            
            setFlash('success', 'Record deleted successfully!');
            header('Location: model.php?class=' . urlencode($class));
            exit;
        
        // ===== UPDATE ADMIN PANEL =====
        case 'update_admin':
            require_once __DIR__ . '/version.php';
            
            $result = performUpdate();
            
            if ($result['success']) {
                setFlash('success', $result['message']);
            } else {
                setFlash('error', $result['message'] . (isset($result['errors']) ? ' Errors: ' . implode(', ', $result['errors']) : ''));
            }
            
            header('Location: index.php');
            exit;
        
        // ===== CHANGE LANGUAGE =====
        case 'change_language':
            $lang = $_GET['lang'] ?? $_POST['lang'] ?? null;
            
            if ($lang && in_array($lang, ['tr', 'en'])) {
                setLanguage($lang);
            }
            
            $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
            header('Location: ' . $referer);
            exit;
        
        // ===== UPLOAD EDITOR IMAGE =====
        case 'upload_editor_image':
            // Check if file was uploaded
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'No file uploaded or upload error']);
                exit;
            }
            
            $file = $_FILES['file'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type. Only images allowed.']);
                exit;
            }
            
            // Validate file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['error' => 'File too large. Maximum size is 5MB.']);
                exit;
            }
            
            // Upload image to media/editor/ directory
            $imagePath = uploadImage($file, 'editor');
            
            if ($imagePath) {
                // Return path-only URL (e.g., /d/media/editor/uuid.png)
                // This works better than absolute URL - no domain/protocol issues
                echo json_encode(['location' => $imagePath]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to upload image']);
            }
            exit;
        
        // ===== UNKNOWN ACTION =====
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    setFlash('error', $e->getMessage());
    
    // Redirect back to appropriate page
    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header('Location: ' . $referer);
    exit;
}
