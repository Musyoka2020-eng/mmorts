<?php
require __DIR__ . '/../../system/includes.php';

// Use new globals system
$g = globals();
$conn = $g->getDatabase();

// Define the resource ID
$resourceId = 1; // Change this to the ID of the resource you want to update

// Define the new production rate values
$newWoodProduction = 100;
$newOilProduction = 200;
$newIronProduction = 150;
$newFoodProduction = 500;
$newStoneProduction = 300;


// Query the building table to get the level of the resource
$query = "SELECT level FROM buildings WHERE resource_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $resourceId);
$stmt->execute();
$result = $stmt->get_result();

// Get the last level of the resource from the productions table
$lastLevel = null;
$query = "SELECT level FROM productions WHERE resource_id = ? ORDER BY level DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $resourceId);
$stmt->execute();
$result2 = $stmt->get_result();
if ($row2 = $result2->fetch_assoc()) {
    $lastLevel = $row2['level'];
}

// Update the production rate for each level of the resource
while ($row = $result->fetch_assoc()) {
    $level = $row['level'];
    switch ($level) {
        case 1:
            $woodProduction = $newWoodProduction;
            $oilProduction = $newOilProduction;
            $ironProduction = $newIronProduction;
            $foodProduction = $newFoodProduction;
            $stoneProduction = $newStoneProduction;
            break;
            // Add more cases for each level of the resource
        default:
            // Increase the production rate by 10% if the level has changed since the last run
            if ($level > $lastLevel) {
                $woodProduction *= 1.1;
                $oilProduction *= 1.1;
                $ironProduction *= 1.1;
                $foodProduction *= 1.1;
                $stoneProduction *= 1.1;
            }
            break;
    }

    // Update the production rate for the current level of the resource
    $query = "UPDATE productions SET 
    wood_production = ?, 
    oil_production = ?, 
    iron_production = ?, 
    food_production = ?, 
    stone_production = ?, 
    WHERE resource_id = ? 
    AND level = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ddddddddii", $woodProduction, $oilProduction, $ironProduction, $foodProduction, $stoneProduction, $resourceId, $level);
    $stmt->execute();
}
