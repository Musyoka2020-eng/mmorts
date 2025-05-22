<?php
/**
 * Map Generator Class
 * 
 * Handles creation and management of the world map.
 */
class MapGenerator {
    private $conn;
    private $mapSize;
    
    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection
     * @param int $mapSize Size of the map (NxN)
     */
    public function __construct($conn, $mapSize = 100) {
        $this->conn = $conn;
        $this->mapSize = $mapSize;
    }
    
    /**
     * Create a new world map
     * 
     * @return bool True if map was created successfully
     */
    public function createWorldMap() {
        // First, ensure the world_map table exists
        $this->createWorldMapTable();
        
        // Then clear any existing map data
        $this->clearWorldMap();
        
        // Generate terrain and resources for each cell
        $failed = false;
        for ($x = 0; $x < $this->mapSize; $x++) {
            for ($y = 0; $y < $this->mapSize; $y++) {
                $result = $this->generateMapCell($x, $y);
                if (!$result) {
                    $failed = true;
                    echo "Error generating map cell at ($x, $y)\n";
                }
            }
        }
        
        return !$failed;
    }
    
    /**
     * Create the world_map table if it doesn't exist
     */
    private function createWorldMapTable() {
        $sql = "CREATE TABLE IF NOT EXISTS world_map (
            id INT AUTO_INCREMENT PRIMARY KEY,
            location_x INT NOT NULL,
            location_y INT NOT NULL,
            terrain_type VARCHAR(50) NOT NULL,
            resource_type VARCHAR(50),
            resource_amount INT DEFAULT 0,
            occupied BOOLEAN DEFAULT FALSE,
            occupier_id INT,
            occupier_type ENUM('player', 'ai', 'npc') DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (location_x, location_y)
        )";
        
        if (!$this->conn->query($sql)) {
            echo "Error creating world_map table: " . $this->conn->error . "\n";
        }
    }
      
    /**
     * Clear existing world map data
     */
    private function clearWorldMap() {
        // First check if the table exists
        $tableCheck = "SHOW TABLES LIKE 'world_map'";
        $result = $this->conn->query($tableCheck);
        
        if ($result && $result->num_rows > 0) {
            $query = "DELETE FROM world_map";
            $this->conn->query($query);
        }
    }
    
    /**
     * Generate a single map cell
     * 
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @return bool True if cell was created successfully
     */
    private function generateMapCell($x, $y) {
        // Determine terrain type
        $terrainType = $this->determineTerrainType($x, $y);
        
        // Determine resource type based on terrain
        list($resourceType, $resourceAmount) = $this->determineResource($terrainType);
        
        // Insert the map cell
        $query = "INSERT INTO world_map (location_x, location_y, terrain_type, resource_type, resource_amount, occupied) 
                 VALUES (?, ?, ?, ?, ?, 0)";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            echo "Error preparing statement: " . $this->conn->error . "\n";
            return false;
        }
        
        $stmt->bind_param("iissi", $x, $y, $terrainType, $resourceType, $resourceAmount);
        $result = $stmt->execute();
        
        if (!$result) {
            echo "Error executing statement: " . $stmt->error . "\n";
            return false;
        }
        
        return true;
    }
    
    /**
     * Determine terrain type for a map cell
     * 
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @return string Terrain type
     */
    private function determineTerrainType($x, $y) {
        // Simple random terrain distribution
        // In a more advanced implementation, we would use noise functions for more natural terrain generation
        $rand = mt_rand(1, 100);
        
        if ($rand <= 40) {
            return 'plains';
        } elseif ($rand <= 60) {
            return 'forest';
        } elseif ($rand <= 75) {
            return 'hills';
        } elseif ($rand <= 85) {
            return 'mountains';
        } elseif ($rand <= 95) {
            return 'desert';
        } else {
            return 'water';
        }
    }
    
    /**
     * Determine resource type and amount based on terrain
     * 
     * @param string $terrainType Terrain type
     * @return array Array containing resource type and amount
     */
    private function determineResource($terrainType) {
        $resourceType = null;
        $resourceAmount = 0;
        
        // Each terrain type has specific resource probabilities
        switch ($terrainType) {
            case 'plains':
                if (mt_rand(1, 100) <= 60) {
                    $resourceType = 'food';
                    $resourceAmount = mt_rand(500, 1500);
                }
                break;
                
            case 'forest':
                if (mt_rand(1, 100) <= 80) {
                    $resourceType = 'wood';
                    $resourceAmount = mt_rand(800, 2000);
                }
                break;
                
            case 'hills':
                if (mt_rand(1, 100) <= 70) {
                    $resourceType = 'stone';
                    $resourceAmount = mt_rand(600, 1800);
                }
                break;
                
            case 'mountains':
                if (mt_rand(1, 100) <= 85) {
                    $resourceType = 'iron';
                    $resourceAmount = mt_rand(700, 2200);
                }
                break;
                
            case 'desert':
                if (mt_rand(1, 100) <= 50) {
                    $resourceType = 'oil';
                    $resourceAmount = mt_rand(400, 1200);
                }
                break;
                
            case 'water':
                // No resources on water
                break;
        }
        
        return [$resourceType, $resourceAmount];
    }
    
    /**
     * Get map data for a specific area
     * 
     * @param int $centerX Center X coordinate
     * @param int $centerY Center Y coordinate
     * @param int $radius View radius
     * @return array Map data for the specified area
     */    public function getMapArea($centerX, $centerY, $radius = 10) {
        $mapData = [];
        
        $minX = max(0, $centerX - $radius);
        $maxX = min($this->mapSize - 1, $centerX + $radius);
        $minY = max(0, $centerY - $radius);
        $maxY = min($this->mapSize - 1, $centerY + $radius);
        
        $query = "SELECT wm.*, 
                  CASE 
                    WHEN wm.occupier_type = 'ai' THEN (
                      SELECT ap.name FROM ai_players ap 
                      JOIN ai_cities ac ON ap.id = ac.ai_player_id 
                      WHERE ac.location_x = wm.location_x AND ac.location_y = wm.location_y LIMIT 1
                    )
                    WHEN wm.occupier_type = 'player' THEN (
                      SELECT p.username FROM players p 
                      JOIN cities c ON p.id = c.player_id 
                      WHERE c.location_x = wm.location_x AND c.location_y = wm.location_y LIMIT 1
                    )
                    ELSE NULL
                  END as occupier_name,
                  CASE 
                    WHEN wm.occupier_type = 'ai' THEN (
                      SELECT ac.name FROM ai_cities ac 
                      WHERE ac.location_x = wm.location_x AND ac.location_y = wm.location_y LIMIT 1
                    )
                    WHEN wm.occupier_type = 'player' THEN (
                      SELECT c.name FROM cities c 
                      WHERE c.location_x = wm.location_x AND c.location_y = wm.location_y LIMIT 1
                    )
                    ELSE NULL
                  END as city_name
                 FROM world_map wm
                 WHERE wm.location_x BETWEEN ? AND ? 
                 AND wm.location_y BETWEEN ?  AND ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiii", $minX, $maxX, $minY, $maxY);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $mapData[] = $row;
        }
        
        return $mapData;
    }
    
    /**
     * Get map cell data
     * 
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @return array|null Map cell data or null if not found
     */
    public function getMapCell($x, $y) {
        $query = "SELECT * FROM world_map WHERE location_x = ? AND location_y = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $x, $y);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        } else {
            return null;
        }
    }
    
    /**
     * Get map size
     * 
     * @return int Map size
     */
    public function getMapSize() {
        return $this->mapSize;
    }
}
