<?php
/**
 * Main Functions - Updated to use Centralized Globals
 * Migrated from old global variable system to new Globals manager
 * 
 * @deprecated The old global variable usage is deprecated. Use globals() instead.
 */

// Define development mode (set to false in production)
if (!defined('DEVELOPMENT_MODE')) {
    define('DEVELOPMENT_MODE', true);
}

function getPage()
{
    // Use new globals system instead of scattered global declarations
    $g = globals();
    
    // Add deprecation warning for development
    if (DEVELOPMENT_MODE) {
        error_log('DEPRECATED: getPage() function should be updated to use dependency injection. Use getPageWithDependencies() instead.');
    }
    
    // Check maintenance mode before serving any page
    if ($g->isMaintenanceMode() && !in_array($_GET['page'] ?? '', ['login', 'register'])) {
        include_once 'frontend/pages/maintenance.php';
        return;
    }
    if (isset($_GET['page'])) {
        $page = $_GET['page'];
        switch ($page) {
            case 'home':
                include_once 'frontend/pages/home.php';
                break;
            case 'contact':
                include_once 'frontend/pages/contact.php';
                break;
            case 'login':
                include_once 'frontend/pages/login.php';
                break;
            case 'register':
                include_once 'frontend/pages/register.php';
                break;
            case 'community':
                include_once 'frontend/pages/community.php';
                break;
            case 'support':
                include_once 'frontend/pages/support.php';
                break;
            case 'about':
                include_once 'frontend/pages/about.php';
                break;
            case 'logout':
                include_once 'frontend/pages/logout.php';
                break;
            case 'world_map':
                include_once 'frontend/pages/world_map.php';
                break;
            case 'battle':
                include_once 'frontend/pages/battle.php';
                break;
            case 'training':
                include_once 'frontend/pages/training.php';
                break;
            case 'battle_report':
                include_once 'frontend/pages/battle_report.php';
                break;
            case 'gather':
                include_once 'frontend/pages/gather.php';
                break;
            case 'initialize_world':
                include_once 'frontend/pages/initialize_world.php';
                break;            case 'process_ai_turn':
                include_once 'frontend/pages/process_ai_turn.php';
                break;
            case 'ai_opponents':
                include_once 'frontend/pages/ai_opponents.php';
                break;
            case 'battle_history':
                include_once 'frontend/pages/battle_history.php';
                break;
            default:
                include_once 'frontend/pages/404.php';
                break;
        }

    } else {
        require 'frontend/pages/home.php';
    }
}

/**
 * NEW HELPER FUNCTIONS - Clean Global Variable Access
 */

/**
 * Helper function to get database connection cleanly
 */
function getDatabase() {
    return globals()->getDatabase();
}

/**
 * Helper function to check user authentication
 */
function requireLogin($redirectTo = 'login') {
    $g = globals();
    if (!$g->isUserLoggedIn()) {
        header("Location: " . $g->getBaseUrl() . "?page=" . $redirectTo);
        exit();
    }
    return $g->getCurrentUser();
}

/**
 * Helper function to generate URLs
 */
function url($page = '', $params = []) {
    $g = globals();
    $baseUrl = $g->getBaseUrl();
    
    if ($page) {
        $url = $baseUrl . "?page=" . $page;
        if (!empty($params)) {
            $url .= "&" . http_build_query($params);
        }
        return $url;
    }
    
    return $baseUrl;
}

/**
 * Helper function to get user-friendly error messages
 */
function getErrorMessage($type = 'general') {
    $messages = [
        'database' => 'Database connection error. Please try again later.',
        'login' => 'Please log in to access this feature.',
        'permission' => 'You do not have permission to access this resource.',
        'general' => 'An error occurred. Please try again.',
        'maintenance' => 'The game is currently under maintenance. Please check back later.'
    ];
    
    return $messages[$type] ?? $messages['general'];
}

/**
 * Check if user has permission for a specific action
 */
function hasPermission($permission) {
    $g = globals();
    if (!$g->isUserLoggedIn()) {
        return false;
    }
    
    // Add your permission logic here
    $user = $g->getCurrentUser();
    return isset($user['permissions']) && in_array($permission, $user['permissions']);
}
