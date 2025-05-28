<?php
// AJAX endpoint for starting gathering operations with robust concurrency control
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../system/config.php';

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    $response['message'] = 'Not logged in';
    echo json_encode($response);
    exit;
}

$playerId = $_SESSION['user']['id'];

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $response['message'] = 'Invalid JSON data';
    echo json_encode($response);
    exit;
}

$action = $input['action'] ?? '';

switch ($action) {
    case 'start_gathering':
        $targetX = intval($input['target_x'] ?? 0);
        $targetY = intval($input['target_y'] ?? 0);
        $gatherAmount = intval($input['gather_amount'] ?? 0);
        $startTime = intval($input['start_time'] ?? time());
        $timezoneOffset = intval($input['timezone_offset'] ?? 0); // Get timezone offset in seconds

        if ($targetX === 0 || $targetY === 0 || $gatherAmount <= 0) {
            $response['message'] = 'Invalid parameters';
            echo json_encode($response);
            exit;
        }

        // ROBUST CONCURRENCY CONTROL with database locking
        $conn->autocommit(false);

        try {
            // STEP 1: Lock the specific map cell to prevent concurrent access
            $stmt = $conn->prepare("SELECT * FROM world_map WHERE location_x = ? AND location_y = ? FOR UPDATE");
            $stmt->bind_param("ii", $targetX, $targetY);
            $stmt->execute();
            $mapResult = $stmt->get_result();

            if ($mapResult->num_rows !== 1) {
                $conn->rollback();
                $response['message'] = 'Invalid location';
                echo json_encode($response);
                exit;
            }

            $mapCell = $mapResult->fetch_assoc();

            // STEP 2: Check if ANY player has an active gathering operation at this location
            $stmt = $conn->prepare("SELECT id, player_id FROM gathering_operations WHERE location_x = ? AND location_y = ? AND status = 'active'");
            $stmt->bind_param("ii", $targetX, $targetY);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $existingOp = $result->fetch_assoc();
                $conn->rollback();

                if ($existingOp['player_id'] == $playerId) {
                    $response['message'] = 'You already have an active gathering operation at this location';
                } else {
                    $response['message'] = 'Another player is currently gathering at this location. Please find another resource node.';
                }
                echo json_encode($response);
                exit;
            }

            // STEP 3: Validate location and resources
            if ($mapCell['occupied'] != 0) {
                $conn->rollback();
                $response['message'] = 'Location is occupied by a city or structure';
                echo json_encode($response);
                exit;
            }

            if (empty($mapCell['resource_type']) || $mapCell['resource_amount'] <= 0) {
                $conn->rollback();
                $response['message'] = 'No resources available at this location';
                echo json_encode($response);
                exit;
            }

            $resourceType = $mapCell['resource_type'];
            $availableAmount = $mapCell['resource_amount'];

            // STEP 4: Validate and cap gather amount
            if ($gatherAmount > $availableAmount) {
                $gatherAmount = $availableAmount;
            }

            // STEP 5: Get gathering rate for this resource
            $stmt = $conn->prepare("SELECT base_rate FROM gathering_rates WHERE resource_type = ?");
            $stmt->bind_param("s", $resourceType);
            $stmt->execute();
            $result = $stmt->get_result();

            $gatheringRate = 10.0; // Default rate
            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $gatheringRate = floatval($row['base_rate']);
            }

            // STEP 6: Calculate completion time with precision
            $timeToComplete = ceil($gatherAmount / $gatheringRate); // minutes
            $totalTime = $timeToComplete * 60; // seconds
            $estimatedCompletion = $startTime + $totalTime + $timezoneOffset; 
            $timeStamp = (int)$estimatedCompletion;
            $completionTime = date('Y-m-d H:i:s', $timeStamp);

            // STEP 7: Create gathering operation atomically
            $stmt = $conn->prepare("INSERT INTO gathering_operations (player_id, location_x, location_y, resource_type, amount_to_gather, gathering_rate, estimated_completion) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisids", $playerId, $targetX, $targetY, $resourceType, $gatherAmount, $gatheringRate, $completionTime);

            if ($stmt->execute()) {
                $operationId = $conn->insert_id;
                $conn->commit();

                $response['success'] = true;
                $response['message'] = 'Gathering operation started successfully';
                $response['data'] = [
                    'operation_id' => $operationId,
                    'resource_type' => $resourceType,
                    'amount_to_gather' => $gatherAmount,
                    'gathering_rate' => $gatheringRate,
                    'estimated_completion' => $completionTime,
                    'time_to_complete_minutes' => $timeToComplete,
                    'start_time' => date('Y-m-d H:i:s', $startTime),
                    'time' => $startTime,
                    'timezone_offset' => $timezoneOffset
                ];
            } else {
                $conn->rollback();
                $response['message'] = 'Failed to start gathering operation: ' . $conn->error;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $response['message'] = 'Database error: ' . $e->getMessage();
        }

        $conn->autocommit(true);
        break;

    case 'cancel_gathering':
        $operationId = intval($input['operation_id'] ?? 0);

        if ($operationId <= 0) {
            $response['message'] = 'Invalid operation ID';
            echo json_encode($response);
            exit;
        }

        // Cancel the gathering operation with validation
        $stmt = $conn->prepare("UPDATE gathering_operations SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND player_id = ? AND status = 'active'");
        $stmt->bind_param("ii", $operationId, $playerId);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Gathering operation cancelled successfully';
        } else {
            $response['message'] = 'Operation not found, already completed, or not owned by you';
        }
        break;

    case 'collect_resources':
        $operationId = intval($input['operation_id'] ?? 0);

        if ($operationId <= 0) {
            $response['message'] = 'Invalid operation ID';
            echo json_encode($response);
            exit;
        }

        // Player acknowledges collection of resources already processed by cron.
        try {
            $stmt = $conn->prepare("SELECT id, player_id, resource_type, amount_gathered, estimated_completion, status FROM gathering_operations WHERE id = ? AND player_id = ?");
            $stmt->bind_param("ii", $operationId, $playerId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows !== 1) {
                $response['message'] = 'Operation not found or not owned by you.';
                echo json_encode($response);
                exit;
            }

            $operation = $result->fetch_assoc();

            if ($operation['status'] === 'active') {
                // Optionally, update a timestamp like `player_acknowledged_at = NOW()` if needed for tracking.
                // For now, simply confirming it's 'completed' is enough.
                // The cron job handles actual resource crediting and status change to 'completed'.
                $response['success'] = true;
                $response['message'] = "Resources from operation #{$operationId} acknowledged.";
                $response['data'] = [
                    'resource_type' => $operation['resource_type'],
                    'amount_collected' => $operation['amount_gathered'], // Amount already determined by cron
                    'operation_id' => $operationId
                ];
            } elseif ($operation['status'] === 'active') {
                if (strtotime($operation['estimated_completion']) <= time()) {
                    $response['message'] = 'Operation is being processed by the system. Please try again shortly.';
                } else {
                    $response['message'] = 'Operation is still in progress.';
                }
            } else {
                $response['message'] = 'Operation already processed, cancelled, or in an unknown state.';
            }
        } catch (Exception $e) {
            $response['message'] = 'Error checking collection status: ' . $e->getMessage();
        }
        break;

    case 'check_location_status':
        $targetX = intval($input['target_x'] ?? 0);
        $targetY = intval($input['target_y'] ?? 0);

        if ($targetX === 0 || $targetY === 0) {
            $response['message'] = 'Invalid parameters';
            echo json_encode($response);
            exit;
        }

        // Check if location is available for gathering
        $stmt = $conn->prepare("
            SELECT 
                wm.resource_type, 
                wm.resource_amount, 
                wm.occupied,
                COUNT(go.id) as active_operations
            FROM world_map wm 
            LEFT JOIN gathering_operations go ON wm.location_x = go.location_x 
                AND wm.location_y = go.location_y 
                AND go.status = 'active'
            WHERE wm.location_x = ? AND wm.location_y = ?
            GROUP BY wm.location_x, wm.location_y
        ");
        $stmt->bind_param("ii", $targetX, $targetY);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $locationData = $result->fetch_assoc();
            $response['success'] = true;
            $response['data'] = [
                'available' => $locationData['active_operations'] == 0 &&
                    $locationData['occupied'] == 0 &&
                    !empty($locationData['resource_type']) &&
                    $locationData['resource_amount'] > 0,
                'resource_type' => $locationData['resource_type'],
                'resource_amount' => $locationData['resource_amount'],
                'occupied' => $locationData['occupied'],
                'active_operations' => $locationData['active_operations']
            ];
        } else {
            $response['message'] = 'Location not found';
        }
        break;

    default:
        $response['message'] = 'Invalid action';
        break;
}

echo json_encode($response);
