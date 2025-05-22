<?php
require_once __DIR__ . '/system/config.php';

// Check the structure of the players table
$query = "DESCRIBE players";
$result = $conn->query($query);

echo "Players table structure:\n";
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']} - {$row['Key']}\n";
}

?>
