<?php
// AJAX endpoint for battle report data
header('Content-Type: application/json');

// Include necessary files
require_once __DIR__ . '/../../system/includes.php';
require_once __DIR__ . '/../../backend/combat/battle_manager.php';

// Use new globals system
$g = globals();
$conn = $g->getDatabase();

// Check if user is logged in
if (!$g->isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Check if battle ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid battle ID']);
    exit;
}

$battleId = intval($_GET['id']);
$playerId = $_SESSION['user']['id'];

try {
    // Initialize battle manager
    $battleManager = new BattleManager($conn);
    
    // Get battle details
    $query = "SELECT * FROM battles WHERE id = ? AND 
             ((attacker_id = ? AND attacker_type = 'player') OR (defender_id = ? AND defender_type = 'player'))";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $battleId, $playerId, $playerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        http_response_code(404);
        echo json_encode(['error' => 'Battle not found or no permission']);
        exit;
    }

    $battle = $result->fetch_assoc();

    // Decode JSON data with error handling
    $attackerLosses = json_decode($battle['attacker_units_lost'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($attackerLosses)) {
        $attackerLosses = ['fighters' => 0, 'shooters' => 0, 'vehicles' => 0, 'skirmishers' => 0, 'riders' => 0, 'canons' => 0, 'jets' => 0, 'archers' => 0, 'marauders' => 0];
    }

    $defenderLosses = json_decode($battle['defender_units_lost'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($defenderLosses)) {
        $defenderLosses = ['fighters' => 0, 'shooters' => 0, 'vehicles' => 0, 'skirmishers' => 0, 'riders' => 0, 'canons' => 0, 'jets' => 0, 'archers' => 0, 'marauders' => 0];
    }

    $resourcesPlundered = json_decode($battle['resources_plundered'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($resourcesPlundered)) {
        $resourcesPlundered = ['wood' => 0, 'oil' => 0, 'iron' => 0, 'food' => 0, 'stone' => 0];
    }

    // Get entity names
    $attackerName = getEntityName($conn, $battle['attacker_id'], $battle['attacker_type']);
    $defenderName = getEntityName($conn, $battle['defender_id'], $battle['defender_type']);

    // Get battle report
    $battleReport = $battleManager->getBattleReport($battleId);

    // Determine player's role and outcome
    $playerRole = null;
    $playerWon = false;
    
    if ($battle['attacker_id'] == $playerId && $battle['attacker_type'] == 'player') {
        $playerRole = 'attacker';
        $playerWon = ($battle['battle_result'] === 'attacker_victory');
    } elseif ($battle['defender_id'] == $playerId && $battle['defender_type'] == 'player') {
        $playerRole = 'defender';
        $playerWon = ($battle['battle_result'] === 'defender_victory');
    }

    // Return all data as JSON
    echo json_encode([
        'success' => true,
        'battle' => [
            'id' => $battle['id'],
            'date' => $battle['battle_date'],
            'result' => $battle['battle_result'],
            'attacker_id' => $battle['attacker_id'],
            'attacker_type' => $battle['attacker_type'],
            'defender_id' => $battle['defender_id'],
            'defender_type' => $battle['defender_type'],
            'attacker_name' => $attackerName,
            'defender_name' => $defenderName,
            'player_role' => $playerRole,
            'player_won' => $playerWon
        ],
        'losses' => [
            'attacker' => $attackerLosses,
            'defender' => $defenderLosses
        ],
        'resources_plundered' => $resourcesPlundered,
        'battle_report' => $battleReport
    ]);

} catch (Exception $e) {
    error_log('Battle report AJAX error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Get entity name helper function
 */
function getEntityName($conn, $id, $type) {
    if ($type === 'player') {
        $query = "SELECT username FROM players WHERE id = ?";
    } elseif ($type === 'ai') {
        $query = "SELECT name FROM ai_cities WHERE id = ?";
    } else {
        return "Unknown Entity";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        return $row['username'] ?? $row['name'] ?? "Unknown Entity";
    }
    
    return "Unknown Entity";
}
?>
