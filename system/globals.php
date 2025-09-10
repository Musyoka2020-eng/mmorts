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
     * Initialize configuration from database or defaults
     */
    private function initializeConfig() {
        // Read configuration directly from the database
        $config = $this->loadConfigFromDatabase();
        
        $this->config = [
            'site' => [
                'title' => $config['name'] ?? 'MMORTS',
                'separator' => $config['separator'] ?? ' _ ',
                'description' => $config['description'] ?? 'My first MMORTS description',
                'logo' => $config['logo'] ?? '',
                'base_url' => $this->generateBaseUrl(),
                'maintenance' => (bool)($config['maintainance'] ?? false)
            ]
        ];
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase() {
        // Load database connection from config instead of global
        $this->database = $this->createDatabaseConnection();
    }

    /**
     * Load configuration from database
     */
    private function loadConfigFromDatabase() {
        // Create a temporary database connection to load config
        $tempConn = $this->createDatabaseConnection();
        
        if (!$tempConn) {
            return [];
        }
        
        $query = "SELECT * FROM configuration LIMIT 1";
        $result = $tempConn->query($query);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return [];
    }

    /**
     * Create database connection
     */
    private function createDatabaseConnection() {
        // Read database config from environment or config file
        $config = $this->getDatabaseConfig();
        
        if (!$config) {
            return null;
        }
        
        $conn = new mysqli(
            $config['server'],
            $config['username'], 
            $config['password'],
            $config['database']
        );
        
        if ($conn->connect_error) {
            return null;
        }
        
        return $conn;
    }

    /**
     * Get database configuration
     */
    private function getDatabaseConfig() {
        // Try to load from env.ini file
        $envFile = __DIR__ . '/env.ini';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            return [
                'server' => $env['DB_SERVER'] ?? 'localhost',
                'username' => $env['DB_USERNAME'] ?? 'root',
                'password' => $env['DB_PASSWORD'] ?? '',
                'database' => $env['DB_NAME'] ?? 'mmorts'
            ];
        }
        
        // Fallback to default values
        return [
            'server' => 'localhost',
            'username' => 'root', 
            'password' => '',
            'database' => 'mmorts'
        ];
    }

    /**
     * Generate base URL
     */
    private function generateBaseUrl() {
        // Check if we're in CLI mode
        if (php_sapi_name() === 'cli') {
            return 'http://localhost/mmorts/';
        }
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Determine if the script is in a subdirectory
        $script_name_parts = explode('/', $_SERVER['SCRIPT_NAME'] ?? '');
        $project_subdir = '';
        
        $mmorts_key = array_search('mmorts', $script_name_parts);
        if ($mmorts_key !== false && isset($script_name_parts[$mmorts_key])) {
            $project_subdir = '/' . $script_name_parts[$mmorts_key] . '/';
        } else if (count($script_name_parts) > 2) {
            if(!empty($script_name_parts[1]) && $script_name_parts[1] !== 'index.php') {
                 $project_subdir = '/' . $script_name_parts[1] . '/';
            } else {
                $project_subdir = '/';
            }
        } else {
            $project_subdir = '/';
        }
        
        // Ensure project_subdir ends with a slash if it's not just "/"
        if (strlen($project_subdir) > 1 && substr($project_subdir, -1) !== '/') {
            $project_subdir .= '/';
        }
        
        return $protocol . $host . '/mmorts/';
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
