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
        
        // Unit costs and training limits
        $unitCosts = [
            'fighters' => ['wood' => 50, 'iron' => 30, 'food' => 20],
            'shooters' => ['wood' => 40, 'iron' => 50, 'food' => 20],
            'vehicles' => ['wood' => 100, 'iron' => 150, 'oil' => 50, 'food' => 30],
            'riders' => ['wood' => 80, 'iron' => 70, 'food' => 40],
            'canons' => ['wood' => 200, 'iron' => 300, 'oil' => 100, 'food' => 50],
        ];
        
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

<div class="main">
    <section class="content py-3">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3>Train Units</h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($errors) && !empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= $error ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($success)): ?>
                                <div class="alert alert-success"><?= $success ?></div>
                            <?php endif; ?>
                            
                            <form method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header">Basic Units</div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label for="train_fighters" class="form-label">Fighters</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="train_fighters" name="train_fighters" min="0" value="0">
                                                        <span class="input-group-text">
                                                            Wood: 50, Iron: 30, Food: 20
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="train_shooters" class="form-label">Shooters</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="train_shooters" name="train_shooters" min="0" value="0">
                                                        <span class="input-group-text">
                                                            Wood: 40, Iron: 50, Food: 20
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="card mb-3">
                                            <div class="card-header">Advanced Units</div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label for="train_vehicles" class="form-label">Vehicles</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="train_vehicles" name="train_vehicles" min="0" value="0">
                                                        <span class="input-group-text">
                                                            Wood: 100, Iron: 150, Oil: 50, Food: 30
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="train_riders" class="form-label">Riders</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="train_riders" name="train_riders" min="0" value="0">
                                                        <span class="input-group-text">
                                                            Wood: 80, Iron: 70, Food: 40
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="train_canons" class="form-label">Canons</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="train_canons" name="train_canons" min="0" value="0">
                                                        <span class="input-group-text">
                                                            Wood: 200, Iron: 300, Oil: 100, Food: 50
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header">Your Resources</div>
                                            <div class="card-body">
                                                <ul class="list-group">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Wood
                                                        <span id="wood_available"><?= $resources['wood'] ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Iron
                                                        <span id="iron_available"><?= $resources['iron'] ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Food
                                                        <span id="food_available"><?= $resources['food'] ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Oil
                                                        <span id="oil_available"><?= $resources['oil'] ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Stone
                                                        <span id="stone_available"><?= $resources['stone'] ?></span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="card mb-3">
                                            <div class="card-header">Current Army</div>
                                            <div class="card-body">
                                                <ul class="list-group">
                                                    <?php foreach ($army as $unit => $count): ?>
                                                        <?php if ($unit !== 'id' && $unit !== 'player_id' && $unit !== 'updated_at'): ?>
                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                <?= ucfirst($unit) ?>
                                                                <span><?= $count ?></span>
                                                            </li>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 mt-3">
                                    <button type="submit" name="train_units" class="btn btn-primary">Train Units</button>
                                    <a href="index.php?page=home" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>Training Information</h3>
                        </div>
                        <div class="card-body">
                            <p>Train new units to strengthen your army and defend your city or attack enemies.</p>
                            <h5>Unit Types</h5>
                            <ul>
                                <li><strong>Fighters:</strong> Basic infantry units with balanced attack and defense.</li>
                                <li><strong>Shooters:</strong> Ranged units with high attack but low defense.</li>
                                <li><strong>Vehicles:</strong> Heavy units with high defense and moderate attack.</li>
                                <li><strong>Riders:</strong> Fast cavalry units with moderate attack and defense.</li>
                                <li><strong>Canons:</strong> Artillery units with very high attack but low defense and mobility.</li>
                            </ul>
                            <h5>Training Tips</h5>
                            <ul>
                                <li>Balance your army with different unit types.</li>
                                <li>Consider the cost efficiency of each unit.</li>
                                <li>Make sure to gather enough resources before training large armies.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    // Calculate and update resource usage as user inputs numbers
    document.addEventListener('DOMContentLoaded', function() {
        const unitCosts = {
            'fighters': {'wood': 50, 'iron': 30, 'food': 20},
            'shooters': {'wood': 40, 'iron': 50, 'food': 20},
            'vehicles': {'wood': 100, 'iron': 150, 'oil': 50, 'food': 30},
            'riders': {'wood': 80, 'iron': 70, 'food': 40},
            'canons': {'wood': 200, 'iron': 300, 'oil': 100, 'food': 50}
        };
        
        const inputs = document.querySelectorAll('input[type="number"]');
        inputs.forEach(input => {
            input.addEventListener('input', updateResourceUsage);
        });
        
        function updateResourceUsage() {
            let totalUsage = {
                'wood': 0,
                'iron': 0,
                'food': 0,
                'oil': 0,
                'stone': 0
            };
            
            inputs.forEach(input => {
                const unitType = input.id.replace('train_', '');
                if (unitCosts[unitType]) {
                    const count = parseInt(input.value) || 0;
                    for (const resource in unitCosts[unitType]) {
                        totalUsage[resource] += unitCosts[unitType][resource] * count;
                    }
                }
            });
            
            // Update displayed remaining resources
            for (const resource in totalUsage) {
                const available = parseInt(document.getElementById(`${resource}_available`).textContent);
                const remaining = available - totalUsage[resource];
                document.getElementById(`${resource}_available`).textContent = available;
                if (remaining < 0) {
                    document.getElementById(`${resource}_available`).classList.add('text-danger');
                } else {
                    document.getElementById(`${resource}_available`).classList.remove('text-danger');
                }
            }
        }
    });
</script>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>
