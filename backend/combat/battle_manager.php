<?php
/**
 * Battle Manager Class
 * 
 * Handles combat between player and AI armies.
 */
class BattleManager {
    private $conn;
    
    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection
     */
    public function __construct($conn) {
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
     */
    public function initiateBattle($attackerId, $attackerType, $defenderId, $defenderType) {
        // Get attacker armies
        $attackerArmies = $this->getArmies($attackerId, $attackerType);
        
        // Get defender armies
        $defenderArmies = $this->getArmies($defenderId, $defenderType);
        
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
        
        return $battleResult;
    }
    
    /**
     * Get armies for a player or AI
     * 
     * @param int $id Player or AI ID
     * @param string $type Type (player, ai, npc)
     * @return array Army data
     */
    private function getArmies($id, $type) {
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
    private function calculateBattleResult($attackerArmies, $defenderArmies) {
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
    private function calculateArmyPower($armies, $isDefender = false) {
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
    private function calculateArmyLosses($armies, $lossRate) {
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
    private function calculateRemainingArmies($armies, $losses) {
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
    private function determineBattleResult($attackerPower, $defenderPower, $attackerLossRate, $defenderLossRate) {
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
    private function calculatePlunderedResources($defenderPower, $attackerPower) {
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
     */
    private function recordBattle($attackerId, $attackerType, $defenderId, $defenderType, $result, $attackerLosses, $defenderLosses, $resourcesPlundered) {
        $attackerLossesJson = json_encode($attackerLosses);
        $defenderLossesJson = json_encode($defenderLosses);
        $resourcesPlunderedJson = json_encode($resourcesPlundered);
        
        // Generate a battle report
        $report = $this->generateBattleReport($attackerId, $attackerType, $defenderId, $defenderType, $result, $attackerLosses, $defenderLosses, $resourcesPlundered);
        
        $query = "INSERT INTO battles (attacker_id, attacker_type, defender_id, defender_type, battle_result, attacker_units_lost, defender_units_lost, resources_plundered, battle_report) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isississs", $attackerId, $attackerType, $defenderId, $defenderType, $result, $attackerLossesJson, $defenderLossesJson, $resourcesPlunderedJson, $report);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to record battle: " . $stmt->error);
        }
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
    private function generateBattleReport($attackerId, $attackerType, $defenderId, $defenderType, $result, $attackerLosses, $defenderLosses, $resourcesPlundered) {
        // Get attacker and defender names
        $attackerName = $this->getEntityName($attackerId, $attackerType);
        $defenderName = $this->getEntityName($defenderId, $defenderType);
        
        // Generate report based on result
        $report = "Battle Report\n";
        $report .= "=============\n\n";
        $report .= "Attacker: $attackerName\n";
        $report .= "Defender: $defenderName\n\n";
        
        if ($result === 'attacker_victory') {
            $report .= "Result: The attacker was victorious!\n\n";
        } elseif ($result === 'defender_victory') {
            $report .= "Result: The defender successfully repelled the attack!\n\n";
        } else {
            $report .= "Result: The battle ended in a draw.\n\n";
        }
        
        // List losses
        $report .= "Losses:\n";
        $report .= "-------\n";
        $report .= "Attacker losses:\n";
        foreach ($attackerLosses as $unit => $count) {
            if ($count > 0) {
                $report .= "  - $unit: $count\n";
            }
        }
        
        $report .= "\nDefender losses:\n";
        foreach ($defenderLosses as $unit => $count) {
            if ($count > 0) {
                $report .= "  - $unit: $count\n";
            }
        }
        
        // List plundered resources
        if ($result === 'attacker_victory' && !empty($resourcesPlundered)) {
            $report .= "\nResources plundered:\n";
            $report .= "------------------\n";
            foreach ($resourcesPlundered as $resource => $amount) {
                if ($amount > 0) {
                    $report .= "  - $resource: $amount\n";
                }
            }
        }
        
        return $report;
    }
    
    /**
     * Get entity name
     * 
     * @param int $id Entity ID
     * @param string $type Entity type
     * @return string Entity name
     */
    private function getEntityName($id, $type) {
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
    private function updateArmiesAfterBattle($id, $type, $armies) {
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
            "iiiiiiiii", 
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
    private function transferResources($attackerId, $attackerType, $defenderId, $defenderType, $resources) {
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
            "iiiii", 
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
            "iiiii", 
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
    private function getResourcesId($id, $type) {
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
    public function getBattleReport($battleId) {
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
    public function getRecentBattles($playerId, $limit = 10) {
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
}
