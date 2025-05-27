<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Add battle-specific CSS
echo '<link rel="stylesheet" href="frontend/design/css/battle-alerts.css">';
echo '<link rel="stylesheet" href="frontend/design/css/battle-report.css">';
echo '<link rel="stylesheet" href="frontend/design/css/enhanced-battle.css">';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">';

// Make base URL available to JavaScript
// system/config.php is included via system/includes.php which is included via header.php
// So $base_url should be available here if header.php includes system/config.php directly or indirectly.
// Assuming $base_url is set in config.php and accessible here.
if (isset($base_url)) {
    echo "<script>const baseUrl = '" . rtrim($base_url, '/') . "/';</script>";
} else {
    // Fallback or error if base_url isn't set, crucial for AJAX calls
    // For debugging, you might want to set a default or throw an error
    error_log("Warning: \$base_url is not set in battle.php. AJAX calls might fail.");
    // echo "<script>console.error('Base URL not set in PHP, AJAX calls may fail.'); const baseUrl = '/'; /* Fallback */ </script>";
    // A safer fallback might be to try and guess or use a known default if your structure is fixed.
    // For now, we'll rely on it being set. If issues persist, this is an area to check.
    // Let's assume for now it will be set via includes.php -> config.php
}


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
            $playerCityId = $row['id'];

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
                    'skirmishers' => 0,
                    'riders' => 0,
                    'canons' => 0,
                    'jets' => 0,
                    'archers' => 0,
                    'marauders' => 0
                ];
            }
            // Check if battle was requested
            if (isset($_POST['attack'])) {
                try {
                    // Get selected units from form
                    $selectedUnits = isset($_POST['units']) ? $_POST['units'] : [];
                    $strategy = isset($_POST['strategy']) ? $_POST['strategy'] : 'balanced';
                    
                    // Validate selected units
                    if (empty($selectedUnits) || array_sum($selectedUnits) <= 0) {
                        throw new Exception("No units selected for battle!");
                    }
                    
                    // Validate player has enough units
                    foreach ($selectedUnits as $unitType => $count) {
                        if ($count > 0) {
                            $availableCount = isset($playerArmies[$unitType]) ? $playerArmies[$unitType] : 0;
                            if ($count > $availableCount) {
                                throw new Exception("Not enough {$unitType} available. You only have {$availableCount} but tried to use {$count}.");
                            }
                        }
                    }
                    
                    // Create a custom battle manager method that accepts selected units
                    $battleResult = $battleManager->initiateBattleWithSelectedUnits(
                        $playerCityId, 
                        'player', 
                        $targetId, 
                        $targetType, 
                        $selectedUnits, 
                        $strategy
                    );
                    
                    // Display battle result
                    $battleJustHappened = true;
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
$recentBattles = $battleManager->getRecentBattles($playerId, 8);
?>

<div class="main battle-page">    <section class="content py-3">
        <div class="container-fluid battle-content">
            <?php if (isset($error)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                        </div>
                    </div>
                </div>
            <?php elseif (isset($battleJustHappened) && $battleJustHappened): ?>
                <!-- Battle Result Display -->
                <div class="row">
                    <div class="col-12">
                        <div class="battle-result-card">
                            <div class="battle-result-header">
                                <h2>
                                    <i class="fas fa-crossed-swords me-2"></i>
                                    Battle Complete
                                </h2>
                                <div class="battle-outcome">
                                    <?php if ($battleResult['result'] === 'attacker_victory'): ?>
                                        <span class="outcome-victory">
                                            <i class="fas fa-trophy me-2"></i>VICTORY!
                                        </span>
                                    <?php elseif ($battleResult['result'] === 'defender_victory'): ?>
                                        <span class="outcome-defeat">
                                            <i class="fas fa-skull me-2"></i>DEFEAT!
                                        </span>
                                    <?php else: ?>
                                        <span class="outcome-draw">
                                            <i class="fas fa-handshake me-2"></i>DRAW!
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="battle-summary">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="casualties-card attacker">
                                            <h4><i class="fas fa-user-injured me-2"></i>Your Casualties</h4>
                                            <div class="casualties-list">
                                                <?php foreach ($battleResult['attacker_losses'] as $unit => $count): ?>
                                                    <?php if ($count > 0): ?>
                                                        <div class="casualty-item">
                                                            <i class="fas fa-users"></i>
                                                            <span class="unit-name"><?= ucfirst($unit) ?></span>
                                                            <span class="casualty-count">-<?= $count ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="casualties-card defender">
                                            <h4><i class="fas fa-user-injured me-2"></i>Enemy Casualties</h4>
                                            <div class="casualties-list">
                                                <?php foreach ($battleResult['defender_losses'] as $unit => $count): ?>
                                                    <?php if ($count > 0): ?>
                                                        <div class="casualty-item">
                                                            <i class="fas fa-users"></i>
                                                            <span class="unit-name"><?= ucfirst($unit) ?></span>
                                                            <span class="casualty-count">-<?= $count ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($battleResult['result'] === 'attacker_victory' && !empty($battleResult['resources_plundered'])): ?>
                                    <div class="plunder-section">
                                        <h4><i class="fas fa-coins me-2"></i>Resources Plundered</h4>
                                        <div class="plunder-grid">
                                            <?php foreach ($battleResult['resources_plundered'] as $resource => $amount): ?>
                                                <?php if ($amount > 0): ?>
                                                    <div class="plunder-item">
                                                        <img src="frontend/images/<?= $resource ?>.png" alt="<?= $resource ?>" class="resource-icon">
                                                        <span class="resource-name"><?= ucfirst($resource) ?></span>
                                                        <span class="resource-amount">+<?= $amount ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="battle-actions">
                                <a href="index.php?page=world_map" class="btn btn-primary">
                                    <i class="fas fa-map me-2"></i>Return to Map
                                </a>
                                <a href="index.php?page=battle_report&id=<?= $battleResult['battle_id'] ?>" class="btn btn-secondary">
                                    <i class="fas fa-file-alt me-2"></i>View Detailed Report
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif (isset($targetId) && isset($targetType)): ?>
                <!-- Enhanced Battle Preparation Interface -->
                <div class="battle-preparation-interface">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="battle-header">
                                <h2><i class="fas fa-sword me-3"></i>Prepare for Battle</h2>
                                <div class="target-info">
                                    <span class="target-location">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        Target: [<?= $targetX ?>, <?= $targetY ?>]
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Army Selection and Strategy -->                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Unit Selection Panel -->
                            <div class="unit-selection-panel">
                                <div class="panel-header">
                                    <h3><i class="fas fa-users me-2"></i>Select Your Forces</h3>
                                    <div class="selection-summary">
                                        <span class="total-selected">Total: <span id="totalSelected">0</span> units</span>
                                        <span class="combat-power">Power: <span id="totalPower">0</span></span>
                                        <button type="button" class="btn btn-outline-warning btn-sm" id="quickSelect">
                                            <i class="fas fa-magic me-1"></i>Auto Select
                                        </button>
                                    </div>
                                </div>                                  <form id="battleForm" method="post">
                                    <!-- Hidden inputs for additional data -->
                                    <input type="hidden" name="target_id" value="<?= isset($targetId) ? htmlspecialchars($targetId) : '' ?>">
                                    <input type="hidden" name="target_type" value="<?= isset($targetType) ? htmlspecialchars($targetType) : '' ?>">
                                    <input type="hidden" name="strategy" id="strategyInput" value="balanced">
                                    
                                    <div class="unit-selection-header">
                                        <button type="button" id="autoSelectBtn" class="btn btn-info btn-sm">
                                            <i class="fas fa-magic me-2"></i>Auto-Select Based on Strategy
                                        </button>
                                    </div>
                                    <div class="units-grid">
                                        <?php 
                                        $unitStats = [
                                            'fighters' => ['icon' => 'fa-user-shield', 'attack' => 15, 'defense' => 12, 'name' => 'Fighters'],
                                            'shooters' => ['icon' => 'fa-crosshairs', 'attack' => 20, 'defense' => 8, 'name' => 'Shooters'],
                                            'vehicles' => ['icon' => 'fa-truck-monster', 'attack' => 25, 'defense' => 20, 'name' => 'Vehicles'],
                                            'riders' => ['icon' => 'fa-motorcycle', 'attack' => 18, 'defense' => 10, 'name' => 'Riders'],
                                            'canons' => ['icon' => 'fa-bomb', 'attack' => 35, 'defense' => 5, 'name' => 'Cannons'],
                                            'skirmishers' => ['icon' => 'fa-running', 'attack' => 12, 'defense' => 15, 'name' => 'Skirmishers'],
                                            'jets' => ['icon' => 'fa-fighter-jet', 'attack' => 40, 'defense' => 8, 'name' => 'Jets'],
                                            'archers' => ['icon' => 'fa-bullseye', 'attack' => 16, 'defense' => 9, 'name' => 'Archers'],
                                            'marauders' => ['icon' => 'fa-mask', 'attack' => 22, 'defense' => 14, 'name' => 'Marauders']
                                        ];
                                        
                                        foreach ($unitStats as $unit => $stats):
                                            $available = isset($playerArmies[$unit]) ? $playerArmies[$unit] : 0;
                                            if ($available > 0):
                                        ?>
                                            <div class="unit-card" data-unit="<?= $unit ?>" data-attack="<?= $stats['attack'] ?>" data-defense="<?= $stats['defense'] ?>">
                                                <div class="unit-header">
                                                    <div class="unit-icon">
                                                        <i class="fas <?= $stats['icon'] ?>"></i>
                                                    </div>
                                                    <div class="unit-info">
                                                        <h4><?= $stats['name'] ?></h4>
                                                        <div class="unit-stats">
                                                            <span class="stat-attack">
                                                                <i class="fas fa-sword"></i> <?= $stats['attack'] ?>
                                                            </span>
                                                            <span class="stat-defense">
                                                                <i class="fas fa-shield"></i> <?= $stats['defense'] ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="unit-selection">
                                                    <div class="available-count">
                                                        Available: <span class="count"><?= $available ?></span>
                                                    </div>
                                                    <div class="selection-controls">
                                                        <button type="button" class="btn-select-all" data-max="<?= $available ?>">All</button>
                                                        <div class="input-group">
                                                            <button type="button" class="btn-decrease">-</button>
                                                            <input type="number" name="units[<?= $unit ?>]" min="0" max="<?= $available ?>" value="0" class="unit-input">
                                                            <button type="button" class="btn-increase" data-max="<?= $available ?>">+</button>
                                                        </div>
                                                        <button type="button" class="btn-select-none">None</button>
                                                    </div>
                                                </div>
                                            </div>                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                    
                                    <!-- Battle Actions inside form -->
                                    <div class="battle-actions mt-4">
                                        <button type="submit" id="startBattle" class="btn btn-danger btn-lg" disabled>
                                            <i class="fas fa-play me-2"></i>
                                            Start Battle
                                        </button>
                                        <a href="index.php?page=world_map" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>
                                            Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <!-- Strategy and Intel Panel -->
                            <div class="strategy-panel">
                                <div class="strategy-selection">
                                    <h4><i class="fas fa-chess me-2"></i>Battle Strategy</h4>
                                    <div class="strategy-options">
                                        <label class="strategy-option" for="balanced">
                                            <input type="radio" name="strategy" value="balanced" id="balanced" checked>
                                            <div class="strategy-card">
                                                <i class="fas fa-balance-scale"></i>
                                                <h5>Balanced</h5>
                                                <p>Equal focus on offense and defense</p>
                                                <div class="strategy-bonus">+10% overall effectiveness</div>
                                            </div>
                                        </label>
                                        
                                        <label class="strategy-option" for="aggressive">
                                            <input type="radio" name="strategy" value="aggressive" id="aggressive">
                                            <div class="strategy-card">
                                                <i class="fas fa-fire"></i>
                                                <h5>Aggressive</h5>
                                                <p>Maximum attack power, risky defense</p>
                                                <div class="strategy-bonus">+25% attack, -15% defense</div>
                                            </div>
                                        </label>
                                        
                                        <label class="strategy-option" for="defensive">
                                            <input type="radio" name="strategy" value="defensive" id="defensive">
                                            <div class="strategy-card">
                                                <i class="fas fa-shield-alt"></i>
                                                <h5>Defensive</h5>
                                                <p>Minimize losses, steady advance</p>
                                                <div class="strategy-bonus">+20% defense, -10% attack</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Recommended Units -->
                                <div class="recommendations">
                                    <h4><i class="fas fa-lightbulb me-2"></i>Recommended Units</h4>
                                    <div id="unitRecommendations" class="recommendations-list">
                                        <p class="text-muted">Select a strategy to see recommendations</p>
                                    </div>
                                </div>

                                <!-- Enemy Intel -->
                                <div class="enemy-intel">
                                    <h4><i class="fas fa-search me-2"></i>Enemy Intelligence</h4>
                                    <div class="intel-info">
                                        <?php if (isset($targetArmies)): ?>
                                            <div class="intel-item">
                                                <span class="intel-label">Estimated Strength:</span>
                                                <div class="strength-bar">
                                                    <?php 
                                                    $totalEnemyPower = 0;
                                                    foreach ($targetArmies as $unit => $count) {
                                                        if (isset($unitStats[$unit])) {
                                                            $totalEnemyPower += $count * ($unitStats[$unit]['attack'] + $unitStats[$unit]['defense']) / 2;
                                                        }
                                                    }
                                                    $strengthLevel = min(100, ($totalEnemyPower / 500) * 100);
                                                    ?>
                                                    <div class="strength-fill" style="width: <?= $strengthLevel ?>%"></div>
                                                    <span class="strength-text"><?= round($strengthLevel) ?>%</span>
                                                </div>
                                            </div>
                                            <div class="intel-item">
                                                <span class="intel-label">Detected Units:</span>
                                                <div class="detected-units">
                                                    <?php 
                                                    $detectedUnits = ['fighters', 'shooters', 'vehicles'];
                                                    foreach ($detectedUnits as $unit):
                                                        if (isset($targetArmies[$unit]) && $targetArmies[$unit] > 0):
                                                            $approxCount = round($targetArmies[$unit] / 10) * 10;
                                                    ?>
                                                        <span class="detected-unit">
                                                            <i class="fas <?= $unitStats[$unit]['icon'] ?>"></i>
                                                            ~<?= $approxCount ?>
                                                        </span>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-warning">
                                                <i class="fas fa-eye-slash me-2"></i>
                                                Enemy strength unknown
                                            </p>                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Battles Section -->
                    <?php if (!isset($battleJustHappened) || !$battleJustHappened): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="recent-battles-panel">
                                <div class="panel-header">
                                    <h4><i class="fas fa-history me-2"></i>Recent Battles</h4>
                                    <div class="panel-actions">
                                        <a href="index.php?page=training" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-dumbbell me-1"></i>Train Units
                                        </a>
                                        <a href="index.php?page=battle_history" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-list me-1"></i>Battle History
                                        </a>
                                    </div>
                                </div>
                                
                                <?php if (empty($recentBattles)): ?>
                                    <div class="no-battles">
                                        <i class="fas fa-peace fa-2x mb-3"></i>
                                        <h5>No Recent Battles</h5>
                                        <p class="text-muted">Your battle history will appear here once you engage in combat.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="recent-battles-grid">
                                        <?php foreach ($recentBattles as $battle): ?>
                                            <div class="battle-card">
                                                <div class="battle-card-header">
                                                    <?php 
                                                    $isAttacker = ($battle['attacker_id'] == $playerId && $battle['attacker_type'] == 'player');
                                                    $isVictory = false;
                                                    
                                                    if ($battle['battle_result'] === 'attacker_victory' && $isAttacker) {
                                                        $isVictory = true;
                                                    } elseif ($battle['battle_result'] === 'defender_victory' && !$isAttacker) {
                                                        $isVictory = true;
                                                    }
                                                    ?>
                                                    <div class="battle-result-icon">
                                                        <i class="fas <?= $isVictory ? 'fa-trophy text-success' : 'fa-skull text-danger' ?>"></i>
                                                    </div>
                                                    <div class="battle-type-badge <?= $isAttacker ? 'attack' : 'defense' ?>">
                                                        <?= $isAttacker ? 'Attack' : 'Defense' ?>
                                                    </div>
                                                </div>
                                                <div class="battle-card-body">
                                                    <div class="battle-date">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        <?= date('M d, Y H:i', strtotime($battle['battle_date'])) ?>
                                                    </div>
                                                    <div class="battle-result">
                                                        <strong><?= $isVictory ? 'Victory' : 'Defeat' ?></strong>
                                                    </div>
                                                </div>
                                                <div class="battle-card-footer">
                                                    <a href="index.php?page=battle_report&id=<?= $battle['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-file-alt me-1"></i>View Report
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- No Target Selected -->
                <div class="row">
                    <div class="col-12">
                        <div class="no-target-card">
                            <div class="no-target-content">
                                <i class="fas fa-crosshairs fa-3x mb-3"></i>
                                <h3>No Target Selected</h3>
                                <p>Select an AI city on the world map to begin planning your attack.</p>
                                <a href="index.php?page=world_map" class="btn btn-primary btn-lg">
                                    <i class="fas fa-map me-2"></i>
                                    Open World Map
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Enhanced Battle Animation Modal -->
<div class="modal fade" id="battleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content battle-modal">            <div class="modal-header battle-modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-crossed-swords me-2"></i>
                    <span id="battleTitle">Battle in Progress</span>
                </h4>
                <div class="battle-round-counter">
                    Round <span id="currentRound">1</span> of <span id="totalRounds">?</span>
                </div>
                <button type="button" class="btn-close btn-close-white" onclick="battleSystem.closeBattle()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body battle-modal-body">
                <div class="battle-animation-container">
                    <!-- Battlefield Environment -->
                    <div class="battlefield-environment">
                        <div class="battlefield-bg"></div>
                        <div class="weather-effects" id="weatherEffects"></div>
                    </div>
                    
                    <!-- Battle Stats Bar -->
                    <div class="battle-stats-bar">
                        <div class="army-health-bar attacker-health">
                            <div class="health-label">Your Army</div>
                            <div class="health-bar">
                                <div class="health-fill" id="attackerHealth" style="width: 100%"></div>
                                <span class="health-text" id="attackerHealthText">100%</span>
                            </div>
                        </div>
                        
                        <div class="battle-timer">
                            <i class="fas fa-clock"></i>
                            <span id="battleTimer">00:00</span>
                        </div>
                        
                        <div class="army-health-bar defender-health">
                            <div class="health-label">Enemy Army</div>
                            <div class="health-bar">
                                <div class="health-fill" id="defenderHealth" style="width: 100%"></div>
                                <span class="health-text" id="defenderHealthText">100%</span>
                            </div>
                        </div>
                    </div>

                    <div class="battlefield">
                        <div class="army-side attacker-side">
                            <div class="army-header">
                                <h5><i class="fas fa-user me-2"></i>Your Forces</h5>
                                <div class="army-morale">
                                    <span class="morale-label">Morale:</span>
                                    <div class="morale-bar">
                                        <div class="morale-fill" id="attackerMorale" style="width: 100%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="army-units" id="attackerUnits"></div>
                        </div>
                        
                        <div class="battle-center">
                            <div class="battle-log-container">
                                <h6><i class="fas fa-scroll me-2"></i>Battle Log</h6>
                                <div class="battle-log" id="battleLog">
                                    <div class="log-entry system">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Armies are positioning for battle...</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Combat Effects -->
                            <div class="combat-effects" id="combatEffects"></div>
                        </div>
                        
                        <div class="army-side defender-side">
                            <div class="army-header">
                                <h5><i class="fas fa-user-shield me-2"></i>Enemy Forces</h5>
                                <div class="army-morale">
                                    <span class="morale-label">Morale:</span>
                                    <div class="morale-bar">
                                        <div class="morale-fill" id="defenderMorale" style="width: 100%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="army-units" id="defenderUnits"></div>
                        </div>
                    </div>
                    
                    <div class="battle-progress-section">
                        <div class="battle-controls">
                            <button class="btn btn-sm btn-outline-light" id="pauseBattle" disabled>
                                <i class="fas fa-pause"></i> Pause
                            </button>
                            <button class="btn btn-sm btn-outline-light" id="speedUp">
                                <i class="fas fa-forward"></i> Speed Up
                            </button>
                            <button class="btn btn-sm btn-outline-danger" id="skipBattle">
                                <i class="fas fa-fast-forward"></i> Skip to End
                            </button>
                        </div>
                        
                        <div class="progress battle-progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 id="battleProgress" style="width: 0%"></div>
                        </div>
                        
                        <div class="battle-status" id="battleStatus">Preparing for battle...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer battle-modal-footer" style="display: none;">
                <button type="button" class="btn btn-primary" id="viewDetailedReport">
                    <i class="fas fa-file-alt me-2"></i>View Detailed Report
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
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

<!-- Pass real AI army data to JavaScript -->
<script>
// Make real AI army data available to JavaScript
window.targetArmies = <?= isset($targetArmies) ? json_encode($targetArmies) : 'null' ?>;
window.targetId = <?= isset($targetId) ? json_encode($targetId) : 'null' ?>;
window.targetType = <?= isset($targetType) ? json_encode($targetType) : 'null' ?>;
</script>

<?php if ($battleJustHappened && $battleResult && !$error): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof GameAlerts !== 'undefined') {        
        <?php 
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

<script src="frontend/design/js/enhanced-battle.js"></script>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>
