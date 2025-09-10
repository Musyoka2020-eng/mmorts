<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$timezone = date_default_timezone_get();
date_default_timezone_set($timezone);
ob_start();
$path = __DIR__ . '/env.ini';

if (!file_exists($path)) {
    return false;
}

$config = parse_ini_file($path);

$dbserver       = $config['dbhost'];
$dbusername     = $config['dbuser'];
$dbpassword     = $config['dbpassword'];
$db             = $config['database'];

$conn = new mysqli(
    $dbserver,
    $dbusername,
    $dbpassword,
    $db
);

//when the connection failes?
if ($conn->connect_error) {
    die('connection to the database failed: ' . $conn->connect_error);
} else {
    $query = "SELECT * FROM configuration";
    $result = $conn->query($query);
    $row = mysqli_fetch_assoc($result);
}
$title          = $row['name'] ?? "MechaEmpire";
$separator      = $row['separator'] ?? " _ ";
$description    = $row['description'] ?? "My first MechaEmpire description";
$logo           = $row['logo'];

// Define Base URL - adjust if your local setup is different (e.g., includes a port or different subdirectory)
// Assumes the project is in a subdirectory named 'mechaEmpire' directly under the web server's document root.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Determine if the script is in a subdirectory.
// This attempts to find 'mechaEmpire' in the path. If your project root is different, adjust this logic.
$script_name_parts = explode('/', $_SERVER['SCRIPT_NAME'] ?? '');
$project_subdir = '';
// Find the 'MechaEmpire' part or assume it's the first directory if not found (less reliable)
$mechaEmpire_key = array_search('MechaEmpire', $script_name_parts);
if ($mechaEmpire_key !== false && isset($script_name_parts[$mechaEmpire_key])) {
    $project_subdir = '/' . $script_name_parts[$mechaEmpire_key] . '/';
} else if (count($script_name_parts) > 2) { // Fallback if 'MechaEmpire' isn't in path, e.g. /index.php
    // This fallback might not be perfect for all setups.
    // If SCRIPT_NAME is /MechaEmpire/index.php, $script_name_parts[1] would be 'MechaEmpire'.
    // If SCRIPT_NAME is /index.php (root), this might be empty or incorrect.
    // A more robust solution might involve a manually set config value if auto-detection is tricky.
    if(!empty($script_name_parts[1]) && $script_name_parts[1] !== 'index.php') {
         $project_subdir = '/' . $script_name_parts[1] . '/';
    } else {
        $project_subdir = '/'; // Assume root if no clear subdirectory found
    }
} else {
    $project_subdir = '/'; // Default to root if SCRIPT_NAME is very short (e.g., /index.php)
}
// Ensure project_subdir ends with a slash if it's not just "/"
if (strlen($project_subdir) > 1 && substr($project_subdir, -1) !== '/') {
    $project_subdir .= '/';
}


// A simpler, more direct approach if you know your base path:
// $base_url = $protocol . $host . '/MechaEmpire/';
// For dynamic detection, the above is an attempt. If it fails, hardcode or use a .env variable.
// Let's use a more direct approach for now, assuming 'MechaEmpire' is the known subdirectory.
$base_url = $protocol . $host . '/MechaEmpire/'; // Ensure this matches your actual setup.

$maintainance     = (bool) $row['maintainance'];
