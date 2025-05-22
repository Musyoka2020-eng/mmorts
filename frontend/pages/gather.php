<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

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
            
            // Process gathering request
            if (isset($_POST['gather'])) {
                // Determine how much to gather (max 20% of available)
                $gatherAmount = min(
                    $resourceAmount, 
                    ceil($resourceAmount * 0.2),
                    $_POST['gather_amount']
                );
                
                // Get player's city resources ID
                $query = "SELECT resources_id FROM cities WHERE player_id = ? LIMIT 1";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $playerId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $row = $result->fetch_assoc();
                    $resourcesId = $row['resources_id'];
                    
                    // Update player's resources
                    $query = "UPDATE resources SET $resourceType = $resourceType + ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $gatherAmount, $resourcesId);
                    
                    if ($stmt->execute()) {
                        // Update map cell resources
                        $newAmount = $resourceAmount - $gatherAmount;
                        $query = "UPDATE world_map SET resource_amount = ? WHERE location_x = ? AND location_y = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("iii", $newAmount, $targetX, $targetY);
                        $stmt->execute();
                        
                        $success = "Successfully gathered $gatherAmount $resourceType.";
                        
                        // Refresh the map cell data
                        $query = "SELECT * FROM world_map WHERE location_x = ? AND location_y = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("ii", $targetX, $targetY);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $mapCell = $result->fetch_assoc();
                        $resourceAmount = $mapCell['resource_amount'];
                    } else {
                        $error = "Failed to update resources.";
                    }
                } else {
                    $error = "Player city not found.";
                }
            }
        } else {
            $error = "No resources found at this location.";
        }
    } else {
        $error = "Invalid target location or location is occupied.";
    }
} else {
    $error = "No target location specified.";
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

<div class="main">
    <section class="content py-3">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3>Gather Resources</h3>
                            <?php if (isset($mapCell)): ?>
                                <p class="mb-0">Location: <?= $targetX ?>, <?= $targetY ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                                <a href="index.php?page=world_map" class="btn btn-primary">Return to Map</a>
                            <?php elseif (isset($success)): ?>
                                <div class="alert alert-success"><?= $success ?></div>
                                
                                <?php if ($resourceAmount > 0): ?>
                                    <p>There are <?= $resourceAmount ?> <?= $resourceType ?> remaining at this location.</p>
                                    
                                    <form method="post" class="mb-4">
                                        <div class="mb-3">
                                            <label for="gather_amount" class="form-label">Amount to Gather</label>
                                            <input type="number" class="form-control" id="gather_amount" name="gather_amount" 
                                                min="1" max="<?= $resourceAmount ?>" value="<?= min($resourceAmount, 100) ?>">
                                            <div class="form-text">Maximum available: <?= $resourceAmount ?></div>
                                        </div>
                                        
                                        <button type="submit" name="gather" class="btn btn-primary">Gather More</button>
                                        <a href="index.php?page=world_map" class="btn btn-secondary">Return to Map</a>
                                    </form>
                                <?php else: ?>
                                    <p>This location has been depleted of all resources.</p>
                                    <a href="index.php?page=world_map" class="btn btn-primary">Return to Map</a>
                                <?php endif; ?>
                            <?php elseif (isset($mapCell) && isset($resourceType) && isset($resourceAmount)): ?>
                                <div class="resource-info mb-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <h4>Resource Information</h4>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="resource-icon resource-<?= $resourceType ?> me-3"></div>
                                                <div>
                                                    <h5 class="mb-0"><?= ucfirst($resourceType) ?></h5>
                                                    <p class="mb-0">Amount Available: <?= $resourceAmount ?></p>
                                                </div>
                                            </div>
                                            
                                            <p>
                                                <?php
                                                switch ($resourceType) {
                                                    case 'wood':
                                                        echo "Wood is a basic construction resource used for buildings and training certain units.";
                                                        break;
                                                    case 'iron':
                                                        echo "Iron is used for weapons, armor, and advanced military units.";
                                                        break;
                                                    case 'food':
                                                        echo "Food is essential for maintaining and training your army.";
                                                        break;
                                                    case 'oil':
                                                        echo "Oil powers advanced vehicles and is used in high-tech production.";
                                                        break;
                                                    case 'stone':
                                                        echo "Stone is used for constructing defensive structures and certain buildings.";
                                                        break;
                                                    default:
                                                        echo "This resource is useful for your city's development.";
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="gather_amount" class="form-label">Amount to Gather</label>
                                        <input type="number" class="form-control" id="gather_amount" name="gather_amount" 
                                            min="1" max="<?= $resourceAmount ?>" value="<?= min($resourceAmount, 100) ?>">
                                        <div class="form-text">Maximum available: <?= $resourceAmount ?></div>
                                    </div>
                                    
                                    <button type="submit" name="gather" class="btn btn-primary">Gather Resources</button>
                                    <a href="index.php?page=world_map" class="btn btn-secondary">Cancel</a>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>Your Resources</h3>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="resource-icon resource-wood me-2"></div>
                                        Wood
                                    </div>
                                    <span><?= $playerResources['wood'] ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="resource-icon resource-iron me-2"></div>
                                        Iron
                                    </div>
                                    <span><?= $playerResources['iron'] ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="resource-icon resource-food me-2"></div>
                                        Food
                                    </div>
                                    <span><?= $playerResources['food'] ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="resource-icon resource-oil me-2"></div>
                                        Oil
                                    </div>
                                    <span><?= $playerResources['oil'] ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="resource-icon resource-stone me-2"></div>
                                        Stone
                                    </div>
                                    <span><?= $playerResources['stone'] ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>Gathering Tips</h3>
                        </div>
                        <div class="card-body">
                            <ul>
                                <li>You can gather resources from various locations on the world map.</li>
                                <li>Different terrain types yield different resources.</li>
                                <li>Resources gradually replenish over time.</li>
                                <li>Balance your resource gathering based on your needs.</li>
                                <li>Be strategic about which resources to prioritize.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
    .resource-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
    }
    
    .resource-wood {
        background-color: #563c1e;
    }
    
    .resource-iron {
        background-color: #737373;
    }
    
    .resource-food {
        background-color: #f0c479;
    }
    
    .resource-oil {
        background-color: #333333;
    }
    
    .resource-stone {
        background-color: #9c9c9c;
    }
</style>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>
