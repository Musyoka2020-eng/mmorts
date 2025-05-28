<?php
// Create tables for time-based gathering system
require_once __DIR__ . '/../../system/config.php';

// Create gathering_operations table for tracking active gathering
$sql = "CREATE TABLE IF NOT EXISTS gathering_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT NOT NULL,
    location_x INT NOT NULL,
    location_y INT NOT NULL,
    resource_type ENUM('wood', 'iron', 'food', 'oil', 'stone') NOT NULL,
    amount_to_gather INT NOT NULL,
    amount_gathered INT DEFAULT 0,
    gathering_rate DECIMAL(5,2) DEFAULT 10.00, -- units per minute
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estimated_completion TIMESTAMP NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_player_status (player_id, status),
    INDEX idx_location (location_x, location_y),
    INDEX idx_completion (estimated_completion),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table gathering_operations created successfully\n";
} else {
    echo "Error creating gathering_operations table: " . $conn->error . "\n";
}

// Create gathering_rates table for different resource gathering speeds
$sql = "CREATE TABLE IF NOT EXISTS gathering_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_type ENUM('wood', 'iron', 'food', 'oil', 'stone') NOT NULL UNIQUE,
    base_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00, -- base units per minute
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table gathering_rates created successfully\n";
} else {
    echo "Error creating gathering_rates table: " . $conn->error . "\n";
}

// Insert default gathering rates
$defaultRates = [
    ['wood', 15.0, 'Common resource, quick to gather'],
    ['food', 12.0, 'Moderate gathering speed for sustenance'],
    ['stone', 8.0, 'Heavy material, slower to extract'],
    ['iron', 6.0, 'Dense ore requiring careful extraction'],
    ['oil', 4.0, 'Precious liquid resource, slow extraction']
];

$stmt = $conn->prepare("INSERT INTO gathering_rates (resource_type, base_rate, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE base_rate = VALUES(base_rate), description = VALUES(description)");

foreach ($defaultRates as $rate) {
    $stmt->bind_param("sds", $rate[0], $rate[1], $rate[2]);
    $stmt->execute();
}

echo "Default gathering rates inserted successfully\n";

$conn->close();
?>
