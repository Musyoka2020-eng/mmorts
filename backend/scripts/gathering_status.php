<?php
// AJAX endpoint for getting gathering status and progress
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

// Get all active gathering operations for the player
$stmt = $conn->prepare("
    SELECT go.*, wm.resource_type as map_resource_type, wm.resource_amount as available_amount 
    FROM gathering_operations go 
    LEFT JOIN world_map wm ON go.location_x = wm.location_x AND go.location_y = wm.location_y 
    WHERE go.player_id = ? AND go.status = 'active' 
    ORDER BY go.estimated_completion ASC
");
$stmt->bind_param("i", $playerId);
$stmt->execute();
$result = $stmt->get_result();

$activeOperations = [];
$completedOperations = [];

while ($row = $result->fetch_assoc()) {
    $currentTime = time();
    $startTime = strtotime($row['start_time']);
    $completionTime = strtotime($row['estimated_completion']);
    
    // Calculate progress
    $totalTime = $completionTime - $startTime;
    $elapsedTime = $currentTime - $startTime;
    $progressPercent = min(100, max(0, ($elapsedTime / $totalTime) * 100));
    
    // Calculate current gathered amount based on time
    $currentGathered = min($row['amount_to_gather'], floor($progressPercent / 100 * $row['amount_to_gather']));
    
    $operationData = [
        'id' => $row['id'],
        'location_x' => $row['location_x'],
        'location_y' => $row['location_y'],
        'resource_type' => $row['resource_type'],
        'amount_to_gather' => $row['amount_to_gather'],
        'amount_gathered' => $currentGathered,
        'gathering_rate' => $row['gathering_rate'],
        'start_time' => $row['start_time'],
        'estimated_completion' => $row['estimated_completion'],
        'progress_percent' => round($progressPercent, 1),
        'is_completed' => $currentTime >= $completionTime,
        'time_remaining_seconds' => max(0, $completionTime - $currentTime),
        'available_at_location' => $row['available_amount']
    ];
    
    if ($operationData['is_completed']) {
        $completedOperations[] = $operationData;
    } else {
        $activeOperations[] = $operationData;
    }
}

// Get recently completed operations (last 24 hours)
$stmt = $conn->prepare("
    SELECT * FROM gathering_operations 
    WHERE player_id = ? AND status = 'completed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY updated_at DESC 
    LIMIT 10
");
$stmt->bind_param("i", $playerId);
$stmt->execute();
$result = $stmt->get_result();

$recentlyCompleted = [];
while ($row = $result->fetch_assoc()) {
    $recentlyCompleted[] = [
        'id' => $row['id'],
        'location_x' => $row['location_x'],
        'location_y' => $row['location_y'],
        'resource_type' => $row['resource_type'],
        'amount_gathered' => $row['amount_gathered'],
        'completed_at' => $row['updated_at']
    ];
}

// Get player's current resources
$stmt = $conn->prepare("
    SELECT r.* FROM resources r 
    JOIN cities c ON r.id = c.resources_id 
    WHERE c.player_id = ?
");
$stmt->bind_param("i", $playerId);
$stmt->execute();
$result = $stmt->get_result();

$currentResources = [];
if ($result->num_rows === 1) {
    $currentResources = $result->fetch_assoc();
} else {
    $currentResources = [
        'wood' => 0,
        'iron' => 0,
        'food' => 0,
        'oil' => 0,
        'stone' => 0
    ];
}

$response['success'] = true;
$response['data'] = [
    'active_operations' => $activeOperations,
    'completed_operations' => $completedOperations,
    'recently_completed' => $recentlyCompleted,
    'current_resources' => $currentResources,
    'total_active' => count($activeOperations),
    'total_completed_ready' => count($completedOperations),
    'server_timestamp' => time()
];

echo json_encode($response);
?>
