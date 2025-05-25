<?php
// Connect to database using configuration
require_once __DIR__ . '/../../system/config.php';

// SQL statements to create AI players and related tables
$sql_statements = [
    // AI Players table
    "CREATE TABLE IF NOT EXISTS ai_players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        personality_type VARCHAR(50) NOT NULL,
        difficulty_level INT NOT NULL,
        active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // AI Cities table
    "CREATE TABLE IF NOT EXISTS ai_cities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ai_player_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        resources_id INT,
        buildings_id INT,
        location_x INT NOT NULL,
        location_y INT NOT NULL,
        power INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ai_player_id) REFERENCES ai_players(id) ON DELETE CASCADE,
        FOREIGN KEY (resources_id) REFERENCES resources(id) ON DELETE SET NULL
    )",

    // World Map table
    "CREATE TABLE IF NOT EXISTS world_map (
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
    )",

    // Battles table
    "CREATE TABLE IF NOT EXISTS battles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attacker_id INT NOT NULL,
        attacker_type ENUM('player', 'ai', 'npc') NOT NULL,
        defender_id INT NOT NULL,
        defender_type ENUM('player', 'ai', 'npc') NOT NULL,
        battle_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        battle_result ENUM('attacker_victory', 'defender_victory', 'draw') NOT NULL,
        attacker_units_lost JSON,
        defender_units_lost JSON,
        resources_plundered JSON,
        battle_report TEXT
    )",

    // AI Armies table
    "CREATE TABLE IF NOT EXISTS ai_armies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ai_city_id INT NOT NULL,
        fighters INT DEFAULT 0,
        shooters INT DEFAULT 0,
        vehicles INT DEFAULT 0,
        skirmishers INT DEFAULT 0,
        riders INT DEFAULT 0,
        canons INT DEFAULT 0,
        jets INT DEFAULT 0,
        archers INT DEFAULT 0,
        marauders INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (ai_city_id) REFERENCES ai_cities(id) ON DELETE CASCADE
    )"
];

// Execute SQL statements
$results = [];
foreach ($sql_statements as $sql) {
    if ($conn->query($sql) === TRUE) {
        $results[] = "Table created or already exists successfully";
    } else {
        $results[] = "Error creating table: " . $conn->error;
    }
}

// Output results
echo "<pre>";
print_r($results);
echo "</pre>";

// Close connection
$conn->close();
