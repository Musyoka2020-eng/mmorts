<?php
// Initialize Game World
// This script sets up the game world with AI opponents and a map

// Include required files
require_once __DIR__ . '/../../system/config.php';
require_once __DIR__ . '/../ai/ai_manager.php';
require_once __DIR__ . '/../world/map_generator.php';

// First, ensure tables are created
include_once __DIR__ . '/../../fix_database.php';

// Configuration
$mapSize = 50; // 50x50 map
$aiCount = 5;  // 5 AI opponents
$aiDifficulty = 1; // Starting difficulty (1-3)

// Create the map
$mapGenerator = new MapGenerator($conn, $mapSize);
echo "Generating world map...\n";
$mapGenerator->createWorldMap();
echo "World map generated successfully.\n";

// Create AI opponents
$aiManager = new AIManager($conn);
echo "Creating AI opponents...\n";
$aiManager->createInitialAIPlayers($aiCount, $aiDifficulty);
echo "AI opponents created successfully.\n";

// Load AI opponents
$aiManager->loadAllAI();

// Place AI cities on the map
echo "Placing AI cities on the map...\n";
$aiManager->placeAICities($mapSize);
echo "AI cities placed successfully.\n";

// Mark that the game has been initialized
$query = "UPDATE configuration SET game_initialized = 1 WHERE id = 1";
$conn->query($query);

echo "Game world initialization complete!\n";

// Add a player_armies table if it doesn't exist yet
$query = "CREATE TABLE IF NOT EXISTS player_armies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    fighters INT DEFAULT 0,
    shooters INT DEFAULT 0,
    vehicles INT DEFAULT 0,
    skmisher INT DEFAULT 0,
    rides INT DEFAULT 0,
    canons INT DEFAULT 0,
    jets INT DEFAULT 0,
    archers INT DEFAULT 0,
    marauders INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
)";
$conn->query($query);

// Function to add starter armies for new players
function addStarterArmies($conn, $playerId) {
    // Check if player already has armies
    $query = "SELECT id FROM player_armies WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Add starter armies
        $query = "INSERT INTO player_armies (player_id, fighters, shooters) VALUES (?, 20, 10)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $playerId);
        $stmt->execute();
    }
}

// Add a column to configuration table to track game initialization
$query = "SHOW COLUMNS FROM configuration LIKE 'game_initialized'";
$result = $conn->query($query);
if ($result->num_rows === 0) {
    $query = "ALTER TABLE configuration ADD COLUMN game_initialized TINYINT(1) DEFAULT 0";
    $conn->query($query);
}

// Close connection
$conn->close();
