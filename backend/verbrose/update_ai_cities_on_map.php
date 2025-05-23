<?php

/**
 * Update AI Cities on World Map
 * 
 * This script ensures AI cities are properly reflected on the world map
 * by updating any AI cities that might not be properly marked on the map.
 */

// Include configuration
require_once __DIR__ . '/../../system/config.php';
require_once __DIR__ . '/../ai/ai_manager.php';

echo "Updating AI cities on the world map...\n";

// Check if game is initialized
$query = "SELECT game_initialized FROM configuration WHERE id = 1";
$result = $conn->query($query);
$isInitialized = false;

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $isInitialized = $row['game_initialized'] == 1;
}

if (!$isInitialized) {
    die("Game world is not initialized yet! Please initialize the game world first.\n");
}

// Get all AI cities
$query = "SELECT ai_player_id, id, location_x, location_y FROM ai_cities";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "Found " . $result->num_rows . " AI cities to update.\n";

    while ($city = $result->fetch_assoc()) {
        // Update the world map to mark this location as occupied by AI
        $x = $city['location_x'];
        $y = $city['location_y'];
        $aiPlayerId = $city['ai_player_id'];

        echo "Updating city at coordinates ($x, $y) for AI player ID: $aiPlayerId\n";

        $query = "UPDATE world_map 
                 SET occupied = 1, 
                     occupier_id = ?, 
                     occupier_type = 'ai' 
                 WHERE location_x = ? AND location_y = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $aiPlayerId, $x, $y);

        if ($stmt->execute()) {
            echo "- Updated successfully\n";
        } else {
            echo "- ERROR: Failed to update: " . $stmt->error . "\n";
        }
    }

    echo "All AI cities have been updated on the world map.\n";
} else {
    echo "No AI cities found in the database.\n";
}

// Close database connection
$conn->close();
