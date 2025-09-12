<?php
/**
 * BuildingManager - Core Building System Management
 * 
 * Handles all building-related operations including:
 * - Building construction and upgrades
 * - Resource cost calculation and validation
 * - Construction time management and queue processing
 * - Building effects calculation and application
 * - Integration with existing resource production system
 */

require_once __DIR__ . '/../../system/includes.php';

class BuildingManager {
    
    private $conn;
    private $g;
    
    public function __construct() {
        $this->g = globals();
        $this->conn = $this->g->getDatabase();
    }
    
    /**
     * Get all buildings in a city
     * @param int $cityId
     * @return array
     */
    public function getCityBuildings($cityId) {
        $query = "SELECT cb.*, bt.name, bt.display_name, bt.description, bt.icon, bt.category, bt.max_level
                  FROM city_buildings cb
                  JOIN building_types bt ON cb.building_type_id = bt.id
                  WHERE cb.city_id = ? AND cb.is_active = 1
                  ORDER BY bt.category, bt.name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $cityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $buildings = [];
        while ($row = $result->fetch_assoc()) {
            $buildings[] = $row;
        }
        
        return $buildings;
    }
    
    /**
     * Get available building types for construction
     * @param int $cityId City to check prerequisites for
     * @param string|null $category Filter by category
     * @return array
     */
    public function getAvailableBuildingTypes($cityId, $category = null) {
        $buildings = GameConfig::getBuildingTypes($this->conn, $category);
        $available = [];
        
        foreach ($buildings as $building) {
            // Check if building already exists in city (for unique buildings)
            if ($this->isBuildingUnique($building['id'])) {
                $existing = $this->getCityBuildingByType($cityId, $building['id']);
                if ($existing) {
                    // Building exists, check if it can be upgraded
                    if ($existing['level'] >= $building['max_level']) {
                        continue; // Max level reached
                    }
                    $building['existing'] = $existing;
                    $building['can_upgrade'] = true;
                }
            }
            
            // Check prerequisites
            list($prereqMet, $reason) = GameConfig::checkBuildingPrerequisites($this->conn, $cityId, $building['id']);
            $building['prerequisites_met'] = $prereqMet;
            $building['prerequisite_reason'] = $reason;
            
            $available[] = $building;
        }
        
        return $available;
    }
    
    /**
     * Start building construction
     * @param int $cityId
     * @param int $buildingTypeId
     * @param int $positionX
     * @param int $positionY
     * @return array [bool $success, string $message, array $data]
     */
    public function startConstruction($cityId, $buildingTypeId, $positionX = 0, $positionY = 0) {
        try {
            // Get building type
            $query = "SELECT * FROM building_types WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $buildingTypeId);
            $stmt->execute();
            $buildingType = $stmt->get_result()->fetch_assoc();
            
            if (!$buildingType) {
                return [false, 'Building type not found', []];
            }
            
            // Check prerequisites
            list($prereqMet, $reason) = GameConfig::checkBuildingPrerequisites($this->conn, $cityId, $buildingTypeId);
            if (!$prereqMet) {
                return [false, $reason, []];
            }
            
            // Check if position is available
            if (!$this->isPositionAvailable($cityId, $positionX, $positionY)) {
                return [false, 'Position already occupied', []];
            }
            
            // Calculate costs and construction time
            $costs = GameConfig::calculateBuildingCost($buildingType, 1);
            $constructionTime = GameConfig::calculateBuildingTime($buildingType, 1);
            
            // Check if player has enough resources
            $canAfford = $this->checkResourceAvailability($cityId, $costs);
            if (!$canAfford['success']) {
                return [false, $canAfford['message'], []];
            }
            
            // Start transaction
            $this->conn->begin_transaction();
            
            // Deduct resources
            $this->deductResources($cityId, $costs);
            
            // Add to construction queue
            $queueId = $this->addToConstructionQueue($cityId, $buildingTypeId, null, 'build', 1, 
                $positionX, $positionY, $costs, $constructionTime);
            
            if (!$queueId) {
                $this->conn->rollback();
                return [false, 'Failed to add to construction queue', []];
            }
            
            $this->conn->commit();
            
            return [true, 'Construction started successfully', [
                'queue_id' => $queueId,
                'construction_time' => $constructionTime,
                'costs' => $costs
            ]];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [false, 'Error starting construction: ' . $e->getMessage(), []];
        }
    }
    
    /**
     * Start building upgrade
     * @param int $cityBuildingId
     * @return array [bool $success, string $message, array $data]
     */
    public function startUpgrade($cityBuildingId) {
        try {
            // Get building info
            $query = "SELECT cb.*, bt.* FROM city_buildings cb
                     JOIN building_types bt ON cb.building_type_id = bt.id
                     WHERE cb.id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $cityBuildingId);
            $stmt->execute();
            $building = $stmt->get_result()->fetch_assoc();
            
            if (!$building) {
                return [false, 'Building not found', []];
            }
            
            // Check if can be upgraded
            if ($building['level'] >= $building['max_level']) {
                return [false, 'Building is already at maximum level', []];
            }
            
            $targetLevel = $building['level'] + 1;
            
            // Calculate upgrade costs and time
            $costs = GameConfig::calculateBuildingCost($building, $targetLevel);
            $constructionTime = GameConfig::calculateBuildingTime($building, $targetLevel);
            
            // Check if player has enough resources
            $canAfford = $this->checkResourceAvailability($building['city_id'], $costs);
            if (!$canAfford['success']) {
                return [false, $canAfford['message'], []];
            }
            
            // Check if building is already being upgraded
            $query = "SELECT id FROM construction_queue 
                     WHERE city_building_id = ? AND status IN ('queued', 'building')";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $cityBuildingId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return [false, 'Building is already being upgraded', []];
            }
            
            // Start transaction
            $this->conn->begin_transaction();
            
            // Deduct resources
            $this->deductResources($building['city_id'], $costs);
            
            // Add to construction queue
            $queueId = $this->addToConstructionQueue($building['city_id'], $building['building_type_id'], 
                $cityBuildingId, 'upgrade', $targetLevel, $building['position_x'], $building['position_y'], 
                $costs, $constructionTime);
            
            if (!$queueId) {
                $this->conn->rollback();
                return [false, 'Failed to add to construction queue', []];
            }
            
            $this->conn->commit();
            
            return [true, 'Upgrade started successfully', [
                'queue_id' => $queueId,
                'target_level' => $targetLevel,
                'construction_time' => $constructionTime,
                'costs' => $costs
            ]];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [false, 'Error starting upgrade: ' . $e->getMessage(), []];
        }
    }
    
    /**
     * Process construction queue (to be called by cron job)
     * @param int|null $cityId Process specific city or all cities
     * @return array Processing results
     */
    public function processConstructionQueue($cityId = null) {
        $whereClause = $cityId ? "AND city_id = ?" : "";
        $query = "SELECT * FROM construction_queue 
                 WHERE status IN ('queued', 'building') AND started_at <= NOW() 
                 $whereClause
                 ORDER BY city_id, queue_order, started_at";
        
        if ($cityId) {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $cityId);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->conn->query($query);
        }
        
        $processed = [];
        $currentTime = time();
        
        while ($queue = $result->fetch_assoc()) {
            $startedAt = strtotime($queue['started_at']);
            $completedAt = $startedAt + $queue['construction_time'];
            
            if ($currentTime >= $completedAt) {
                // Construction is complete
                $result = $this->completeConstruction($queue);
                $processed[] = [
                    'queue_id' => $queue['id'],
                    'city_id' => $queue['city_id'],
                    'type' => $queue['queue_type'],
                    'result' => $result
                ];
            } else {
                // Update status to building if it was queued
                if ($queue['status'] === 'queued') {
                    $this->updateConstructionStatus($queue['id'], 'building');
                }
            }
        }
        
        return $processed;
    }
    
    /**
     * Complete a construction/upgrade
     * @param array $queue Queue item data
     * @return array [bool $success, string $message]
     */
    private function completeConstruction($queue) {
        try {
            $this->conn->begin_transaction();
            
            if ($queue['queue_type'] === 'build') {
                // Create new building
                $query = "INSERT INTO city_buildings (city_id, building_type_id, level, position_x, position_y, 
                         construction_started_at, construction_completed_at, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("iiiiss", 
                    $queue['city_id'], 
                    $queue['building_type_id'], 
                    $queue['target_level'],
                    $queue['position_x'], 
                    $queue['position_y'],
                    $queue['started_at']
                );
                $stmt->execute();
                $buildingId = $this->conn->insert_id;
                
            } else if ($queue['queue_type'] === 'upgrade') {
                // Upgrade existing building
                $query = "UPDATE city_buildings 
                         SET level = ?, last_upgraded_at = NOW() 
                         WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("ii", $queue['target_level'], $queue['city_building_id']);
                $stmt->execute();
                $buildingId = $queue['city_building_id'];
            }
            
            // Mark queue item as completed
            $this->updateConstructionStatus($queue['id'], 'completed');
            
            // Update city's resource production rates
            $this->updateCityProductionRates($queue['city_id']);
            
            $this->conn->commit();
            
            return [true, 'Construction completed successfully'];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [false, 'Error completing construction: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update city's resource production rates based on buildings
     * @param int $cityId
     */
    private function updateCityProductionRates($cityId) {
        // Get all production effects from city buildings
        $query = "SELECT be.effect_target, SUM(be.effect_value) as total_bonus
                 FROM city_buildings cb
                 JOIN building_effects be ON cb.building_type_id = be.building_type_id AND cb.level >= be.level
                 WHERE cb.city_id = ? AND cb.is_active = 1 AND be.effect_type = 'production'
                 GROUP BY be.effect_target";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $cityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $bonuses = [];
        while ($row = $result->fetch_assoc()) {
            $bonuses[$row['effect_target']] = $row['total_bonus'];
        }
        
        // Update productions table
        $baseProduction = [
            'wood' => 10,
            'iron' => 8,
            'food' => 12,
            'stone' => 5,
            'oil' => 2
        ];
        
        $updateFields = [];
        $values = [];
        foreach ($baseProduction as $resource => $base) {
            $bonus = $bonuses[$resource] ?? 0;
            $newProduction = $base + $bonus;
            $updateFields[] = "{$resource}_production = ?";
            $values[] = $newProduction;
        }
        $values[] = $cityId;
        
        $query = "UPDATE productions SET " . implode(', ', $updateFields) . " WHERE city_id = ?";
        $stmt = $this->conn->prepare($query);
        $types = str_repeat('i', count($values));
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    }
    
    /**
     * Get construction queue for a city
     * @param int $cityId
     * @return array
     */
    public function getConstructionQueue($cityId) {
        $query = "SELECT cq.*, bt.display_name, bt.icon
                 FROM construction_queue cq
                 JOIN building_types bt ON cq.building_type_id = bt.id
                 WHERE cq.city_id = ? AND cq.status IN ('queued', 'building')
                 ORDER BY cq.queue_order, cq.started_at";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $cityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $queue = [];
        $currentTime = time();
        
        while ($row = $result->fetch_assoc()) {
            $startedAt = strtotime($row['started_at']);
            $completedAt = $startedAt + $row['construction_time'];
            
            $row['started_timestamp'] = $startedAt;
            $row['completed_timestamp'] = $completedAt;
            $row['remaining_time'] = max(0, $completedAt - $currentTime);
            $row['progress_percent'] = min(100, (($currentTime - $startedAt) / $row['construction_time']) * 100);
            
            $queue[] = $row;
        }
        
        return $queue;
    }
    
    /**
     * Cancel construction
     * @param int $queueId
     * @param bool $refundResources
     * @return array [bool $success, string $message]
     */
    public function cancelConstruction($queueId, $refundResources = true) {
        try {
            // Get queue item
            $query = "SELECT * FROM construction_queue WHERE id = ? AND status IN ('queued', 'building')";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $queueId);
            $stmt->execute();
            $queue = $stmt->get_result()->fetch_assoc();
            
            if (!$queue) {
                return [false, 'Construction not found or already completed'];
            }
            
            $this->conn->begin_transaction();
            
            if ($refundResources) {
                // Refund resources (partial refund based on progress)
                $progress = min(1.0, (time() - strtotime($queue['started_at'])) / $queue['construction_time']);
                $refundPercent = max(0.5, 1.0 - $progress); // Minimum 50% refund
                
                $refunds = [
                    'wood' => (int)($queue['cost_wood'] * $refundPercent),
                    'iron' => (int)($queue['cost_iron'] * $refundPercent),
                    'stone' => (int)($queue['cost_stone'] * $refundPercent),
                    'food' => (int)($queue['cost_food'] * $refundPercent),
                    'oil' => (int)($queue['cost_oil'] * $refundPercent)
                ];
                
                $this->refundResources($queue['city_id'], $refunds);
            }
            
            // Mark as cancelled
            $this->updateConstructionStatus($queueId, 'cancelled');
            
            $this->conn->commit();
            
            return [true, 'Construction cancelled successfully'];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [false, 'Error cancelling construction: ' . $e->getMessage()];
        }
    }
    
    // Helper methods
    
    private function getCityBuildingByType($cityId, $buildingTypeId) {
        $query = "SELECT * FROM city_buildings 
                 WHERE city_id = ? AND building_type_id = ? AND is_active = 1 
                 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $cityId, $buildingTypeId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    private function isBuildingUnique($buildingTypeId) {
        // For now, assume certain buildings are unique per city
        $uniqueBuildings = [
            'city_hall', 'research_lab', 'warehouse'
        ];
        
        $query = "SELECT name FROM building_types WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $buildingTypeId);
        $stmt->execute();
        $name = $stmt->get_result()->fetch_assoc()['name'];
        
        return in_array($name, $uniqueBuildings);
    }
    
    private function isPositionAvailable($cityId, $positionX, $positionY) {
        $query = "SELECT id FROM city_buildings 
                 WHERE city_id = ? AND position_x = ? AND position_y = ? AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $cityId, $positionX, $positionY);
        $stmt->execute();
        return $stmt->get_result()->num_rows === 0;
    }
    
    private function checkResourceAvailability($cityId, $costs) {
        $query = "SELECT * FROM resources WHERE city_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $cityId);
        $stmt->execute();
        $resources = $stmt->get_result()->fetch_assoc();
        
        if (!$resources) {
            return ['success' => false, 'message' => 'City resources not found'];
        }
        
        foreach ($costs as $resource => $cost) {
            if ($resources[$resource] < $cost) {
                return ['success' => false, 'message' => "Not enough $resource"];
            }
        }
        
        return ['success' => true, 'message' => ''];
    }
    
    private function deductResources($cityId, $costs) {
        $updateParts = [];
        $values = [];
        
        foreach ($costs as $resource => $cost) {
            if ($cost > 0) {
                $updateParts[] = "$resource = $resource - ?";
                $values[] = $cost;
            }
        }
        
        if (empty($updateParts)) return;
        
        $values[] = $cityId;
        $query = "UPDATE resources SET " . implode(', ', $updateParts) . " WHERE city_id = ?";
        $stmt = $this->conn->prepare($query);
        $types = str_repeat('i', count($values));
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    }
    
    private function refundResources($cityId, $refunds) {
        $updateParts = [];
        $values = [];
        
        foreach ($refunds as $resource => $amount) {
            if ($amount > 0) {
                $updateParts[] = "$resource = $resource + ?";
                $values[] = $amount;
            }
        }
        
        if (empty($updateParts)) return;
        
        $values[] = $cityId;
        $query = "UPDATE resources SET " . implode(', ', $updateParts) . " WHERE city_id = ?";
        $stmt = $this->conn->prepare($query);
        $types = str_repeat('i', count($values));
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    }
    
    private function addToConstructionQueue($cityId, $buildingTypeId, $cityBuildingId, $queueType, 
                                          $targetLevel, $positionX, $positionY, $costs, $constructionTime) {
        $query = "INSERT INTO construction_queue (
                    city_id, building_type_id, city_building_id, queue_type, target_level,
                    position_x, position_y, cost_wood, cost_iron, cost_stone, cost_food, cost_oil,
                    construction_time, status
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued')";
        
        $stmt = $this->conn->prepare($query);
        
        // Extract values to avoid bind_param reference issues
        $costWood = $costs['wood'] ?? 0;
        $costIron = $costs['iron'] ?? 0;
        $costStone = $costs['stone'] ?? 0;
        $costFood = $costs['food'] ?? 0;
        $costOil = $costs['oil'] ?? 0;
        
        $stmt->bind_param("iiisiiiiiiiii",
            $cityId, $buildingTypeId, $cityBuildingId, $queueType, $targetLevel,
            $positionX, $positionY, 
            $costWood, $costIron, $costStone, 
            $costFood, $costOil, $constructionTime
        );
        
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        return false;
    }
    
    private function updateConstructionStatus($queueId, $status) {
        $query = "UPDATE construction_queue SET status = ?, completed_at = ? WHERE id = ?";
        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $status, $completedAt, $queueId);
        $stmt->execute();
    }
}
?>
