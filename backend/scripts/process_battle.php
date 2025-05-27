<?php
header('Content-Type: application/json');

// Include necessary files
require_once __DIR__ . '/../../system/includes.php';
require_once __DIR__ . '/../combat/battle_manager.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $playerId = $_SESSION['user']['id'];
    
    // Validate required parameters
    if (!isset($_POST['target_id']) || !isset($_POST['target_type']) || !isset($_POST['units'])) {
        throw new Exception('Missing required battle parameters');
    }
    
    $targetId = intval($_POST['target_id']);
    $targetType = $_POST['target_type'];
    $selectedUnits = $_POST['units'];
    $strategy = isset($_POST['strategy']) ? $_POST['strategy'] : 'balanced';
    
    // Validate selected units
    if (empty($selectedUnits) || array_sum($selectedUnits) <= 0) {
        throw new Exception('No units selected for battle!');
    }
    
    // Get player's city ID
    $query = "SELECT id FROM cities WHERE player_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        throw new Exception("You don't have a city to attack from!");
    }
    
    $row = $result->fetch_assoc();
    $playerCityId = $row['id'];
    
    // Initialize battle manager and process battle
    $battleManager = new BattleManager($conn);
    $battleResult = $battleManager->initiateBattleWithSelectedUnits(
        $playerCityId,
        'player',
        $targetId,
        $targetType,
        $selectedUnits,
        $strategy
    );
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'result' => $battleResult['result'],
        'battle_id' => $battleResult['battle_id'],
        'attacker_losses' => $battleResult['attacker_losses'],
        'defender_losses' => $battleResult['defender_losses'],
        'resources_plundered' => isset($battleResult['resources_plundered']) ? $battleResult['resources_plundered'] : [],
        'battle_log' => isset($battleResult['battle_log']) ? $battleResult['battle_log'] : [], // Add battle_log
        'attacker_morale_final' => isset($battleResult['attacker_morale_final']) ? $battleResult['attacker_morale_final'] : 100, // Add final morale
        'defender_morale_final' => isset($battleResult['defender_morale_final']) ? $battleResult['defender_morale_final'] : 100, // Add final morale
        'message' => 'Battle completed successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Battle processing error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>