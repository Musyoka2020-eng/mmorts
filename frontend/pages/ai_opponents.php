<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Check if game is initialized
$query = "SELECT game_initialized FROM configuration WHERE id = 1";
$result = $conn->query($query);
$isInitialized = false;

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $isInitialized = $row['game_initialized'] == 1;
}

// Get all AI players if the game is initialized
$aiPlayers = [];
if ($isInitialized) {
    $query = "SELECT ap.id, ap.name, ap.personality_type, ap.difficulty_level, COUNT(ac.id) as city_count 
             FROM ai_players ap
             LEFT JOIN ai_cities ac ON ap.id = ac.ai_player_id
             WHERE ap.active = 1
             GROUP BY ap.id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $aiPlayers[] = $row;
            
            // Get details for this AI player's cities
            $query = "SELECT ac.id, ac.name, ac.location_x, ac.location_y, ac.power 
                     FROM ai_cities ac 
                     WHERE ac.ai_player_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $row['id']);
            $stmt->execute();
            $cityResult = $stmt->get_result();
            
            $aiPlayers[count($aiPlayers) - 1]['cities'] = [];
            if ($cityResult && $cityResult->num_rows > 0) {
                while ($cityRow = $cityResult->fetch_assoc()) {
                    $aiPlayers[count($aiPlayers) - 1]['cities'][] = $cityRow;
                }
            }
            
            // Get battle count involving this AI player
            $query = "SELECT COUNT(*) as battle_count 
                    FROM battles 
                    WHERE (attacker_id = ? AND attacker_type = 'ai') 
                       OR (defender_id = ? AND defender_type = 'ai')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $row['id'], $row['id']);
            $stmt->execute();
            $battleResult = $stmt->get_result();
            
            if ($battleResult && $battleResult->num_rows > 0) {
                $aiPlayers[count($aiPlayers) - 1]['battle_count'] = $battleResult->fetch_assoc()['battle_count'];
            } else {
                $aiPlayers[count($aiPlayers) - 1]['battle_count'] = 0;
            }
            
            // Get win/loss record
            $query = "SELECT 
                     SUM(CASE WHEN (attacker_id = ? AND attacker_type = 'ai' AND battle_result = 'attacker_victory') 
                              OR (defender_id = ? AND defender_type = 'ai' AND battle_result = 'defender_victory') 
                              THEN 1 ELSE 0 END) as wins,
                     SUM(CASE WHEN (attacker_id = ? AND attacker_type = 'ai' AND battle_result = 'defender_victory') 
                              OR (defender_id = ? AND defender_type = 'ai' AND battle_result = 'attacker_victory') 
                              THEN 1 ELSE 0 END) as losses
                    FROM battles 
                    WHERE (attacker_id = ? AND attacker_type = 'ai') 
                       OR (defender_id = ? AND defender_type = 'ai')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiiiii", $row['id'], $row['id'], $row['id'], $row['id'], $row['id'], $row['id']);
            $stmt->execute();
            $recordResult = $stmt->get_result();
            
            if ($recordResult && $recordResult->num_rows > 0) {
                $record = $recordResult->fetch_assoc();
                $aiPlayers[count($aiPlayers) - 1]['wins'] = $record['wins'] ?: 0;
                $aiPlayers[count($aiPlayers) - 1]['losses'] = $record['losses'] ?: 0;
            } else {
                $aiPlayers[count($aiPlayers) - 1]['wins'] = 0;
                $aiPlayers[count($aiPlayers) - 1]['losses'] = 0;
            }
        }
    }
}

// Add AI Opponents CSS
echo '<link rel="stylesheet" href="frontend/design/css/ai-opponents.css">';
?>

<div class="main">
    <section class="content py-3">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3>AI Opponents</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!$isInitialized): ?>
                                <div class="alert alert-warning">
                                    <h4>Game World Not Initialized</h4>
                                    <p>The game world must be initialized before you can view AI opponents.</p>
                                    <a href="index.php?page=initialize_world" class="btn btn-primary">Initialize Game World</a>
                                </div>
                            <?php elseif (empty($aiPlayers)): ?>
                                <div class="alert alert-info">
                                    <h4>No AI Opponents</h4>
                                    <p>There are no AI opponents in the game world. Try initializing the game world again.</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($aiPlayers as $ai): ?>
                                        <div class="col-md-6">
                                            <div class="ai-card">
                                                <div class="ai-header">
                                                    <div class="ai-name"><?= htmlspecialchars($ai['name']) ?></div>
                                                    
                                                    <div class="ai-personality">
                                                        <div class="personality-badge <?= $ai['personality_type'] ?>" data-personality="<?= $ai['personality_type'] ?>">
                                                            <?= ucfirst($ai['personality_type']) ?>
                                                        </div>
                                                        
                                                        <div class="difficulty-container">
                                                            <div class="difficulty-stars" data-level="<?= $ai['difficulty_level'] ?>">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <div class="star <?= $i <= $ai['difficulty_level'] ? 'star-active' : '' ?>">★</div>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="ai-content">
                                                    <p>Controls <?= $ai['city_count'] ?> <?= $ai['city_count'] == 1 ? 'city' : 'cities' ?>. 
                                                       Battle record: <?= $ai['wins'] ?> wins, <?= $ai['losses'] ?> losses.</p>
                                                    
                                                    <?php if (!empty($ai['cities'])): ?>
                                                        <div class="ai-cities">
                                                            <h5>Cities:</h5>
                                                            <?php foreach ($ai['cities'] as $city): ?>
                                                                <div class="city-item">
                                                                    <div>
                                                                        <div class="city-name"><?= htmlspecialchars($city['name']) ?></div>
                                                                        <div class="city-location">Location: [<?= $city['location_x'] ?>, <?= $city['location_y'] ?>]</div>
                                                                    </div>
                                                                    <div>
                                                                        <div class="city-strength">
                                                                            <div class="city-strength-bar" data-strength="<?= $city['power'] ?: rand(10, 90) ?>"></div>
                                                                        </div>
                                                                        <div class="city-strength-label">Strength</div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="text-center mt-3">
                                                        <button class="view-ai-details" data-ai-id="<?= $ai['id'] ?>">View Details</button>
                                                    </div>
                                                </div>
                                                
                                                <div id="ai-details-<?= $ai['id'] ?>" class="ai-details-container d-none">
                                                    <div class="ai-stats">
                                                        <div class="stat-item">
                                                            <div class="stat-value"><?= $ai['city_count'] ?></div>
                                                            <div class="stat-label">Cities</div>
                                                        </div>
                                                        <div class="stat-item">
                                                            <div class="stat-value"><?= $ai['battle_count'] ?></div>
                                                            <div class="stat-label">Battles</div>
                                                        </div>
                                                        <div class="stat-item">
                                                            <div class="stat-value"><?= $ai['wins'] ?></div>
                                                            <div class="stat-label">Victories</div>
                                                        </div>
                                                        <div class="stat-item">
                                                            <div class="stat-value"><?= number_format(($ai['battle_count'] > 0 ? ($ai['wins'] / $ai['battle_count']) * 100 : 0), 1) ?>%</div>
                                                            <div class="stat-label">Win Rate</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="ai-strategy">
                                                        <div class="strategy-title">Strategy Analysis</div>
                                                        <div class="strategy-description">
                                                            <?php
                                                            // Generate a strategy description based on personality type
                                                            switch ($ai['personality_type']) {
                                                                case 'aggressive':
                                                                    echo "This AI focuses on rapid military expansion and frequent attacks. Expect regular raids on resource-rich territories and swift counterattacks if provoked. They prioritize offensive units over defensive structures.";
                                                                    break;
                                                                case 'defensive':
                                                                    echo "This AI builds strong defenses around their cities before launching any attacks. They prefer to turtle up and slowly expand their territory, making their cities difficult to conquer but potentially vulnerable to resource shortages.";
                                                                    break;
                                                                case 'balanced':
                                                                    echo "This AI maintains a balanced approach to city development and military operations. They are unpredictable in their attack patterns and adapt their strategy based on their opponents' actions.";
                                                                    break;
                                                                case 'economic':
                                                                    echo "This AI focuses primarily on resource gathering and economic development. They are vulnerable in the early game but become formidable if allowed to build up their economy unhindered.";
                                                                    break;
                                                                default:
                                                                    echo "This AI's strategy is unclear. Proceed with caution and observe their patterns before committing to any major actions.";
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($ai['battle_count'] > 0): ?>
                                                        <div class="ai-battle-history">
                                                            <h5 class="mt-3 mb-2">Recent Battles:</h5>
                                                            <?php
                                                            // Get recent battles involving this AI
                                                            $query = "SELECT b.*, 
                                                                    CASE WHEN b.attacker_id = ? AND b.attacker_type = 'ai' THEN 'attacker' ELSE 'defender' END as ai_role,
                                                                    CASE WHEN (b.attacker_id = ? AND b.attacker_type = 'ai' AND b.battle_result = 'attacker_victory')
                                                                            OR (b.defender_id = ? AND b.defender_type = 'ai' AND b.battle_result = 'defender_victory')
                                                                            THEN 'victory' ELSE 'defeat' END as ai_result
                                                                    FROM battles b
                                                                    WHERE (b.attacker_id = ? AND b.attacker_type = 'ai')
                                                                        OR (b.defender_id = ? AND b.defender_type = 'ai')
                                                                    ORDER BY b.battle_date DESC
                                                                    LIMIT 5";
                                                            $stmt = $conn->prepare($query);
                                                            $stmt->bind_param("iiiii", $ai['id'], $ai['id'], $ai['id'], $ai['id'], $ai['id']);
                                                            $stmt->execute();
                                                            $battles = $stmt->get_result();
                                                            
                                                            if ($battles && $battles->num_rows > 0):
                                                                while ($battle = $battles->fetch_assoc()):
                                                                    // Get opponent name
                                                                    $opponentType = ($battle['ai_role'] === 'attacker') ? $battle['defender_type'] : $battle['attacker_type'];
                                                                    $opponentId = ($battle['ai_role'] === 'attacker') ? $battle['defender_id'] : $battle['attacker_id'];
                                                                    $opponentName = "Unknown";
                                                                    
                                                                    if ($opponentType === 'player') {
                                                                        $query = "SELECT name FROM players WHERE id = ?";
                                                                        $stmt = $conn->prepare($query);
                                                                        $stmt->bind_param("i", $opponentId);
                                                                        $stmt->execute();
                                                                        $opponent = $stmt->get_result();
                                                                        if ($opponent && $opponent->num_rows === 1) {
                                                                            $opponentName = $opponent->fetch_assoc()['name'];
                                                                        }
                                                                    } else if ($opponentType === 'ai') {
                                                                        $query = "SELECT name FROM ai_players WHERE id = ?";
                                                                        $stmt = $conn->prepare($query);
                                                                        $stmt->bind_param("i", $opponentId);
                                                                        $stmt->execute();
                                                                        $opponent = $stmt->get_result();
                                                                        if ($opponent && $opponent->num_rows === 1) {
                                                                            $opponentName = $opponent->fetch_assoc()['name'] . " (AI)";
                                                                        }
                                                                    }
                                                                    ?>
                                                                    <div class="battle-item">
                                                                        <div>
                                                                            <div class="battle-opponent"><?= htmlspecialchars($opponentName) ?></div>
                                                                            <div class="battle-date"><?= date("M d, Y", strtotime($battle['battle_date'])) ?></div>
                                                                        </div>
                                                                        <div class="battle-result result-<?= $battle['ai_result'] ?>">
                                                                            <?= ucfirst($battle['ai_result']) ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endwhile; ?>
                                                            <?php else: ?>
                                                                <p>No recent battle records available.</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <a href="index.php?page=world_map" class="btn btn-primary">Return to World Map</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="frontend/design/js/ai-opponents.js"></script>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
?>
