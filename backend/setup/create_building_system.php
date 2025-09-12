<?php
/**
 * Building System Database Setup
 * Creates all necessary tables for the building system
 */

require_once __DIR__ . '/../../system/includes.php';

$g = globals();
$conn = $g->getDatabase();

echo "Creating Building System Database Tables...\n\n";

// 1. Building Types Table - Defines all available buildings
$query = "CREATE TABLE IF NOT EXISTS building_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    category ENUM('resource', 'military', 'defense', 'research', 'special') DEFAULT 'resource',
    icon VARCHAR(10) DEFAULT '🏢',
    max_level INT DEFAULT 20,
    base_cost_wood INT DEFAULT 0,
    base_cost_iron INT DEFAULT 0,
    base_cost_stone INT DEFAULT 0,
    base_cost_food INT DEFAULT 0,
    base_cost_oil INT DEFAULT 0,
    base_construction_time INT DEFAULT 300,
    cost_multiplier DECIMAL(3,2) DEFAULT 1.5,
    time_multiplier DECIMAL(3,2) DEFAULT 1.2,
    prerequisite_building_id INT NULL,
    prerequisite_level INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prerequisite_building_id) REFERENCES building_types(id) ON DELETE SET NULL
) ENGINE=InnoDB";

if ($conn->query($query)) {
    echo "✅ Created building_types table\n";
} else {
    echo "❌ Error creating building_types table: " . $conn->error . "\n";
}

// 2. Building Effects Table - Defines what each building level provides
$query = "CREATE TABLE IF NOT EXISTS building_effects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_type_id INT NOT NULL,
    level INT NOT NULL,
    effect_type ENUM('production', 'storage', 'military', 'defense', 'research', 'population') NOT NULL,
    effect_target VARCHAR(50) NOT NULL,
    effect_value INT NOT NULL,
    effect_modifier ENUM('add', 'multiply', 'percent') DEFAULT 'add',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_building_effect (building_type_id, level, effect_type, effect_target)
) ENGINE=InnoDB";

if ($conn->query($query)) {
    echo "✅ Created building_effects table\n";
} else {
    echo "❌ Error creating building_effects table: " . $conn->error . "\n";
}

// 3. City Buildings Table - Buildings actually constructed in cities
$query = "CREATE TABLE IF NOT EXISTS city_buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city_id INT NOT NULL,
    building_type_id INT NOT NULL,
    level INT DEFAULT 1,
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    construction_started_at TIMESTAMP NULL,
    construction_completed_at TIMESTAMP NULL,
    last_upgraded_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE,
    INDEX idx_city_buildings (city_id),
    INDEX idx_building_type (building_type_id),
    UNIQUE KEY unique_city_building_position (city_id, position_x, position_y)
) ENGINE=InnoDB";

if ($conn->query($query)) {
    echo "✅ Created city_buildings table\n";
} else {
    echo "❌ Error creating city_buildings table: " . $conn->error . "\n";
}

// 4. Construction Queue Table - Manages building construction timing
$query = "CREATE TABLE IF NOT EXISTS construction_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city_id INT NOT NULL,
    building_type_id INT NOT NULL,
    city_building_id INT NULL,
    queue_type ENUM('build', 'upgrade') NOT NULL,
    target_level INT NOT NULL,
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    cost_wood INT DEFAULT 0,
    cost_iron INT DEFAULT 0,
    cost_stone INT DEFAULT 0,
    cost_food INT DEFAULT 0,
    cost_oil INT DEFAULT 0,
    construction_time INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('queued', 'building', 'completed', 'cancelled') DEFAULT 'queued',
    queue_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_type_id) REFERENCES building_types(id) ON DELETE CASCADE,
    FOREIGN KEY (city_building_id) REFERENCES city_buildings(id) ON DELETE CASCADE,
    INDEX idx_construction_city (city_id),
    INDEX idx_construction_status (status)
) ENGINE=InnoDB";

if ($conn->query($query)) {
    echo "✅ Created construction_queue table\n";
} else {
    echo "❌ Error creating construction_queue table: " . $conn->error . "\n";
}

// 5. Insert Default Building Types
echo "\nInserting default building types...\n";

$buildingTypes = [
    // Resource Production Buildings
    [
        'name' => 'lumber_mill',
        'display_name' => 'Lumber Mill',
        'description' => 'Increases wood production for your city',
        'category' => 'resource',
        'icon' => '🪓',
        'max_level' => 25,
        'base_cost_wood' => 100,
        'base_cost_stone' => 50,
        'base_construction_time' => 180
    ],
    [
        'name' => 'iron_mine',
        'display_name' => 'Iron Mine',
        'description' => 'Increases iron production for your city',
        'category' => 'resource',
        'icon' => '⛏️',
        'max_level' => 25,
        'base_cost_wood' => 80,
        'base_cost_iron' => 60,
        'base_cost_stone' => 120,
        'base_construction_time' => 200
    ],
    [
        'name' => 'farm',
        'display_name' => 'Farm',
        'description' => 'Increases food production for your city',
        'category' => 'resource',
        'icon' => '🚜',
        'max_level' => 25,
        'base_cost_wood' => 150,
        'base_cost_stone' => 100,
        'base_construction_time' => 150
    ],
    [
        'name' => 'quarry',
        'display_name' => 'Stone Quarry',
        'description' => 'Increases stone production for your city',
        'category' => 'resource',
        'icon' => '🗿',
        'max_level' => 25,
        'base_cost_wood' => 120,
        'base_cost_iron' => 80,
        'base_construction_time' => 240
    ],
    [
        'name' => 'oil_rig',
        'display_name' => 'Oil Rig',
        'description' => 'Increases oil production for your city',
        'category' => 'resource',
        'icon' => '🛢️',
        'max_level' => 25,
        'base_cost_wood' => 200,
        'base_cost_iron' => 300,
        'base_cost_stone' => 200,
        'base_construction_time' => 360
    ],
    
    // Storage Buildings
    [
        'name' => 'warehouse',
        'display_name' => 'Warehouse',
        'description' => 'Increases resource storage capacity',
        'category' => 'resource',
        'icon' => '🏭',
        'max_level' => 20,
        'base_cost_wood' => 300,
        'base_cost_iron' => 150,
        'base_cost_stone' => 250,
        'base_construction_time' => 300
    ],
    
    // Military Buildings
    [
        'name' => 'barracks',
        'display_name' => 'Barracks',
        'description' => 'Trains infantry units and increases army capacity',
        'category' => 'military',
        'icon' => '🏠',
        'max_level' => 20,
        'base_cost_wood' => 250,
        'base_cost_iron' => 150,
        'base_cost_stone' => 200,
        'base_construction_time' => 480
    ],
    [
        'name' => 'armory',
        'display_name' => 'Armory',
        'description' => 'Produces advanced military equipment and units',
        'category' => 'military',
        'icon' => '🔧',
        'max_level' => 15,
        'base_cost_wood' => 150,
        'base_cost_iron' => 400,
        'base_cost_stone' => 200,
        'base_cost_oil' => 100,
        'base_construction_time' => 600
    ],
    [
        'name' => 'vehicle_factory',
        'display_name' => 'Vehicle Factory',
        'description' => 'Produces vehicles and advanced military units',
        'category' => 'military',
        'icon' => '🏭',
        'max_level' => 15,
        'base_cost_wood' => 300,
        'base_cost_iron' => 500,
        'base_cost_stone' => 300,
        'base_cost_oil' => 200,
        'base_construction_time' => 720
    ],
    
    // Defense Buildings
    [
        'name' => 'watchtower',
        'display_name' => 'Watch Tower',
        'description' => 'Provides early warning and increases city defense',
        'category' => 'defense',
        'icon' => '🗼',
        'max_level' => 10,
        'base_cost_wood' => 100,
        'base_cost_stone' => 200,
        'base_construction_time' => 240
    ],
    [
        'name' => 'city_wall',
        'display_name' => 'City Wall',
        'description' => 'Strongly increases city defense against attacks',
        'category' => 'defense',
        'icon' => '🧱',
        'max_level' => 15,
        'base_cost_wood' => 50,
        'base_cost_stone' => 500,
        'base_cost_iron' => 100,
        'base_construction_time' => 600
    ],
    
    // Research Buildings
    [
        'name' => 'research_lab',
        'display_name' => 'Research Laboratory',
        'description' => 'Enables technology research and development',
        'category' => 'research',
        'icon' => '🔬',
        'max_level' => 15,
        'base_cost_wood' => 200,
        'base_cost_iron' => 300,
        'base_cost_stone' => 150,
        'base_construction_time' => 900
    ],
    
    // Special Buildings
    [
        'name' => 'city_hall',
        'display_name' => 'City Hall',
        'description' => 'Administrative center that enables city growth and management',
        'category' => 'special',
        'icon' => '🏛️',
        'max_level' => 10,
        'base_cost_wood' => 500,
        'base_cost_iron' => 300,
        'base_cost_stone' => 800,
        'base_construction_time' => 1800
    ]
];

foreach ($buildingTypes as $building) {
    $query = "INSERT INTO building_types (
        name, display_name, description, category, icon, max_level,
        base_cost_wood, base_cost_iron, base_cost_stone, base_cost_food, base_cost_oil,
        base_construction_time
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        display_name = VALUES(display_name),
        description = VALUES(description),
        category = VALUES(category),
        icon = VALUES(icon)";
    
    $stmt = $conn->prepare($query);
    
    // Fix the bind_param issue by extracting values first
    $base_cost_iron = $building['base_cost_iron'] ?? 0;
    $base_cost_stone = $building['base_cost_stone'] ?? 0;
    $base_cost_food = $building['base_cost_food'] ?? 0;
    $base_cost_oil = $building['base_cost_oil'] ?? 0;
    
    $stmt->bind_param("sssssiiiiiii",
        $building['name'],
        $building['display_name'],
        $building['description'],
        $building['category'],
        $building['icon'],
        $building['max_level'],
        $building['base_cost_wood'],
        $base_cost_iron,
        $base_cost_stone,
        $base_cost_food,
        $base_cost_oil,
        $building['base_construction_time']
    );
    
    if ($stmt->execute()) {
        echo "✅ Added building type: " . $building['display_name'] . "\n";
    } else {
        echo "❌ Error adding " . $building['display_name'] . ": " . $stmt->error . "\n";
    }
}

// 6. Insert Default Building Effects
echo "\nInserting default building effects...\n";

$effects = [
    // Lumber Mill Effects
    ['building' => 'lumber_mill', 'level' => 1, 'type' => 'production', 'target' => 'wood', 'value' => 5],
    ['building' => 'lumber_mill', 'level' => 5, 'type' => 'production', 'target' => 'wood', 'value' => 15],
    ['building' => 'lumber_mill', 'level' => 10, 'type' => 'production', 'target' => 'wood', 'value' => 30],
    ['building' => 'lumber_mill', 'level' => 15, 'type' => 'production', 'target' => 'wood', 'value' => 50],
    ['building' => 'lumber_mill', 'level' => 20, 'type' => 'production', 'target' => 'wood', 'value' => 75],
    ['building' => 'lumber_mill', 'level' => 25, 'type' => 'production', 'target' => 'wood', 'value' => 100],
    
    // Iron Mine Effects
    ['building' => 'iron_mine', 'level' => 1, 'type' => 'production', 'target' => 'iron', 'value' => 3],
    ['building' => 'iron_mine', 'level' => 5, 'type' => 'production', 'target' => 'iron', 'value' => 10],
    ['building' => 'iron_mine', 'level' => 10, 'type' => 'production', 'target' => 'iron', 'value' => 20],
    ['building' => 'iron_mine', 'level' => 15, 'type' => 'production', 'target' => 'iron', 'value' => 35],
    ['building' => 'iron_mine', 'level' => 20, 'type' => 'production', 'target' => 'iron', 'value' => 55],
    ['building' => 'iron_mine', 'level' => 25, 'type' => 'production', 'target' => 'iron', 'value' => 80],
    
    // Farm Effects
    ['building' => 'farm', 'level' => 1, 'type' => 'production', 'target' => 'food', 'value' => 8],
    ['building' => 'farm', 'level' => 5, 'type' => 'production', 'target' => 'food', 'value' => 20],
    ['building' => 'farm', 'level' => 10, 'type' => 'production', 'target' => 'food', 'value' => 40],
    ['building' => 'farm', 'level' => 15, 'type' => 'production', 'target' => 'food', 'value' => 65],
    ['building' => 'farm', 'level' => 20, 'type' => 'production', 'target' => 'food', 'value' => 95],
    ['building' => 'farm', 'level' => 25, 'type' => 'production', 'target' => 'food', 'value' => 130],
    
    // Stone Quarry Effects
    ['building' => 'quarry', 'level' => 1, 'type' => 'production', 'target' => 'stone', 'value' => 2],
    ['building' => 'quarry', 'level' => 5, 'type' => 'production', 'target' => 'stone', 'value' => 8],
    ['building' => 'quarry', 'level' => 10, 'type' => 'production', 'target' => 'stone', 'value' => 18],
    ['building' => 'quarry', 'level' => 15, 'type' => 'production', 'target' => 'stone', 'value' => 30],
    ['building' => 'quarry', 'level' => 20, 'type' => 'production', 'target' => 'stone', 'value' => 45],
    ['building' => 'quarry', 'level' => 25, 'type' => 'production', 'target' => 'stone', 'value' => 65],
    
    // Oil Rig Effects
    ['building' => 'oil_rig', 'level' => 1, 'type' => 'production', 'target' => 'oil', 'value' => 1],
    ['building' => 'oil_rig', 'level' => 5, 'type' => 'production', 'target' => 'oil', 'value' => 5],
    ['building' => 'oil_rig', 'level' => 10, 'type' => 'production', 'target' => 'oil', 'value' => 12],
    ['building' => 'oil_rig', 'level' => 15, 'type' => 'production', 'target' => 'oil', 'value' => 22],
    ['building' => 'oil_rig', 'level' => 20, 'type' => 'production', 'target' => 'oil', 'value' => 35],
    ['building' => 'oil_rig', 'level' => 25, 'type' => 'production', 'target' => 'oil', 'value' => 50],
    
    // Warehouse Effects (Storage)
    ['building' => 'warehouse', 'level' => 1, 'type' => 'storage', 'target' => 'all', 'value' => 1000],
    ['building' => 'warehouse', 'level' => 5, 'type' => 'storage', 'target' => 'all', 'value' => 5000],
    ['building' => 'warehouse', 'level' => 10, 'type' => 'storage', 'target' => 'all', 'value' => 12000],
    ['building' => 'warehouse', 'level' => 15, 'type' => 'storage', 'target' => 'all', 'value' => 25000],
    ['building' => 'warehouse', 'level' => 20, 'type' => 'storage', 'target' => 'all', 'value' => 50000],
    
    // Defense Buildings Effects
    ['building' => 'watchtower', 'level' => 1, 'type' => 'defense', 'target' => 'city', 'value' => 10],
    ['building' => 'watchtower', 'level' => 5, 'type' => 'defense', 'target' => 'city', 'value' => 25],
    ['building' => 'watchtower', 'level' => 10, 'type' => 'defense', 'target' => 'city', 'value' => 50],
    
    ['building' => 'city_wall', 'level' => 1, 'type' => 'defense', 'target' => 'city', 'value' => 25],
    ['building' => 'city_wall', 'level' => 5, 'type' => 'defense', 'target' => 'city', 'value' => 75],
    ['building' => 'city_wall', 'level' => 10, 'type' => 'defense', 'target' => 'city', 'value' => 150],
    ['building' => 'city_wall', 'level' => 15, 'type' => 'defense', 'target' => 'city', 'value' => 300],
    
    // Military Buildings Effects
    ['building' => 'barracks', 'level' => 1, 'type' => 'military', 'target' => 'training_speed', 'value' => 10],
    ['building' => 'barracks', 'level' => 5, 'type' => 'military', 'target' => 'training_speed', 'value' => 25],
    ['building' => 'barracks', 'level' => 10, 'type' => 'military', 'target' => 'training_speed', 'value' => 50],
    ['building' => 'barracks', 'level' => 15, 'type' => 'military', 'target' => 'army_capacity', 'value' => 100],
    ['building' => 'barracks', 'level' => 20, 'type' => 'military', 'target' => 'army_capacity', 'value' => 250],
];

// Get building type IDs for effects
$buildingIds = [];
$result = $conn->query("SELECT id, name FROM building_types");
while ($row = $result->fetch_assoc()) {
    $buildingIds[$row['name']] = $row['id'];
}

foreach ($effects as $effect) {
    if (!isset($buildingIds[$effect['building']])) {
        continue;
    }
    
    $buildingTypeId = $buildingIds[$effect['building']];
    
    $query = "INSERT INTO building_effects (building_type_id, level, effect_type, effect_target, effect_value)
              VALUES (?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE effect_value = VALUES(effect_value)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iissi",
        $buildingTypeId,
        $effect['level'],
        $effect['type'],
        $effect['target'],
        $effect['value']
    );
    
    if ($stmt->execute()) {
        echo "✅ Added effect for " . $effect['building'] . " level " . $effect['level'] . "\n";
    } else {
        echo "❌ Error adding effect: " . $stmt->error . "\n";
    }
}

echo "\n🎉 Building System Database Setup Complete!\n\n";
echo "Created tables:\n";
echo "- building_types (building definitions)\n";
echo "- building_effects (level-based bonuses)\n";
echo "- city_buildings (constructed buildings)\n";
echo "- construction_queue (time-based construction)\n\n";
echo "Next steps:\n";
echo "1. Add BuildingManager class\n";
echo "2. Integrate with existing GameConfig\n";
echo "3. Create building UI interface\n";
echo "4. Connect with resource production system\n";
?>
