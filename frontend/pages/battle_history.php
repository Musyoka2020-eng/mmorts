<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Add battle-related CSS and Font Awesome
echo '<link rel="stylesheet" href="frontend/design/css/battle-history.css">';
echo '<link rel="stylesheet" href="frontend/design/css/battle-summary.css">';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php?page=login&msg=' . urlencode('You must be logged in to access this page.'));
    exit;
}

// Get player ID
$playerId = $_SESSION['user']['id'];

// Get all battles involving this player with pagination
$page = isset($_GET['battle_page']) ? max(1, intval($_GET['battle_page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$query = "SELECT * FROM battles 
         WHERE (attacker_id = ? AND attacker_type = 'player') 
            OR (defender_id = ? AND defender_type = 'player')
         ORDER BY battle_date DESC 
         LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $playerId, $playerId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$battles = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $battles[] = $row;
    }
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM battles 
              WHERE (attacker_id = ? AND attacker_type = 'player') 
                 OR (defender_id = ? AND defender_type = 'player')";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("ii", $playerId, $playerId);
$countStmt->execute();
$totalBattles = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalBattles / $limit);

// Calculate stats
$wins = 0;
$losses = 0;
$totalDamageDealt = 0;
$totalResourcesGained = 0;

foreach ($battles as $battle) {
    $isAttacker = ($battle['attacker_type'] === 'player' && $battle['attacker_id'] == $playerId);
    $playerWon = ($isAttacker && $battle['battle_result'] === 'attacker_victory') ||
        (!$isAttacker && $battle['battle_result'] === 'defender_victory');

    if ($playerWon) $wins++;
    else $losses++;

    // Calculate resources gained (simplified)
    $resources = json_decode($battle['resources_plundered'] ?? '{}', true);
    if (is_array($resources)) {
        $totalResourcesGained += array_sum($resources);
    }
}
?>

<div class="main battle-history-main">
    <section class="content py-4">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="battle-history-header">
                        <div class="header-content">
                            <h1 class="page-title">
                                <i class="fas fa-skull"></i>
                                Battle Chronicles
                            </h1>
                            <p class="page-subtitle">Your complete combat history and achievements</p>
                        </div>
                        <div class="header-stats">
                            <div class="stat-card wins">
                                <div class="stat-number"><?= $wins ?></div>
                                <div class="stat-label">Victories</div>
                            </div>
                            <div class="stat-card losses">
                                <div class="stat-number"><?= $losses ?></div>
                                <div class="stat-label">Defeats</div>
                            </div>
                            <div class="stat-card winrate">
                                <div class="stat-number"><?= ($wins + $losses) > 0 ? round(($wins / ($wins + $losses)) * 100) : 0 ?>%</div>
                                <div class="stat-label">Win Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="battle-filters">
                        <div class="filter-group">
                            <button class="filter-btn active" data-filter="all">
                                <i class="fas fa-list"></i> All Battles
                            </button>
                            <button class="filter-btn" data-filter="wins">
                                <i class="fas fa-trophy"></i> Victories
                            </button>
                            <button class="filter-btn" data-filter="losses">
                                <i class="fas fa-skull"></i> Defeats
                            </button>
                            <button class="filter-btn" data-filter="recent">
                                <i class="fas fa-clock"></i> Recent
                            </button>
                        </div>
                        <div class="search-group">
                            <input type="text" class="search-input" placeholder="Search battles..." id="battleSearch">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Battle List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($battles)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-shield"></i>
                            </div>
                            <h3>No Battles Yet</h3>
                            <p>Your combat history will appear here once you participate in battles.</p>
                            <a href="index.php?page=world_map" class="btn btn-primary">
                                <i class="fas fa-map"></i> Explore World Map
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="battle-timeline">
                            <?php foreach ($battles as $index => $battle): ?>
                                <?php
                                // Initialize all variables to prevent undefined errors
                                $attackerUnitsLost = [];
                                $defenderUnitsLost = [];
                                $resourcesPlundered = [];
                                $attackerName = "Unknown";
                                $defenderName = "Unknown";
                                // Determine player role and victory
                                $isAttacker = ($battle['attacker_type'] === 'player' && $battle['attacker_id'] == $playerId);
                                $isDefender = ($battle['defender_type'] === 'player' && $battle['defender_id'] == $playerId);
                                $playerWon = ($isAttacker && $battle['battle_result'] === 'attacker_victory') ||
                                    ($isDefender && $battle['battle_result'] === 'defender_victory');

                                // Get participant names
                                if ($battle['attacker_type'] === 'player') {
                                    if ($battle['attacker_id'] == $playerId) {
                                        $attackerName = 'You';
                                    } else {
                                        $query = "SELECT name FROM players WHERE id = ?";
                                        $stmt = $conn->prepare($query);
                                        $stmt->bind_param("i", $battle['attacker_id']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result && $result->num_rows === 1) {
                                            $attackerName = $result->fetch_assoc()['name'];
                                        }
                                    }
                                } else if ($battle['attacker_type'] === 'ai') {
                                    $query = "SELECT name FROM ai_players WHERE id = ?";
                                    $stmt = $conn->prepare($query);
                                    $stmt->bind_param("i", $battle['attacker_id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result && $result->num_rows === 1) {
                                        $attackerName = $result->fetch_assoc()['name'] . " (AI)";
                                    }
                                }

                                if ($battle['defender_type'] === 'player') {
                                    if ($battle['defender_id'] == $playerId) {
                                        $defenderName = 'You';
                                    } else {
                                        $query = "SELECT name FROM players WHERE id = ?";
                                        $stmt = $conn->prepare($query);
                                        $stmt->bind_param("i", $battle['defender_id']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result && $result->num_rows === 1) {
                                            $defenderName = $result->fetch_assoc()['name'];
                                        }
                                    }
                                } else if ($battle['defender_type'] === 'ai') {
                                    $query = "SELECT name FROM ai_players WHERE id = ?";
                                    $stmt = $conn->prepare($query);
                                    $stmt->bind_param("i", $battle['defender_id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result && $result->num_rows === 1) {
                                        $defenderName = $result->fetch_assoc()['name'] . " (AI)";
                                    }
                                }

                                // Parse battle data safely
                                $attackerUnitsLost = json_decode($battle['attacker_units_lost'] ?? '{}', true) ?: [];
                                $defenderUnitsLost = json_decode($battle['defender_units_lost'] ?? '{}', true) ?: [];
                                $resourcesPlundered = json_decode($battle['resources_plundered'] ?? '{}', true) ?: [];
                                // Calculate totals
                                $totalAttackerLosses = array_sum($attackerUnitsLost);
                                $totalDefenderLosses = array_sum($defenderUnitsLost);
                                $totalResourcesStolen = array_sum($resourcesPlundered);

                                // Battle intensity (based on total units lost)
                                $battleIntensity = min(100, ($totalAttackerLosses + $totalDefenderLosses) * 2);
                                ?>

                                <div class="battle-entry <?= $playerWon ? 'victory' : 'defeat' ?>" data-battle-id="<?= $battle['id'] ?>" style="animation-delay: <?= $index * 0.1 ?>s">
                                    <div class="battle-timeline-marker">
                                        <div class="timeline-icon <?= $playerWon ? 'victory-icon' : 'defeat-icon' ?>">
                                            <i class="fas <?= $playerWon ? 'fa-crown' : 'fa-skull' ?>"></i>
                                        </div>
                                    </div>

                                    <div class="battle-card-modern">
                                        <div class="battle-header-modern">
                                            <div class="battle-meta">
                                                <div class="battle-date-modern">
                                                    <i class="fas fa-calendar"></i>
                                                    <?= date("M d, Y", strtotime($battle['battle_date'])) ?>
                                                </div>
                                                <div class="battle-time">
                                                    <i class="fas fa-clock"></i>
                                                    <?= date("H:i", strtotime($battle['battle_date'])) ?>
                                                </div>
                                            </div>

                                            <div class="battle-result-modern <?= $playerWon ? 'victory' : 'defeat' ?>">
                                                <i class="fas <?= $playerWon ? 'fa-trophy' : 'fa-times-circle' ?>"></i>
                                                <?= $playerWon ? 'VICTORY' : 'DEFEAT' ?>
                                            </div>
                                        </div>

                                        <div class="battle-participants">
                                            <div class="participant attacker <?= $isAttacker ? 'player' : '' ?>">
                                                <div class="participant-header">
                                                    <i class="fas fa-skull"></i>
                                                    <span class="role">Attacker</span>
                                                </div>
                                                <div class="participant-name"><?= htmlspecialchars($attackerName) ?></div>
                                                <div class="participant-losses">
                                                    <i class="fas fa-heart-broken"></i>
                                                    <?= $totalAttackerLosses ?> units lost
                                                </div>
                                            </div>

                                            <div class="battle-vs">
                                                <div class="vs-indicator">VS</div>
                                                <div class="battle-intensity-bar">
                                                    <div class="intensity-fill" style="width: <?= $battleIntensity ?>%"></div>
                                                </div>
                                            </div>

                                            <div class="participant defender <?= $isDefender ? 'player' : '' ?>">
                                                <div class="participant-header">
                                                    <i class="fas fa-shield"></i>
                                                    <span class="role">Defender</span>
                                                </div>
                                                <div class="participant-name"><?= htmlspecialchars($defenderName) ?></div>
                                                <div class="participant-losses">
                                                    <i class="fas fa-heart-broken"></i>
                                                    <?= $totalDefenderLosses ?> units lost
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($totalResourcesStolen > 0): ?>
                                            <div class="battle-spoils">
                                                <div class="spoils-header">
                                                    <i class="fas fa-coins"></i>
                                                    Resources Plundered
                                                </div>
                                                <div class="spoils-list">
                                                    <?php foreach ($resourcesPlundered as $resource => $amount): ?>
                                                        <?php if ($amount > 0): ?>
                                                            <div class="spoil-item">
                                                                <img src="frontend/images/<?= $resource ?>.png" alt="<?= $resource ?>">
                                                                <span class="spoil-amount"><?= number_format($amount) ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="battle-actions">
                                            <button class="action-btn expand-btn" data-battle-id="<?= $battle['id'] ?>">
                                                <i class="fas fa-chevron-down"></i>
                                                View Details
                                            </button>
                                            <button class="action-btn report-btn" onclick="window.open('index.php?page=battle_report&id=<?= $battle['id'] ?>', '_blank')">
                                                <i class="fas fa-file-alt"></i>
                                                Full Report
                                            </button>
                                        </div>
                                        <div class="battle-details" id="details-<?= $battle['id'] ?>" style="display: none;">
                                            <div class="details-grid">
                                                <div class="detail-section">
                                                    <h4><i class="fas fa-users"></i> Unit Losses</h4>
                                                    <div class="units-breakdown">
                                                        <div class="army-section">
                                                            <h5><i class="fas fa-skull"></i> Attacker Losses</h5>
                                                            <div class="losses-container">
                                                                <?php if ($totalAttackerLosses > 0): ?>
                                                                    <div class="unit-losses-horizontal">
                                                                        <?php foreach ($attackerUnitsLost as $unit => $count): ?>
                                                                            <?php if ($count > 0): ?>
                                                                                <div class="unit-loss-compact">
                                                                                    <span class="unit-name"><?= ucfirst(str_replace('_', ' ', $unit)) ?></span>
                                                                                    <span class="unit-count"><?= number_format($count) ?></span>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="no-losses">
                                                                        <i class="fas fa-shield-alt"></i>
                                                                        <span>No units lost</span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="army-section">
                                                            <h5><i class="fas fa-shield"></i> Defender Losses</h5>
                                                            <div class="losses-container">
                                                                <?php if ($totalDefenderLosses > 0): ?>
                                                                    <div class="unit-losses-horizontal">
                                                                        <?php foreach ($defenderUnitsLost as $unit => $count): ?>
                                                                            <?php if ($count > 0): ?>
                                                                                <div class="unit-loss-compact">
                                                                                    <span class="unit-name"><?= ucfirst(str_replace('_', ' ', $unit)) ?></span>
                                                                                    <span class="unit-count"><?= number_format($count) ?></span>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="no-losses">
                                                                        <i class="fas fa-shield-alt"></i>
                                                                        <span>No units lost</span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php if (!empty($battle['battle_report'])): ?> <div class="detail-section">
                                                        <h4><i class="fas fa-scroll"></i> Battle Summary</h4>
                                                        <div class="battle-summary">
                                                            <?php
                                                            // Parse and format battle report into a comprehensive summary sentence
                                                            $reportText = $battle['battle_report'];

                                                            // Extract the basic info using regex
                                                            if (preg_match('/Battle Report([^A]*)Attacker: ([^D]*)Defender: ([^T]*)(The [^C]*)(Casualties[^R]*)(Resources[^$]*)/s', $reportText, $matches)) {
                                                                $battleInfo = trim($matches[1]);
                                                                $attacker = trim($matches[2]);
                                                                $defender = trim($matches[3]);
                                                                $result = trim($matches[4]);
                                                                $casualties = trim($matches[5]);
                                                                $resources = trim($matches[6]);

                                                                // Extract date from battle info
                                                                $dateInfo = '';
                                                                if (preg_match('/(\w+ \d+, \d+, \d+:\d+ [ap]m)/', $battleInfo, $dateMatch)) {
                                                                    $dateInfo = $dateMatch[1];
                                                                }

                                                                // Format casualties into a readable string
                                                                $casualtySummary = '';
                                                                $attackerLosses = [];
                                                                $defenderLosses = [];

                                                                if (!empty($casualties) && $casualties !== 'Casualties') {
                                                                    $casualtyLines = explode("\n", $casualties);
                                                                    foreach ($casualtyLines as $line) {
                                                                        $line = trim($line);
                                                                        if (!empty($line) && $line !== 'Casualties' && strpos($line, 'Attacker') !== false) {
                                                                            $attackerLosses[] = trim(str_replace('Attacker Losses:', '', $line));
                                                                        } elseif (!empty($line) && $line !== 'Casualties' && strpos($line, 'Defender') !== false) {
                                                                            $defenderLosses[] = trim(str_replace('Defender Losses:', '', $line));
                                                                        }
                                                                    }
                                                                }

                                                                // Format resources into a readable string
                                                                $resourceSummary = '';
                                                                if (!empty($resources) && $resources !== 'Resources' && strpos($resources, 'plundered') !== false) {
                                                                    $resourceSummary = ' and plundered resources';
                                                                }

                                                                // Generate a comprehensive summary sentence
                                                                echo "<p>On <span class='highlight'>" . htmlspecialchars($dateInfo) . "</span>, ";
                                                                echo "<span class='highlight'>" . htmlspecialchars($attacker) . "</span> attacked ";
                                                                echo "<span class='highlight'>" . htmlspecialchars($defender) . "</span>. ";

                                                                // Determine victory/defeat and color accordingly
                                                                $resultClass = 'victory';
                                                                if (
                                                                    stripos($result, 'defender successfully') !== false ||
                                                                    stripos($result, 'defender repelled') !== false
                                                                ) {
                                                                    $resultClass = 'defeat';
                                                                }

                                                                echo "<span class='" . $resultClass . "'>" . htmlspecialchars($result) . "</span> ";

                                                                // Add casualties if available
                                                                if (count($attackerLosses) > 0 || count($defenderLosses) > 0) {
                                                                    echo "In the battle, ";

                                                                    if (count($attackerLosses) > 0) {
                                                                        echo "the attacker lost <span class='casualties'>";
                                                                        echo htmlspecialchars(implode(', ', $attackerLosses));
                                                                        echo "</span>";

                                                                        if (count($defenderLosses) > 0) {
                                                                            echo " while ";
                                                                        }
                                                                    }

                                                                    if (count($defenderLosses) > 0) {
                                                                        echo "the defender lost <span class='casualties'>";
                                                                        echo htmlspecialchars(implode(', ', $defenderLosses));
                                                                        echo "</span>";
                                                                    }

                                                                    echo ".";
                                                                }

                                                                // Add resource information if available
                                                                if (!empty($resourceSummary)) {
                                                                    echo " <span class='resources'>" . htmlspecialchars($resourceSummary) . "</span>.";
                                                                }
                                                                echo "</p>";
                                                            } else {
                                                                // Fallback: clean the text and display normally
                                                                $cleanText = strip_tags($reportText);
                                                                $cleanText = preg_replace('/\s+/', ' ', $cleanText);
                                                                $shortReport = strlen($cleanText) > 250 ? substr($cleanText, 0, 250) . '...' : $cleanText;
                                                                echo "<p>" . htmlspecialchars($shortReport) . "</p>";
                                                            }
                                                            ?>
                                                            <?php if (strlen($battle['battle_report']) > 250): ?>
                                                                <div class="read-more">
                                                                    <a href="index.php?page=battle_report&id=<?= $battle['id'] ?>" target="_blank" class="read-more-link">
                                                                        <i class="fas fa-external-link-alt"></i> Read full report
                                                                    </a>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="battle-pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=battle_history&battle_page=<?= $i ?>"
                                        class="page-btn <?= $i === $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Back Button -->
            <div class="row mt-4">
                <div class="col-12 text-center">
                    <a href="index.php?page=world_map" class="btn btn-primary btn-lg">
                        <i class="fas fa-map"></i>
                        Return to World Map
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="frontend/design/js/battle-history.js"></script>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>