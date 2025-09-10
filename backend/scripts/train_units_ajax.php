<?php
// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../system/includes.php';
require_once '../../system/game_config.php';

// Function to send JSON response
function sendResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
    exit;
}

// Use new globals system
$g = globals();
$conn = $g->getDatabase();

// Check if user is logged in
if (!$g->isUserLoggedIn()) {
    sendResponse(false, 'You must be logged in to train units');
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

// Get and validate JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendResponse(false, 'Invalid JSON data');
}

// Debug: Log the input
error_log("Training AJAX: Received input: " . json_encode($input));

// Get player ID using new globals system
$playerId = $g->getCurrentUser('id');

try {
    // Start transaction
    $conn->begin_transaction();

    // Debug: Log the attempt
    error_log("Training AJAX: Starting training for player ID: " . $playerId);

    // Get player's resources
    $query = "SELECT r.* FROM resources r 
             JOIN cities c ON r.id = c.resources_id 
             WHERE c.player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        throw new Exception('Could not retrieve player resources');
    }
    
    $resources = $result->fetch_assoc();
    error_log("Training AJAX: Found resources: " . json_encode($resources));
    
    // Get unit costs from centralized configuration
    $unitCosts = GameConfig::getUnitCosts();
    error_log("Training AJAX: Loaded unit costs: " . json_encode(array_keys($unitCosts)));
    
    // Validate and process unit selections
    $totalResourcesUsed = [
        'wood' => 0,
        'iron' => 0,
        'food' => 0,
        'oil' => 0,
        'stone' => 0
    ];
    
    $unitsToTrain = [];
    $errors = [];
    
    // Validate input structure
    if (!isset($input['units']) || !is_array($input['units'])) {
        throw new Exception('Invalid units data structure');
    }
    
    // Process each unit type
    foreach ($input['units'] as $unitType => $count) {
        $count = intval($count);
        
        if ($count <= 0) {
            continue; // Skip zero or negative counts
        }
        
        // Validate unit type exists in configuration
        if (!isset($unitCosts[$unitType])) {
            $errors[] = "Invalid unit type: $unitType";
            continue;
        }
        
        // Calculate resource usage for this unit type
        foreach ($unitCosts[$unitType] as $resource => $cost) {
            $totalResourcesUsed[$resource] += $cost * $count;
        }
        
        $unitsToTrain[$unitType] = $count;
    }
    
    // Check if any units were selected
    if (empty($unitsToTrain)) {
        throw new Exception('No valid units selected for training');
    }
    
    // Check if player has enough resources
    foreach ($totalResourcesUsed as $resource => $amount) {
        if ($amount > $resources[$resource]) {
            $errors[] = "Insufficient $resource: need $amount but only have " . $resources[$resource];
        }
    }
    
    if (!empty($errors)) {
        throw new Exception(implode('; ', $errors));
    }
    
    // Deduct resources
    $query = "UPDATE resources SET 
             wood = wood - ?, 
             iron = iron - ?, 
             food = food - ?, 
             oil = oil - ?, 
             stone = stone - ? 
             WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "iiiiii", 
        $totalResourcesUsed['wood'],
        $totalResourcesUsed['iron'],
        $totalResourcesUsed['food'],
        $totalResourcesUsed['oil'],
        $totalResourcesUsed['stone'],
        $resources['id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update resources');
    }
    
    // Add units to player's army
    $trainedUnits = [];
    foreach ($unitsToTrain as $unitType => $count) {
        // Try to update existing army record
        $query = "UPDATE player_armies SET $unitType = $unitType + ? WHERE player_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $count, $playerId);
        $stmt->execute();
        
        // If no rows were affected, player doesn't have an armies record yet
        if ($stmt->affected_rows === 0) {
            // Check if player has any army record
            $checkQuery = "SELECT id FROM player_armies WHERE player_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("i", $playerId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows === 0) {
                // Create new army record with this unit type
                $createQuery = "INSERT INTO player_armies (player_id, $unitType) VALUES (?, ?)";
                $createStmt = $conn->prepare($createQuery);
                $createStmt->bind_param("ii", $playerId, $count);
                if (!$createStmt->execute()) {
                    throw new Exception("Failed to create army record for $unitType");
                }
            } else {
                // Army record exists but unit count was 0, try update again
                $retryQuery = "UPDATE player_armies SET $unitType = ? WHERE player_id = ?";
                $retryStmt = $conn->prepare($retryQuery);
                $retryStmt->bind_param("ii", $count, $playerId);
                if (!$retryStmt->execute()) {
                    throw new Exception("Failed to update army for $unitType");
                }
            }
        }
        
        $trainedUnits[$unitType] = $count;
    }
    
    // Get updated resources for response
    $updatedQuery = "SELECT r.* FROM resources r 
                    JOIN cities c ON r.id = c.resources_id 
                    WHERE c.player_id = ?";
    $updatedStmt = $conn->prepare($updatedQuery);
    $updatedStmt->bind_param("i", $playerId);
    $updatedStmt->execute();
    $updatedResult = $updatedStmt->get_result();
    $updatedResources = $updatedResult->fetch_assoc();
    
    // Get updated army for response
    $armyQuery = "SELECT * FROM player_armies WHERE player_id = ?";
    $armyStmt = $conn->prepare($armyQuery);
    $armyStmt->bind_param("i", $playerId);
    $armyStmt->execute();
    $armyResult = $armyStmt->get_result();
    $updatedArmy = $armyResult->fetch_assoc() ?: [];
    
    // Commit transaction
    $conn->commit();
    
    // Prepare success response
    $totalUnits = array_sum($trainedUnits);
    $message = "Successfully trained $totalUnits unit" . ($totalUnits > 1 ? 's' : '') . "!";
    
    // Send success response with updated data
    sendResponse(true, $message, [
        'trainedUnits' => $trainedUnits,
        'resourcesUsed' => $totalResourcesUsed,
        'updatedResources' => [
            'wood' => $updatedResources['wood'],
            'iron' => $updatedResources['iron'],
            'food' => $updatedResources['food'],
            'oil' => $updatedResources['oil'],
            'stone' => $updatedResources['stone']
        ],
        'updatedArmy' => $updatedArmy
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log error for debugging
    error_log("Training AJAX Error: " . $e->getMessage());
    
    // Send error response
    sendResponse(false, $e->getMessage());
}
?>
