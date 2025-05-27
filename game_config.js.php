<?php
/**
 * JavaScript Configuration Generator
 * Generates JavaScript configuration from centralized game config
 */

// Include the necessary files
require_once __DIR__ . '/system/includes.php';

// Set proper content type for JavaScript
header('Content-Type: application/javascript');

// Generate JavaScript configuration
echo "// Auto-generated game configuration - DO NOT EDIT MANUALLY\n";
echo "// This file is generated from system/game_config.php\n\n";

echo "window.GameConfig = " . GameConfig::getJavaScriptConfig() . ";\n\n";

echo "// Legacy compatibility\n";
echo "window.gameConfig = window.GameConfig;\n"; // Add lowercase version for backward compatibility
echo "window.unitCosts = window.GameConfig.unitCosts;\n";
echo "window.unitStats = window.GameConfig.unitStats;\n";
echo "window.unitDisplay = window.GameConfig.unitDisplay;\n";
echo "window.unitCategories = window.GameConfig.unitCategories;\n";
echo "window.resourceTypes = window.GameConfig.resourceTypes;\n";
?>
