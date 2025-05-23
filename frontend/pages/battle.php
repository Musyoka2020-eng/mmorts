<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Add battle-specific CSS
echo '<link rel="stylesheet" href="frontend/design/css/battle-alerts.css">';
echo '<link rel="stylesheet" href="frontend/design/css/battle-report.css">';

// Include battle manager
require_once __DIR__ . '/../../backend/combat/battle_manager.php';

// Error handling variables
$error = null;
$battleJustHappened = false;
$battleResult = null;

try {
    // Initialize battle manager
    $battleManager = new BattleManager($conn);
    
    // Check if user is logged in
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        header('Location: index.php?page=login&msg=' . urlencode('You must be logged in to access this page.'));
        exit;
    }
} catch (Exception $e) {
    $error = "Error initializing battle system: " . $e->getMessage();
}

// Get player ID
$playerId = $_SESSION['user']['id'];

// Check if there's a target
if (isset($_GET['target_x']) && isset($_GET['target_y'])) {
    $targetX = $_GET['target_x'];
    $targetY = $_GET['target_y'];
    
    // Check if there's an AI city at the target location
    $query = "SELECT occupier_id, occupier_type FROM world_map 
             WHERE location_x = ? AND location_y = ? AND occupied = 1 AND occupier_type = 'ai'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $targetX, $targetY);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $targetId = $row['occupier_id'];
        $targetType = $row['occupier_type'];
        
        // Get player's city ID
        $query = "SELECT id FROM cities WHERE player_id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $playerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $playerCityId = $row['id'];              // Check if battle was requested
            if (isset($_POST['attack'])) {
                try {
                    // Initiate battle
                    $battleResult = $battleManager->initiateBattle($playerCityId, 'player', $targetId, $targetType);
                    
                    // Display battle result                    $battleJustHappened = true;
                } catch (Exception $e) {
                    // Enhanced error logging
                    $errorMessage = $e->getMessage();
                    error_log("Battle error in battle.php: " . $errorMessage);
                    error_log("Stack trace: " . $e->getTraceAsString());
                    
                    // Show a user-friendly message but include technical details if it's a JSON error
                    if (strpos($errorMessage, 'JSON') !== false) {
                        $error = "Battle system error: There was a problem with the battle data. Technical details: " . $errorMessage;
                    } else {
                        $error = "Battle error: " . $errorMessage;
                    }
                }
            }
        } else {
            $error = "You don't have a city to attack from!";
        }
    } else {
        $error = "No AI city found at the specified location.";
    }
}

// Get player's armies
$query = "SELECT * FROM player_armies WHERE player_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $playerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $playerArmies = $result->fetch_assoc();
} else {
    // Player has no armies yet, create default armies
    $query = "INSERT INTO player_armies (player_id, fighters, shooters) VALUES (?, 20, 10)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    
    $playerArmies = [
        'fighters' => 20,
        'shooters' => 10,
        'vehicles' => 0,
        'skmisher' => 0,
        'rides' => 0,
        'canons' => 0,
        'jets' => 0,
        'archers' => 0,
        'marauders' => 0
    ];
}

// Get target armies if available
if (isset($targetId) && isset($targetType) && $targetType === 'ai') {
    $query = "SELECT * FROM ai_armies WHERE ai_city_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $targetId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $targetArmies = $result->fetch_assoc();
    }
}

// Get recent battles
$recentBattles = $battleManager->getRecentBattles($playerId, 5);
?>

<div class="main">
    <section class="content py-3">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3>Battle</h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                            <?php elseif (isset($battleJustHappened) && $battleJustHappened): ?>
                                <div class="battle-result">
                                    <h4>Battle Result</h4>
                                    <p>
                                        <?php if ($battleResult['result'] === 'attacker_victory'): ?>
                                            <span class="text-success">Victory!</span> Your forces have defeated the enemy.
                                        <?php elseif ($battleResult['result'] === 'defender_victory'): ?>
                                            <span class="text-danger">Defeat!</span> Your forces were repelled by the enemy.
                                        <?php else: ?>
                                            <span class="text-warning">Draw!</span> Neither side gained a decisive advantage.
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5>Your Losses</h5>
                                            <ul class="list-group">
                                                <?php foreach ($battleResult['attacker_losses'] as $unit => $count): ?>
                                                    <?php if ($count > 0): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <?= ucfirst($unit) ?>
                                                            <span class="badge bg-danger rounded-pill"><?= $count ?></span>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h5>Enemy Losses</h5>
                                            <ul class="list-group">
                                                <?php foreach ($battleResult['defender_losses'] as $unit => $count): ?>
                                                    <?php if ($count > 0): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <?= ucfirst($unit) ?>
                                                            <span class="badge bg-danger rounded-pill"><?= $count ?></span>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <?php if ($battleResult['result'] === 'attacker_victory' && !empty($battleResult['resources_plundered'])): ?>
                                        <h5 class="mt-4">Resources Plundered</h5>
                                        <ul class="list-group">
                                            <?php foreach ($battleResult['resources_plundered'] as $resource => $amount): ?>
                                                <?php if ($amount > 0): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?= ucfirst($resource) ?>
                                                        <span class="badge bg-success rounded-pill"><?= $amount ?></span>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    
                                    <div class="mt-4">
                                        <a href="index.php?page=world_map" class="btn btn-primary">Return to Map</a>
                                    </div>
                                </div>
                            <?php elseif (isset($targetId) && isset($targetType)): ?>
                                <div class="battle-preparation">
                                    <h4>Prepare for Battle</h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5>Your Forces</h5>
                                            <ul class="list-group">
                                                <?php foreach ($playerArmies as $unit => $count): ?>
                                                    <?php if ($unit !== 'id' && $unit !== 'player_id' && $unit !== 'updated_at'): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <?= ucfirst($unit) ?>
                                                            <span class="badge bg-primary rounded-pill"><?= $count ?></span>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h5>Enemy Forces</h5>
                                            <p class="text-warning">Enemy force strength is estimated.</p>
                                            <?php if (isset($targetArmies)): ?>
                                                <ul class="list-group">
                                                    <?php 
                                                    // Only show some units to add mystery
                                                    $shownUnits = ['fighters', 'shooters', 'vehicles'];
                                                    foreach ($shownUnits as $unit): 
                                                        // Show approximate values, not exact
                                                        $approxCount = round($targetArmies[$unit] / 10) * 10;
                                                        $variation = rand(-2, 2) * 10;
                                                        $displayCount = max(0, $approxCount + $variation);
                                                    ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <?= ucfirst($unit) ?>
                                                            <span class="badge bg-danger rounded-pill">~<?= $displayCount ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                    <li class="list-group-item text-center">
                                                        <em>Other units unknown</em>
                                                    </li>
                                                </ul>
                                            <?php else: ?>
                                                <p>Intelligence gathering failed. Enemy strength unknown.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <form method="post" class="mt-4">
                                        <div class="mb-3">
                                            <label for="battleStrategy" class="form-label">Battle Strategy</label>
                                            <select class="form-select" id="battleStrategy" name="strategy">
                                                <option value="balanced">Balanced Attack</option>
                                                <option value="aggressive">Aggressive Attack</option>
                                                <option value="defensive">Defensive Attack</option>
                                            </select>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="attack" class="btn btn-danger">Attack!</button>
                                            <a href="index.php?page=world_map" class="btn btn-secondary">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            <?php else: ?>
                                <p>No target selected. Please select an AI city on the world map to attack.</p>
                                <a href="index.php?page=world_map" class="btn btn-primary">Return to Map</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>Recent Battles</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentBattles)): ?>
                                <p>No recent battles.</p>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($recentBattles as $battle): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1">
                                                    <?php 
                                                    if ($battle['attacker_id'] == $playerId && $battle['attacker_type'] == 'player') {
                                                        echo "You attacked";
                                                    } else {
                                                        echo "You defended";
                                                    }
                                                    ?>
                                                </h5>
                                                <small><?= date('M d, H:i', strtotime($battle['battle_date'])) ?></small>
                                            </div>
                                            <p class="mb-1">
                                                Result: 
                                                <?php 
                                                if ($battle['battle_result'] === 'attacker_victory') {
                                                    if ($battle['attacker_id'] == $playerId && $battle['attacker_type'] == 'player') {
                                                        echo '<span class="text-success">Victory</span>';
                                                    } else {
                                                        echo '<span class="text-danger">Defeat</span>';
                                                    }
                                                } elseif ($battle['battle_result'] === 'defender_victory') {
                                                    if ($battle['defender_id'] == $playerId && $battle['defender_type'] == 'player') {
                                                        echo '<span class="text-success">Victory</span>';
                                                    } else {
                                                        echo '<span class="text-danger">Defeat</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-warning">Draw</span>';
                                                }
                                                ?>
                                            </p>
                                            <a href="index.php?page=battle_report&id=<?= $battle['id'] ?>" class="btn btn-sm btn-outline-primary">View Report</a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>Training</h3>
                        </div>
                        <div class="card-body">
                            <p>Train more units for your army:</p>
                            <a href="index.php?page=training" class="btn btn-primary">Train Units</a>
                        </div>
                    </div>
                </div>
            </div>        </div>
    </section>
</div>

<?php if ($error): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof GameAlerts !== 'undefined') {
        GameAlerts.error('Battle Error', '<?= addslashes($error) ?>');
    } else {
        alert('<?= addslashes($error) ?>');
    }
});
</script>
<?php endif; ?>

<?php if ($battleJustHappened && $battleResult && !$error): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof GameAlerts !== 'undefined') {        <?php 
            // Ensure losses and resources are properly formatted for JSON
            $attackerLosses = $battleResult['attacker_losses'];
            if (!is_array($attackerLosses)) {
                $attackerLosses = ['fighters' => 0, 'shooters' => 0];
            }
            
            $resourcesPlundered = isset($battleResult['resources_plundered']) ? $battleResult['resources_plundered'] : [];
            if (!is_array($resourcesPlundered)) {
                $resourcesPlundered = ['wood' => 0, 'oil' => 0, 'iron' => 0, 'food' => 0, 'stone' => 0];
            }
        ?>
        <?php if ($battleResult['result'] === 'attacker_victory'): ?>
        GameAlerts.battleResult('victory', {
            losses: <?= json_encode($attackerLosses) ?>,
            plunder: <?= json_encode($resourcesPlundered) ?>
        });
        <?php elseif ($battleResult['result'] === 'defender_victory'): ?>
        GameAlerts.battleResult('defeat', {
            losses: <?= json_encode($attackerLosses) ?>
        });
        <?php else: ?>
        GameAlerts.battleResult('draw', {
            losses: <?= json_encode($attackerLosses) ?>
        });
        <?php endif; ?>
    }
});
</script>
<?php endif; ?>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>
