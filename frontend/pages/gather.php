<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

echo '<link rel="stylesheet" href="frontend/design/css/gather.css">';
echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
// echo '<script src="frontend/design/js/gathering-interface.js"></script>';


// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php?page=login&msg=' . urlencode('You must be logged in to access this page.'));
    exit;
}

// Get player ID
$playerId = $_SESSION['user']['id'];

// Check if there's a target location
if (isset($_GET['target_x']) && isset($_GET['target_y'])) {
    $targetX = $_GET['target_x'];
    $targetY = $_GET['target_y'];

    // Get map cell info
    $query = "SELECT * FROM world_map WHERE location_x = ? AND location_y = ? AND occupied = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $targetX, $targetY);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $mapCell = $result->fetch_assoc();
        // Check if cell has resources
        if (!empty($mapCell['resource_type']) && $mapCell['resource_amount'] > 0) {
            $resourceType = $mapCell['resource_type'];
            $resourceAmount = $mapCell['resource_amount'];
        } else {
            $error = "No resources found at this location.";
        }
    } else {
        $error = "Invalid target location or location is occupied.";
    }
} else {
    $error = "No target location specified.";
}

// Check for existing gathering operation at this location
$existingOperation = null;
if (isset($targetX) && isset($targetY)) {
    $query = "SELECT * FROM gathering_operations 
              WHERE player_id = ? AND location_x = ? AND location_y = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $playerId, $targetX, $targetY);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $existingOperation = $result->fetch_assoc();
        // Calculate progress
        $currentTime = time();
        $startTime = strtotime($existingOperation['start_time']);
        $completionTime = strtotime($existingOperation['estimated_completion']);
        $totalTime = $completionTime - $startTime;
        $elapsedTime = $currentTime - $startTime;
        $progressPercent = min(100, max(0, ($elapsedTime / $totalTime) * 100));
        $currentGathered = min($existingOperation['amount_to_gather'], floor($progressPercent / 100 * $existingOperation['amount_to_gather']));
        $timeRemaining = max(0, $completionTime - $currentTime);

        $existingOperation['progress_percent'] = $progressPercent;
        $existingOperation['current_gathered'] = $currentGathered;
        $existingOperation['time_remaining'] = $timeRemaining;
        $existingOperation['is_completed'] = $timeRemaining <= 0;
    }
}

// Get player's current resources
$query = "SELECT r.* FROM resources r 
         JOIN cities c ON r.id = c.resources_id 
         WHERE c.player_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $playerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $playerResources = $result->fetch_assoc();
} else {
    $playerResources = [
        'wood' => 0,
        'iron' => 0,
        'food' => 0,
        'oil' => 0,
        'stone' => 0
    ];
}
?>

<div class="gather-main">
    <div class="gather-container">
        <!-- Header Section -->
        <div class="gather-header">
            <h1 class="gather-title">
                <i class="fas fa-hammer"></i>
                Resource Gathering
            </h1>
            <p class="gather-subtitle">Harvest valuable resources to expand your empire</p>
            <?php if (isset($mapCell)): ?>
                <div class="location-badge">
                    <div class="location-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <span>Coordinates: <?= $targetX ?>, <?= $targetY ?></span>
                </div>
            <?php endif; ?>
        </div> <!-- Alert Messages -->
        <?php if (isset($error)): ?>
            <div class="gather-alert gather-alert-error">
                <div class="gather-alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <strong>Error:</strong> <?= $error ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="gather-grid">
            <!-- Main Content -->
            <div class="main-content">
                <?php if (isset($error)): ?>
                    <div class="resource-discovery-card">
                        <div class="card-header-enhanced">
                            <h2 class="card-title-enhanced">
                                <i class="fas fa-exclamation-circle"></i>
                                Unable to Access Location
                            </h2>
                            <p class="card-subtitle">The specified location cannot be accessed for resource gathering</p>
                        </div>
                        <div class="card-body-enhanced">
                            <div style="text-align: center; padding: 2rem;">
                                <i class="fas fa-map-marked-alt" style="font-size: 4rem; color: rgba(255, 255, 255, 0.3); margin-bottom: 1rem;"></i>
                                <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">
                                    This location may be occupied, depleted, or invalid. Please return to the world map to find available resource nodes.
                                </p>
                                <a href="index.php?page=world_map" class="btn-enhanced btn-primary-enhanced">
                                    <i class="fas fa-map"></i>
                                    Return to World Map
                                </a>
                            </div>
                        </div>
                    </div> <?php elseif (isset($mapCell) && isset($resourceType) && isset($resourceAmount)): ?>
                    <?php if ($existingOperation): ?>
                        <!-- Show existing gathering operation -->
                        <script>
                            // Initialize operation data for live updates
                            window.currentOperationData = {
                                operation_id: <?= $existingOperation['id'] ?>,
                                amount_to_gather: <?= $existingOperation['amount_to_gather'] ?>,
                                time_remaining_seconds: <?= $existingOperation['time_remaining'] ?>,
                                progress_percent: <?= $existingOperation['progress_percent'] ?>,
                                current_gathered: <?= $existingOperation['current_gathered'] ?>,
                                is_completed: <?= $existingOperation['is_completed'] ? 'true' : 'false' ?>,
                                resource_type: <?= json_encode($existingOperation['resource_type']) ?>,
                                gathering_rate: <?= json_encode($existingOperation['gathering_rate']) ?>,
                            };
                            // document.addEventListener('DOMContentLoaded', function() {
                            //     // Initialize the gathering interface with existing operation data
                            //     setTimeout(function() {
                            //         // Initialize the gathering interface with existing operation data
                            //         if (typeof gatheringInterface !== 'undefined') {
                            //             gatheringInterface.showExistingOperation(window.currentOperationData);
                            //         } else {
                            //             console.error('gatheringInterface is not defined.');
                            //         }
                            //     }, 10000);

                            // });
                        </script>
                    <?php else: ?>
                        <!-- Show resource discovery and gathering form -->
                        <div class="resource-discovery-card">
                            <div class="card-header-enhanced">
                                <h2 class="card-title-enhanced">
                                    <i class="fas fa-gem"></i>
                                    Resource Discovery
                                </h2>
                                <p class="card-subtitle">Valuable resources have been located at this site</p>
                            </div>
                            <div class="card-body-enhanced">
                                <!-- Resource Showcase -->
                                <div class="resource-showcase resource-<?= $resourceType ?>">
                                    <div class="resource-main-info">
                                        <div class="resource-icon-large">
                                            <img src="frontend/images/<?= $resourceType ?>.png" alt="<?= ucfirst($resourceType) ?>">
                                        </div>
                                        <div class="resource-details-main">
                                            <h3 class="resource-name-large"><?= ucfirst($resourceType) ?></h3>
                                            <div class="resource-amount-large">
                                                <i class="fas fa-cubes"></i>
                                                <?= number_format($resourceAmount) ?> units available
                                            </div>
                                            <div class="resource-description">
                                                <?php
                                                $descriptions = [
                                                    'wood' => "Essential timber for construction and basic infrastructure. Wood forms the foundation of your empire's growth and is crucial for building defensive structures.",
                                                    'iron' => "Durable metal ore perfect for crafting weapons and armor. Iron is the backbone of military production and advanced defensive systems.",
                                                    'food' => "Nutritious sustenance vital for population growth and army maintenance. Food ensures your citizens and soldiers remain strong and productive.",
                                                    'oil' => "Precious liquid fuel that powers advanced machinery and vehicles. Oil enables the production of modern military units and industrial capabilities.",
                                                    'stone' => "Solid building material for fortifications and monuments. Stone provides the strength needed for lasting defensive structures and prestigious buildings."
                                                ];
                                                echo $descriptions[$resourceType] ?? "This valuable resource will greatly benefit your empire's development and strategic capabilities.";
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($resourceAmount > 0): ?>
                                        <div class="gathering-progress">
                                            <div class="gathering-progress-bar" style="width: <?= min(100, ($resourceAmount / 1000) * 100) ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($resourceAmount > 0): ?> <!-- Gathering Form -->
                                    <form class="gathering-form" id="gathering-form">
                                        <div class="form-group-enhanced">
                                            <label for="gather_amount" class="form-label-enhanced">
                                                <i class="fas fa-sliders-h"></i>
                                                Gathering Amount
                                            </label>
                                            <input type="number"
                                                class="form-input-enhanced"
                                                id="gather_amount"
                                                name="gather_amount"
                                                min="1"
                                                max="<?= $resourceAmount ?>"
                                                value="<?= min($resourceAmount, ceil($resourceAmount * 0.2)) ?>"
                                                placeholder="Enter amount to gather">
                                            <div class="form-help-text">
                                                <i class="fas fa-info-circle"></i>
                                                Maximum available: <?= number_format($resourceAmount) ?> units
                                                • Recommended: <?= number_format(ceil($resourceAmount * 0.2)) ?> units (20%)
                                            </div>
                                        </div>

                                        <div class="form-actions">
                                            <button type="submit" class="btn-enhanced btn-primary-enhanced">
                                                <i class="fas fa-hammer"></i>
                                                Start Gathering Operation
                                            </button>
                                            <a href="index.php?page=world_map" class="btn-enhanced btn-secondary-enhanced">
                                                <i class="fas fa-times"></i>
                                                Cancel & Return
                                            </a>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div style="text-align: center; padding: 2rem;">
                                        <i class="fas fa-search-minus" style="font-size: 3rem; color: rgba(255, 255, 255, 0.3); margin-bottom: 1rem;"></i>
                                        <h3 style="color: white; margin-bottom: 1rem;">Location Depleted</h3>
                                        <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">
                                            This resource node has been completely harvested. Resources will gradually replenish over time.
                                        </p>
                                        <a href="index.php?page=world_map" class="btn-enhanced btn-primary-enhanced">
                                            <i class="fas fa-map"></i>
                                            Find New Resources
                                        </a>
                                    </div> <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="gather-sidebar">
                <!-- Current Resources Panel -->
                <div class="current-resources-panel">
                    <div class="card-header-enhanced">
                        <h3 class="card-title-enhanced">
                            <i class="fas fa-warehouse"></i>
                            Your Resources
                        </h3>
                        <p class="card-subtitle">Current inventory status</p>
                    </div>
                    <div class="resources-list">
                        <?php
                        $resourceIcons = [
                            'wood' => 'fas fa-tree',
                            'iron' => 'fas fa-hammer',
                            'food' => 'fas fa-apple-alt',
                            'oil' => 'fas fa-oil-can',
                            'stone' => 'fas fa-mountain'
                        ];

                        foreach (['wood', 'iron', 'food', 'oil', 'stone'] as $resource):
                        ?>
                            <div class="resource-item-enhanced">
                                <div class="resource-icon-small">
                                    <img src="frontend/images/<?= $resource ?>.png" alt="<?= ucfirst($resource) ?>">
                                </div>
                                <div class="resource-info-small">
                                    <div class="resource-name-small"><?= ucfirst($resource) ?></div>
                                    <div class="resource-amount-small">
                                        <?= number_format($playerResources[$resource]) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tips Panel -->
                <div class="tips-panel">
                    <div class="card-header-enhanced">
                        <h3 class="card-title-enhanced">
                            <i class="fas fa-lightbulb"></i>
                            Gathering Tips
                        </h3>
                        <p class="card-subtitle">Maximize your resource efficiency</p>
                    </div>
                    <div class="tips-list">
                        <div class="tip-item">
                            <div class="tip-icon">1</div>
                            <span>Explore different terrain types to discover various resource nodes scattered across the world map.</span>
                        </div>
                        <div class="tip-item">
                            <div class="tip-icon">2</div>
                            <span>Resources gradually replenish over time, so remember to revisit depleted locations later.</span>
                        </div>
                        <div class="tip-item">
                            <div class="tip-icon">3</div>
                            <span>Balance your gathering strategy based on your current construction and military needs.</span>
                        </div>
                        <div class="tip-item">
                            <div class="tip-icon">4</div>
                            <span>Prioritize gathering resources that are currently in short supply for optimal empire growth.</span>
                        </div>
                        <div class="tip-item">
                            <div class="tip-icon">5</div>
                            <span>Consider the strategic value of each resource type when planning your gathering expeditions.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="frontend/design/js/gathering-interface.js"></script>
<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>