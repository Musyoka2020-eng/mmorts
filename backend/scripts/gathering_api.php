<?php
/**
 * Gathering API - Complete Backend for MMORTS Resource Gathering System
 * Handles all gathering operations: discovery, starting, progress tracking, and collection
 * Updated to use new Globals system
 */

// Start session and include necessary files
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../system/includes.php';

// Use new globals system
$g = globals();
$conn = $g->getDatabase();

// Set JSON content type
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

/**
 * Main API handler
 */
class GatheringAPI {
    private $conn;
    private $player_id;
    private $globals;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->globals = globals();
        $this->player_id = $this->globals->getCurrentUser('id');
    }
    
    /**
     * Main API endpoint router
     */
    public function handleRequest() {
        // Check if user is logged in using new globals system
        if (!$this->globals->isUserLoggedIn() || !$this->player_id) {
            return $this->sendError('User not authenticated');
        }
        
        // Get and decode JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['action'])) {
            return $this->sendError('Invalid request format');
        }
        
        $action = $input['action'];
        
        try {
            switch ($action) {
                case 'get_page_data':
                    return $this->getPageData($input);
                    
                case 'start_gathering':
                    return $this->startGathering($input);
                    
                case 'collect_resources':
                    return $this->collectResources($input);
                    
                case 'cancel_gathering':
                    return $this->cancelGathering($input);
                    
                case 'update_progress':
                    return $this->updateProgress();
                    
                case 'debug_session':
                    return $this->debugSession();
                    
                default:
                    return $this->sendError('Unknown action: ' . $action);
            }
        } catch (Exception $e) {
            error_log("Gathering API Error: " . $e->getMessage());
            return $this->sendError('Internal server error');
        }
    }
    
    /**
     * Get complete page data for a location
     */
    private function getPageData($input) {
        $target_x = intval($input['target_x'] ?? 0);
        $target_y = intval($input['target_y'] ?? 0);
        
        if (!$target_x || !$target_y) {
            return $this->sendError('Invalid coordinates provided');
        }
        
        // Get location data
        $location = $this->getLocationData($target_x, $target_y);
        
        // Get current operation at this location (if any)
        $current_operation = $this->getCurrentOperation($target_x, $target_y);
        
        // Get all player operations
        $all_operations = $this->getAllPlayerOperations();
        
        return $this->sendSuccess([
            'location' => $location,
            'current_operation' => $current_operation,
            'all_operations' => $all_operations
        ]);
    }
    
    /**
     * Start a new gathering operation
     */
    private function startGathering($input) {
        $target_x = intval($input['target_x'] ?? 0);
        $target_y = intval($input['target_y'] ?? 0);
        $gather_amount = intval($input['gather_amount'] ?? 0);
        
        // Validate input
        if (!$target_x || !$target_y || !$gather_amount) {
            return $this->sendError('Invalid parameters provided');
        }
        
        // Check if location is valid and available
        $location = $this->getLocationData($target_x, $target_y);
        if (!$location['is_valid']) {
            return $this->sendError($location['error'] ?? 'Location is not available for gathering');
        }
        
        // Check if amount is valid
        if ($gather_amount > $location['resource_amount']) {
            return $this->sendError('Requested amount exceeds available resources');
        }
        
        if ($gather_amount <= 0) {
            return $this->sendError('Gathering amount must be greater than zero');
        }
        
        // Check if player already has an operation at this location
        $existing_op = $this->getCurrentOperation($target_x, $target_y);
        if ($existing_op) {
            return $this->sendError('You already have an active operation at this location');
        }
        
        // Check max concurrent operations (limit to 5)
        $active_count = $this->getActiveOperationsCount();
        if ($active_count >= 5) {
            return $this->sendError('Maximum number of concurrent operations reached (5)');
        }
        
        // Get gathering rate for this resource type
        $gathering_rate = $this->getGatheringRate($location['resource_type']);
        
        // Calculate total duration in seconds (ensure minimum 60 seconds for realistic gameplay)
        $total_minutes = max(1, $gather_amount / $gathering_rate);
        $total_duration_seconds = max(60, round($total_minutes * 60));
        
        // Debug logging for development
        $current_time = date('Y-m-d H:i:s');
        error_log("Gathering Debug: Current Time={$current_time}, Amount={$gather_amount}, Rate={$gathering_rate}, Duration={$total_duration_seconds}s");
        
        // Create gathering operation
        $sql = "INSERT INTO gathering_operations (
            player_id, location_x, location_y, resource_type, 
            amount_to_gather, gathering_rate, total_duration_seconds, 
            elapsed_seconds
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iiisidi", 
            $this->player_id, $target_x, $target_y, $location['resource_type'],
            $gather_amount, $gathering_rate, $total_duration_seconds
        );
        
        if ($stmt->execute()) {
            $operation_id = $this->conn->insert_id;
            
            // Update world map to mark as occupied
            $this->updateWorldMapOccupation($target_x, $target_y, true);
            
            $minutes_text = $total_minutes < 1 ? 'less than 1 minute' : round($total_minutes) . ' minutes';
            
            return $this->sendSuccess([
                'operation_id' => $operation_id,
                'total_duration_seconds' => $total_duration_seconds,
                'total_minutes' => round($total_minutes, 1)
            ], "Gathering operation started successfully! Estimated completion: {$minutes_text}.");
            
        } else {
            return $this->sendError('Failed to start gathering operation');
        }
    }
    
    /**
     * Collect resources from completed operation
     */
    private function collectResources($input) {
        $operation_id = intval($input['operation_id'] ?? 0);
        
        if (!$operation_id) {
            return $this->sendError('Invalid operation ID');
        }
        
        // Get operation details
        $operation = $this->getOperationById($operation_id);
        
        if (!$operation) {
            return $this->sendError('Operation not found');
        }
        
        if ($operation['player_id'] != $this->player_id) {
            return $this->sendError('You do not own this operation');
        }
        
        if ($operation['status'] !== 'completed') {
            return $this->sendError('Operation is not ready for collection');
        }
        
        // Start transaction
        $this->conn->autocommit(false);
        
        try {
            // Add resources to player inventory
            $this->addResourcesToPlayer($operation['resource_type'], $operation['amount_gathered']);
            
            // Remove the completed operation
            $this->deleteOperation($operation_id);
            
            // Free up the location
            $this->updateWorldMapOccupation($operation['location_x'], $operation['location_y'], false);
            
            // Commit transaction
            $this->conn->commit();
            $this->conn->autocommit(true);
            
            $resource_name = ucfirst($operation['resource_type']);
            return $this->sendSuccess([
                'resources_collected' => [
                    'type' => $operation['resource_type'],
                    'amount' => $operation['amount_gathered']
                ]
            ], "Successfully collected {$operation['amount_gathered']} {$resource_name}! Resources added to your inventory.");
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->conn->rollback();
            $this->conn->autocommit(true);
            return $this->sendError('Failed to collect resources: ' . $e->getMessage());
        }
    }
    
    /**
     * Update progress for all active operations (called by frontend timer)
     */
    private function updateProgress() {
        // Update elapsed time for all active operations
        $this->updateElapsedTimeForActiveOperations();
        
        // Get all operations to return updated data
        $all_operations = $this->getAllPlayerOperations();
        
        return $this->sendSuccess([
            'all_operations' => $all_operations,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'Progress updated successfully');
    }

    /**
     * Cancel an active gathering operation
     */
    private function cancelGathering($input) {
        $operation_id = intval($input['operation_id'] ?? 0);
        
        if (!$operation_id) {
            return $this->sendError('Invalid operation ID');
        }
        
        // Get operation details
        $operation = $this->getOperationById($operation_id);
        
        if (!$operation) {
            return $this->sendError('Operation not found');
        }
        
        if ($operation['player_id'] != $this->player_id) {
            return $this->sendError('You do not own this operation');
        }
        
        if ($operation['status'] !== 'active') {
            return $this->sendError('Operation cannot be cancelled');
        }
        
        // Start transaction
        $this->conn->autocommit(false);
        
        try {
            // Calculate partial resources based on elapsed time
            $total_duration = intval($operation['total_duration_seconds']);
            $elapsed = intval($operation['elapsed_seconds']);
            
            // Calculate progress and amount gathered so far
            $progress = $total_duration > 0 ? min(100, ($elapsed / $total_duration) * 100) : 0;
            $amount_gathered = round(($progress / 100) * $operation['amount_to_gather']);
            
            // If player has gathered any resources, give them partial collection
            if ($amount_gathered > 0) {
                $this->addResourcesToPlayer($operation['resource_type'], $amount_gathered);
                $message = "Gathering cancelled. Collected {$amount_gathered} {$operation['resource_type']} based on {$progress}% progress.";
            } else {
                $message = 'Gathering operation cancelled. No resources were collected.';
            }
            
            // Update operation status to cancelled
            $sql = "UPDATE gathering_operations SET status = 'cancelled', amount_gathered = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $amount_gathered, $operation_id);
            $stmt->execute();
            
            // Free up the location
            $this->updateWorldMapOccupation($operation['location_x'], $operation['location_y'], false);
            
            // Commit transaction
            $this->conn->commit();
            $this->conn->autocommit(true);
            
            return $this->sendSuccess([
                'resources_collected' => [
                    'type' => $operation['resource_type'],
                    'amount' => $amount_gathered,
                    'progress_percent' => round($progress, 1)
                ]
            ], $message);
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->conn->rollback();
            $this->conn->autocommit(true);
            return $this->sendError('Failed to cancel operation');
        }
    }
    
    /**
     * Get location data including resource availability
     */
    private function getLocationData($x, $y) {
        $sql = "SELECT location_x, location_y, terrain_type, resource_type, resource_amount, occupied, occupier_id, occupier_type 
                FROM world_map WHERE location_x = ? AND location_y = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $x, $y);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $is_valid = true;
            $error = null;
            
            // Check if location can be gathered from
            if (!$row['resource_type'] || $row['resource_amount'] <= 0) {
                $is_valid = false;
                $error = 'No resources available at this location';
            } elseif ($row['occupied'] && $row['occupier_id'] != $this->player_id) {
                $is_valid = false;
                $error = 'This location is currently occupied by another player';
            }
            
            return [
                'x' => $row['location_x'],
                'y' => $row['location_y'],
                'terrain_type' => $row['terrain_type'],
                'resource_type' => $row['resource_type'],
                'resource_amount' => intval($row['resource_amount']),
                'is_valid' => $is_valid,
                'error' => $error,
                'occupied' => boolval($row['occupied'])
            ];
        } else {
            return [
                'x' => $x,
                'y' => $y,
                'is_valid' => false,
                'error' => 'Location does not exist',
                'resource_type' => null,
                'resource_amount' => 0
            ];
        }
    }
    
    /**
     * Get current operation at specific location for this player
     */
    private function getCurrentOperation($x, $y) {
        $sql = "SELECT * FROM gathering_operations 
                WHERE player_id = ? AND location_x = ? AND location_y = ? 
                AND status IN ('active', 'completed')
                ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iii", $this->player_id, $x, $y);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $total_duration = intval($row['total_duration_seconds']);
            $elapsed = intval($row['elapsed_seconds']);
            
            // Calculate progress based on counter system
            $progress = $total_duration > 0 ? min(100, ($elapsed / $total_duration) * 100) : 0;
            $amount_gathered = round(($progress / 100) * $row['amount_to_gather']);
            
            // Determine if operation is completed based on counter
            $is_completed = $elapsed >= $total_duration;
            $current_status = $is_completed ? 'completed' : $row['status'];
            
            // Update status if completed
            if ($is_completed && $row['status'] !== 'completed') {
                $this->updateOperationStatus($row['id'], 'completed');
                $this->updateAmountGathered($row['id'], $row['amount_to_gather']);
            }
            
            // Calculate remaining time
            $remaining_seconds = max(0, $total_duration - $elapsed);
            $time_remaining_formatted = $this->formatTimeRemainingFromSeconds($remaining_seconds);
            
            // Debug logging
            error_log("Operation Debug: ID={$row['id']}, Total={$total_duration}s, Elapsed={$elapsed}s, Progress={$progress}%, Status={$current_status}");
            
            return [
                'id' => $row['id'],
                'resource_type' => $row['resource_type'],
                'amount_to_gather' => intval($row['amount_to_gather']),
                'amount_gathered' => $amount_gathered,
                'gathering_rate' => floatval($row['gathering_rate']),
                'progress_percent' => round($progress, 1),
                'time_remaining_formatted' => $time_remaining_formatted,
                'start_time' => $row['created_at'], // Use created_at instead of start_time
                'can_collect' => $current_status === 'completed',
                'status' => $current_status,
                'total_duration_seconds' => $total_duration,
                'elapsed_seconds' => $elapsed
            ];
        }
        
        return null;
    }
    
    /**
     * Get all operations for the current player
     */
    private function getAllPlayerOperations() {
        // First update elapsed time for all active operations
        $this->updateElapsedTimeForActiveOperations();
        
        $sql = "SELECT * FROM gathering_operations 
                WHERE player_id = ? AND status IN ('active', 'completed')
                ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $active = [];
        $completed = [];
        
        while ($row = $result->fetch_assoc()) {
            $total_duration = intval($row['total_duration_seconds']);
            $elapsed = intval($row['elapsed_seconds']);
            
            // Calculate progress based on counter system
            $progress = $total_duration > 0 ? min(100, ($elapsed / $total_duration) * 100) : 0;
            $amount_gathered = round(($progress / 100) * $row['amount_to_gather']);
            
            // Determine if operation is completed based on counter
            $is_completed = $elapsed >= $total_duration;
            $current_status = $is_completed ? 'completed' : $row['status'];
            
            // Update status if completed
            if ($is_completed && $row['status'] !== 'completed') {
                $this->updateOperationStatus($row['id'], 'completed');
                $this->updateAmountGathered($row['id'], $row['amount_to_gather']);
            }
            
            // Calculate remaining time
            $remaining_seconds = max(0, $total_duration - $elapsed);
            $time_remaining_formatted = $this->formatTimeRemainingFromSeconds($remaining_seconds);
            
            $operation = [
                'id' => $row['id'],
                'location_x' => intval($row['location_x']),
                'location_y' => intval($row['location_y']),
                'resource_type' => $row['resource_type'],
                'amount_to_gather' => intval($row['amount_to_gather']),
                'amount_gathered' => $amount_gathered,
                'gathering_rate' => floatval($row['gathering_rate']),
                'progress_percent' => round($progress, 1),
                'time_remaining_formatted' => $time_remaining_formatted,
                'start_time' => $row['created_at'], // Use created_at instead of start_time
                'status' => $current_status,
                'total_duration_seconds' => $total_duration,
                'elapsed_seconds' => $elapsed
            ];
            
            if ($current_status === 'completed') {
                $completed[] = $operation;
            } else {
                $active[] = $operation;
            }
        }
        
        return [
            'active' => $active,
            'completed' => $completed
        ];
    }
    
    /**
     * Get player's current resources
     */
    private function getPlayerResources() {
        // First, try to get existing resources for the player
        $sql = "SELECT * FROM resources WHERE id = (
                    SELECT resources_id FROM cities WHERE player_id = ? LIMIT 1
                ) OR id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $this->player_id, $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return [
                'iron' => intval($row['iron'] ?? 0),
                'wood' => intval($row['wood'] ?? 0),
                'stone' => intval($row['stone'] ?? 0),
                'food' => intval($row['food'] ?? 0),
                'oil' => intval($row['oil'] ?? 0)
            ];
        } else {
            // Create default resources for new player
            $this->createDefaultPlayerResources();
            return [
                'iron' => 1000,
                'wood' => 500,
                'stone' => 500,
                'food' => 500,
                'oil' => 0
            ];
        }
    }
    
    /**
     * Get gathering rate for a resource type
     */
    private function getGatheringRate($resource_type) {
        $sql = "SELECT base_rate FROM gathering_rates WHERE resource_type = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $resource_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return floatval($row['base_rate']);
        }
        
        // Default rates if not found in database (units per minute - slower for better gameplay)
        $default_rates = [
            'wood' => 2.0,    // 2 units per minute (30 units = 15 minutes)
            'food' => 1.5,    // 1.5 units per minute  
            'stone' => 1.0,   // 1 unit per minute (slower, heavy material)
            'iron' => 0.8,    // 0.8 units per minute (dense ore)
            'oil' => 0.5      // 0.5 units per minute (slowest, precious resource)
        ];
        
        return $default_rates[$resource_type] ?? 10.0;
    }
    
    /**
     * Helper methods
     */
    private function getOperationById($id) {
        $sql = "SELECT * FROM gathering_operations WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    private function getActiveOperationsCount() {
        $sql = "SELECT COUNT(*) as count FROM gathering_operations WHERE player_id = ? AND status = 'active'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return intval($row['count']);
    }
    
    private function updateOperationStatus($id, $status) {
        $sql = "UPDATE gathering_operations SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
    }
    
    private function updateWorldMapOccupation($x, $y, $occupied) {
        $occupier_id = $occupied ? $this->player_id : null;
        $occupier_type = $occupied ? 'player' : null;
        
        $sql = "UPDATE world_map SET occupied = ?, occupier_id = ?, occupier_type = ? WHERE location_x = ? AND location_y = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iisii", $occupied, $occupier_id, $occupier_type, $x, $y);
        $stmt->execute();
    }
    
    private function addResourcesToPlayer($resource_type, $amount) {
        // Validate resource type for security
        $valid_resources = ['wood', 'stone', 'iron', 'food', 'oil'];
        if (!in_array($resource_type, $valid_resources)) {
            throw new Exception("Invalid resource type: " . $resource_type);
        }
        
        // Get the player's resource record
        $resources_id = $this->getPlayerResourcesId();
        
        // Use direct SQL since column name can't be parameterized (but we validated it above)
        $sql = "UPDATE resources SET `" . $resource_type . "` = `" . $resource_type . "` + " . intval($amount) . " WHERE id = " . intval($resources_id);
        
        if (!$this->conn->query($sql)) {
            throw new Exception("Failed to add resources to player inventory: " . $this->conn->error);
        }
    }
    
    private function getPlayerResourcesId() {
        // Try to get from cities table first
        $sql = "SELECT resources_id FROM cities WHERE player_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (($row = $result->fetch_assoc()) && $row['resources_id']) {
            return $row['resources_id'];
        }
        
        // If not found, use player_id as resources_id (direct mapping)
        return $this->player_id;
    }
    
    private function createDefaultPlayerResources() {
        $sql = "INSERT INTO resources (id, iron, wood, stone, food, oil) VALUES (?, 1000, 500, 500, 500, 0) 
                ON DUPLICATE KEY UPDATE iron = iron";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
    }
    
    private function deleteOperation($id) {
        $sql = "DELETE FROM gathering_operations WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    
    private function formatTimeRemaining($minutes) {
        if ($minutes <= 0) {
            return 'Complete!';
        }
        
        if ($minutes < 1) {
            return 'Less than 1m';
        }
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($hours > 0) {
            return "{$hours}h {$mins}m";
        } else {
            return "{$mins}m";
        }
    }
    
    /**
     * Debug session information (remove in production)
     */
    private function debugSession() {
        return $this->sendSuccess([
            'user_logged_in' => $this->globals->isUserLoggedIn(),
            'current_user' => $this->globals->getCurrentUser(),
            'player_id' => $this->player_id,
            'session_id' => session_id(),
            'session_status' => session_status()
        ], 'Debug session information');
    }
    
    private function sendSuccess($data, $message = 'Success') {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
    
    private function sendError($message) {
        echo json_encode([
            'success' => false,
            'message' => $message,
            'data' => null
        ]);
        exit;
    }
    
    /**
     * Update elapsed time for all active operations (adds fixed increment)
     */
    private function updateElapsedTimeForActiveOperations() {
        // Add 5 seconds to all active operations each time this is called
        // The frontend should call this every 5 seconds for accurate timing
        $increment_seconds = 5;
        
        // First, get all active operations to calculate new amount_gathered
        $sql = "SELECT id, amount_to_gather, total_duration_seconds, elapsed_seconds 
                FROM gathering_operations 
                WHERE player_id = ? AND status = 'active'";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->player_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $operations_updated = 0;
        while ($row = $result->fetch_assoc()) {
            $new_elapsed = $row['elapsed_seconds'] + $increment_seconds;
            $total_duration = intval($row['total_duration_seconds']);
            
            // Calculate new progress and amount_gathered
            $progress = $total_duration > 0 ? min(100, ($new_elapsed / $total_duration) * 100) : 0;
            $amount_gathered = round(($progress / 100) * $row['amount_to_gather']);
            
            // Update both elapsed_seconds and amount_gathered
            $update_sql = "UPDATE gathering_operations 
                          SET elapsed_seconds = ?, amount_gathered = ? 
                          WHERE id = ?";
            
            $update_stmt = $this->conn->prepare($update_sql);
            $update_stmt->bind_param("iii", $new_elapsed, $amount_gathered, $row['id']);
            $update_stmt->execute();
            
            $operations_updated++;
        }
        
        // Log how many operations were updated
        if ($operations_updated > 0) {
            error_log("Updated {$operations_updated} active operations with +{$increment_seconds} seconds and real-time amount_gathered");
        }
    }
    
    /**
     * Update the amount gathered for a completed operation
     */
    private function updateAmountGathered($operation_id, $amount) {
        $sql = "UPDATE gathering_operations SET amount_gathered = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $amount, $operation_id);
        $stmt->execute();
    }
    
    /**
     * Format remaining time from seconds
     */
    private function formatTimeRemainingFromSeconds($seconds) {
        if ($seconds <= 0) {
            return "Ready for deployment, Commander!";
        }
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        $timeStr = "";
        if ($hours > 0) {
            $timeStr .= $hours . " hour" . ($hours !== 1 ? "s" : "") . " ";
        }
        if ($minutes > 0) {
            $timeStr .= $minutes . " minute" . ($minutes !== 1 ? "s" : "") . " ";
        }
        if ($secs > 0 || ($hours === 0 && $minutes === 0)) {
            $timeStr .= $secs . " second" . ($secs !== 1 ? "s" : "");
        }
        
        return "ETA: " . trim($timeStr);
    }
}

// Initialize and handle the API request
try {
    $api = new GatheringAPI($conn);
    $api->handleRequest();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred',
        'data' => null
    ]);
}
?>
