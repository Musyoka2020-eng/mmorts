<?php
session_start();
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
$title          = $row['name'] ?? "MMORTS";
$separator      = $row['separator'] ?? " _ ";
$description    = $row['description'] ?? "My first MMORTS description";
$logo           = $row['logo'];

$maintainance     = (bool) $row['maintainance'];