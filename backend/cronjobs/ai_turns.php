<?php
/**
 * AI Turn Processing Script
 * 
 * This script handles the AI players' turns, including resource production,
 * building improvements, army training, and military actions.
 */

// Include required files
require_once __DIR__ . '/../../system/includes.php';
require_once __DIR__ . '/../ai/ai_manager.php';

// Use new globals system
$g = globals();
$conn = $g->getDatabase();

// Check if game is initialized
$query = "SELECT game_initialized FROM configuration WHERE id = 1";
$result = $conn->query($query);
$isInitialized = false;

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $isInitialized = $row['game_initialized'] == 1;
}

if (!$isInitialized) {
    die("Game world is not initialized yet!");
}

// Record start time for performance monitoring
$startTime = microtime(true);

// Initialize AI Manager
$aiManager = new AIManager($conn);

// Load all active AI players
$aiManager->loadAllAI();

// Process AI turns
$aiManager->processAITurns();

// Update resource production (simplified version)
// In a more advanced implementation, we would have different production rates based on building levels
$query = "UPDATE resources r
         JOIN ai_cities c ON r.id = c.resources_id
         JOIN productions p ON c.id = p.city_id
         SET r.wood = r.wood + p.wood_production / 6,
             r.oil = r.oil + p.oil_production / 6,
             r.iron = r.iron + p.iron_production / 6,
             r.food = r.food + p.food_production / 6,
             r.stone = r.stone + p.stone_production / 6";
$conn->query($query);

// Record execution time
$executionTime = microtime(true) - $startTime;

// Log the execution
$query = "INSERT INTO ai_turn_log (execution_time, processed_at) VALUES (?, NOW())";
$stmt = $conn->prepare($query);
$stmt->bind_param("d", $executionTime);
$stmt->execute();

echo "AI turns processed successfully in " . round($executionTime, 4) . " seconds.";

// Only close the connection if this script is being run directly, not when included
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $conn->close();
}
