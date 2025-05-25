<?php
// Fix database tables and configuration for the MMORTS project

// Include configuration
require_once __DIR__ . '/../../system/config.php';

// Check if configuration table has game_initialized column
echo "Checking configuration table structure...\n";
$sql = "SHOW COLUMNS FROM configuration LIKE 'game_initialized'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Adding game_initialized column to configuration table...\n";
    $sql = "ALTER TABLE configuration ADD COLUMN game_initialized TINYINT(1) DEFAULT 0";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'game_initialized' added successfully\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "game_initialized column already exists\n";
}

// Check if world_map table exists
echo "\nChecking world_map table...\n";
$sql = "SHOW TABLES LIKE 'world_map'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating world_map table...\n";
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
    if ($conn->query($sql) === TRUE) {
        echo "world_map table created successfully\n";
    } else {
        echo "Error creating world_map table: " . $conn->error . "\n";
    }
} else {
    echo "world_map table already exists\n";
}

// Check if ai_players table exists
echo "\nChecking ai_players table...\n";
$sql = "SHOW TABLES LIKE 'ai_players'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating ai_players table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS ai_players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        personality_type VARCHAR(50) NOT NULL,
        difficulty_level INT NOT NULL,
        active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === TRUE) {
        echo "ai_players table created successfully\n";
    } else {
        echo "Error creating ai_players table: " . $conn->error . "\n";
    }
} else {
    echo "ai_players table already exists\n";
}

// Check if ai_cities table exists
echo "\nChecking ai_cities table...\n";
$sql = "SHOW TABLES LIKE 'ai_cities'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating ai_cities table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS ai_cities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ai_player_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        resources_id INT,
        buildings_id INT,
        location_x INT NOT NULL,
        location_y INT NOT NULL,
        power INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ai_player_id) REFERENCES ai_players(id) ON DELETE CASCADE
    )";
    if ($conn->query($sql) === TRUE) {
        echo "ai_cities table created successfully\n";
    } else {
        echo "Error creating ai_cities table: " . $conn->error . "\n";
    }
} else {
    echo "ai_cities table already exists\n";
}

// Check if battles table exists
echo "\nChecking battles table...\n";
$sql = "SHOW TABLES LIKE 'battles'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating battles table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS battles (
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
    )";
    if ($conn->query($sql) === TRUE) {
        echo "battles table created successfully\n";
    } else {
        echo "Error creating battles table: " . $conn->error . "\n";
    }
} else {
    echo "battles table already exists\n";
}

// Check if ai_armies table exists
echo "\nChecking ai_armies table...\n";
$sql = "SHOW TABLES LIKE 'ai_armies'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating ai_armies table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS ai_armies (
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
    )";
    if ($conn->query($sql) === TRUE) {
        echo "ai_armies table created successfully\n";
    } else {
        echo "Error creating ai_armies table: " . $conn->error . "\n";
    }
} else {
    echo "ai_armies table already exists\n";
}

// Check if players table exists
echo "\nChecking players table...\n";
$sql = "SHOW TABLES LIKE 'players'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating players table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS players (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        uuid VARCHAR(255),
        username VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        ban_status TINYINT DEFAULT 0,
        avatar VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === TRUE) {
        echo "players table created successfully\n";
    } else {
        echo "Error creating players table: " . $conn->error . "\n";
    }
} else {
    echo "players table already exists\n";
}

// Check if player_armies table exists
echo "\nChecking player_armies table...\n";
$sql = "SHOW TABLES LIKE 'player_armies'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating player_armies table...\n";
    // First check if players table exists
    $playerTableCheck = "SHOW TABLES LIKE 'players'";
    $playerTableResult = $conn->query($playerTableCheck);
    if ($playerTableResult && $playerTableResult->num_rows > 0) {
        // Players table exists, create with foreign key
        $sql = "CREATE TABLE IF NOT EXISTS player_armies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            player_id BIGINT NOT NULL,
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
            FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
        )";
    } else {
        // Players table doesn't exist, create without foreign key
        $sql = "CREATE TABLE IF NOT EXISTS player_armies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            player_id INT NOT NULL,
            fighters INT DEFAULT 0,
            shooters INT DEFAULT 0,
            vehicles INT DEFAULT 0,
            skirmishers INT DEFAULT 0,
            riders INT DEFAULT 0,
            canons INT DEFAULT 0,
            jets INT DEFAULT 0,
            archers INT DEFAULT 0,
            marauders INT DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
    }

    if ($conn->query($sql) === TRUE) {
        echo "player_armies table created successfully\n";
    } else {
        echo "Error creating player_armies table: " . $conn->error . "\n";
    }
} else {
    echo "player_armies table already exists\n";
}

echo "\nDatabase setup completed!\n";

// Check if cities table exists
echo "\nChecking cities table...\n";
$sql = "SHOW TABLES LIKE 'cities'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating cities table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS cities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id BIGINT NOT NULL,
        name VARCHAR(255) NOT NULL,
        resources_id INT,
        buildings_id INT,
        location_x INT NOT NULL,
        location_y INT NOT NULL,
        power INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    )";
    if ($conn->query($sql) === TRUE) {
        echo "cities table created successfully\n";
    } else {
        echo "Error creating cities table: " . $conn->error . "\n";
    }
} else {
    echo "cities table already exists\n";
}

// Check if ai_turn_log table exists
echo "\nChecking ai_turn_log table...\n";
$sql = "SHOW TABLES LIKE 'ai_turn_log'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating ai_turn_log table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS ai_turn_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        execution_time FLOAT NOT NULL,
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === TRUE) {
        echo "ai_turn_log table created successfully\n";
    } else {
        echo "Error creating ai_turn_log table: " . $conn->error . "\n";
    }
} else {
    echo "ai_turn_log table already exists\n";
}

// Check if ai_action_log table exists
echo "\nChecking ai_action_log table...\n";
$sql = "SHOW TABLES LIKE 'ai_action_log'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating ai_action_log table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS ai_action_log (
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
    if ($conn->query($sql) === TRUE) {
        echo "ai_action_log table created successfully\n";
    } else {
        echo "Error creating ai_action_log table: " . $conn->error . "\n";
    }
} else {
    echo "ai_action_log table already exists\n";
}

// Check if resources table exists
echo "\nChecking resources table...\n";
$sql = "SHOW TABLES LIKE 'resources'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating resources table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        iron INT DEFAULT 1000,
        wood INT DEFAULT 500,
        stone INT DEFAULT 500,
        food INT DEFAULT 500,
        teleports INT DEFAULT 2,
        diamonds INT DEFAULT 0,
        last_update INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === TRUE) {
        echo "resources table created successfully\n";
    } else {
        echo "Error creating resources table: " . $conn->error . "\n";
    }
} else {
    echo "resources table already exists\n";

    // Check if last_update column exists in resources table
    $sql = "SHOW COLUMNS FROM resources LIKE 'last_update'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows == 0) {
        echo "Adding last_update column to resources table...\n";
        $sql = "ALTER TABLE resources ADD COLUMN last_update INT DEFAULT 0";
        if ($conn->query($sql) === TRUE) {
            echo "Column 'last_update' added successfully\n";
        } else {
            echo "Error adding column: " . $conn->error . "\n";
        }
    }
}

// check if resources table has teleport and diamond columns
$sql = "SHOW COLUMNS FROM resources LIKE 'teleports'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Adding teleports column to resources table...\n";
    $sql = "ALTER TABLE resources ADD COLUMN teleports INT DEFAULT 2";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'teleports' added successfully\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "teleports column already exists\n";
}

$sql = "SHOW COLUMNS FROM resources LIKE 'diamonds'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Adding diamonds column to resources table...\n";
    $sql = "ALTER TABLE resources ADD COLUMN diamonds INT DEFAULT 0";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'diamonds' added successfully\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "diamonds column already exists\n";
}

// Check if productions table exists
echo "\nChecking productions table...\n";
$sql = "SHOW TABLES LIKE 'productions'";
$result = $conn->query($sql);
if ($result && $result->num_rows == 0) {
    echo "Creating productions table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS productions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gold_rate INT DEFAULT 10,
        wood_rate INT DEFAULT 5,
        stone_rate INT DEFAULT 5,
        food_rate INT DEFAULT 8,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if ($conn->query($sql) === TRUE) {
        echo "productions table created successfully\n";
    } else {
        echo "Error creating productions table: " . $conn->error . "\n";
    }
} else {
    echo "productions table already exists\n";
}
