<?php
/**
 * AI Base Class
 * 
 * Base class for AI opponents in the game.
 */
class AIBase {
    private $conn;
    private $id;
    private $name;
    private $personalityType;
    private $difficultyLevel;
    private $cities = [];
    
    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection
     * @param int $id AI player ID (optional, for loading existing AI)
     */
    public function __construct($conn, $id = null) {
        $this->conn = $conn;
        
        if ($id !== null) {
            $this->id = $id;
            $this->loadData();
        }
    }
    
    /**
     * Load AI data from database
     */
    private function loadData() {
        $query = "SELECT * FROM ai_players WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $data = $result->fetch_assoc();
            $this->name = $data['name'];
            $this->personalityType = $data['personality_type'];
            $this->difficultyLevel = $data['difficulty_level'];
            
            // Load cities
            $this->loadCities();
        } else {
            throw new Exception("AI player not found: ID $this->id");
        }
    }
    
    /**
     * Load cities for this AI
     */
    private function loadCities() {
        $query = "SELECT id FROM ai_cities WHERE ai_player_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $this->cities[] = $row['id'];
        }
    }
    
    /**
     * Create a new AI player
     * 
     * @param string $name AI player name
     * @param string $personalityType Personality type (aggressive, balanced, defensive)
     * @param int $difficultyLevel Difficulty level (1-3)
     * @return int New AI player ID
     */
    public function create($name, $personalityType, $difficultyLevel) {
        $this->name = $name;
        $this->personalityType = $personalityType;
        $this->difficultyLevel = $difficultyLevel;
        
        $query = "INSERT INTO ai_players (name, personality_type, difficulty_level) 
                 VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $this->name, $this->personalityType, $this->difficultyLevel);
        
        if ($stmt->execute()) {
            $this->id = $stmt->insert_id;
            return $this->id;
        } else {
            throw new Exception("Failed to create AI player: " . $stmt->error);
        }
    }
    
    /**
     * Create a new city for this AI
     * 
     * @param string $name City name
     * @param int $locationX X coordinate on world map
     * @param int $locationY Y coordinate on world map
     * @return int New city ID
     */
    public function createCity($name, $locationX, $locationY) {
        // First, create resources for the city
        $resourcesId = $this->createResources();
        
        // Then create the city
        $query = "INSERT INTO ai_cities (ai_player_id, name, resources_id, location_x, location_y) 
                 VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isiii", $this->id, $name, $resourcesId, $locationX, $locationY);
        
        if ($stmt->execute()) {
            $cityId = $stmt->insert_id;
            
            // Mark location as occupied on the world map
            $this->occupyLocation($locationX, $locationY, $cityId);
            
            // Create initial armies for the city
            $this->createInitialArmies($cityId);
            
            return $cityId;
        } else {
            throw new Exception("Failed to create AI city: " . $stmt->error);
        }
    }
    
    /**
     * Create resources for a new city
     * 
     * @return int New resources ID
     */
    protected function createResources() {
        // Base values depend on difficulty
        $baseValue = 1000 * $this->difficultyLevel;
        
        // Check if resources table exists
        $query = "SHOW TABLES LIKE 'resources'";
        $result = $this->conn->query($query);
        
        if ($result->num_rows === 0) {
            // Create resources table if it doesn't exist
            $query = "CREATE TABLE IF NOT EXISTS resources (
                id INT AUTO_INCREMENT PRIMARY KEY,
                city_id BIGINT DEFAULT 0,
                wood INT DEFAULT 0,
                oil INT DEFAULT 0,
                iron INT DEFAULT 0,
                food INT DEFAULT 0,
                stone INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $this->conn->query($query);
        }
        
        // Set placeholder values for and city_id - we'll update city_id later
        $tempCityId = 0; // Will be updated after city creation

        $query = "INSERT INTO resources (city_id, wood, oil, iron, food, stone) 
                 VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiiiii", $tempCityId, $baseValue, $baseValue, $baseValue, $baseValue, $baseValue);
        
        if ($stmt->execute()) {
            $resourcesId = $stmt->insert_id;
            
            // Create production rates
            $this->createProduction($resourcesId);
            
            return $resourcesId;
        } else {
            throw new Exception("Failed to create AI resources: " . $stmt->error);
        }
    }
    
    /**
     * Create production rates for a city
     * 
     * @param int $cityId City ID
     * @return int New production ID
     */
    protected function createProduction($resourcesId) {
        // Base production rates depend on difficulty
        $baseProduction = 50 * $this->difficultyLevel;
        
        // Check if productions table exists
        $query = "SHOW TABLES LIKE 'productions'";
        $result = $this->conn->query($query);
        
        if ($result->num_rows === 0) {
            // Create productions table if it doesn't exist
            $query = "CREATE TABLE IF NOT EXISTS productions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                city_id INT NOT NULL,
                wood_production INT DEFAULT 0,
                oil_production INT DEFAULT 0,
                iron_production INT DEFAULT 0,
                food_production INT DEFAULT 0,
                stone_production INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->conn->query($query);
        }
        
        // For now, just use resourcesId as city_id (a placeholder)
        $query = "INSERT INTO productions (city_id, wood_production, oil_production, iron_production, food_production, stone_production) 
                 VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiiiii", $resourcesId, $baseProduction, $baseProduction, $baseProduction, $baseProduction, $baseProduction);
        
        if ($stmt->execute()) {
            return $stmt->insert_id;
        } else {
            throw new Exception("Failed to create AI production rates: " . $stmt->error);
        }
    }
    
    /**
     * Create initial armies for a city
     * 
     * @param int $cityId City ID
     */
    protected function createInitialArmies($cityId) {
        // Base army size depends on difficulty
        $baseArmy = 10 * $this->difficultyLevel;
        
        $query = "INSERT INTO ai_armies (ai_city_id, fighters, shooters) 
                 VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $cityId, $baseArmy, $baseArmy);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create AI armies: " . $stmt->error);
        }
    }
    
    /**
     * Mark a location as occupied on the world map
     * 
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @param int $cityId City ID
     */
    protected function occupyLocation($x, $y, $cityId) {
        $query = "UPDATE world_map SET occupied = 1, occupier_id = ?, occupier_type = 'ai' 
                 WHERE location_x = ? AND location_y = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $this->id, $x, $y);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to mark location as occupied: " . $stmt->error);
        }
    }
    
    /**
     * Take a turn for this AI
     */
    public function takeTurn() {
        // Process each city
        foreach ($this->cities as $cityId) {
            // 1. Produce resources
            $this->produceResources($cityId);
            
            // 2. Build or upgrade buildings
            $this->improveBuildingsAndTechnology($cityId);
            
            // 3. Train military units
            $this->trainMilitaryUnits($cityId);
            
            // 4. Make military decisions (attack or defend)
            $this->makeMilitaryDecisions($cityId);
        }
    }
    
    /**
     * Produce resources for a city
     * 
     * @param int $cityId City ID
     */
    private function produceResources($cityId) {
        // Get the resources_id for this city
        $query = "SELECT resources_id FROM ai_cities WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $cityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $resourcesId = $row['resources_id'];
            
            // Get production rates
            $query = "SELECT * FROM productions WHERE city_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $resourcesId); // Using resourcesId as city_id in productions
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows === 1) {
                $production = $result->fetch_assoc();
                
                // Update resources based on production rates
                $query = "UPDATE resources 
                         SET wood = wood + ?,
                             oil = oil + ?,
                             iron = iron + ?,
                             food = food + ?,
                             stone = stone + ?
                         WHERE id = ?";
                
                $woodProduction = $production['wood_production'];
                $oilProduction = $production['oil_production'];
                $ironProduction = $production['iron_production'];
                $foodProduction = $production['food_production'];
                $stoneProduction = $production['stone_production'];
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("iiiiii", 
                    $woodProduction, 
                    $oilProduction, 
                    $ironProduction, 
                    $foodProduction, 
                    $stoneProduction, 
                    $resourcesId
                );
                $stmt->execute();
            }
        }
    }
    
    /**
     * Improve buildings and technology for a city
     * 
     * @param int $cityId City ID
     */
    private function improveBuildingsAndTechnology($cityId) {
        // This is a simplified version - in the full game, we would have:
        // 1. Check available resources
        // 2. Determine what buildings/tech to upgrade based on AI personality
        // 3. Deduct resources and improve the chosen building/tech
        
        // For now, we'll just randomly increase production rates
        $query = "SELECT resources_id FROM ai_cities WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $cityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $resourcesId = $row['resources_id'];
            
            // Randomly improve one production type
            $resourceTypes = ['wood_production', 'oil_production', 'iron_production', 'food_production', 'stone_production'];
            $resourceToImprove = $resourceTypes[array_rand($resourceTypes)];
            
            // Increase by 1-5 based on difficulty
            $improvement = rand(1, 5) * $this->difficultyLevel;
            
            $query = "UPDATE productions 
                     SET $resourceToImprove = $resourceToImprove + ? 
                     WHERE city_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ii", $improvement, $resourcesId);
            $stmt->execute();
        }
    }
    
    /**
     * Train military units for a city
     * 
     * @param int $cityId City ID
     */
    private function trainMilitaryUnits($cityId) {
        // Get resources for this city
        $query = "SELECT r.* FROM resources r
                 JOIN ai_cities c ON r.id = c.resources_id
                 WHERE c.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $cityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $resources = $result->fetch_assoc();
            
            // Determine what units to train based on AI personality and available resources
            $unitsToTrain = [];
            $resourceCost = 0;
            
            // Simplified training logic
            if ($this->personalityType === 'aggressive') {
                // Aggressive AIs prefer offensive units
                if ($resources['iron'] >= 100 && $resources['food'] >= 50) {
                    $unitsToTrain['fighters'] = rand(2, 5) * $this->difficultyLevel;
                    $resourceCost = $unitsToTrain['fighters'] * 20; // Cost per fighter
                }
            } elseif ($this->personalityType === 'defensive') {
                // Defensive AIs prefer defensive units
                if ($resources['wood'] >= 100 && $resources['stone'] >= 50) {
                    $unitsToTrain['shooters'] = rand(2, 5) * $this->difficultyLevel;
                    $resourceCost = $unitsToTrain['shooters'] * 25; // Cost per shooter
                }
            } else {
                // Balanced AIs train a mix
                if ($resources['iron'] >= 50 && $resources['food'] >= 50 && $resources['wood'] >= 50) {
                    $unitsToTrain['fighters'] = rand(1, 3) * $this->difficultyLevel;
                    $unitsToTrain['shooters'] = rand(1, 3) * $this->difficultyLevel;
                    $resourceCost = ($unitsToTrain['fighters'] * 20) + ($unitsToTrain['shooters'] * 25);
                }
            }
            
            // Train units if we have enough resources
            if (!empty($unitsToTrain) && $resourceCost <= $resources['iron'] + $resources['food'] + $resources['wood']) {
                $query = "UPDATE ai_armies SET ";
                $updateParts = [];
                $params = [];
                $types = "";
                
                foreach ($unitsToTrain as $unit => $count) {
                    $updateParts[] = "$unit = $unit + ?";
                    $params[] = $count;
                    $types .= "i";
                }
                
                $query .= implode(", ", $updateParts);
                $query .= " WHERE ai_city_id = ?";
                $params[] = $cityId;
                $types .= "i";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                
                // Deduct resources (simplified - just deduct from iron and food)
                $query = "UPDATE resources r
                         JOIN ai_cities c ON r.id = c.resources_id
                         SET r.iron = r.iron - ?,
                             r.food = r.food - ?
                         WHERE c.id = ?";
                $ironCost = $resourceCost / 2;
                $foodCost = $resourceCost / 2;
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("iii", $ironCost, $foodCost, $cityId);
                $stmt->execute();
            }
        }
    }
    
    /**
     * Make military decisions for a city
     * 
     * @param int $cityId City ID
     */
    private function makeMilitaryDecisions($cityId) {
        // Get the city location
        $query = "SELECT location_x, location_y FROM ai_cities WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $cityId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $city = $result->fetch_assoc();
            $x = $city['location_x'];
            $y = $city['location_y'];
            
            // Chance to attack depends on personality
            $attackChance = 0;
            switch ($this->personalityType) {
                case 'aggressive':
                    $attackChance = 0.3 * $this->difficultyLevel; // 30-90% chance
                    break;
                case 'balanced':
                    $attackChance = 0.15 * $this->difficultyLevel; // 15-45% chance
                    break;
                case 'defensive':
                    $attackChance = 0.05 * $this->difficultyLevel; // 5-15% chance
                    break;
            }
            
            // Roll the dice
            if (mt_rand() / mt_getrandmax() < $attackChance) {
                // Look for nearby players or other AI to attack
                $searchRadius = 3 * $this->difficultyLevel;
                
                // Find nearby occupied cells
                $query = "SELECT * FROM world_map 
                         WHERE occupied = 1
                         AND occupier_id != ?
                         AND occupier_type != 'ai'
                         AND location_x BETWEEN ? AND ?
                         AND location_y BETWEEN ? AND ?";
                
                $minX = max(0, $x - $searchRadius);
                $maxX = $x + $searchRadius;
                $minY = max(0, $y - $searchRadius);
                $maxY = $y + $searchRadius;
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("iiiii", $this->id, $minX, $maxX, $minY, $maxY);
                $stmt->execute();
                $result = $stmt->get_result();
                
                // If we found a target, initiate an attack
                if ($result && $result->num_rows > 0) {
                    $targets = [];
                    while ($row = $result->fetch_assoc()) {
                        $targets[] = $row;
                    }
                    
                    // Pick a random target
                    $target = $targets[array_rand($targets)];
                    
                    // First find the target city ID
                    $targetCityId = null;
                    if ($target['occupier_type'] === 'player') {
                        $query = "SELECT id FROM cities WHERE player_id = ? LIMIT 1";
                        $stmt = $this->conn->prepare($query);
                        $stmt->bind_param("i", $target['occupier_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result && $result->num_rows === 1) {
                            $targetCityId = $result->fetch_assoc()['id'];
                        }
                    } else if ($target['occupier_type'] === 'ai') {
                        $query = "SELECT id FROM ai_cities 
                                 WHERE ai_player_id = ? 
                                 AND location_x = ? 
                                 AND location_y = ? 
                                 LIMIT 1";
                        $stmt = $this->conn->prepare($query);
                        $stmt->bind_param("iii", $target['occupier_id'], $target['location_x'], $target['location_y']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result && $result->num_rows === 1) {
                            $targetCityId = $result->fetch_assoc()['id'];
                        }
                    }
                    
                    // If we found the target city ID, initiate the battle
                    if ($targetCityId) {
                        // Include battle manager
                        require_once __DIR__ . '/../combat/battle_manager.php';
                        $battleManager = new BattleManager($this->conn);
                        
                        // Initiate battle
                        try {
                            $battleResult = $battleManager->initiateBattle($cityId, 'ai', $targetCityId, $target['occupier_type']);
                            
                            // Log the attack
                            $query = "INSERT INTO ai_action_log (ai_player_id, ai_city_id, action_type, target_x, target_y, target_id, target_type, result)
                                     VALUES (?, ?, 'attack', ?, ?, ?, ?, ?)";
                            $stmt = $this->conn->prepare($query);
                            
                            // Create result text
                            $resultText = "Battle Result: " . ucfirst(str_replace('_', ' ', $battleResult['result']));
                            
                            // Check if the prepared statement worked
                            if ($stmt) {
                                $stmt->bind_param("iiiiiss", $this->id, $cityId, $target['location_x'], $target['location_y'], 
                                                $target['occupier_id'], $target['occupier_type'], $resultText);
                                $stmt->execute();
                            } else {
                                // Create the table if it doesn't exist
                                $createQuery = "CREATE TABLE IF NOT EXISTS ai_action_log (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    ai_player_id INT NOT NULL,
                                    ai_city_id INT NOT NULL,
                                    action_type VARCHAR(50) NOT NULL,
                                    target_x INT,
                                    target_y INT,
                                    target_id INT,
                                    target_type VARCHAR(50),
                                    result TEXT,
                                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                )";
                                $this->conn->query($createQuery);
                                
                                // Try again
                                $stmt = $this->conn->prepare($query);
                                if ($stmt) {
                                    $stmt->bind_param("iiiiiss", $this->id, $cityId, $target['location_x'], $target['location_y'], 
                                                    $target['occupier_id'], $target['occupier_type'], $resultText);
                                    $stmt->execute();
                                }
                            }
                        } catch (Exception $e) {
                            // Log the error
                            error_log("AI battle error: " . $e->getMessage());
                        }
                    } else {
                        // Just log the intention to attack if we couldn't find the target city ID
                        $query = "INSERT INTO ai_action_log (ai_player_id, ai_city_id, action_type, target_x, target_y, target_id, target_type, result)
                                 VALUES (?, ?, 'attack', ?, ?, ?, ?, 'Target city not found')";
                        $stmt = $this->conn->prepare($query);
                        
                        // Check if the prepared statement worked
                        if ($stmt) {
                            $stmt->bind_param("iiiiis", $this->id, $cityId, $target['location_x'], $target['location_y'], 
                                            $target['occupier_id'], $target['occupier_type']);
                            $stmt->execute();
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Get AI data
     * 
     * @return array AI data
     */
    public function getData() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'personalityType' => $this->personalityType,
            'difficultyLevel' => $this->difficultyLevel,
            'cities' => $this->cities
        ];
    }
}