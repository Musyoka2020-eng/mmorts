<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

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
        
        // Decode JSON data
        $attackerLosses = json_decode($battle['attacker_units_lost'], true);
        $defenderLosses = json_decode($battle['defender_units_lost'], true);
        $resourcesPlundered = json_decode($battle['resources_plundered'], true);
        
        // Get battle report
        $battleReport = $battleManager->getBattleReport($battleId);
    } else {
        $error = "Battle not found or you don't have permission to view it.";
    }
} else {
    $error = "No battle ID provided.";
}
?>

<div class="main">
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
                                            <h4 class="text-center mb-4">
                                                <?php
                                                if ($battle['battle_result'] === 'attacker_victory') {
                                                    if ($battle['attacker_id'] == $playerId && $battle['attacker_type'] == 'player') {
                                                        echo '<span class="text-success">Victory!</span>';
                                                    } else {
                                                        echo '<span class="text-danger">Defeat!</span>';
                                                    }
                                                    echo ' The attacker was victorious.';
                                                } elseif ($battle['battle_result'] === 'defender_victory') {
                                                    if ($battle['defender_id'] == $playerId && $battle['defender_type'] == 'player') {
                                                        echo '<span class="text-success">Victory!</span>';
                                                    } else {
                                                        echo '<span class="text-danger">Defeat!</span>';
                                                    }
                                                    echo ' The defender successfully repelled the attack.';
                                                } else {
                                                    echo '<span class="text-warning">Draw!</span> Neither side gained a decisive advantage.';
                                                }
                                                ?>
                                            </h4>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h5>Attacker Losses</h5>
                                                    <ul class="list-group">
                                                        <?php foreach ($attackerLosses as $unit => $count): ?>
                                                            <?php if ($count > 0): ?>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <?= ucfirst($unit) ?>
                                                                    <span class="badge bg-danger rounded-pill"><?= $count ?></span>
                                                                </li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                        <?php if (array_sum($attackerLosses) === 0): ?>
                                                            <li class="list-group-item">No losses</li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <h5>Defender Losses</h5>
                                                    <ul class="list-group">
                                                        <?php foreach ($defenderLosses as $unit => $count): ?>
                                                            <?php if ($count > 0): ?>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <?= ucfirst($unit) ?>
                                                                    <span class="badge bg-danger rounded-pill"><?= $count ?></span>
                                                                </li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                        <?php if (array_sum($defenderLosses) === 0): ?>
                                                            <li class="list-group-item">No losses</li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <?php if ($battle['battle_result'] === 'attacker_victory' && !empty($resourcesPlundered)): ?>
                                                <div class="mt-4">
                                                    <h5>
                                                        <?php
                                                        if ($battle['attacker_id'] == $playerId && $battle['attacker_type'] == 'player') {
                                                            echo "Resources Plundered";
                                                        } else {
                                                            echo "Resources Lost";
                                                        }
                                                        ?>
                                                    </h5>
                                                    <ul class="list-group">
                                                        <?php foreach ($resourcesPlundered as $resource => $amount): ?>
                                                            <?php if ($amount > 0): ?>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <?= ucfirst($resource) ?>
                                                                    <span class="badge bg-success rounded-pill"><?= $amount ?></span>
                                                                </li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="battle-details">
                                    <div class="card">
                                        <div class="card-header">
                                            Detailed Report
                                        </div>
                                        <div class="card-body">
                                            <pre class="battle-report"><?= $battleReport ?></pre>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="index.php?page=battle" class="btn btn-primary">Back to Battle</a>
                                    <a href="index.php?page=world_map" class="btn btn-secondary">Return to Map</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
    .battle-report {
        font-family: monospace;
        white-space: pre-wrap;
        padding: 15px;
        background-color: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 5px;
    }
</style>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>
