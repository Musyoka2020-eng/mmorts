<?php
/**
 * Building System API
 * Handles all building-related AJAX requests
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../system/includes.php';
require_once __DIR__ . '/../buildings/building_manager.php';

// Check if user is logged in
$g = globals();
if (!$g->isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$playerId = $g->getCurrentUser('id');
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Get player's city
$conn = $g->getDatabase();
$query = "SELECT * FROM cities WHERE player_id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $playerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'City not found']);
    exit;
}

$playerCity = $result->fetch_assoc();
$cityId = $playerCity['id'];
$buildingManager = new BuildingManager();

try {
    switch ($action) {
        
        case 'get_city_buildings':
            $buildings = $buildingManager->getCityBuildings($cityId);
            $queue = $buildingManager->getConstructionQueue($cityId);
            
            echo json_encode([
                'success' => true,
                'buildings' => $buildings,
                'construction_queue' => $queue
            ]);
            break;
            
        case 'get_available_buildings':
            $category = $_GET['category'] ?? null;
            $buildings = $buildingManager->getAvailableBuildingTypes($cityId, $category);
            
            // Add cost calculations for each building
            foreach ($buildings as &$building) {
                $level = 1;
                if (isset($building['existing'])) {
                    $level = $building['existing']['level'] + 1;
                }
                $building['costs'] = GameConfig::calculateBuildingCost($building, $level);
                $building['construction_time'] = GameConfig::calculateBuildingTime($building, $level);
                $building['target_level'] = $level;
            }
            
            echo json_encode([
                'success' => true,
                'buildings' => $buildings,
                'categories' => GameConfig::getBuildingCategories()
            ]);
            break;
            
        case 'start_construction':
            $buildingTypeId = (int)($_POST['building_type_id'] ?? 0);
            $positionX = (int)($_POST['position_x'] ?? 0);
            $positionY = (int)($_POST['position_y'] ?? 0);
            
            if (!$buildingTypeId) {
                echo json_encode(['success' => false, 'message' => 'Invalid building type']);
                break;
            }
            
            $result = $buildingManager->startConstruction($cityId, $buildingTypeId, $positionX, $positionY);
            echo json_encode(['success' => $result[0], 'message' => $result[1], 'data' => $result[2]]);
            break;
            
        case 'start_upgrade':
            $cityBuildingId = (int)($_POST['city_building_id'] ?? 0);
            
            if (!$cityBuildingId) {
                echo json_encode(['success' => false, 'message' => 'Invalid building']);
                break;
            }
            
            $result = $buildingManager->startUpgrade($cityBuildingId);
            echo json_encode(['success' => $result[0], 'message' => $result[1], 'data' => $result[2]]);
            break;
            
        case 'cancel_construction':
            $queueId = (int)($_POST['queue_id'] ?? 0);
            $refundResources = $_POST['refund'] !== 'false';
            
            if (!$queueId) {
                echo json_encode(['success' => false, 'message' => 'Invalid queue item']);
                break;
            }
            
            $result = $buildingManager->cancelConstruction($queueId, $refundResources);
            echo json_encode(['success' => $result[0], 'message' => $result[1]]);
            break;
            
        case 'get_construction_queue':
            $queue = $buildingManager->getConstructionQueue($cityId);
            echo json_encode([
                'success' => true,
                'queue' => $queue
            ]);
            break;
            
        case 'process_construction':
            // Manual construction processing (usually done by cron)
            $results = $buildingManager->processConstructionQueue($cityId);
            echo json_encode([
                'success' => true,
                'processed' => $results
            ]);
            break;
            
        case 'get_building_details':
            $buildingId = (int)($_GET['building_id'] ?? 0);
            if (!$buildingId) {
                echo json_encode(['success' => false, 'message' => 'Invalid building ID']);
                break;
            }
            
            // Get building with effects
            $query = "SELECT cb.*, bt.name, bt.display_name, bt.description, bt.icon, 
                            bt.category, bt.max_level
                     FROM city_buildings cb
                     JOIN building_types bt ON cb.building_type_id = bt.id
                     WHERE cb.id = ? AND cb.city_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $buildingId, $cityId);
            $stmt->execute();
            $building = $stmt->get_result()->fetch_assoc();
            
            if (!$building) {
                echo json_encode(['success' => false, 'message' => 'Building not found']);
                break;
            }
            
            // Get current effects
            $effects = GameConfig::getBuildingEffects($conn, $building['building_type_id'], $building['level']);
            
            // Get next level effects (for upgrade preview)
            $nextLevelEffects = [];
            if ($building['level'] < $building['max_level']) {
                $nextLevelEffects = GameConfig::getBuildingEffects($conn, $building['building_type_id'], $building['level'] + 1);
                $building['upgrade_cost'] = GameConfig::calculateBuildingCost($building, $building['level'] + 1);
                $building['upgrade_time'] = GameConfig::calculateBuildingTime($building, $building['level'] + 1);
            }
            
            echo json_encode([
                'success' => true,
                'building' => $building,
                'current_effects' => $effects,
                'next_level_effects' => $nextLevelEffects
            ]);
            break;
            
        case 'get_building_categories':
            $categories = GameConfig::getBuildingCategories();
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
