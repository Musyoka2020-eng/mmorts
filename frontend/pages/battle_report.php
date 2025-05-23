<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Add battle report CSS
echo '<link rel="stylesheet" href="frontend/design/css/battle-report.css">';
// Add resource plunder styles
echo '<link rel="stylesheet" href="frontend/design/css/resource-plunder.css">';
// Add Font Awesome if not already included
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">';

// Include battle manager
require_once __DIR__ . '/../../backend/combat/battle_manager.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php?page=login&msg=' . urlencode('You must be logged in to access this page.'));
    exit;
}

// Get player ID
$playerId = $_SESSION['user']['id'];

// Initialize battle manager
$battleManager = new BattleManager($conn);

// Check if battle ID is provided
if (isset($_GET['id'])) {
    $battleId = $_GET['id'];

    // Get battle details
    $query = "SELECT * FROM battles WHERE id = ? AND 
             ((attacker_id = ? AND attacker_type = 'player') OR (defender_id = ? AND defender_type = 'player'))";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $battleId, $playerId, $playerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $battle = $result->fetch_assoc();

        // Decode JSON data with error handling
        $attackerLosses = [];
        $defenderLosses = [];
        $resourcesPlundered = [];        try {
            $attackerLosses = json_decode($battle['attacker_units_lost'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($attackerLosses) || $attackerLosses === 0) {
                error_log('Error decoding attacker losses JSON: ' . json_last_error_msg());
                $attackerLosses = ['fighters' => 0, 'shooters' => 0, 'vehicles' => 0, 'skmisher' => 0, 'rides' => 0, 'canons' => 0, 'jets' => 0, 'archers' => 0, 'marauders' => 0];
            }

            // Ensure all values are numeric
            foreach ($attackerLosses as $key => $value) {
                $attackerLosses[$key] = intval($value);
            }
        } catch (Exception $e) {
            error_log('Exception decoding attacker losses: ' . $e->getMessage());
            $attackerLosses = ['fighters' => 0, 'shooters' => 0, 'vehicles' => 0];
        }
        try {
            $defenderLosses = json_decode($battle['defender_units_lost'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($defenderLosses)) {
                error_log('Error decoding defender losses JSON: ' . json_last_error_msg());
                $defenderLosses = ['fighters' => 0, 'shooters' => 0, 'vehicles' => 0];
            }

            // Ensure all values are numeric
            foreach ($defenderLosses as $key => $value) {
                $defenderLosses[$key] = intval($value);
            }
        } catch (Exception $e) {
            error_log('Exception decoding defender losses: ' . $e->getMessage());
            $defenderLosses = ['fighters' => 0, 'shooters' => 0, 'vehicles' => 0];
        }
        try {
            $resourcesPlundered = json_decode($battle['resources_plundered'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($resourcesPlundered)) {
                error_log('Error decoding resources plundered JSON: ' . json_last_error_msg());
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
        } catch (Exception $e) {
            error_log('Exception decoding resources plundered: ' . $e->getMessage());
            $resourcesPlundered = ['wood' => 0, 'oil' => 0, 'iron' => 0, 'food' => 0, 'stone' => 0];
        }

        // Get battle report
        $battleReport = $battleManager->getBattleReport($battleId);
    } else {
        $error = "Battle not found or you don't have permission to view it.";
    }
} else {
    $error = "No battle ID provided.";
}
?>

<div class="main battle-report-page">
    <section class="content py-3">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-header">
                            <h3>Battle Report</h3>
                            <?php if (isset($battle)): ?>
                                <p class="text-muted mb-0">
                                    <?= date('F j, Y, g:i a', strtotime($battle['battle_date'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                                <a href="index.php?page=home" class="btn btn-primary">Return to Home</a>
                            <?php elseif (isset($battle)): ?>
                                <div class="battle-participants mb-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header bg-primary text-white">
                                                    Attacker
                                                </div>
                                                <div class="card-body">
                                                    <h5>
                                                        <?php
                                                        if ($battle['attacker_id'] == $playerId && $battle['attacker_type'] == 'player') {
                                                            echo "Your Forces";
                                                        } else {
                                                            // Get AI city name
                                                            $query = "SELECT name FROM ai_cities WHERE id = ?";
                                                            $stmt = $conn->prepare($query);
                                                            $stmt->bind_param("i", $battle['attacker_id']);
                                                            $stmt->execute();
                                                            $result = $stmt->get_result();
                                                            $row = $result->fetch_assoc();
                                                            echo $row['name'] ?? "AI Forces";
                                                        }
                                                        ?>
                                                    </h5>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card">
                                                <div class="card-header bg-danger text-white">
                                                    Defender
                                                </div>
                                                <div class="card-body">
                                                    <h5>
                                                        <?php
                                                        if ($battle['defender_id'] == $playerId && $battle['defender_type'] == 'player') {
                                                            echo "Your Forces";
                                                        } else {
                                                            // Get AI city name
                                                            $query = "SELECT name FROM ai_cities WHERE id = ?";
                                                            $stmt = $conn->prepare($query);
                                                            $stmt->bind_param("i", $battle['defender_id']);
                                                            $stmt->execute();
                                                            $result = $stmt->get_result();
                                                            $row = $result->fetch_assoc();
                                                            echo $row['name'] ?? "AI Forces";
                                                        }
                                                        ?>
                                                    </h5>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="battle-result mb-4">
                                    <div class="card">
                                        <div class="card-header">
                                            Battle Outcome
                                        </div>
                                        <div class="card-body">
                                            <div class="battle-outcome-banner mb-4">
                                                <?php
                                                $outcomeClass = '';
                                                $outcomeIcon = '';
                                                $outcomeText = '';

                                                if ($battle['battle_result'] === 'attacker_victory') {
                                                    if ($battle['attacker_id'] == $playerId && $battle['attacker_type'] == 'player') {
                                                        $outcomeClass = 'victory';
                                                        $outcomeIcon = 'trophy';
                                                        $outcomeText = 'Victory!';
                                                        $outcomeDescription = 'Your forces have prevailed on the battlefield!';
                                                    } else {
                                                        $outcomeClass = 'defeat';
                                                        $outcomeIcon = 'skull-crossbones';
                                                        $outcomeText = 'Defeat!';
                                                        $outcomeDescription = 'Your defenses were overwhelmed by the enemy.';
                                                    }
                                                } elseif ($battle['battle_result'] === 'defender_victory') {
                                                    if ($battle['defender_id'] == $playerId && $battle['defender_type'] == 'player') {
                                                        $outcomeClass = 'victory';
                                                        $outcomeIcon = 'shield-alt';
                                                        $outcomeText = 'Victory!';
                                                        $outcomeDescription = 'Your defenses have held strong against the attack!';
                                                    } else {
                                                        $outcomeClass = 'defeat';
                                                        $outcomeIcon = 'flag';
                                                        $outcomeText = 'Defeat!';
                                                        $outcomeDescription = 'Your attack was repelled by the enemy defenses.';
                                                    }
                                                } else {
                                                    $outcomeClass = 'draw';
                                                    $outcomeIcon = 'balance-scale';
                                                    $outcomeText = 'Draw!';
                                                    $outcomeDescription = 'Neither side gained a decisive advantage.';
                                                }
                                                ?>
                                                <div class="outcome-container <?= $outcomeClass ?>">
                                                    <div class="outcome-icon">
                                                        <i class="fas fa-<?= $outcomeIcon ?>"></i>
                                                    </div>
                                                    <div class="outcome-text">
                                                        <h3><?= $outcomeText ?></h3>
                                                        <p><?= $outcomeDescription ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="battle-outcome-details">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="loss-card attacker mb-4">
                                                            <h5 class="loss-header attacker-header">
                                                                <i class="fas fa-shield-alt"></i> Attacker Losses
                                                            </h5>
                                                            <ul class="list-group unit-list">
                                                                <?php foreach ($attackerLosses as $unit => $count): ?>
                                                                    <?php if ($count > 0): ?>
                                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                            <div class="unit-name">
                                                                                <i class="fas fa-<?= getUnitIcon($unit) ?>"></i> <?= ucfirst($unit) ?>
                                                                            </div>
                                                                            <span class="badge bg-danger rounded-pill"><?= $count ?></span>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                                <?php
                                                                // Check if there are any losses
                                                                $hasLosses = false;
                                                                foreach ($attackerLosses as $unit => $count) {
                                                                    if ($count > 0) {
                                                                        $hasLosses = true;
                                                                        break;
                                                                    }
                                                                }
                                                                if (!$hasLosses):
                                                                ?>
                                                                    <li class="list-group-item no-losses">
                                                                        <i class="fas fa-check-circle"></i> No losses
                                                                    </li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="loss-card defender mb-4">
                                                            <h5 class="loss-header defender-header">
                                                                <i class="fas fa-skull-crossbones"></i> Defender Losses
                                                            </h5>
                                                            <ul class="list-group unit-list">
                                                                <?php foreach ($defenderLosses as $unit => $count): ?>
                                                                    <?php if ($count > 0): ?>
                                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                            <div class="unit-name">
                                                                                <i class="fas fa-<?= getUnitIcon($unit) ?>"></i> <?= ucfirst($unit) ?>
                                                                            </div>
                                                                            <span class="badge bg-danger rounded-pill"><?= $count ?></span>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                                <?php
                                                                // Check if there are any losses
                                                                $hasLosses = false;
                                                                foreach ($defenderLosses as $unit => $count) {
                                                                    if ($count > 0) {
                                                                        $hasLosses = true;
                                                                        break;
                                                                    }
                                                                }
                                                                if (!$hasLosses):
                                                                ?>
                                                                    <li class="list-group-item no-losses">
                                                                        <i class="fas fa-check-circle"></i> No losses
                                                                    </li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>                                                <?php 
                                                // Always show the resources section, even if there are no resources or no victory
                                                ?>
                                                    <div class="resources-section mt-4">                                                        <h5 class="resources-header">
                                                            <i class="fas fa-coins"></i>
                                                            <?php
                                                            // Check if any resources were actually plundered
                                                            $anyResourcesPlundered = false;
                                                            if (is_array($resourcesPlundered)) {
                                                                foreach ($resourcesPlundered as $amount) {
                                                                    if ($amount > 0) {
                                                                        $anyResourcesPlundered = true;
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                            
                                                            if ($anyResourcesPlundered) {
                                                                if ($battle['attacker_id'] == $playerId && $battle['attacker_type'] == 'player') {
                                                                    echo "Resources Plundered";
                                                                } else {
                                                                    echo "Resources Lost";
                                                                }
                                                            } else {
                                                                echo "Resources (None Plundered)";
                                                            }
                                                            ?>
                                                        </h5>
                                                        <div class="resources-grid">                                                            <?php 
                                                            $resourcesDisplayed = false;
                                                            // Always check for all resources regardless of battle outcome
                                                            // This ensures consistency with the detailed report
                                                            if (is_array($resourcesPlundered)) {
                                                                foreach ($resourcesPlundered as $resource => $amount): 
                                                                    if ($amount > 0): 
                                                                        $resourcesDisplayed = true;
                                                                        ?>
                                                                        <div class="resource-card">
                                                                            <div class="resource-icon">
                                                                                <i class="fas fa-<?= getResourceIcon($resource) ?>"></i>
                                                                            </div>
                                                                            <div class="resource-details">
                                                                                <div class="resource-name"><?= ucfirst($resource) ?></div>
                                                                                <div class="resource-amount"><?= number_format($amount) ?></div>
                                                                            </div>
                                                                        </div>
                                                                    <?php 
                                                                    endif; 
                                                                endforeach;
                                                            }
                                                            
                                                            // If no resources were displayed, show a message
                                                            if (!$resourcesDisplayed): ?>
                                                                <div class="no-resources">
                                                                    <i class="fas fa-info-circle"></i> No resources were plundered in this battle.
                                                                </div>
                                                            <?php endif; ?>                                                        </div>
                                                    </div>
                                            </div>

                                            <?php
                                            // Helper function to get Font Awesome icon for units
                                            function getUnitIcon($unit)
                                            {
                                                switch (strtolower($unit)) {
                                                    case 'fighters':
                                                        return 'user-shield';
                                                    case 'shooters':
                                                        return 'crosshairs';
                                                    case 'vehicles':
                                                        return 'truck-monster';
                                                    case 'skmisher':
                                                        return 'running';
                                                    case 'rides':
                                                        return 'horse';
                                                    case 'canons':
                                                        return 'bomb';
                                                    case 'jets':
                                                        return 'fighter-jet';
                                                    case 'archers':
                                                        return 'bow-arrow';
                                                    case 'marauders':
                                                        return 'user-ninja';
                                                    default:
                                                        return 'user-alt';
                                                }
                                            }

                                            // Helper function to get Font Awesome icon for resources
                                            function getResourceIcon($resource)
                                            {
                                                switch (strtolower($resource)) {
                                                    case 'wood':
                                                        return 'tree';
                                                    case 'oil':
                                                        return 'oil-can';
                                                    case 'iron':
                                                        return 'hammer';
                                                    case 'food':
                                                        return 'drumstick-bite';
                                                    case 'stone':
                                                        return 'cubes';
                                                    default:
                                                        return 'box';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>                                <div class="battle-details">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Detailed Report</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="battle-report-container"><?= $battleReport ?? '' ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="action-buttons mt-4">
                                    <div class="d-flex justify-content-between">
                                        <a href="index.php?page=battle" class="btn btn-primary">
                                            Back to Battle
                                        </a>
                                        <a href="index.php?page=world_map" class="btn btn-secondary">
                                            Return to Map
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="frontend/design/js/battle-report-formatter.js" defer></script>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>