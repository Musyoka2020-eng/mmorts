<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Add battle-related CSS
echo '<link rel="stylesheet" href="frontend/design/css/battle-history.css">';
echo '<link rel="stylesheet" href="frontend/design/css/battle-report.css">';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php?page=login&msg=' . urlencode('You must be logged in to access this page.'));
    exit;
}

// Get player ID
$playerId = $_SESSION['user']['id'];

// Get all battles involving this player
$query = "SELECT * FROM battles 
         WHERE (attacker_id = ? AND attacker_type = 'player') 
            OR (defender_id = ? AND defender_type = 'player')
         ORDER BY battle_date DESC 
         LIMIT 20";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $playerId, $playerId);
$stmt->execute();
$result = $stmt->get_result();

$battles = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $battles[] = $row;
    }
}

// Add battle history CSS
echo '<link rel="stylesheet" href="frontend/design/css/battle-history.css">';
?>

<div class="main">
    <section class="content py-3">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3>Battle History</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($battles)): ?>
                                <div class="alert alert-info">
                                    <p>You haven't participated in any battles yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($battles as $battle): ?>
                                    <?php
                                    // Determine if player won or lost
                                    $isAttacker = ($battle['attacker_type'] === 'player' && $battle['attacker_id'] == $playerId);
                                    $isDefender = ($battle['defender_type'] === 'player' && $battle['defender_id'] == $playerId);
                                    
                                    $playerWon = ($isAttacker && $battle['battle_result'] === 'attacker_victory') || 
                                                ($isDefender && $battle['battle_result'] === 'defender_victory');
                                    
                                    // Get attacker/defender details
                                    $attackerName = '';
                                    $defenderName = '';
                                    
                                    if ($battle['attacker_type'] === 'player' && $battle['attacker_id'] == $playerId) {
                                        $attackerName = 'You';
                                    } else if ($battle['attacker_type'] === 'player') {
                                        $query = "SELECT name FROM players WHERE id = ?";
                                        $stmt = $conn->prepare($query);
                                        $stmt->bind_param("i", $battle['attacker_id']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result && $result->num_rows === 1) {
                                            $attackerName = $result->fetch_assoc()['name'];
                                        } else {
                                            $attackerName = "Unknown Player";
                                        }
                                    } else if ($battle['attacker_type'] === 'ai') {
                                        $query = "SELECT name FROM ai_players WHERE id = ?";
                                        $stmt = $conn->prepare($query);
                                        $stmt->bind_param("i", $battle['attacker_id']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result && $result->num_rows === 1) {
                                            $attackerName = $result->fetch_assoc()['name'] . " (AI)";
                                        } else {
                                            $attackerName = "Unknown AI";
                                        }
                                    }
                                    
                                    if ($battle['defender_type'] === 'player' && $battle['defender_id'] == $playerId) {
                                        $defenderName = 'You';
                                    } else if ($battle['defender_type'] === 'player') {
                                        $query = "SELECT name FROM players WHERE id = ?";
                                        $stmt = $conn->prepare($query);
                                        $stmt->bind_param("i", $battle['defender_id']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result && $result->num_rows === 1) {
                                            $defenderName = $result->fetch_assoc()['name'];
                                        } else {
                                            $defenderName = "Unknown Player";
                                        }
                                    } else if ($battle['defender_type'] === 'ai') {
                                        $query = "SELECT name FROM ai_players WHERE id = ?";
                                        $stmt = $conn->prepare($query);
                                        $stmt->bind_param("i", $battle['defender_id']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result && $result->num_rows === 1) {
                                            $defenderName = $result->fetch_assoc()['name'] . " (AI)";
                                        } else {
                                            $defenderName = "Unknown AI";
                                        }
                                    }
                                    // Get units lost from JSON with better error handling                                    $attackerUnitsLost = json_decode($battle['attacker_units_lost'] ?? '{}', true);
                                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($attackerUnitsLost) || $attackerUnitsLost === 0) {
                                        error_log('Error decoding attacker losses JSON in battle history: ' . json_last_error_msg());
                                        $attackerUnitsLost = ['fighters' => 0, 'shooters' => 0, 'vehicles' => 0, 'skmisher' => 0, 'rides' => 0, 'canons' => 0, 'jets' => 0, 'archers' => 0, 'marauders' => 0];
                                    }
                                    
                                    // Ensure all values are numeric
                                    foreach ($attackerUnitsLost as $key => $value) {
                                        $attackerUnitsLost[$key] = intval($value);
                                    }

                                    $defenderUnitsLost = json_decode($battle['defender_units_lost'] ?? '{}', true);
                                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($defenderUnitsLost)) {
                                        error_log('Error decoding defender losses JSON in battle history: ' . json_last_error_msg());
                                        $defenderUnitsLost = ['fighters' => 0, 'shooters' => 0];
                                    }
                                    
                                    // Ensure all values are numeric
                                    foreach ($defenderUnitsLost as $key => $value) {
                                        $defenderUnitsLost[$key] = intval($value);
                                    }

                                    // Get resources plundered
                                    $resourcesPlundered = json_decode($battle['resources_plundered'] ?? '{}', true);
                                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($resourcesPlundered)) {
                                        error_log('Error decoding resources plundered JSON in battle history: ' . json_last_error_msg());
                                        $resourcesPlundered = ['wood' => 0, 'oil' => 0, 'iron' => 0, 'food' => 0, 'stone' => 0];
                                    }
                                    
                                    // Ensure all values are numeric and all required keys exist
                                    $requiredResources = ['wood', 'oil', 'iron', 'food', 'stone'];
                                    foreach ($requiredResources as $resource) {
                                        if (!isset($resourcesPlundered[$resource])) {
                                            $resourcesPlundered[$resource] = 0;
                                        } else {
                                            $resourcesPlundered[$resource] = intval($resourcesPlundered[$resource]);
                                        }
                                    }
                                    
                                    // Calculate strength estimation for graph
                                    $attackerStrength = rand(30, 70); // Placeholder - would be calculated from actual battle data
                                    $defenderStrength = rand(30, 70); // Placeholder
                                    ?>
                                    
                                    <div class="battle-card battle-row">
                                        <div class="battle-header">
                                            <span class="battle-date"><?= date("M d, Y H:i", strtotime($battle['battle_date'])) ?></span>
                                            <span class="<?= $playerWon ? 'battle-result-win' : 'battle-result-loss' ?>">
                                                <?= $playerWon ? 'Victory' : 'Defeat' ?>
                                            </span>
                                        </div>
                                        <div class="battle-content">
                                            <div class="battle-sides">
                                                <div class="battle-side attacker-side">
                                                    <h5>Attacker</h5>
                                                    <div><?= htmlspecialchars($attackerName) ?></div>
                                                    <?php if (!empty($attackerUnitsLost)): ?>
                                                        <div class="battle-forces">
                                                            <?php foreach ($attackerUnitsLost as $unit => $count): ?>
                                                                <div class="unit-icon">
                                                                    <?php 
                                                                    // Display an icon based on unit type
                                                                    switch ($unit) {
                                                                        case 'fighters': echo '<i class="fas fa-user-ninja"></i>'; break;
                                                                        case 'shooters': echo '<i class="fas fa-crosshairs"></i>'; break;
                                                                        case 'vehicles': echo '<i class="fas fa-truck-monster"></i>'; break;
                                                                        case 'skmisher': echo '<i class="fas fa-running"></i>'; break;
                                                                        case 'rides': echo '<i class="fas fa-horse"></i>'; break;
                                                                        case 'canons': echo '<i class="fas fa-bomb"></i>'; break;
                                                                        case 'jets': echo '<i class="fas fa-fighter-jet"></i>'; break;
                                                                        case 'archers': echo '<i class="fas fa-bow-arrow"></i>'; break;
                                                                        case 'marauders': echo '<i class="fas fa-skull"></i>'; break;
                                                                        default: echo '<i class="fas fa-user-shield"></i>';
                                                                    }
                                                                    ?>
                                                                    <span class="unit-count"><?= $count ?></span>
                                                                    <div class="unit-tooltip">
                                                                        <?= ucfirst($unit) ?> lost: <?= $count ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="battle-side defender-side">
                                                    <h5>Defender</h5>
                                                    <div><?= htmlspecialchars($defenderName) ?></div>
                                                    <?php if (!empty($defenderUnitsLost)): ?>
                                                        <div class="battle-forces">
                                                            <?php foreach ($defenderUnitsLost as $unit => $count): ?>
                                                                <div class="unit-icon">
                                                                    <?php 
                                                                    // Display an icon based on unit type
                                                                    switch ($unit) {
                                                                        case 'fighters': echo '<i class="fas fa-user-ninja"></i>'; break;
                                                                        case 'shooters': echo '<i class="fas fa-crosshairs"></i>'; break;
                                                                        case 'vehicles': echo '<i class="fas fa-truck-monster"></i>'; break;
                                                                        case 'skmisher': echo '<i class="fas fa-running"></i>'; break;
                                                                        case 'rides': echo '<i class="fas fa-horse"></i>'; break;
                                                                        case 'canons': echo '<i class="fas fa-bomb"></i>'; break;
                                                                        case 'jets': echo '<i class="fas fa-fighter-jet"></i>'; break;
                                                                        case 'archers': echo '<i class="fas fa-bow-arrow"></i>'; break;
                                                                        case 'marauders': echo '<i class="fas fa-skull"></i>'; break;
                                                                        default: echo '<i class="fas fa-user-shield"></i>';
                                                                    }
                                                                    ?>
                                                                    <span class="unit-count"><?= $count ?></span>
                                                                    <div class="unit-tooltip">
                                                                        <?= ucfirst($unit) ?> lost: <?= $count ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="battle-graph" data-attacker-strength="<?= $attackerStrength ?>" data-defender-strength="<?= $defenderStrength ?>">
                                                <div class="attacker-bar"></div>
                                                <div class="defender-bar"></div>
                                            </div>
                                            
                                            <?php if (!empty($resourcesPlundered)): ?>
                                                <div class="resources-plundered">
                                                    <div><strong>Resources plundered:</strong></div>
                                                    <?php foreach ($resourcesPlundered as $resource => $amount): ?>
                                                        <div class="resource-item">
                                                            <img src="frontend/images/<?= $resource ?>.png" class="resource-icon" alt="<?= $resource ?>">
                                                            <span class="resource-value"><?= $amount ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="text-center mt-3">
                                                <button class="view-report-btn" data-battle-id="<?= $battle['id'] ?>">View Battle Report</button>
                                            </div>
                                            
                                            <div id="report-<?= $battle['id'] ?>" class="battle-report-container d-none">
                                                <div class="battle-report">
                                                    <?= !empty($battle['battle_report']) ? 
                                                        '<div id="formatted-report-' . $battle['id'] . '">' . nl2br(htmlspecialchars($battle['battle_report'])) . '</div>' : 
                                                        'No detailed report available.' ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <a href="index.php?page=world_map" class="btn btn-primary">Return to World Map</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="frontend/design/js/battle-history.js"></script>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>
