<?php
require_once __DIR__ . '/system/config.php';

// Check the structure of the resources table
$query = "DESCRIBE resources";
$result = $conn->query($query);

if ($result) {
    echo "Resources table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} - {$row['Type']} - {$row['Key']} - {$row['Default']}\n";
    }
} else {
    echo "Resources table doesn't exist or can't be accessed.\n";
}
?>
