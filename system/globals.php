<?php
/**
 * Global Variables Manager
 * Centralized management for all global variables in the MechaEmpire project
 * 
 * This class provides a clean interface to access global variables throughout
 * the application while maintaining type safety and preventing direct global access.
 */

class Globals {
    private static $instance = null;
    private $config = [];
    private $user = [];
    private $database = null;
    
    private function __construct() {
        $this->initializeConfig();
        $this->initializeDatabase();
        $this->initializeUser();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize configuration from existing global variables
     */
    private function initializeConfig() {
        // Load from existing global variables if they exist
        global $title, $separator, $description, $logo, $base_url, $maintainance;
        
        $this->config = [
            'site' => [
                'title' => $title ?? 'MMORTS',
                'separator' => $separator ?? ' _ ',
                'description' => $description ?? 'My first MMORTS description',
                'logo' => $logo ?? '',
                'base_url' => $base_url ?? '',
                'maintenance' => $maintainance ?? false
            ]
        ];
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase() {
        global $conn;
        $this->database = $conn ?? null;
    }
    
    /**
     * Initialize user session data
     */
    private function initializeUser() {
        $this->user = [
            'logged_in' => $_SESSION['logged_in'] ?? false,
            'data' => $_SESSION['user'] ?? null
        ];
    }
    
    /**
     * Get database connection
     */
    public function getDatabase() {
        return $this->database;
    }
    
    /**
     * Get site configuration
     */
    public function getSiteConfig($key = null) {
        if ($key === null) {
            return $this->config['site'];
        }
        return $this->config['site'][$key] ?? null;
    }
    
    /**
     * Get base URL
     */
    public function getBaseUrl() {
        return $this->config['site']['base_url'];
    }
    
    /**
     * Check if site is in maintenance mode
     */
    public function isMaintenanceMode() {
        return $this->config['site']['maintenance'];
    }
    
    /**
     * Get user login status
     */
    public function isUserLoggedIn() {
        return $this->user['logged_in'];
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser($key = null) {
        if (!$this->isUserLoggedIn()) {
            return null;
        }
        
        if ($key === null) {
            return $this->user['data'];
        }
        
        return $this->user['data'][$key] ?? null;
    }
    
    /**
     * Get site title with optional page title
     */
    public function getPageTitle($pageTitle = null) {
        $siteTitle = $this->config['site']['title'];
        if ($pageTitle) {
            return $pageTitle . $this->config['site']['separator'] . $siteTitle;
        }
        return $siteTitle;
    }
    
    /**
     * Update user session (call when user logs in/out)
     */
    public function updateUserSession() {
        $this->initializeUser();
    }
    
    /**
     * Get all config for debugging (remove in production)
     */
    public function debug() {
        return [
            'config' => $this->config,
            'user' => $this->user,
            'database_connected' => $this->database !== null
        ];
    }
}

/**
 * Helper function to get globals instance
 * This provides a clean way to access the globals throughout the app
 */
function globals() {
    return Globals::getInstance();
}
