<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Add World Map specific CSS and JS
echo '<link rel="stylesheet" href="frontend/design/css/world-map.css">';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">';
echo '<script src="frontend/design/js/world-map.js" defer></script>';

// Include world map script
require_once __DIR__ . '/../../backend/world/map_generator.php';

// Get player location
$playerX = 25; // Default center of map
$playerY = 25; // Default center of map

// If player is logged in, get their city location
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $playerId = $_SESSION['user']['id'];
    
    // Get player city location
    $query = "SELECT location_x, location_y FROM cities 
             WHERE player_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $playerX = $row['location_x'];
        $playerY = $row['location_y'];
    } else {
        // Player has no city yet, initialize one
        // This is a placeholder for the city creation process
        $playerX = rand(10, 40);
        $playerY = rand(10, 40);
        
        // Create a city for the player (simplified)
        $query = "INSERT INTO cities (player_id, name, location_x, location_y) 
                 VALUES (?, 'Home City', ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $playerId, $playerX, $playerY);
        $stmt->execute();
        
        // Mark the location as occupied
        $query = "UPDATE world_map SET occupied = 1, occupier_id = ?, occupier_type = 'player' 
                 WHERE location_x = ? AND location_y = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $playerId, $playerX, $playerY);
        $stmt->execute();
    }
}

// Initialize map generator
$mapGenerator = new MapGenerator($conn);

// Get map view radius (how much of the map to show)
$viewRadius = 5;

// Get map data for the area around the player
$mapData = $mapGenerator->getMapArea($playerX, $playerY, $viewRadius);
?>

<div class="main">
    <div class="container my-4">
        <div class="game-container">
            <div class="game-panel">
                <div class="game-panel-header">
                    <h3><i class="fas fa-globe-americas"></i> World Map</h3>
                    <div class="coordinates-display">
                        <span class="coords-icon"><i class="fas fa-map-marker-alt"></i></span>
                        Position: <?= $playerX ?>, <?= $playerY ?>
                    </div>
                </div>
                <div class="world-map-container">
                    <div class="map-navigation mb-3">
                        <button id="map-north" class="game-button"><i class="fas fa-chevron-up"></i> North</button>
                        <button id="map-west" class="game-button"><i class="fas fa-chevron-left"></i> West</button>
                        <div class="map-position">
                            <span class="badge"><?= $playerX ?>, <?= $playerY ?></span>
                        </div>
                        <button id="map-east" class="game-button"><i class="fas fa-chevron-right"></i> East</button>
                        <button id="map-south" class="game-button"><i class="fas fa-chevron-down"></i> South</button>
                    </div>
                    
                    <div class="map-grid" style="--map-size: <?= (2 * $viewRadius) + 1 ?>">
                        <?php
                        // Draw the map grid
                        for ($y = $playerY - $viewRadius; $y <= $playerY + $viewRadius; $y++) {
                            for ($x = $playerX - $viewRadius; $x <= $playerX + $viewRadius; $x++) {
                                // Determine tile type and styling
                                $tileClass = 'map-tile terrain-plains'; // Default
                                $tileText = '';
                                $tileTooltip = "Grassland\nCoordinates: ($x, $y)";
                                $resourceClass = '';
                                $tileContent = '';
                                
                                // Check if this is the player's position
                                $isPlayerPosition = ($x == $playerX && $y == $playerY);
                                if ($isPlayerPosition) {
                                    $tileClass .= ' player-city';
                                    $tileText = '<span>P</span>';
                                    $tileTooltip = "Your City\nCoordinates: ($x, $y)";
                                }
                                
                                // Get the tile data from the map data array
                                foreach ($mapData as $tile) {
                                    if ($tile['location_x'] == $x && $tile['location_y'] == $y) {
                                        // Set terrain type class
                                        switch ($tile['terrain_type']) {
                                            case 'forest':
                                                $tileClass = 'map-tile terrain-forest';
                                                $tileTooltip = "Forest\nCoordinates: ($x, $y)";
                                                break;
                                            case 'hills':
                                                $tileClass = 'map-tile terrain-hills';
                                                $tileTooltip = "Hills\nCoordinates: ($x, $y)";
                                                break;
                                            case 'mountains':
                                                $tileClass = 'map-tile terrain-mountains';
                                                $tileTooltip = "Mountains\nCoordinates: ($x, $y)";
                                                break;
                                            case 'water':
                                                $tileClass = 'map-tile terrain-water';
                                                $tileTooltip = "Water\nCoordinates: ($x, $y)";
                                                break;
                                            case 'desert':
                                                $tileClass = 'map-tile terrain-desert';
                                                $tileTooltip = "Desert\nCoordinates: ($x, $y)";
                                                break;
                                            default:
                                                $tileClass = 'map-tile terrain-plains';
                                                $tileTooltip = "Plains\nCoordinates: ($x, $y)";
                                        }
                                        
                                        // Add resource information
                                        if (!empty($tile['resource_type'])) {
                                            switch ($tile['resource_type']) {
                                                case 'diamond':
                                                    $resourceClass = 'resource-diamond';
                                                    $tileTooltip .= "\nDiamond: " . $tile['resource_amount'];
                                                    break;
                                                case 'wood':
                                                    $resourceClass = 'resource-wood';
                                                    $tileTooltip .= "\nWood: " . $tile['resource_amount'];
                                                    break;
                                                case 'stone':
                                                    $resourceClass = 'resource-stone';
                                                    $tileTooltip .= "\nStone: " . $tile['resource_amount'];
                                                    break;
                                                case 'food':
                                                    $resourceClass = 'resource-food';
                                                    $tileTooltip .= "\nFood: " . $tile['resource_amount'];
                                                    break;
                                                case 'iron':
                                                    $resourceClass = 'resource-iron';
                                                    $tileTooltip .= "\nIron: " . $tile['resource_amount'];
                                                    break;
                                                case 'oil':
                                                    $resourceClass = 'resource-oil';
                                                    $tileTooltip .= "\nOil: " . $tile['resource_amount'];
                                                    break;
                                            }
                                        }
                                          // Check if tile is occupied
                                        if ($tile['occupied'] && !$isPlayerPosition) {
                                            if ($tile['occupier_type'] == 'player') {
                                                $tileClass .= ' city-tile';
                                                $tileText = '<span>P</span>';
                                                $cityName = isset($tile['city_name']) ? $tile['city_name'] : 'Unknown City';
                                                $ownerName = isset($tile['occupier_name']) ? $tile['occupier_name'] : 'Unknown Player';
                                                $tileTooltip = "Player City\nOwner: $ownerName\nCity: $cityName\nCoordinates: ($x, $y)";
                                            } else if ($tile['occupier_type'] == 'ai') {
                                                $tileClass .= ' city-tile';
                                                $tileText = '<span>A</span>';
                                                $cityName = isset($tile['city_name']) ? $tile['city_name'] : 'Unknown City';
                                                $ownerName = isset($tile['occupier_name']) ? $tile['occupier_name'] : 'Unknown AI';
                                                $tileTooltip = "AI City\nController: $ownerName\nCity: $cityName\nCoordinates: ($x, $y)";
                                            }
                                        }
                                    }
                                }
                                
                                // Add resource HTML
                                $resourceHtml = '';
                                if ($resourceClass) {
                                    $resourceHtml = "<div class='tile-resource $resourceClass'></div>";
                                }

                                // Output the tile with data attributes for JavaScript
                                echo "<div class='$tileClass' data-x='$x' data-y='$y' data-content='$tileTooltip' title='$tileTooltip'>
                                        $tileText
                                        $resourceHtml
                                      </div>";
                            }
                        }
                        ?>
                    </div>
                    
                    <div class="map-legend mt-3">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="fas fa-mountain"></i> Terrain Types</h5>
                                <div class="d-flex flex-wrap">
                                    <div class="legend-item">
                                        <div class="map-tile terrain-plains d-inline-block"></div>
                                        <span>Plains</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-tile terrain-forest d-inline-block"></div>
                                        <span>Forest</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-tile terrain-hills d-inline-block"></div>
                                        <span>Hills</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-tile terrain-mountains d-inline-block"></div>
                                        <span>Mountains</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-tile terrain-water d-inline-block"></div>
                                        <span>Water</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-tile terrain-desert d-inline-block"></div>
                                        <span>Desert</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="fas fa-city"></i> Cities & Resources</h5>
                                <div class="d-flex flex-wrap">
                                    <div class="legend-item">
                                        <div class="map-tile player-city d-inline-block"><span>P</span></div>
                                        <span>Your City</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-tile city-tile d-inline-block"><span>A</span></div>
                                        <span>AI City</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-tile terrain-plains d-inline-block">
                                            <div class="tile-resource resource-diamond"></div>
                                        </div>
                                        <span>Diamond</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-tile terrain-plains d-inline-block">
                                            <div class="tile-resource resource-wood"></div>
                                        </div>
                                        <span>Wood</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-tile terrain-plains d-inline-block">
                                            <div class="tile-resource resource-stone"></div>
                                        </div>
                                        <span>Stone</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-tile terrain-plains d-inline-block">
                                            <div class="tile-resource resource-food"></div>
                                        </div>
                                        <span>Food</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Map interaction scripts are now in world-map.js -->

<?php include_once __DIR__ . '/../' . 'templates/footer.php'; ?>
