<?php
/**
 * Deprecation Warning System
 * Helps identify and track usage of old global variable patterns
 */

class DeprecationWarning {
    private static $warnings = [];
    private static $logFile = null;
    
    /**
     * Initialize the deprecation warning system
     */
    public static function init($logFile = null) {
        if ($logFile) {
            self::$logFile = $logFile;
        } else {
            self::$logFile = __DIR__ . '/../logs/deprecation_warnings.log';
        }
        
        // Create logs directory if it doesn't exist
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Log a deprecation warning
     */
    public static function warn($message, $file = null, $line = null) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? $backtrace[0];
        
        $file = $file ?? ($caller['file'] ?? 'unknown');
        $line = $line ?? ($caller['line'] ?? 'unknown');
        
        $warning = [
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::$warnings[] = $warning;
        
        // Log to file if in development mode
        if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE && self::$logFile) {
            $logMessage = sprintf(
                "[%s] DEPRECATED: %s in %s on line %s\n",
                $warning['timestamp'],
                $warning['message'],
                $warning['file'],
                $warning['line']
            );
            file_put_contents(self::$logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
        
        // Also log to PHP error log
        error_log("DEPRECATED: $message in $file on line $line");
    }
    
    /**
     * Get all deprecation warnings
     */
    public static function getWarnings() {
        return self::$warnings;
    }
    
    /**
     * Clear all warnings
     */
    public static function clearWarnings() {
        self::$warnings = [];
    }
    
    /**
     * Check if a specific pattern is being used and warn if so
     */
    public static function checkGlobalUsage($varName, $alternative = '') {
        if (isset($GLOBALS[$varName])) {
            $alternative = $alternative ?: "globals()->get{$varName}()";
            self::warn(
                "Direct access to global variable \${$varName} is deprecated. Use {$alternative} instead.",
                null,
                null
            );
        }
    }
}

// Initialize the deprecation warning system
DeprecationWarning::init();

/**
 * Helper function to easily log deprecation warnings
 */
function deprecation_warning($message, $file = null, $line = null) {
    DeprecationWarning::warn($message, $file, $line);
}

/**
 * Helper function to check for old global variable patterns
 */
function check_deprecated_globals() {
    // Check for common deprecated global variables
    $deprecatedGlobals = [
        'conn' => 'globals()->getDatabase()',
        'base_url' => 'globals()->getBaseUrl()',
        'title' => 'globals()->getSiteConfig("title")',
        'description' => 'globals()->getSiteConfig("description")',
        'logo' => 'globals()->getSiteConfig("logo")',
        'maintainance' => 'globals()->isMaintenanceMode()',
        'user_logged_in' => 'globals()->isUserLoggedIn()',
        'current_user' => 'globals()->getCurrentUser()'
    ];
    
    foreach ($deprecatedGlobals as $var => $alternative) {
        DeprecationWarning::checkGlobalUsage($var, $alternative);
    }
}
