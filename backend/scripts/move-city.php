<?php
require_once __DIR__ . '/../../system/includes.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Check if coordinates are provided
if (!isset($_POST['target_x']) || !isset($_POST['target_y'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing coordinates']);
    exit;
}

// Get coordinates
$targetX = intval($_POST['target_x']);
$targetY = intval($_POST['target_y']);

// Get player ID
$playerId = $_SESSION['user']['id'];

// Check if there's a target location
if ($targetX !== null && $targetY !== null) {
    // Get map cell info - check if target location is unoccupied
    $query = "SELECT * FROM world_map WHERE location_x = ? AND location_y = ? AND occupied = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $targetX, $targetY);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $mapCell = $result->fetch_assoc();
        
        // Get the player's city info
        $cityId = '';
        $currentX = 0;
        $currentY = 0;
        $query = "SELECT * FROM cities WHERE player_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $playerId);
        $stmt->execute();
        $cityResult = $stmt->get_result();
        
        if ($cityResult->num_rows === 1) {
            $city = $cityResult->fetch_assoc();
            $cityId = $city['id'];
            $currentX = $city['location_x'];
            $currentY = $city['location_y'];        } else {
            echo json_encode(['status' => 'error', 'message' => 'City not found']);
            exit;
        }
        
        // Check if the player has enough resources to move (teleport cost)
        $requiredResources = 1; // Requires 1 teleport
        $playerResourcesQuery = "SELECT teleports FROM resources WHERE city_id = ?";
        $playerResourcesStmt = $conn->prepare($playerResourcesQuery);
        $playerResourcesStmt->bind_param("i", $cityId);
        $playerResourcesStmt->execute();
        $playerResourcesResult = $playerResourcesStmt->get_result();

        if ($playerResourcesResult->num_rows === 1) {
            $playerResources = $playerResourcesResult->fetch_assoc()['teleports'];

            if ($playerResources >= $requiredResources) {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Update the old location to be unoccupied
                    $query = "UPDATE world_map SET occupied = 0, occupier_id = NULL, occupier_type = NULL WHERE location_x = ? AND location_y = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $currentX, $currentY);
                    $stmt->execute();
                    
                    // Move the city to new location
                    $query = "UPDATE cities SET location_x = ?, location_y = ? WHERE player_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iii", $targetX, $targetY, $playerId);
                    $stmt->execute();

                    // Mark the new location as occupied
                    $query = "UPDATE world_map SET occupied = 1, occupier_id = ?, occupier_type = 'player' WHERE location_x = ? AND location_y = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iii", $playerId, $targetX, $targetY);
                    $stmt->execute();

                    // Deduct teleport resources
                    $newResources = $playerResources - $requiredResources;
                    $query = "UPDATE resources SET teleports = ? WHERE city_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $newResources, $cityId);
                    $stmt->execute();

                    // Commit transaction
                    $conn->commit();

                    // Return success response
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'City moved successfully.',
                        'new_location' => [
                            'x' => $targetX,
                            'y' => $targetY
                        ],
                        'old_location' => [
                            'x' => $currentX,
                            'y' => $currentY
                        ],
                        'remaining_teleports' => $newResources
                    ]);
                    exit;
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    echo json_encode(['status' => 'error', 'message' => 'Failed to move city: ' . $e->getMessage()]);
                    exit;
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Not enough teleports to move city. Required: ' . $requiredResources . ', Available: ' . $playerResources]);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Player resources not found']);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid target location or location is occupied']);
        exit;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No target location specified']);
    exit;
}