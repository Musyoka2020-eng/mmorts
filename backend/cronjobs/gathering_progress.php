<?php
// Automated script to update gathering progress and complete operations
// This should be run as a cron job every minute
require_once __DIR__ . '/../../system/config.php';

// Update progress for all active gathering operations
$stmt = $conn->prepare("
    SELECT id, player_id, location_x, location_y, resource_type, amount_to_gather, 
           gathering_rate, start_time, estimated_completion 
    FROM gathering_operations 
    WHERE status = 'active' AND estimated_completion <= NOW()
");
$stmt->execute();
$result = $stmt->get_result();

$completedOperations = [];

while ($operation = $result->fetch_assoc()) {
    $operationId = $operation['id'];
    $playerId = $operation['player_id'];
    $resourceType = $operation['resource_type'];
    $amountToGather = $operation['amount_to_gather'];
    $locationX = $operation['location_x'];
    $locationY = $operation['location_y'];
    
    // Start transaction
    $conn->autocommit(false);
    
    try {
        // Get player's resources ID
        $stmt2 = $conn->prepare("SELECT resources_id FROM cities WHERE player_id = ? LIMIT 1");
        $stmt2->bind_param("i", $playerId);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        if ($result2->num_rows === 1) {
            $row = $result2->fetch_assoc();
            $resourcesId = $row['resources_id'];
            
            // Update player's resources
            $stmt3 = $conn->prepare("UPDATE resources SET $resourceType = $resourceType + ? WHERE id = ?");
            $stmt3->bind_param("ii", $amountToGather, $resourcesId);
            $stmt3->execute();
            
            // Update map cell resources
            $stmt4 = $conn->prepare("UPDATE world_map SET resource_amount = GREATEST(0, resource_amount - ?) WHERE location_x = ? AND location_y = ?");
            $stmt4->bind_param("iii", $amountToGather, $locationX, $locationY);
            $stmt4->execute();
            
            // Mark operation as completed
            $stmt5 = $conn->prepare("UPDATE gathering_operations SET status = 'completed', amount_gathered = ?, updated_at = NOW() WHERE id = ?");
            $stmt5->bind_param("ii", $amountToGather, $operationId);
            $stmt5->execute();
            
            $conn->commit();
            
            $completedOperations[] = [
                'operation_id' => $operationId,
                'player_id' => $playerId,
                'resource_type' => $resourceType,
                'amount' => $amountToGather,
                'location' => "($locationX, $locationY)"
            ];
            
            echo "Completed operation #$operationId: Gathered $amountToGather $resourceType for player $playerId\n";
        } else {
            echo "Error: Player city not found for operation #$operationId\n";
            $conn->rollback();
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error completing operation #$operationId: " . $e->getMessage() . "\n";
    }
}

$conn->autocommit(true);

if (empty($completedOperations)) {
    echo "No gathering operations to complete at " . date('Y-m-d H:i:s') . "\n";
} else {
    echo "Completed " . count($completedOperations) . " gathering operations at " . date('Y-m-d H:i:s') . "\n";
}

$conn->close();
?>
