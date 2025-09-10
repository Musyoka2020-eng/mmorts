<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Add training-specific CSS and JS
echo '<link rel="stylesheet" href="frontend/design/css/training.css">';
echo '<link rel="stylesheet" href="frontend/design/css/training-unit-tooltips.css">'; // Enhanced version with multipliers and advanced features
echo '<script src="game_config.js.php"></script>'; // Load game configuration first
echo '<script src="frontend/design/js/training-enhanced.js"></script>'; // Enhanced version with multipliers and advanced features
echo '<script src="frontend/design/js/training-unit-tooltips.js"></script>'; // Tooltips for unit training

// Use new globals system for authentication and database access
$g = globals();
$conn = $g->getDatabase();

// Check if user is logged in using new system
if (!$g->isUserLoggedIn()) {
    header('Location: ' . url('login', ['msg' => 'You must be logged in to access this page.']));
    exit;
}

// Get player ID through globals
$playerId = $g->getCurrentUser('id');

// Check if form was submitted
if (isset($_POST['train_units'])) {
    // Get player's resources
    $query = "SELECT r.* FROM resources r 
             JOIN cities c ON r.id = c.resources_id 
             WHERE c.player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $resources = $result->fetch_assoc();

        // Get unit costs from centralized configuration
        $unitCosts = GameConfig::getUnitCosts();

        // Process each unit type
        $totalResourcesUsed = [
            'wood' => 0,
            'iron' => 0,
            'food' => 0,
            'oil' => 0,
            'stone' => 0
        ];

        $unitsToTrain = [];
        $errors = [];

        foreach ($_POST as $key => $value) {
            if (strpos($key, 'train_') === 0 && intval($value) > 0) {
                $unitType = substr($key, 6); // Remove 'train_' prefix

                if (isset($unitCosts[$unitType])) {
                    $count = intval($value);

                    // Calculate resource usage
                    foreach ($unitCosts[$unitType] as $resource => $cost) {
                        $totalResourcesUsed[$resource] += $cost * $count;
                    }

                    $unitsToTrain[$unitType] = $count;
                }
            }
        }

        // Check if player has enough resources
        $canAfford = true;
        foreach ($totalResourcesUsed as $resource => $amount) {
            if ($amount > $resources[$resource]) {
                $canAfford = false;
                $errors[] = "Not enough $resource. Need $amount but you only have " . $resources[$resource];
            }
        }

        if ($canAfford && !empty($unitsToTrain)) {
            // Deduct resources
            $query = "UPDATE resources SET 
                     wood = wood - ?, 
                     iron = iron - ?, 
                     food = food - ?, 
                     oil = oil - ?, 
                     stone = stone - ? 
                     WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "iiiiii",
                $totalResourcesUsed['wood'],
                $totalResourcesUsed['iron'],
                $totalResourcesUsed['food'],
                $totalResourcesUsed['oil'],
                $totalResourcesUsed['stone'],
                $resources['id']
            );
            $stmt->execute();

            // Add units to player's army
            foreach ($unitsToTrain as $unitType => $count) {
                $query = "UPDATE player_armies SET $unitType = $unitType + ? WHERE player_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $count, $playerId);
                $stmt->execute();

                // If no rows were affected, player doesn't have an armies record yet
                if ($stmt->affected_rows === 0) {
                    $query = "INSERT INTO player_armies (player_id, $unitType) VALUES (?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $playerId, $count);
                    $stmt->execute();
                }
            }

            $success = "Units trained successfully!";
        } elseif (empty($unitsToTrain)) {
            $errors[] = "No units selected for training.";
        }
    } else {
        $errors[] = "Could not retrieve player resources.";
    }
}

// Get all centralized game configuration data
$unitCosts = GameConfig::getUnitCosts();
$unitStats = GameConfig::getUnitStats();
$unitDisplay = GameConfig::getUnitDisplay();
$unitCategories = GameConfig::getUnitCategories();
$resourceTypes = GameConfig::getResourceTypes();

// Get player's current army
$query = "SELECT * FROM player_armies WHERE player_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $playerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $army = $result->fetch_assoc();
} else {
    $army = [
        'fighters' => 0,
        'shooters' => 0,
        'vehicles' => 0,
        'skirmishers' => 0,
        'riders' => 0,
        'canons' => 0,
        'jets' => 0,
        'archers' => 0,
        'marauders' => 0
    ];
}

// Get player's resources
$query = "SELECT r.* FROM resources r 
         JOIN cities c ON r.id = c.resources_id 
         WHERE c.player_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $playerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $resources = $result->fetch_assoc();
} else {
    $resources = [
        'wood' => 0,
        'iron' => 0,
        'food' => 0,
        'oil' => 0,
        'stone' => 0
    ];
}
?>

<div class="training-container">
    <!-- Header Section -->
    <div class="training-header">
        <h1 class="training-title">🏗️ Train Your Units</h1>
        <p class="training-subtitle">Select units to train and manage your army effectively.</p>
    </div>

    <!-- Main Content Grid -->
    <div class="training-grid">
        <!-- Unit Training Section -->
        <div class="units-section">
            <!-- Alert Messages -->
            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger animate-slide-in" style="margin: 1rem;">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="alert alert-success animate-slide-in" style="margin: 1rem;"><?= $success ?></div>
            <?php endif; ?>

            <!-- Dynamic Unit Category Tabs -->
            <div class="units-tabs">
                <?php $firstTab = true; ?>
                <?php foreach ($unitCategories as $categoryKey => $category): ?>
                    <button class="units-tab <?= $firstTab ? 'active' : '' ?>" data-tab="<?= $category['tab'] ?>">
                        <?= $category['name'] ?>
                    </button>
                    <?php $firstTab = false; ?>
                <?php endforeach; ?>
            </div>

            <!-- Dynamic Units Content -->
            <div class="units-content">
                <form method="post" id="training-form">
                    <?php $firstTabContent = true; ?>
                    <?php foreach ($unitCategories as $categoryKey => $category): ?>
                        <!-- <?= ucfirst($categoryKey) ?> Units Tab -->
                        <div class="unit-tab-content <?= $firstTabContent ? 'active' : '' ?>" id="<?= $category['tab'] ?>-tab">
                            <div class="unit-grid">
                                <?php foreach ($unitDisplay as $unitKey => $unit): ?>
                                    <?php if ($unit['category'] === $categoryKey): ?>
                                        <!-- <?= $unit['name'] ?> -->
                                        <div class="unit-card training-unit-tooltip" data-unit="<?= $unitKey ?>" data-tooltip="">
                                            <div class="unit-header">
                                                <div class="unit-icon"><?= $unit['icon'] ?></div>
                                                <div class="unit-name"><?= $unit['name'] ?></div>
                                            </div>

                                            <div class="unit-stats">
                                                <?php foreach ($unitStats[$unitKey] as $statName => $statValue): ?>
                                                    <div class="stat-item">
                                                        <div class="stat-label"><?= ucfirst($statName) ?></div>
                                                        <div class="stat-value"><?= $statValue ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <div class="unit-costs">
                                                <div class="costs-title">Training Cost</div>
                                                <div class="costs-grid">
                                                    <?php foreach ($unitCosts[$unitKey] as $resource => $cost): ?>
                                                        <div class="cost-item">
                                                            <div class="cost-icon training-resource-<?= $resource ?>"></div>
                                                            <span><?= $cost ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="training-controls">
                                                <div class="quantity-selector">
                                                    <button type="button" class="quantity-btn" data-action="decrease" style="border-radius:0px 4px 4px 0px">−</button>
                                                    <input type="number" class="quantity-input" id="train_<?= $unitKey ?>" name="train_<?= $unitKey ?>" min="0" value="0" max="999">
                                                    <button type="button" class="quantity-btn" data-action="increase" style="border-radius:4px 0px 0px 4px">+</button>
                                                </div>
                                                <button type="button" class="train-btn" data-action="max">MAX</button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php $firstTabContent = false; ?>
                    <?php endforeach; ?>

                    <!-- Action Buttons -->
                    <div style="padding: 2rem; border-top: 1px solid rgba(212, 175, 55, 0.2); background: rgba(0, 0, 0, 0.2);">
                        <div style="display: flex; gap: 1rem; justify-content: center;">
                            <button type="submit" name="train_units" class="train-btn" style="padding: 1rem 3rem; font-size: 1.1rem;">
                                🏗️ Train Selected Units
                            </button>
                            <a href="index.php?page=home" class="train-btn" style="background: linear-gradient(135deg, #718096 0%, #4a5568 100%); text-decoration: none; display: inline-flex; align-items: center; padding: 1rem 2rem;">
                                ↩️ Return to Base
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div> <!-- Sidebar -->
        <div class="training-sidebar">
            <!-- Resources Panel -->
            <div class="training-resources-panel animate-slide-in">
                <div class="panel-header">
                    <h3 class="panel-title">💰 Empire Resources</h3>
                </div>
                <div class="panel-content">
                    <div class="training-resource-item training-resource-tooltip" data-tooltip="Wood is used for basic construction and training">
                        <div class="training-resource-info">
                            <div class="training-resource-icon training-resource-wood"></div>
                            <span class="training-resource-name">Wood</span>
                        </div>
                        <span class="training-resource-amount" id="training-wood_available"><?= number_format($resources['wood']) ?></span>
                    </div>

                    <div class="training-resource-item training-resource-tooltip" data-tooltip="Iron is essential for weapons and armor">
                        <div class="training-resource-info">
                            <div class="training-resource-icon training-resource-iron"></div>
                            <span class="training-resource-name">Iron</span>
                        </div>
                        <span class="training-resource-amount" id="training-iron_available"><?= number_format($resources['iron']) ?></span>
                    </div>

                    <div class="training-resource-item training-resource-tooltip" data-tooltip="Food sustains your army and population">
                        <div class="training-resource-info">
                            <div class="training-resource-icon training-resource-food"></div>
                            <span class="training-resource-name">Food</span>
                        </div>
                        <span class="training-resource-amount" id="training-food_available"><?= number_format($resources['food']) ?></span>
                    </div>

                    <div class="training-resource-item training-resource-tooltip" data-tooltip="Oil powers advanced military units">
                        <div class="training-resource-info">
                            <div class="training-resource-icon training-resource-oil"></div>
                            <span class="training-resource-name">Oil</span>
                        </div>
                        <span class="training-resource-amount" id="training-oil_available"><?= number_format($resources['oil']) ?></span>
                    </div>

                    <div class="training-resource-item training-resource-tooltip" data-tooltip="Stone is used for fortifications">
                        <div class="training-resource-info">
                            <div class="training-resource-icon training-resource-stone"></div>
                            <span class="training-resource-name">Stone</span>
                        </div>
                        <span class="training-resource-amount" id="training-stone_available"><?= number_format($resources['stone']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Army Overview Panel -->
            <div class="training-army-panel animate-slide-in">
                <div class="panel-header">
                    <h3 class="panel-title">⚔️ Current Army</h3>
                </div>
                <div class="panel-content">
                    <div class="army-grid"> <?php foreach ($army as $unit => $count): ?>
                            <?php if ($unit !== 'id' && $unit !== 'player_id' && $unit !== 'updated_at'): ?>
                                <div class="army-unit">
                                    <div class="army-unit-icon">
                                        <?php
                                                    // Use centralized unit display configuration
                                                    echo isset($unitDisplay[$unit]) ? $unitDisplay[$unit]['icon'] : '⚔️';
                                        ?>
                                    </div>
                                    <div class="army-unit-name"><?= ucfirst($unit) ?></div>
                                    <div class="army-unit-count" data-army-unit="<?= $unit ?>"><?= number_format($count) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Training Tips Panel -->
            <div class="training-tips-panel animate-slide-in">
                <div class="panel-header">
                    <h3 class="panel-title">💡 Strategic Tips</h3>
                </div>
                <div class="panel-content">
                    <div class="strategic-tips">
                        <div class="strategic-tips-item" style="border-left: 3px solid #38a169;">
                            <div class="strategic-tip-title" style="color: #38a169">⚖️ Balance Your Forces</div>
                            <div class="strategic-tip-description">Mix different unit types for optimal battlefield performance.</div>
                        </div>
                        <div class="strategic-tips-item" style="border-left: 3px solid #3182ce;">
                            <div class="strategic-tip-title" style="color: #3182ce;">💰 Resource Efficiency</div>
                            <div class="strategic-tip-description">Consider cost-per-effectiveness when choosing units to train.</div>
                        </div>

                        <div class="strategic-tips-item" style="border-left: 3px solid #d4af37;">
                            <div class="strategic-tip-title" style="color: #d4af37;">📈 Scale Production</div>
                            <div class="strategic-tip-description">Increase resource production before training large armies.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- JavaScript Configuration for Tooltips -->
<script>
    // Make game configuration available to the tooltip system
    window.gameConfig = <?= GameConfig::getJavaScriptConfig() ?>;
</script>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>