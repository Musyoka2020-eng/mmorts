<?php
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Check if user is logged in
$user_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$username = $user_logged_in ? $_SESSION['user']['uname'] : "";
$playerId = $user_logged_in ? $_SESSION['user']['id'] : 0;

// Check game initialization status
$isInitialized = false;
$query = "SHOW COLUMNS FROM configuration LIKE 'game_initialized'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $query = "SELECT game_initialized FROM configuration WHERE id = 1";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $isInitialized = $row['game_initialized'] == 1;
    }
}

// Get player city and resources if logged in
$playerCity = null;
$cityResources = null;
$productions = null;
$playerArmies = null;
$recentBattles = [];

if ($user_logged_in) {
    // Get player's city
    $query = "SELECT * FROM cities WHERE player_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows === 1) {
        $playerCity = $result->fetch_assoc();
        
        // Get city resources
        if (isset($playerCity['resources_id']) && $playerCity['resources_id']) {
            $query = "SELECT * FROM resources WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $playerCity['resources_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows === 1) {
                $cityResources = $result->fetch_assoc();
            }
        }
        
        // Get productions
        if (isset($playerCity['productions_id']) && $playerCity['productions_id']) {
            $query = "SELECT * FROM productions WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $playerCity['productions_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows === 1) {
                $productions = $result->fetch_assoc();
            }
        }
        
        // Get player's armies
        $query = "SELECT * FROM player_armies WHERE player_id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $playerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $playerArmies = $result->fetch_assoc();
        }
        
        // Get recent battles
        $query = "SELECT * FROM battles 
                 WHERE (attacker_id = ? AND attacker_type = 'player') 
                    OR (defender_id = ? AND defender_type = 'player')
                 ORDER BY battle_date DESC 
                 LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $playerId, $playerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $recentBattles[] = $row;
            }
        }
    }
}

// Get AI player info if the game is initialized
$aiPlayers = [];
if ($isInitialized) {
    $query = "SELECT ap.id, ap.name, ap.personality_type, ap.difficulty_level, 
                    COUNT(ac.id) as city_count 
            FROM ai_players ap
            LEFT JOIN ai_cities ac ON ap.id = ac.ai_player_id
            WHERE ap.active = 1
            GROUP BY ap.id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $aiPlayers[] = $row;
        }
    }
}
?>

<div class="main">
    <div class="content-background">
        <div class="container py-4">
            <?php if (!$isInitialized && $user_logged_in && isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin'] == 1): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h3>Initialize Game World</h3>
                    </div>
                    <div class="card-body">
                        <p>The game world has not been initialized yet. As an admin, you can set up the game world.</p>
                        <a href="index.php?page=initialize_world" class="btn btn-primary">Initialize World</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($user_logged_in): ?>
                <div class="dashboard-container">
                    <?php if ($playerCity): ?>
                        <!-- Left Column - Resources and Quick Actions -->
                        <div class="left-column">
                            <!-- Resource Cards -->
                            <div class="resource-card">
                                <div class="resource-header">
                                    <i class="fas fa-coins mr-2"></i> Resources
                                </div>                                <div class="resource-content">
                                    <?php if ($cityResources): ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1 resource-row gold-resource">
                                                <span><img src="frontend/images/gold.png" width="20" height="20" alt="Gold"> Gold</span>
                                                <span class="resource-value" id="gold-value"><?= number_format($cityResources['gold']) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1 resource-row wood-resource">
                                                <span><img src="frontend/images/wood.png" width="20" height="20" alt="Wood"> Wood</span>
                                                <span class="resource-value" id="wood-value"><?= number_format($cityResources['wood']) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1 resource-row stone-resource">
                                                <span><img src="frontend/images/stone.png" width="20" height="20" alt="Stone"> Stone</span>
                                                <span class="resource-value" id="stone-value"><?= number_format($cityResources['stone']) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between resource-row food-resource">
                                                <span><img src="frontend/images/food.png" width="20" height="20" alt="Food"> Food</span>
                                                <span class="resource-value" id="food-value"><?= number_format($cityResources['food']) ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p>No resources available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Production Rates -->
                            <div class="resource-card">
                                <div class="resource-header">
                                    <i class="fas fa-industry mr-2"></i> Production
                                </div>                                <div class="resource-content">
                                    <?php if ($productions): ?>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between mb-1 production-row gold-production">
                                                <span><img src="frontend/images/gold.png" width="20" height="20" alt="Gold"> Gold</span>
                                                <span class="production-value">+<?= $productions['gold_production'] ?>/min</span>
                                            </div>
                                            <div class="progress-bar-container">
                                                <div class="production-bar" style="background: linear-gradient(90deg, #FFD700 0%, #DAA520 100%);"></div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between mb-1 production-row wood-production">
                                                <span><img src="frontend/images/wood.png" width="20" height="20" alt="Wood"> Wood</span>
                                                <span class="production-value">+<?= $productions['wood_production'] ?>/min</span>
                                            </div>
                                            <div class="progress-bar-container">
                                                <div class="production-bar" style="background: linear-gradient(90deg, #8B4513 0%, #A0522D 100%);"></div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between mb-1 production-row stone-production">
                                                <span><img src="frontend/images/stone.png" width="20" height="20" alt="Stone"> Stone</span>
                                                <span class="production-value">+<?= $productions['stone_production'] ?>/min</span>
                                            </div>
                                            <div class="progress-bar-container">
                                                <div class="production-bar" style="background: linear-gradient(90deg, #808080 0%, #A9A9A9 100%);"></div>
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between mb-1 production-row food-production">
                                                <span><img src="frontend/images/food.png" width="20" height="20" alt="Food"> Food</span>
                                                <span class="production-value">+<?= $productions['food_production'] ?>/min</span>
                                            </div>
                                            <div class="progress-bar-container">
                                                <div class="production-bar" style="background: linear-gradient(90deg, #006400 0%, #228B22 100%);"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p>No production data available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Quick Actions -->
                            <div class="resource-card">
                                <div class="resource-header">
                                    <i class="fas fa-bolt mr-2"></i> Quick Actions
                                </div>                                <div class="resource-content">
                                    <div class="quick-actions">
                                        <button class="quick-action-btn">
                                            <i class="fas fa-hammer"></i> Build
                                        </button>
                                        <button class="quick-action-btn">
                                            <i class="fas fa-users"></i> Recruit
                                        </button>
                                        <button class="quick-action-btn">
                                            <i class="fas fa-flask"></i> Research
                                        </button>
                                        <button class="quick-action-btn">
                                            <i class="fas fa-search"></i> Scout
                                        </button>
                                        <button class="quick-action-btn">
                                            <i class="fas fa-shield-alt"></i> Defend
                                        </button>
                                        <button class="quick-action-btn">
                                            <i class="fas fa-sword"></i> Attack
                                        </button>
                                    </div>
                                    
                                    <div class="mt-3 text-center">
                                        <div class="resource-timer">
                                            <span>Next resource update in:</span>
                                            <div class="timer-countdown" id="resource-timer">01:00</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Center Column - City Overview -->
                        <div class="center-column">
                            <!-- City Overview -->
                            <div class="city-overview">
                                <div class="city-header">
                                    <div class="city-name"><?= htmlspecialchars($playerCity['name']) ?></div>
                                    <div class="city-level">Level <?= $playerCity['level'] ?? 1 ?></div>
                                </div>
                                
                                <div class="city-content">
                                    <!-- 3D City View -->
                                    <div class="city-view-3d"></div>
                                    
                                    <!-- Buildings Grid -->
                                    <h5 class="mb-3">City Buildings</h5>
                                    <div class="buildings-grid">
                                        <div class="building-slot">
                                            <div class="building-icon"><i class="fas fa-home"></i></div>
                                            <div class="building-name">Town Hall</div>
                                            <div class="building-level">5</div>
                                            <div class="building-tooltip">
                                                <div class="tooltip-header">Town Hall</div>
                                                <div class="tooltip-description">The central building of your city. Upgrade to unlock new buildings.</div>
                                                <div class="tooltip-production">+5 Max Population per level</div>
                                                <div class="tooltip-upgrade">Upgrade to Level 6</div>
                                                <div class="tooltip-cost">Cost: 1000 Gold, 800 Wood, 600 Stone</div>
                                            </div>
                                        </div>
                                        <div class="building-slot">
                                            <div class="building-icon"><i class="fas fa-warehouse"></i></div>
                                            <div class="building-name">Warehouse</div>
                                            <div class="building-level">4</div>
                                            <div class="building-tooltip">
                                                <div class="tooltip-header">Warehouse</div>
                                                <div class="tooltip-description">Stores your resources. Upgrade to increase storage capacity.</div>
                                                <div class="tooltip-production">+1000 Storage capacity per level</div>
                                                <div class="tooltip-upgrade">Upgrade to Level 5</div>
                                                <div class="tooltip-cost">Cost: 800 Gold, 1200 Wood, 400 Stone</div>
                                            </div>
                                        </div>
                                        <div class="building-slot">
                                            <div class="building-icon"><i class="fas fa-tree"></i></div>
                                            <div class="building-name">Lumbermill</div>
                                            <div class="building-level">3</div>
                                            <div class="building-tooltip">
                                                <div class="tooltip-header">Lumbermill</div>
                                                <div class="tooltip-description">Produces wood for your city.</div>
                                                <div class="tooltip-production">+10 Wood per hour per level</div>
                                                <div class="tooltip-upgrade">Upgrade to Level 4</div>
                                                <div class="tooltip-cost">Cost: 600 Gold, 300 Wood, 400 Stone</div>
                                            </div>
                                        </div>
                                        <div class="building-slot">
                                            <div class="building-icon"><i class="fas fa-mountain"></i></div>
                                            <div class="building-name">Quarry</div>
                                            <div class="building-level">3</div>
                                            <div class="building-tooltip">
                                                <div class="tooltip-header">Quarry</div>
                                                <div class="tooltip-description">Produces stone for your city.</div>
                                                <div class="tooltip-production">+8 Stone per hour per level</div>
                                                <div class="tooltip-upgrade">Upgrade to Level 4</div>
                                                <div class="tooltip-cost">Cost: 700 Gold, 500 Wood, 200 Stone</div>
                                            </div>
                                        </div>
                                        <div class="building-slot">
                                            <div class="building-icon"><i class="fas fa-wheat"></i></div>
                                            <div class="building-name">Farm</div>
                                            <div class="building-level">4</div>
                                            <div class="building-tooltip">
                                                <div class="tooltip-header">Farm</div>
                                                <div class="tooltip-description">Produces food for your city and army.</div>
                                                <div class="tooltip-production">+12 Food per hour per level</div>
                                                <div class="tooltip-upgrade">Upgrade to Level 5</div>
                                                <div class="tooltip-cost">Cost: 500 Gold, 400 Wood, 300 Stone</div>
                                            </div>
                                        </div>
                                        <div class="building-slot">
                                            <div class="building-icon"><i class="fas fa-flag"></i></div>
                                            <div class="building-name">Barracks</div>
                                            <div class="building-level">3</div>
                                            <div class="building-tooltip">
                                                <div class="tooltip-header">Barracks</div>
                                                <div class="tooltip-description">Train and house your infantry units.</div>
                                                <div class="tooltip-production">+5 Training speed per level</div>
                                                <div class="tooltip-upgrade">Upgrade to Level 4</div>
                                                <div class="tooltip-cost">Cost: 800 Gold, 600 Wood, 500 Stone</div>
                                            </div>
                                        </div>
                                        <div class="building-slot">
                                            <div class="building-icon"><i class="fas fa-archway"></i></div>
                                            <div class="building-name">Archery Range</div>
                                            <div class="building-level">2</div>
                                            <div class="building-tooltip">
                                                <div class="tooltip-header">Archery Range</div>
                                                <div class="tooltip-description">Train and house your ranged units.</div>
                                                <div class="tooltip-production">+5 Training speed per level</div>
                                                <div class="tooltip-upgrade">Upgrade to Level 3</div>
                                                <div class="tooltip-cost">Cost: 700 Gold, 900 Wood, 400 Stone</div>
                                            </div>
                                        </div>
                                        <div class="building-slot empty-slot">
                                            <div class="building-icon"><i class="fas fa-plus"></i></div>
                                            <div class="building-name">Empty Slot</div>
                                            <div class="building-tooltip">
                                                <div class="tooltip-header">Empty Building Slot</div>
                                                <div class="tooltip-description">Click to construct a new building here.</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress Section -->
                                    <div class="progress-section">
                                        <h5 class="mb-2">City Progress</h5>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: 45%;"></div>
                                        </div>
                                        <div class="progress-label">
                                            <span>Level <?= $playerCity['level'] ?? 1 ?></span>
                                            <span>45%</span>
                                            <span>Level <?= ($playerCity['level'] ?? 1) + 1 ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Mini Map -->
                                    <div class="mini-map">
                                        <div class="mini-map-title">Surrounding Area</div>
                                        <div class="mini-map-grid">
                                            <?php 
                                            // Create a 7x7 mini-map centered on player's city
                                            $centerX = $playerCity['location_x'];
                                            $centerY = $playerCity['location_y'];
                                            
                                            for ($y = $centerY - 3; $y <= $centerY + 3; $y++) {
                                                for ($x = $centerX - 3; $x <= $centerX + 3; $x++) {
                                                    // Determine tile type - this is a placeholder and would be replaced with actual map data
                                                    $tileClass = 'mini-terrain-grass';
                                                    $tileContent = '';
                                                    $tooltip = "Location: [$x, $y]";
                                                    
                                                    if ($x == $centerX && $y == $centerY) {
                                                        $tileClass = 'mini-city-player';
                                                        $tooltip = "Your City: " . htmlspecialchars($playerCity['name']);
                                                    } else {
                                                        // Randomly assign terrain types for demo purposes
                                                        $rand = rand(1, 10);
                                                        if ($rand <= 5) {
                                                            $tileClass = 'mini-terrain-grass';
                                                        } else if ($rand <= 7) {
                                                            $tileClass = 'mini-terrain-forest';
                                                            $tooltip .= "\nForest: Provides wood";
                                                        } else if ($rand <= 8) {
                                                            $tileClass = 'mini-terrain-mountain';
                                                            $tooltip .= "\nMountain: Provides stone";
                                                        } else if ($rand <= 9) {
                                                            $tileClass = 'mini-terrain-water';
                                                            $tooltip .= "\nWater: Impassable terrain";
                                                        } else {
                                                            $tileClass = 'mini-terrain-desert';
                                                            $tooltip .= "\nDesert: Harsh terrain";
                                                        }
                                                        
                                                        // Randomly add resources or AI cities for demo purposes
                                                        $featureRand = rand(1, 20);
                                                        if ($featureRand <= 1) {
                                                            $tileClass = 'mini-city-ai';
                                                            $tooltip = "AI City\n" . $tooltip;
                                                        } else if ($featureRand <= 4) {
                                                            $resourceType = rand(1, 4);
                                                            switch ($resourceType) {
                                                                case 1:
                                                                    $tileContent = '<div class="mini-resource mini-resource-gold"></div>';
                                                                    $tooltip = "Gold Deposit\n" . $tooltip;
                                                                    break;
                                                                case 2:
                                                                    $tileContent = '<div class="mini-resource mini-resource-wood"></div>';
                                                                    $tooltip = "Wood Resource\n" . $tooltip;
                                                                    break;
                                                                case 3:
                                                                    $tileContent = '<div class="mini-resource mini-resource-stone"></div>';
                                                                    $tooltip = "Stone Resource\n" . $tooltip;
                                                                    break;
                                                                case 4:
                                                                    $tileContent = '<div class="mini-resource mini-resource-food"></div>';
                                                                    $tooltip = "Food Resource\n" . $tooltip;
                                                                    break;
                                                            }
                                                        }
                                                    }
                                                    
                                                    echo '<div class="mini-map-tile ' . $tileClass . '">';
                                                    echo $tileContent;
                                                    echo '<div class="mini-map-tooltip">' . $tooltip . '</div>';
                                                    echo '</div>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column - Army and Activities -->
                        <div class="right-column">
                            <!-- Army Overview -->
                            <div class="resource-card">
                                <div class="resource-header">
                                    <i class="fas fa-shield-alt mr-2"></i> Your Army
                                </div>
                                <div class="resource-content">
                                    <?php if (isset($playerArmies) && $playerArmies): ?>
                                        <div class="army-units">
                                            <div class="unit-card">
                                                <div class="unit-icon"><i class="fas fa-user-shield"></i></div>
                                                <div class="unit-count"><?= number_format($playerArmies['fighters'] ?? 0) ?></div>
                                                <div class="unit-name">Fighters</div>
                                            </div>
                                            <div class="unit-card">
                                                <div class="unit-icon"><i class="fas fa-crosshairs"></i></div>
                                                <div class="unit-count"><?= number_format($playerArmies['shooters'] ?? 0) ?></div>
                                                <div class="unit-name">Shooters</div>
                                            </div>
                                            <div class="unit-card">
                                                <div class="unit-icon"><i class="fas fa-truck-monster"></i></div>
                                                <div class="unit-count"><?= number_format($playerArmies['vehicles'] ?? 0) ?></div>
                                                <div class="unit-name">Vehicles</div>
                                            </div>
                                            <div class="unit-card">
                                                <div class="unit-icon"><i class="fas fa-horse"></i></div>
                                                <div class="unit-count"><?= number_format($playerArmies['rides'] ?? 0) ?></div>
                                                <div class="unit-name">Rides</div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p>No army information available.</p>
                                        <button class="btn btn-primary btn-sm mt-2">Recruit Your First Units</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Recent Activities -->
                            <div class="resource-card">
                                <div class="resource-header">
                                    <i class="fas fa-history mr-2"></i> Recent Activities
                                </div>
                                <div class="resource-content">
                                    <div class="activity-list">
                                        <?php if (count($recentBattles) > 0): ?>
                                            <?php foreach ($recentBattles as $index => $battle): ?>
                                                <?php
                                                // Determine if player won or lost
                                                $isAttacker = ($battle['attacker_type'] === 'player' && $battle['attacker_id'] == $playerId);
                                                $isDefender = ($battle['defender_type'] === 'player' && $battle['defender_id'] == $playerId);
                                                
                                                $playerWon = ($isAttacker && $battle['battle_result'] === 'attacker_victory') || 
                                                            ($isDefender && $battle['battle_result'] === 'defender_victory');
                                                
                                                // Get opponent name
                                                $opponentName = "Unknown";
                                                $opponentId = $isAttacker ? $battle['defender_id'] : $battle['attacker_id'];
                                                $opponentType = $isAttacker ? $battle['defender_type'] : $battle['attacker_type'];
                                                
                                                if ($opponentType === 'ai') {
                                                    $query = "SELECT name FROM ai_players WHERE id = ?";
                                                    $stmt = $conn->prepare($query);
                                                    $stmt->bind_param("i", $opponentId);
                                                    $stmt->execute();
                                                    $result = $stmt->get_result();
                                                    if ($result && $result->num_rows === 1) {
                                                        $opponentName = $result->fetch_assoc()['name'] . " (AI)";
                                                    }
                                                } else if ($opponentType === 'player') {
                                                    $query = "SELECT name FROM players WHERE id = ?";
                                                    $stmt = $conn->prepare($query);
                                                    $stmt->bind_param("i", $opponentId);
                                                    $stmt->execute();
                                                    $result = $stmt->get_result();
                                                    if ($result && $result->num_rows === 1) {
                                                        $opponentName = $result->fetch_assoc()['name'];
                                                    }
                                                }
                                                ?>
                                                <div class="activity-item activity-type-battle">
                                                    <div class="activity-time"><?= date("M d, H:i", strtotime($battle['battle_date'])) ?></div>
                                                    <div class="activity-content">
                                                        <?php if ($playerWon): ?>
                                                            You <span class="text-success">defeated</span> <?= htmlspecialchars($opponentName) ?> in battle.
                                                        <?php else: ?>
                                                            You were <span class="text-danger">defeated</span> by <?= htmlspecialchars($opponentName) ?> in battle.
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <!-- Some placeholder activities for new players -->
                                            <div class="activity-item activity-type-build">
                                                <div class="activity-time"><?= date("M d, H:i", strtotime("-1 hour")) ?></div>
                                                <div class="activity-content">
                                                    Your <span class="text-success">Town Hall</span> construction was completed.
                                                </div>
                                            </div>
                                            <div class="activity-item activity-type-resource">
                                                <div class="activity-time"><?= date("M d, H:i", strtotime("-3 hours")) ?></div>
                                                <div class="activity-content">
                                                    You collected <span class="text-warning">500 Gold</span> from your city.
                                                </div>
                                            </div>
                                            <div class="activity-item activity-type-system">
                                                <div class="activity-time"><?= date("M d, H:i", strtotime("-1 day")) ?></div>
                                                <div class="activity-content">
                                                    Welcome to <span class="text-primary">MMORTS!</span> Your adventure begins now.
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Navigation Card -->
                            <div class="resource-card">
                                <div class="resource-header">
                                    <i class="fas fa-compass mr-2"></i> Game Navigation
                                </div>
                                <div class="resource-content">
                                    <div class="d-grid gap-2">
                                        <a href="index.php?page=world_map" class="btn btn-primary mb-2">
                                            <i class="fas fa-map-marked-alt"></i> World Map
                                        </a>
                                        <a href="index.php?page=battle" class="btn btn-danger mb-2">
                                            <i class="fas fa-swords"></i> Battle
                                        </a>
                                        <a href="index.php?page=ai_opponents" class="btn btn-warning mb-2">
                                            <i class="fas fa-robot"></i> AI Opponents
                                        </a>
                                        <a href="index.php?page=battle_history" class="btn btn-info">
                                            <i class="fas fa-scroll"></i> Battle History
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="game-panel">
                            <div class="game-panel-header">
                                <h3>Welcome, Commander!</h3>
                            </div>
                            <p>You haven't established your city yet. Create your first settlement to begin your conquest!</p>
                            <a href="#" class="game-button">Found Your City</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="game-container text-center">
                    <h2 class="mb-4">Welcome to the MMORTS Game!</h2>
                    <p class="lead mb-4">Build your city, train your armies, and conquer the world!</p>
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="game-panel">
                                <div class="game-panel-header">
                                    <h3>Build</h3>
                                </div>
                                <p>Construct buildings, gather resources, and expand your settlement into a mighty empire!</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="game-panel">
                                <div class="game-panel-header">
                                    <h3>Train</h3>
                                </div>
                                <p>Recruit and train powerful armies to defend your territory and attack your enemies!</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="game-panel">
                                <div class="game-panel-header">
                                    <h3>Conquer</h3>
                                </div>
                                <p>Battle other players and AI opponents to expand your influence across the world map!</p>
                            </div>
                        </div>
                    </div>
                    <a href="index.php?page=register" class="game-button btn-lg me-2">Register Now</a>
                    <a href="index.php?page=login" class="game-button btn-lg">Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Initialize production progress bars
document.addEventListener('DOMContentLoaded', function() {
    const progressBars = document.querySelectorAll('.production-bar');
    
    // Animate the progress bars
    progressBars.forEach(bar => {
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = '100%';
        }, 100);
    });
    
    // Reset and animate the bars every minute
    setInterval(() => {
        progressBars.forEach(bar => {
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = '100%';
            }, 100);
        });
    }, 60000);
});
</script>

<!-- Include home page specific JS -->
<script src="frontend/design/js/home.js" defer></script>

<?php include_once __DIR__ . '/../' . 'templates/footer.php'; ?>