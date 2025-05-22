<?php
/**
 * AI Battle Simulator
 * 
 * This script allows testing battles between AI and players
 * without requiring user interaction.
 */

// Include configuration and battle management
require_once __DIR__ . '/system/config.php';
require_once __DIR__ . '/backend/combat/battle_manager.php';
require_once __DIR__ . '/backend/ai/ai_manager.php';

// Initialize battle manager
$battleManager = new BattleManager($conn);

echo "Running AI Battle Simulator...\n\n";

// Check if game is initialized
$query = "SELECT game_initialized FROM configuration WHERE id = 1";
$result = $conn->query($query);
$isInitialized = false;

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $isInitialized = $row['game_initialized'] == 1;
}

if (!$isInitialized) {
    die("Game world is not initialized yet! Please initialize the game world first.\n");
}

// Find an active player to attack
$query = "SELECT p.id, c.id as city_id FROM players p 
         JOIN cities c ON p.id = c.player_id 
         LIMIT 1";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $player = $result->fetch_assoc();
    $playerId = $player['id'];
    $playerCityId = $player['city_id'];
    
    echo "Found player ID: $playerId with city ID: $playerCityId\n";
    
    // Find an AI player to be the attacker
    $query = "SELECT ap.id, ap.name, ap.personality_type, ac.id as city_id 
             FROM ai_players ap
             JOIN ai_cities ac ON ap.id = ac.ai_player_id
             WHERE ap.active = 1
             LIMIT 1";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $ai = $result->fetch_assoc();
        $aiId = $ai['id'];
        $aiCityId = $ai['city_id'];
        $aiName = $ai['name'];
        $aiPersonality = $ai['personality_type'];
        
        echo "Found AI ID: $aiId ($aiName, $aiPersonality) with city ID: $aiCityId\n";
        
        // Simulate a battle
        echo "Initiating battle: AI $aiId (City $aiCityId) attacking Player $playerId (City $playerCityId)...\n";
        
        try {
            $battleResult = $battleManager->initiateBattle($aiCityId, 'ai', $playerCityId, 'player');
            
            // Display battle result
            echo "\nBattle Result: " . ucfirst(str_replace('_', ' ', $battleResult['result'])) . "\n";
            
            echo "\nAttacker Losses:\n";
            foreach ($battleResult['attacker_losses'] as $unit => $count) {
                if ($count > 0) {
                    echo "- $unit: $count\n";
                }
            }
            
            echo "\nDefender Losses:\n";
            foreach ($battleResult['defender_losses'] as $unit => $count) {
                if ($count > 0) {
                    echo "- $unit: $count\n";
                }
            }
            
            echo "\nResources Plundered:\n";
            foreach ($battleResult['resources_plundered'] as $resource => $amount) {
                if ($amount > 0) {
                    echo "- $resource: $amount\n";
                }
            }
            
            // Log this AI action
            $query = "INSERT INTO ai_action_log (ai_player_id, ai_city_id, action_type, target_id, target_type, result)
                     VALUES (?, ?, 'attack', ?, 'player', ?)";
            $stmt = $conn->prepare($query);
            $resultText = "Battle Result: " . ucfirst(str_replace('_', ' ', $battleResult['result']));
            $stmt->bind_param("iiis", $aiId, $aiCityId, $playerCityId, $resultText);
            $stmt->execute();
            
            echo "\nBattle simulation completed successfully!\n";
        } catch (Exception $e) {
            echo "Error during battle: " . $e->getMessage() . "\n";
        }
    } else {
        echo "No active AI players found.\n";
    }
} else {
    echo "No players found in the database.\n";
}

// Close database connection
$conn->close();
?>
