<?php
// This file is used via AJAX to get AI details

// Include necessary files
require_once __DIR__ . '/../../system/config.php';

// Check if AI ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid AI ID</div>';
    exit;
}

$aiId = (int)$_GET['id'];

// Get AI player details
$query = "SELECT * FROM ai_players WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $aiId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $ai = $result->fetch_assoc();
    
    // Get AI cities
    $query = "SELECT * FROM ai_cities WHERE ai_player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $aiId);
    $stmt->execute();
    $citiesResult = $stmt->get_result();
    
    $cities = [];
    if ($citiesResult && $citiesResult->num_rows > 0) {
        while ($row = $citiesResult->fetch_assoc()) {
            $cities[] = $row;
        }
    }
    
    // Display AI information
    ?>
    <div class="row">
        <div class="col-md-6">
            <h4><?= htmlspecialchars($ai['name']) ?></h4>
            <p><strong>Personality:</strong> <?= ucfirst(htmlspecialchars($ai['personality_type'])) ?></p>
            <p><strong>Difficulty:</strong> <?= str_repeat('★', $ai['difficulty_level']) ?></p>
            <p><strong>Status:</strong> <?= $ai['active'] ? 'Active' : 'Inactive' ?></p>
            
            <?php if ($ai['personality_type'] === 'aggressive'): ?>
                <div class="alert alert-danger">
                    <strong>Warning:</strong> This AI is aggressive and will frequently attack other players and AI opponents!
                </div>
            <?php elseif ($ai['personality_type'] === 'defensive'): ?>
                <div class="alert alert-info">
                    <strong>Note:</strong> This AI is defensive and will focus on building up its cities before attacking.
                </div>
            <?php else: ?>
                <div class="alert alert-secondary">
                    <strong>Note:</strong> This AI is balanced and will employ a mixed strategy of expansion and defense.
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <h5>Cities (<?= count($cities) ?>)</h5>
            <?php if (empty($cities)): ?>
                <p>This AI has no cities.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Power</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cities as $city): ?>
                                <tr>
                                    <td><?= htmlspecialchars($city['name']) ?></td>
                                    <td>(<?= $city['location_x'] ?>, <?= $city['location_y'] ?>)</td>
                                    <td><?= $city['power'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12">
            <h5>Military Strength</h5>
            <?php
            // Get AI armies
            $query = "SELECT SUM(fighters) as total_fighters, 
                           SUM(shooters) as total_shooters,
                           SUM(vehicles) as total_vehicles,
                           SUM(skmisher) as total_skmishers,
                           SUM(rides) as total_rides,
                           SUM(canons) as total_canons,
                           SUM(jets) as total_jets,
                           SUM(archers) as total_archers,
                           SUM(marauders) as total_marauders
                    FROM ai_armies a
                    JOIN ai_cities c ON a.ai_city_id = c.id
                    WHERE c.ai_player_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $aiId);
            $stmt->execute();
            $armiesResult = $stmt->get_result();
            
            if ($armiesResult && $armiesResult->num_rows === 1) {
                $armies = $armiesResult->fetch_assoc();
                
                // Display army composition
                ?>
                <div class="row">
                    <?php if ($armies['total_fighters'] > 0): ?>
                        <div class="col-md-3 mb-2">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6>Fighters</h6>
                                    <h4><?= $armies['total_fighters'] ?></h4>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($armies['total_shooters'] > 0): ?>
                        <div class="col-md-3 mb-2">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6>Shooters</h6>
                                    <h4><?= $armies['total_shooters'] ?></h4>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($armies['total_vehicles'] > 0): ?>
                        <div class="col-md-3 mb-2">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6>Vehicles</h6>
                                    <h4><?= $armies['total_vehicles'] ?></h4>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($armies['total_skmishers'] > 0): ?>
                        <div class="col-md-3 mb-2">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6>Skirmishers</h6>
                                    <h4><?= $armies['total_skmishers'] ?></h4>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Additional rows for other unit types as needed -->
                </div>
                <?php
            } else {
                echo '<p>No military information available.</p>';
            }
            ?>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12">
            <h5>Recent Actions</h5>
            <?php
            // Get recent AI actions
            $query = "SELECT * FROM ai_action_log 
                     WHERE ai_player_id = ? 
                     ORDER BY created_at DESC 
                     LIMIT 5";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $aiId);
            $stmt->execute();
            $actionsResult = $stmt->get_result();
            
            if ($actionsResult && $actionsResult->num_rows > 0) {
                ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($action = $actionsResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date("M d, Y H:i", strtotime($action['created_at'])) ?></td>
                                    <td><?= ucfirst($action['action_type']) ?></td>
                                    <td>
                                        <?php
                                        if ($action['target_type'] === 'player') {
                                            echo "Player at (" . $action['target_x'] . ", " . $action['target_y'] . ")";
                                        } else {
                                            echo ucfirst($action['target_type']) . " at (" . $action['target_x'] . ", " . $action['target_y'] . ")";
                                        }
                                        ?>
                                    </td>
                                    <td><?= $action['result'] ? htmlspecialchars($action['result']) : 'Pending' ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php
            } else {
                echo '<p>No recent actions.</p>';
            }
            ?>
        </div>
    </div>
    <?php
} else {
    echo '<div class="alert alert-danger">AI opponent not found</div>';
}
?>
