<?php

namespace Proxima\Core;

use Proxima\Model;
use ReflectionClass;

/**
 * ModelDiscovery - Automatically discovers all Model classes in the project
 * Scans loaded classes and finds those extending Proxima\Model
 */
class ModelDiscovery
{
    /**
     * Get all Model classes currently loaded
     * 
     * @return array<string> Array of fully qualified class names
     */
    public static function getModels(): array
    {
        $models = [];
        
        // Get all declared classes
        $declaredClasses = get_declared_classes();
        
        foreach ($declaredClasses as $className) {
            // Skip if class doesn't exist or can't be reflected
            if (!class_exists($className)) {
                continue;
            }
            
            try {
                $reflection = new ReflectionClass($className);
                
                // Check if it extends Model and is not Model itself
                if ($reflection->isSubclassOf(Model::class) && !$reflection->isAbstract()) {
                    $models[] = $className;
                }
            } catch (\Exception $e) {
                // Skip classes that can't be reflected
                continue;
            }
        }
        
        return $models;
    }
    
    /**
     * Load models from standard models/ directory
     * Convention over configuration - expects models in {projectDir}/models/
     * 
     * @param string $projectDir Project root directory
     * @return array<string> Array of fully qualified class names
     */
    public static function loadFromModelsDirectory(string $projectDir): array
    {
        // Normalize path separators for cross-platform compatibility
        $projectDir = str_replace('\\', '/', $projectDir);
        $modelsDir = rtrim($projectDir, '/') . '/models';
        
        if (!is_dir($modelsDir)) {
            return [];
        }
        
        // Load all PHP files in models/ directory (non-recursive)
        $files = glob($modelsDir . '/*.php');
        
        if ($files === false) {
            return [];
        }
        
        foreach ($files as $file) {
            require_once $file;
        }
        
        // Return all discovered models
        return self::getModels();
    }
    
    /**
     * Get model information with table name
     * 
     * @return array<string, array> Array with class name as key and info as value
     */
    public static function getModelsWithInfo(): array
    {
        $models = self::getModels();
        $info = [];
        
        foreach ($models as $modelClass) {
            $reflection = new ReflectionClass($modelClass);
            $attributes = $reflection->getAttributes();
            
            $tableName = null;
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === 'Proxima\\Attributes\\Table') {
                    $args = $attribute->getArguments();
                    $tableName = $args['name'] ?? $args[0] ?? null;
                    break;
                }
            }
            
            $info[$modelClass] = [
                'class' => $modelClass,
                'shortName' => $reflection->getShortName(),
                'tableName' => $tableName,
                'file' => $reflection->getFileName()
            ];
        }
        
        return $info;
    }
}
