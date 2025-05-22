<?php
require_once __DIR__ . '/ai_base.php';

/**
 * AI Manager Class
 * 
 * Manages all AI opponents in the game, including creation, updates, and turn processing.
 */
class AIManager {
    private $conn;
    private $aiPlayers = [];
    
    /**
     * Constructor
     * 
     * @param mysqli $conn Database connection
     */
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Load all active AI players
     */
    public function loadAllAI() {
        $query = "SELECT id FROM ai_players WHERE active = 1";
        $result = $this->conn->query($query);
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $this->aiPlayers[$row['id']] = new AIBase($this->conn, $row['id']);
            }
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
    public function createAIPlayer($name, $personalityType, $difficultyLevel) {
        $ai = new AIBase($this->conn);
        $id = $ai->create($name, $personalityType, $difficultyLevel);
        $this->aiPlayers[$id] = $ai;
        return $id;
    }
    
    /**
     * Create initial AI players for a new game
     * 
     * @param int $count Number of AI players to create
     * @param int $difficultyLevel Default difficulty level
     */
    public function createInitialAIPlayers($count = 5, $difficultyLevel = 1) {
        $personalities = ['aggressive', 'balanced', 'defensive'];
        $namePrefix = ['Iron', 'Shadow', 'Golden', 'Dark', 'Mighty', 'Ancient', 'Noble', 'Mystic'];
        $nameSuffix = ['Legion', 'Empire', 'Kingdom', 'Dominion', 'Republic', 'Nation', 'Clan', 'Tribe'];
        
        for ($i = 0; $i < $count; $i++) {
            // Create a random name
            $prefix = $namePrefix[array_rand($namePrefix)];
            $suffix = $nameSuffix[array_rand($nameSuffix)];
            $name = $prefix . ' ' . $suffix;
            
            // Select a random personality
            $personality = $personalities[array_rand($personalities)];
            
            // Create the AI player
            $this->createAIPlayer($name, $personality, $difficultyLevel);
        }
    }
    
    /**
     * Process AI turns for all active AI players
     */
    public function processAITurns() {
        foreach ($this->aiPlayers as $ai) {
            $ai->takeTurn();
        }
    }    /**
     * Place AI cities on the world map
     * 
     * @param int $mapSize Size of the map (NxN)
     */
    public function placeAICities($mapSize = 100) {
        // First, check if the world_map table exists and has data
        $query = "SELECT COUNT(*) as count FROM world_map";
        $result = $this->conn->query($query);
        
        // If table doesn't exist or is empty, we can't place cities
        if (!$result || $result->fetch_assoc()['count'] == 0) {
            echo "Error: world_map table is empty or doesn't exist. Cannot place AI cities.\n";
            echo "Running fix_database.php to create required tables...\n";
            include_once __DIR__ . '/../../fix_database.php';
            
            // Generate the world map if it doesn't exist
            echo "Generating world map...\n";
            require_once __DIR__ . '/../world/map_generator.php';
            $mapGenerator = new MapGenerator($this->conn, $mapSize);
            $mapGenerator->createWorldMap();
        }
        
        // Place cities for each AI
        foreach ($this->aiPlayers as $ai) {
            // Each AI gets 1-3 cities based on difficulty
            $data = $ai->getData();
            $cityCount = $data['difficultyLevel'];
            
            for ($i = 0; $i < $cityCount; $i++) {
                // Generate a random position that is not occupied
                $placed = false;
                $maxAttempts = 50; // Prevent infinite loops
                $attempts = 0;
                
                while (!$placed && $attempts < $maxAttempts) {
                    $attempts++;
                    $x = rand(0, $mapSize - 1);
                    $y = rand(0, $mapSize - 1);
                    
                    // Check if the location is already occupied
                    $query = "SELECT occupied FROM world_map WHERE location_x = ? AND location_y = ?";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bind_param("ii", $x, $y);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows === 1) {
                        $row = $result->fetch_assoc();
                        if (!$row['occupied']) {
                            // Location is free, place the city
                            $namePrefix = ['New', 'Fort', 'Port', 'Mount', 'Castle', 'Great', 'Old', 'West'];
                            $nameSuffix = ['Haven', 'Town', 'City', 'Peak', 'Falls', 'Ridge', 'Valley', 'Harbor'];
                            $cityName = $namePrefix[array_rand($namePrefix)] . ' ' . $nameSuffix[array_rand($nameSuffix)];
                            
                            // Update the map to mark this location as occupied
                            $updateQuery = "UPDATE world_map SET occupied = 1, occupier_id = ?, occupier_type = 'ai' WHERE location_x = ? AND location_y = ?";
                            $updateStmt = $this->conn->prepare($updateQuery);
                            $aiId = $data['id'];
                            $updateStmt->bind_param("iii", $aiId, $x, $y);
                            $updateStmt->execute();
                            
                            // Create the AI city
                            $ai->createCity($cityName, $x, $y);
                            $placed = true;
                        }
                    }
                }
                
                if (!$placed) {
                    echo "Warning: Could not place city for AI " . $data['name'] . " after " . $maxAttempts . " attempts.\n";
                }
            }
        }
    }
    
    /**
     * Get all AI players
     * 
     * @return array Array of AI player objects
     */
    public function getAllAI() {
        return $this->aiPlayers;
    }
    
    /**
     * Get AI player by ID
     * 
     * @param int $id AI player ID
     * @return AIBase|null AI player object or null if not found
     */
    public function getAIById($id) {
        return isset($this->aiPlayers[$id]) ? $this->aiPlayers[$id] : null;
    }
}
