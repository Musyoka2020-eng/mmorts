<?php
/**
 * Construction Queue Processing Cron Job
 * 
 * This script should be run every minute via cron to process
 * building construction and upgrade completions
 * 
 * Add to crontab:
 * * * * * * /usr/bin/php /path/to/MechaEmpire/backend/cronjobs/construction_queue.php
 */

require_once __DIR__ . '/../../system/includes.php';
require_once __DIR__ . '/../buildings/building_manager.php';

// Set execution time limit
set_time_limit(60);

echo "[" . date('Y-m-d H:i:s') . "] Starting construction queue processing...\n";

try {
    $g = globals();
    $conn = $g->getDatabase();
    $buildingManager = new BuildingManager();
    
    // Get all cities with active construction
    $query = "SELECT DISTINCT city_id FROM construction_queue 
             WHERE status IN ('queued', 'building')";
    $result = $conn->query($query);
    
    $processedCities = 0;
    $completedConstructions = 0;
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cityId = $row['city_id'];
            
            echo "Processing city ID: $cityId\n";
            
            // Process construction queue for this city
            $results = $buildingManager->processConstructionQueue($cityId);
            
            foreach ($results as $result) {
                if ($result['result'][0]) { // Success
                    echo "  ✅ Completed {$result['type']} in city {$result['city_id']}\n";
                    $completedConstructions++;
                } else {
                    echo "  ❌ Failed {$result['type']} in city {$result['city_id']}: {$result['result'][1]}\n";
                }
            }
            
            $processedCities++;
        }
    } else {
        echo "No active construction found.\n";
    }
    
    // Update AI cities construction as well
    echo "Processing AI construction...\n";
    
    $query = "SELECT DISTINCT cq.city_id 
             FROM construction_queue cq
             JOIN ai_cities ac ON cq.city_id = ac.id
             WHERE cq.status IN ('queued', 'building')";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cityId = $row['city_id'];
            
            echo "Processing AI city ID: $cityId\n";
            
            $results = $buildingManager->processConstructionQueue($cityId);
            
            foreach ($results as $result) {
                if ($result['result'][0]) {
                    echo "  ✅ AI completed {$result['type']} in city {$result['city_id']}\n";
                    $completedConstructions++;
                } else {
                    echo "  ❌ AI failed {$result['type']} in city {$result['city_id']}: {$result['result'][1]}\n";
                }
            }
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Construction processing complete.\n";
    echo "Processed $processedCities cities, completed $completedConstructions constructions.\n";
    
    // Clean up old completed/cancelled queue items (older than 24 hours)
    $cleanupQuery = "DELETE FROM construction_queue 
                    WHERE status IN ('completed', 'cancelled') 
                    AND completed_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $conn->query($cleanupQuery);
    
    $deletedRows = $conn->affected_rows;
    if ($deletedRows > 0) {
        echo "Cleaned up $deletedRows old queue items.\n";
    }
    
} catch (Exception $e) {
    echo "[ERROR] " . date('Y-m-d H:i:s') . " - Construction queue processing failed: " . $e->getMessage() . "\n";
    
    // Log error to file
    $logFile = __DIR__ . '/../../logs/construction_errors.log';
    $errorMsg = "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $errorMsg, FILE_APPEND | LOCK_EX);
    
    exit(1);
}

exit(0);
?>
