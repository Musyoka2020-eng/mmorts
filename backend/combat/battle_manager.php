<?php

/**
 * Battle Manager Class
 * 
 * Handles combat between player and AI armies.
 */
class BattleManager
{
    private $conn;

    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Initiate a battle between two armies
     * 
     * @param int $attackerId ID of the attacker
     * @param string $attackerType Type of attacker (player, ai, npc)
     * @param int $defenderId ID of the defender
     * @param string $defenderType Type of defender (player, ai, npc)
     * @return array Battle result
     */    public function initiateBattle($attackerId, $attackerType, $defenderId, $defenderType)
    {
        try {
            // Validate parameters
            if (!$attackerId || !$attackerType || !$defenderId || !$defenderType) {
                throw new Exception("Invalid battle parameters provided");
            }

            // Get attacker armies
            $attackerArmies = $this->getArmies($attackerId, $attackerType);
            if (empty($attackerArmies) || $this->getTotalUnits($attackerArmies) <= 0) {
                throw new Exception("Attacker has no units to battle with");
            }

            // Get defender armies
            $defenderArmies = $this->getArmies($defenderId, $defenderType);
            if (empty($defenderArmies) || $this->getTotalUnits($defenderArmies) <= 0) {
                throw new Exception("Defender has no units to battle with");
            }

            // Start a database transaction
            // Using autocommit(false) instead of begin_transaction() for better compatibility
            $this->conn->autocommit(false);

            // Calculate battle result
            $battleResult = $this->calculateBattleResult($attackerArmies, $defenderArmies);

            // Record battle in database
            $this->recordBattle(
                $attackerId,
                $attackerType,
                $defenderId,
                $defenderType,
                $battleResult['result'],
                $battleResult['attacker_losses'],
                $battleResult['defender_losses'],
                $battleResult['resources_plundered']
            );

            // Update armies after battle
            $this->updateArmiesAfterBattle($attackerId, $attackerType, $battleResult['attacker_remaining']);
            $this->updateArmiesAfterBattle($defenderId, $defenderType, $battleResult['defender_remaining']);

            // If attacker won, transfer resources
            if ($battleResult['result'] === 'attacker_victory') {
                $this->transferResources($attackerId, $attackerType, $defenderId, $defenderType, $battleResult['resources_plundered']);
            }

            // Commit the transaction
            $this->conn->commit();
            return $battleResult;
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            try {
                $this->conn->rollback();
            } catch (Exception $rollbackEx) {
                // Rollback might fail if no transaction was active
                error_log("Battle rollback error: " . $rollbackEx->getMessage());
            }

            // Log the error
            error_log("Battle error: " . $e->getMessage());

            // Rethrow it
            throw $e;
        }
    }

    /**
     * Get armies for a player or AI
     * 
     * @param int $id Player or AI ID
     * @param string $type Type (player, ai, npc)
     * @return array Army data
     */
    private function getArmies($id, $type)
    {
        if ($type === 'player') {
            // Get player armies
            $query = "SELECT fighters, shooters, vehicles, skmisher, rides, canons, jets, archers, marauders 
                     FROM player_armies WHERE player_id = ?";
        } else if ($type === 'ai') {
            // Get AI armies
            $query = "SELECT fighters, shooters, vehicles, skmisher, rides, canons, jets, archers, marauders 
                     FROM ai_armies WHERE ai_city_id = ?";
        } else {
            // Default empty armies
            return [
                'fighters' => 0,
                'shooters' => 0,
                'vehicles' => 0,
                'skmisher' => 0,
                'rides' => 0,
                'canons' => 0,
                'jets' => 0,
                'archers' => 0,
                'marauders' => 0
            ];
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        } else {
            // Default empty armies
            return [
                'fighters' => 0,
                'shooters' => 0,
                'vehicles' => 0,
                'skmisher' => 0,
                'rides' => 0,
                'canons' => 0,
                'jets' => 0,
                'archers' => 0,
                'marauders' => 0
            ];
        }
    }

    /**
     * Calculate battle result
     * 
     * @param array $attackerArmies Attacker armies
     * @param array $defenderArmies Defender armies
     * @return array Battle result data
     */
    private function calculateBattleResult($attackerArmies, $defenderArmies)
    {
        // Calculate attack power of attacker
        $attackerPower = $this->calculateArmyPower($attackerArmies);

        // Calculate defense power of defender
        $defenderPower = $this->calculateArmyPower($defenderArmies, true);

        // Calculate attacker losses
        $attackerLossRate = min(0.8, $defenderPower / ($attackerPower * 1.5));
        $attackerLosses = $this->calculateArmyLosses($attackerArmies, $attackerLossRate);

        // Calculate defender losses
        $defenderLossRate = min(0.8, $attackerPower / ($defenderPower * 1.2));
        $defenderLosses = $this->calculateArmyLosses($defenderArmies, $defenderLossRate);

        // Calculate remaining armies
        $attackerRemaining = $this->calculateRemainingArmies($attackerArmies, $attackerLosses);
        $defenderRemaining = $this->calculateRemainingArmies($defenderArmies, $defenderLosses);

        // Determine battle result
        $result = $this->determineBattleResult($attackerPower, $defenderPower, $attackerLossRate, $defenderLossRate);

        // Calculate plundered resources
        $resourcesPlundered = [];
        if ($result === 'attacker_victory') {
            $resourcesPlundered = $this->calculatePlunderedResources($defenderPower, $attackerPower);
        }

        return [
            'result' => $result,
            'attacker_power' => $attackerPower,
            'defender_power' => $defenderPower,
            'attacker_losses' => $attackerLosses,
            'defender_losses' => $defenderLosses,
            'attacker_remaining' => $attackerRemaining,
            'defender_remaining' => $defenderRemaining,
            'resources_plundered' => $resourcesPlundered
        ];
    }

    /**
     * Calculate army power
     * 
     * @param array $armies Army data
     * @param bool $isDefender Whether this is the defender (gets defensive bonus)
     * @return float Total army power
     */
    private function calculateArmyPower($armies, $isDefender = false)
    {
        // Unit power values
        $unitPower = [
            'fighters' => 1,
            'shooters' => 1.2,
            'vehicles' => 3,
            'skmisher' => 0.8,
            'rides' => 2,
            'canons' => 5,
            'jets' => 4,
            'archers' => 1.5,
            'marauders' => 2.5
        ];

        $totalPower = 0;
        foreach ($armies as $unit => $count) {
            if (isset($unitPower[$unit])) {
                $totalPower += $count * $unitPower[$unit];
            }
        }

        // Defenders get a 20% bonus
        if ($isDefender) {
            $totalPower *= 1.2;
        }

        return $totalPower;
    }

    /**
     * Calculate army losses
     * 
     * @param array $armies Army data
     * @param float $lossRate Rate of losses (0-1)
     * @return array Army losses
     */
    private function calculateArmyLosses($armies, $lossRate)
    {
        $losses = [];
        foreach ($armies as $unit => $count) {
            // Add some randomness to losses
            $actualLossRate = $lossRate * (0.8 + (mt_rand(0, 40) / 100));
            $losses[$unit] = round($count * $actualLossRate);
        }
        return $losses;
    }

    /**
     * Calculate remaining armies after battle
     * 
     * @param array $armies Original army data
     * @param array $losses Army losses
     * @return array Remaining armies
     */
    private function calculateRemainingArmies($armies, $losses)
    {
        $remaining = [];
        foreach ($armies as $unit => $count) {
            $remaining[$unit] = max(0, $count - $losses[$unit]);
        }
        return $remaining;
    }

    /**
     * Determine the result of the battle
     * 
     * @param float $attackerPower Attacker power
     * @param float $defenderPower Defender power
     * @param float $attackerLossRate Attacker loss rate
     * @param float $defenderLossRate Defender loss rate
     * @return string Battle result
     */
    private function determineBattleResult($attackerPower, $defenderPower, $attackerLossRate, $defenderLossRate)
    {
        if ($attackerPower > $defenderPower * 1.1 && $attackerLossRate < $defenderLossRate) {
            return 'attacker_victory';
        } elseif ($defenderPower > $attackerPower * 0.9 || $attackerLossRate > $defenderLossRate * 1.2) {
            return 'defender_victory';
        } else {
            return 'draw';
        }
    }

    /**
     * Calculate resources plundered after a victory
     * 
     * @param float $defenderPower Defender power
     * @param float $attackerPower Attacker power
     * @return array Plundered resources
     */
    private function calculatePlunderedResources($defenderPower, $attackerPower)
    {
        // Base plunder rate is 30% of defender's resources
        $plunderRate = 0.3;

        // Increase plunder rate if attacker is much stronger
        if ($attackerPower > $defenderPower * 2) {
            $plunderRate = 0.5;
        }

        return [
            'wood' => round(1000 * $plunderRate),
            'oil' => round(800 * $plunderRate),
            'iron' => round(600 * $plunderRate),
            'food' => round(1200 * $plunderRate),
            'stone' => round(700 * $plunderRate)
        ];
    }

    /**
     * Record battle in database
     * 
     * @param int $attackerId Attacker ID
     * @param string $attackerType Attacker type
     * @param int $defenderId Defender ID
     * @param string $defenderType Defender type
     * @param string $result Battle result
     * @param array $attackerLosses Attacker losses
     * @param array $defenderLosses Defender losses
     * @param array $resourcesPlundered Resources plundered
     */    private function recordBattle($attackerId, $attackerType, $defenderId, $defenderType, $result, $attackerLosses, $defenderLosses, $resourcesPlundered)
    {
        // Ensure data is valid before processing
        if (!is_array($attackerLosses) || empty($attackerLosses) || array_sum(array_values($attackerLosses)) === 0) {
            $attackerLosses = array(
                "fighters" => 0,
                "shooters" => 0,
                "vehicles" => 0,
                "skmisher" => 0,
                "rides" => 0,
                "canons" => 0,
                "jets" => 0,
                "archers" => 0,
                "marauders" => 0
            );
        }
        if (!is_array($defenderLosses) || empty($defenderLosses) || array_sum(array_values($defenderLosses)) === 0) {
            $defenderLosses = array(
                "fighters" => 0,
                "shooters" => 0,
                "vehicles" => 0,
                "skmisher" => 0,
                "rides" => 0,
                "canons" => 0,
                "jets" => 0,
                "archers" => 0,
                "marauders" => 0
            );
        }
        if (!is_array($resourcesPlundered) || empty($resourcesPlundered)) {
            $resourcesPlundered = array("wood" => 0, "oil" => 0, "iron" => 0, "food" => 0, "stone" => 0);
        }        // Clean and normalize the data to ensure MySQL JSON compatibility
        // Make sure all values are proper numeric types, not strings or other types
        $attackerLosses = $this->sanitizeArrayForJson($attackerLosses);
        $defenderLosses = $this->sanitizeArrayForJson($defenderLosses);
        $resourcesPlundered = $this->sanitizeArrayForJson($resourcesPlundered);

        // Ensure JSON encoding doesn't fail - use JSON_NUMERIC_CHECK for numeric values
        // Additionally use JSON_UNESCAPED_UNICODE to avoid unnecessary escaping
        $jsonOptions = JSON_FORCE_OBJECT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE;

        // Fix for attacker losses - ensure it's always a proper JSON object even if all values are 0
        // This prevents it from being encoded as just '0' instead of a JSON object
        if (is_array($attackerLosses) && array_sum(array_values($attackerLosses)) === 0) {
            // If all values are 0, manually create the JSON to ensure it's an object
            $attackerLossesJson = '{"fighters":0,"shooters":0,"vehicles":0,"skmisher":0,"rides":0,"canons":0,"jets":0,"archers":0,"marauders":0}';
        } else {
            $attackerLossesJson = json_encode($attackerLosses, $jsonOptions);
            error_log("Attacker losses JSON: " . $attackerLossesJson);
        }

        $defenderLossesJson = json_encode($defenderLosses, $jsonOptions);
        $resourcesPlunderedJson = json_encode($resourcesPlundered, $jsonOptions);

        // Ensure we have valid JSON for database - provide valid fallbacks
        if ($attackerLossesJson === false) {
            error_log("Failed to encode attacker losses: " . json_last_error_msg());
            $attackerLossesJson = '{"error":0}';
        }
        if ($defenderLossesJson === false) {
            error_log("Failed to encode defender losses: " . json_last_error_msg());
            $defenderLossesJson = '{"error":0}';
        }
        if ($resourcesPlunderedJson === false) {
            error_log("Failed to encode resources plundered: " . json_last_error_msg());
            $resourcesPlunderedJson = '{"wood":0,"oil":0,"iron":0,"food":0,"stone":0}';
        }
        // Generate a battle report
        $report = $this->generateBattleReport($attackerId, $attackerType, $defenderId, $defenderType, $result, $attackerLosses, $defenderLosses, $resourcesPlundered);

        // Verify JSON validity one more time
        if (!$this->isValidJson($attackerLossesJson)) {
            $attackerLossesJson = '{"fighters":0}';
            error_log("Invalid JSON detected for attacker losses, using default");
        }
        if (!$this->isValidJson($defenderLossesJson)) {
            $defenderLossesJson = '{"fighters":0}';
            error_log("Invalid JSON detected for defender losses, using default");
        }
        if (!$this->isValidJson($resourcesPlunderedJson)) {
            $resourcesPlunderedJson = '{"wood":0}';
            error_log("Invalid JSON detected for resources plundered, using default");
        }
        // When dealing with JSON columns in MySQL 8, we need a different approach
        // Use CAST to ensure proper JSON formatting
        $query = "INSERT INTO battles (attacker_id, attacker_type, defender_id, defender_type, battle_result, 
                 attacker_units_lost, defender_units_lost, resources_plundered, battle_report) 
                 VALUES (?, ?, ?, ?, ?, 
                 CAST(? AS JSON), CAST(? AS JSON), CAST(? AS JSON), ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "isississs",
            $attackerId,
            $attackerType,
            $defenderId,
            $defenderType,
            $result,
            $attackerLossesJson,
            $defenderLossesJson,
            $resourcesPlunderedJson,
            $report
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to record battle: " . $stmt->error);
        }
    }
    /**
     * Count the total units in an army
     * 
     * @param array $armies Army data
     * @return int Total units count
     */
    private function getTotalUnits($armies)
    {
        $total = 0;
        foreach ($armies as $unit => $count) {
            $total += $count;
        }
        return $total;
    }

    /**
     * Generate a battle report
     * 
     * @param int $attackerId Attacker ID
     * @param string $attackerType Attacker type
     * @param int $defenderId Defender ID
     * @param string $defenderType Defender type
     * @param string $result Battle result
     * @param array $attackerLosses Attacker losses
     * @param array $defenderLosses Defender losses
     * @param array $resourcesPlundered Resources plundered
     * @return string Battle report
     */
    private function generateBattleReport($attackerId, $attackerType, $defenderId, $defenderType, $result, $attackerLosses, $defenderLosses, $resourcesPlundered)
    {
        // Get attacker and defender names
        $attackerName = $this->getEntityName($attackerId, $attackerType);
        $defenderName = $this->getEntityName($defenderId, $defenderType);

        // Create HTML battle report
        $report = '<div class="battle-report">';
        $report .= '<h2>Battle Report</h2>';
        $report .= '<div class="battle-timestamp">' . date('F j, Y, g:i a') . '</div>';

        // Participants
        $report .= '<div class="battle-participants">';
        $report .= '<div class="participant attacker"><strong>Attacker:</strong> ' . htmlspecialchars($attackerName) . '</div>';
        $report .= '<div class="participant defender"><strong>Defender:</strong> ' . htmlspecialchars($defenderName) . '</div>';
        $report .= '</div>';

        // Result
        $report .= '<div class="battle-result">';
        if ($result === 'attacker_victory') {
            $report .= '<div class="result victory">The attacker was victorious!</div>';
        } elseif ($result === 'defender_victory') {
            $report .= '<div class="result defeat">The defender successfully repelled the attack!</div>';
        } else {
            $report .= '<div class="result draw">The battle ended in a draw.</div>';
        }
        $report .= '</div>';

        // Losses
        $report .= '<div class="battle-losses">';
        $report .= '<h3>Casualties</h3>';

        // Attacker losses
        $report .= '<div class="losses attacker-losses">';
        $report .= '<h4>Attacker Losses:</h4>';
        $report .= '<ul>';
        $hasAttackerLosses = false;
        foreach ($attackerLosses as $unit => $count) {
            if ($count > 0) {
                $hasAttackerLosses = true;
                $report .= '<li>' . htmlspecialchars($unit) . ': <span class="loss-count">' . $count . '</span></li>';
            }
        }
        if (!$hasAttackerLosses) {
            $report .= '<li>No units were lost</li>';
        }
        $report .= '</ul>';
        $report .= '</div>';

        // Defender losses
        $report .= '<div class="losses defender-losses">';
        $report .= '<h4>Defender Losses:</h4>';
        $report .= '<ul>';
        $hasDefenderLosses = false;
        foreach ($defenderLosses as $unit => $count) {
            if ($count > 0) {
                $hasDefenderLosses = true;
                $report .= '<li>' . htmlspecialchars($unit) . ': <span class="loss-count">' . $count . '</span></li>';
            }
        }
        if (!$hasDefenderLosses) {
            $report .= '<li>No units were lost</li>';
        }
        $report .= '</ul>';
        $report .= '</div>';
        $report .= '</div>';
        // Plundered resources - show regardless of battle outcome if there are resources
        $hasPlunder = false;
        foreach ($resourcesPlundered as $resource => $amount) {
            if ($amount > 0) {
                $hasPlunder = true;
                break;
            }
        }

        if ($hasPlunder) {
            $report .= '<div class="plundered-resources">';
            $report .= '<h3>Resources Plundered</h3>';
            $report .= '<ul>';
            foreach ($resourcesPlundered as $resource => $amount) {
                if ($amount > 0) {
                    $report .= '<li>' . htmlspecialchars($resource) . ': <span class="resource-amount">' . $amount . '</span></li>';
                }
            }
            $report .= '</ul>';
            $report .= '</div>';
        } else {
            $report .= '<div class="plundered-resources">';
            $report .= '<h3>Resources</h3>';
            $report .= '<ul>';
            $report .= '<li>No resources were plundered</li>';
            $report .= '</ul>';
            $report .= '</div>';
        }

        $report .= '</div>';

        return $report;
    }

    /**
     * Get entity name
     * 
     * @param int $id Entity ID
     * @param string $type Entity type
     * @return string Entity name
     */
    private function getEntityName($id, $type)
    {
        if ($type === 'player') {
            $query = "SELECT username FROM players WHERE id = ?";
        } elseif ($type === 'ai') {
            $query = "SELECT name FROM ai_cities WHERE id = ?";
        } else {
            return "Unknown Entity";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            return $row['username'] ?? $row['name'] ?? "Unknown Entity";
        } else {
            return "Unknown Entity";
        }
    }

    /**
     * Update armies after battle
     * 
     * @param int $id Entity ID
     * @param string $type Entity type
     * @param array $armies Remaining armies
     */
    private function updateArmiesAfterBattle($id, $type, $armies)
    {
        if ($type === 'player') {
            $query = "UPDATE player_armies SET 
                     fighters = ?, 
                     shooters = ?, 
                     vehicles = ?, 
                     skmisher = ?, 
                     rides = ?, 
                     canons = ?, 
                     jets = ?, 
                     archers = ?, 
                     marauders = ? 
                     WHERE player_id = ?";
        } elseif ($type === 'ai') {
            $query = "UPDATE ai_armies SET 
                     fighters = ?, 
                     shooters = ?, 
                     vehicles = ?, 
                     skmisher = ?, 
                     rides = ?, 
                     canons = ?, 
                     jets = ?, 
                     archers = ?, 
                     marauders = ? 
                     WHERE ai_city_id = ?";
        } else {
            return;
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iiiiiiiiii",
            $armies['fighters'],
            $armies['shooters'],
            $armies['vehicles'],
            $armies['skmisher'],
            $armies['rides'],
            $armies['canons'],
            $armies['jets'],
            $armies['archers'],
            $armies['marauders'],
            $id
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to update armies after battle: " . $stmt->error);
        }
    }

    /**
     * Transfer resources from defender to attacker
     * 
     * @param int $attackerId Attacker ID
     * @param string $attackerType Attacker type
     * @param int $defenderId Defender ID
     * @param string $defenderType Defender type
     * @param array $resources Resources to transfer
     */
    private function transferResources($attackerId, $attackerType, $defenderId, $defenderType, $resources)
    {
        // Get resource IDs
        $attackerResourcesId = $this->getResourcesId($attackerId, $attackerType);
        $defenderResourcesId = $this->getResourcesId($defenderId, $defenderType);

        if (!$attackerResourcesId || !$defenderResourcesId) {
            return;
        }

        // Update defender resources (subtract)
        $query = "UPDATE resources SET 
                 wood = GREATEST(0, wood - ?), 
                 oil = GREATEST(0, oil - ?), 
                 iron = GREATEST(0, iron - ?), 
                 food = GREATEST(0, food - ?), 
                 stone = GREATEST(0, stone - ?) 
                 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iiiiii",
            $resources['wood'],
            $resources['oil'],
            $resources['iron'],
            $resources['food'],
            $resources['stone'],
            $defenderResourcesId
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to update defender resources: " . $stmt->error);
        }

        // Update attacker resources (add)
        $query = "UPDATE resources SET 
                 wood = wood + ?, 
                 oil = oil + ?, 
                 iron = iron + ?, 
                 food = food + ?, 
                 stone = stone + ? 
                 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iiiiii",
            $resources['wood'],
            $resources['oil'],
            $resources['iron'],
            $resources['food'],
            $resources['stone'],
            $attackerResourcesId
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to update attacker resources: " . $stmt->error);
        }
    }

    /**
     * Get resources ID for an entity
     * 
     * @param int $id Entity ID
     * @param string $type Entity type
     * @return int|null Resources ID or null if not found
     */
    private function getResourcesId($id, $type)
    {
        if ($type === 'player') {
            $query = "SELECT resources_id FROM cities WHERE player_id = ? LIMIT 1";
        } elseif ($type === 'ai') {
            $query = "SELECT resources_id FROM ai_cities WHERE id = ?";
        } else {
            return null;
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            return $row['resources_id'];
        } else {
            return null;
        }
    }

    /**
     * Get battle report
     * 
     * @param int $battleId Battle ID
     * @return string|null Battle report or null if not found
     */
    public function getBattleReport($battleId)
    {
        $query = "SELECT battle_report FROM battles WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $battleId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            return $row['battle_report'];
        } else {
            return null;
        }
    }

    /**
     * Get recent battles for a player
     * 
     * @param int $playerId Player ID
     * @param int $limit Maximum number of battles to return
     * @return array Recent battles
     */
    public function getRecentBattles($playerId, $limit = 10)
    {
        $query = "SELECT * FROM battles 
                 WHERE (attacker_id = ? AND attacker_type = 'player') 
                 OR (defender_id = ? AND defender_type = 'player') 
                 ORDER BY battle_date DESC LIMIT ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $playerId, $playerId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $battles = [];
        while ($row = $result->fetch_assoc()) {
            $battles[] = $row;
        }

        return $battles;
    }
    /**
     * Check if a string is valid JSON
     * 
     * @param string $json The JSON string to validate
     * @return bool True if valid JSON, false otherwise
     */
    private function isValidJson($json)
    {
        if (!is_string($json)) {
            return false;
        }

        // Check if it's just a raw integer "0" encoded as string - this is not valid for our purpose
        if ($json === "0") {
            return false;
        }

        json_decode($json);
        $isValidJson = json_last_error() === JSON_ERROR_NONE;

        // Additional check: ensure it's an object, not just a number
        if ($isValidJson) {
            $decoded = json_decode($json);
            if (!is_object($decoded) && !is_array($decoded)) {
                return false;
            }
        }

        return $isValidJson;
    }

    /**
     * Sanitize an array for JSON storage in MySQL
     * Ensures all values are properly typed for JSON encoding
     * 
     * @param mixed $data The data to sanitize
     * @return array The sanitized array
     */
    private function sanitizeArrayForJson($data)
    {
        // If not an array, create a simple default array
        if (!is_array($data)) {
            return array(
                "fighters" => 0,
                "shooters" => 0,
                "vehicles" => 0,
                "skmisher" => 0,
                "rides" => 0,
                "canons" => 0,
                "jets" => 0,
                "archers" => 0,
                "marauders" => 0
            );
        }

        // If empty array, provide defaults
        if (empty($data)) {
            return array(
                "fighters" => 0,
                "shooters" => 0,
                "vehicles" => 0,
                "skmisher" => 0,
                "rides" => 0,
                "canons" => 0,
                "jets" => 0,
                "archers" => 0,
                "marauders" => 0
            );
        }

        $result = array();

        // Process each key/value pair
        foreach ($data as $key => $value) {
            // Sanitize the key - ensure it's a valid string key
            $sanitizedKey = is_string($key) ? preg_replace('/[^A-Za-z0-9_]/', '_', $key) : "item_$key";

            // Sanitize the value
            if (is_array($value)) {
                // Handle nested arrays (though we don't expect these in battle data)
                $result[$sanitizedKey] = $this->sanitizeArrayForJson($value);
            } elseif (is_numeric($value)) {
                // Convert all numbers to integers
                $result[$sanitizedKey] = (int)$value;
            } elseif (is_null($value)) {
                // Convert null to 0
                $result[$sanitizedKey] = 0;
            } elseif (is_bool($value)) {
                // Convert boolean to 0 or 1
                $result[$sanitizedKey] = $value ? 1 : 0;
            } elseif (is_string($value) && is_numeric($value)) {
                // Convert numeric strings to integers
                $result[$sanitizedKey] = (int)$value;
            } else {
                // Any other type, default to 0
                $result[$sanitizedKey] = 0;
            }
        }

        return $result;
    }
}
