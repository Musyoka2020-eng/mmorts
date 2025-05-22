<?php
require_once __DIR__ . '/system/config.php';

$sql = "SHOW COLUMNS FROM configuration LIKE 'game_initialized'";
$result = $conn->query($sql);
echo 'Column exists: ' . ($result->num_rows > 0 ? 'Yes' : 'No') . "\n";

// If the column doesn't exist, add it
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE configuration ADD COLUMN game_initialized TINYINT(1) DEFAULT 0";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'game_initialized' added successfully\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
}
?>
