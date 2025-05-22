<?php
// This script fixes issues with the initialization process
// and ensures that all necessary tables are created

// Include the configuration
require_once __DIR__ . '/system/config.php';

// Step 1: Check if the configuration table has the game_initialized column
echo "Checking configuration table...\n";
$sql = "SHOW COLUMNS FROM configuration LIKE 'game_initialized'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Adding game_initialized column to configuration table...\n";
    $sql = "ALTER TABLE configuration ADD COLUMN game_initialized TINYINT(1) DEFAULT 0";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'game_initialized' added successfully\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
}

// Step 2: Run fix_database.php to ensure all tables exist
include_once __DIR__ . '/fix_database.php';

// Step 3: Run the initialize_game_world.php script to reset the game world
echo "\nRunning initialize_game_world.php to create the game world...\n";
include_once __DIR__ . '/backend/setup/initialize_game_world.php';

echo "\nGame world initialization completed!\n";
echo "You can now go to the home page and start playing!\n";
?>
