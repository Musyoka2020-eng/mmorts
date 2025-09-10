<?php
/**
 * Migration Test Page
 * Tests the new global variables implementation
 */

// Include the system files
require_once __DIR__ . '/system/includes.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Global Variables Migration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        code { background-color: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔧 Global Variables Migration Test</h1>
    
    <div class="test-section success">
        <h2>✅ New Globals System Test</h2>
        <?php
        try {
            $g = globals();
            echo "<p><strong>Globals Instance:</strong> " . get_class($g) . "</p>";
            
            // Test database connection
            $conn = $g->getDatabase();
            echo "<p><strong>Database Connection:</strong> " . ($conn ? "✅ Connected" : "❌ Not Connected") . "</p>";
            
            // Test site configuration
            echo "<p><strong>Site Title:</strong> " . htmlspecialchars($g->getSiteConfig('title')) . "</p>";
            echo "<p><strong>Base URL:</strong> " . htmlspecialchars($g->getBaseUrl()) . "</p>";
            echo "<p><strong>Maintenance Mode:</strong> " . ($g->isMaintenanceMode() ? "Yes" : "No") . "</p>";
            
            // Test user authentication
            echo "<p><strong>User Logged In:</strong> " . ($g->isUserLoggedIn() ? "Yes" : "No") . "</p>";
            if ($g->isUserLoggedIn()) {
                echo "<p><strong>Username:</strong> " . htmlspecialchars($g->getCurrentUser('uname')) . "</p>";
            }
            
            // Test helper functions
            echo "<p><strong>Helper Functions Test:</strong></p>";
            echo "<ul>";
            echo "<li><code>getDatabase()</code>: " . (getDatabase() ? "✅ Working" : "❌ Failed") . "</li>";
            echo "<li><code>url('home')</code>: " . htmlspecialchars(url('home')) . "</li>";
            echo "<li><code>url('login', ['test' => '123'])</code>: " . htmlspecialchars(url('login', ['test' => '123'])) . "</li>";
            echo "</ul>";
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>
    
    <div class="test-section warning">
        <h2>⚠️ Deprecation Warnings</h2>
        <?php
        $warnings = DeprecationWarning::getWarnings();
        if (empty($warnings)) {
            echo "<p>✅ No deprecation warnings found.</p>";
        } else {
            echo "<p>Found " . count($warnings) . " deprecation warning(s):</p>";
            echo "<ul>";
            foreach ($warnings as $warning) {
                echo "<li><strong>{$warning['timestamp']}</strong>: {$warning['message']} in {$warning['file']} on line {$warning['line']}</li>";
            }
            echo "</ul>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>📊 System Information</h2>
        <ul>
            <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
            <li><strong>Development Mode:</strong> <?php echo defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE ? 'Enabled' : 'Disabled'; ?></li>
            <li><strong>Session Status:</strong> <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?></li>
            <li><strong>Includes Path:</strong> <?php echo __DIR__ . '/system/includes.php'; ?></li>
        </ul>
    </div>
    
    <div class="test-section">
        <h2>🧪 Usage Examples</h2>
        <h3>New Way (Recommended)</h3>
        <pre><code>// Get globals instance
$g = globals();

// Database access
$conn = $g->getDatabase();

// User authentication
if ($g->isUserLoggedIn()) {
    $username = $g->getCurrentUser('uname');
}

// Site configuration
$title = $g->getSiteConfig('title');
$baseUrl = $g->getBaseUrl();

// Helper functions
$loginUrl = url('login');
$user = requireLogin(); // Automatically redirects if not logged in</code></pre>
        
        <h3>Old Way (Deprecated)</h3>
        <pre><code>// DON'T USE THESE ANYMORE - THESE ARE DEPRECATED
// global $conn, $base_url, $title;
// $user_logged_in = $_SESSION['logged_in'] ?? false;</code></pre>
    </div>
    
    <div class="test-section">
        <h2>🎯 Next Steps</h2>
        <ol>
            <li>Review any deprecation warnings above</li>
            <li>Update remaining files to use the new globals system</li>
            <li>Test all functionality to ensure everything works</li>
            <li>Remove old global variable declarations</li>
            <li>Set <code>DEVELOPMENT_MODE = false</code> in production</li>
        </ol>
    </div>
</body>
</html>
