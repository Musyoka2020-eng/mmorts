<?php
// Script to check and fix resource data for existing cities

// Include configuration
require_once __DIR__ . '/system/config.php';

echo "Checking cities and resources data...\n";
$query = "SELECT * FROM cities";
$result = $conn->query($query);

if ($result) {
    echo "Found " . $result->num_rows . " cities\n";
    
    while ($city = $result->fetch_assoc()) {
        echo "\nChecking city ID: " . $city['id'] . ", Player ID: " . $city['player_id'] . "\n";
        
        // Check if city has resources_id
        if (empty($city['resources_id'])) {
            echo "City has no resources_id, creating new resources...\n";
            
            // Create new resources
            $lastUpdate = time();
            $insertQuery = "INSERT INTO resources (gold, wood, stone, food, last_update) VALUES (1000, 500, 500, 500, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("i", $lastUpdate);
            $stmt->execute();
            $resourcesId = $conn->insert_id;
            
            echo "Created new resources with ID: " . $resourcesId . "\n";
            
            // Update city with resources ID
            $updateQuery = "UPDATE cities SET resources_id = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ii", $resourcesId, $city['id']);
            $stmt->execute();
            echo "Updated city with resources_id\n";
        } else {
            echo "City has resources_id: " . $city['resources_id'] . "\n";
            
            // Check if resources exist
            $resourceQuery = "SELECT * FROM resources WHERE id = ?";
            $stmt = $conn->prepare($resourceQuery);
            $stmt->bind_param("i", $city['resources_id']);
            $stmt->execute();
            $resourceResult = $stmt->get_result();
            
            if ($resourceResult && $resourceResult->num_rows > 0) {
                echo "Resources record exists\n";
            } else {
                echo "Resources record doesn't exist, creating new one...\n";
                
                // Create new resources
                $lastUpdate = time();
                $insertQuery = "INSERT INTO resources (id, gold, wood, stone, food, last_update) VALUES (?, 1000, 500, 500, 500, ?)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("ii", $city['resources_id'], $lastUpdate);
                $stmt->execute();
                echo "Created new resources with specific ID: " . $city['resources_id'] . "\n";
            }
        }
        
        // Check if city has productions_id
        if (empty($city['productions_id'])) {
            echo "City has no productions_id, creating new productions...\n";
            
            // Create new productions
            $insertQuery = "INSERT INTO productions (gold_rate, wood_rate, stone_rate, food_rate) VALUES (10, 5, 5, 8)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->execute();
            $productionsId = $conn->insert_id;
            
            echo "Created new productions with ID: " . $productionsId . "\n";
            
            // Update city with productions ID
            $updateQuery = "UPDATE cities SET productions_id = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ii", $productionsId, $city['id']);
            $stmt->execute();
            echo "Updated city with productions_id\n";
        } else {
            echo "City has productions_id: " . $city['productions_id'] . "\n";
            
            // Check if productions exist
            $productionsQuery = "SELECT * FROM productions WHERE id = ?";
            $stmt = $conn->prepare($productionsQuery);
            $stmt->bind_param("i", $city['productions_id']);
            $stmt->execute();
            $productionsResult = $stmt->get_result();
            
            if ($productionsResult && $productionsResult->num_rows > 0) {
                echo "Productions record exists\n";
            } else {
                echo "Productions record doesn't exist, creating new one...\n";
                
                // Create new productions
                $insertQuery = "INSERT INTO productions (id, gold_rate, wood_rate, stone_rate, food_rate) VALUES (?, 10, 5, 5, 8)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("i", $city['productions_id']);
                $stmt->execute();
                echo "Created new productions with specific ID: " . $city['productions_id'] . "\n";
            }
        }
    }
    
    echo "\nDone checking and fixing cities and resources data\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
