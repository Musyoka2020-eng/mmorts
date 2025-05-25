<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Add World Map specific CSS and JS
echo '<link rel="stylesheet" href="frontend/design/css/world-map.css">';
echo '<link rel="stylesheet" href="frontend/design/css/enhanced-tooltips.css">'; // Add enhanced tooltips CSS
echo '<link rel="stylesheet" href="frontend/design/css/smooth-map-navigation.css">'; // Add smooth navigation CSS
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">';
echo '<script src="frontend/design/js/world-map.js" defer></script>';
echo '<script src="frontend/design/js/enhanced-tooltips.js" defer></script>'; // Add the enhanced tooltips
echo '<script src="frontend/design/js/smooth-map-navigation.js" defer></script>'; // Add smooth navigation
echo '<script src="frontend/design/js/ajax-map-loader.js" defer></script>'; // Add AJAX map loader
// Include world map script
require_once __DIR__ . '/../../backend/world/map_generator.php';

// Get player location
$playerX = 25; // Default center of map
$playerY = 25; // Default center of map
$cityX = 0; // Default city X coordinate
$cityY = 0; // Default city Y coordinate

// If player is logged in, get their city location
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $playerId = $_SESSION['user']['id'];

    // Check if the player has a city
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
        $cityName = "Vatican City"; // Default city name

        // First create a city for the player
        $query = "INSERT INTO cities (player_id, name, location_x, location_y) 
                 VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isii", $playerId, $cityName, $playerX, $playerY);
        $stmt->execute();

        // Get the city ID
        $cityId = $conn->insert_id;

        // Create a resources record for the player
        $query = "INSERT INTO resources (city_id, wood, stone, food, iron, oil, teleport, diamond, last_update) 
                VALUES (?, 1000, 500, 500, 500, 500, 2, 0, UNIX_TIMESTAMP())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $cityId);
        $stmt->execute();

        // Get the resources ID
        $resourcesId = $conn->insert_id;

        // Update the city with the resources ID
        $query = "UPDATE cities SET resources_id = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $resourcesId, $cityId);
        $stmt->execute();

        // Mark the location as occupied
        $query = "UPDATE world_map SET occupied = 1, occupier_id = ?, occupier_type = 'player' 
                 WHERE location_x = ? AND location_y = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $playerId, $playerX, $playerY);
        $stmt->execute();

        // Log the creation of new city and resources
        error_log("New player city created at ($playerX, $playerY) with city ID: $cityId, resources ID: $resourcesId");
    }
}

// Fetch real-time coordinates from the database every time the page loads
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $playerId = $_SESSION['user']['id'];

    // Query to get the player's current coordinates
    $query = "SELECT location_x, location_y FROM cities WHERE player_id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $cityX = $row['location_x'];
        $cityY = $row['location_y'];
    } else {
        error_log("World Map: Failed to fetch real-time coordinates for player ID $playerId");
    }
}

// Initialize map generator with proper ordering of parameters
// The constructor takes a database connection and map size
$mapGenerator = new MapGenerator($conn);

// Check if navigation coordinates are provided in URL
if (isset($_GET['x']) && isset($_GET['y'])) {
    // Get coordinates from URL parameters
    $navX = intval($_GET['x']);
    $navY = intval($_GET['y']);

    // Log navigation attempt
    error_log("World Map Navigation: Attempting to navigate to coordinates ($navX, $navY)");

    // Validate coordinates to ensure they're within valid map range
    if (
        $navX >= 0 && $navX < $mapGenerator->getMapSize() &&
        $navY >= 0 && $navY < $mapGenerator->getMapSize()
    ) {
        // Override player coordinates with navigation coordinates
        $playerX = $navX;
        $playerY = $navY;
        error_log("World Map Navigation: Successfully navigated to ($playerX, $playerY)");
    } else {
        error_log("World Map Navigation: Invalid coordinates ($navX, $navY), using default position");
    }
}

// Get map view radius (how much of the map to show)
// Using same dimensions as get_map_data.php for AJAX
$viewRadiusY = 5; // Vertical radius (height)
$viewRadiusX = 8; // Horizontal radius (width) - wider than height

// Check if coordinates are valid for the map size
$mapSize = $mapGenerator->getMapSize();
if ($playerX < 0 || $playerX >= $mapSize || $playerY < 0 || $playerY >= $mapSize) {
    error_log("World Map Error: Player coordinates ($playerX, $playerY) are outside map range (0-$mapSize)");
    // Force valid coordinates
    $playerX = min(max(0, $playerX), $mapSize - 1);
    $playerY = min(max(0, $playerY), $mapSize - 1);
}

// Get map data for the area around the player with the specific X/Y radiuses
$mapData = $mapGenerator->getMapArea($playerX, $playerY, null, $viewRadiusX, $viewRadiusY);

// Debug world map variables
error_log("World Map Debug: PlayerX: $playerX, PlayerY: $playerY");
error_log("World Map Debug: GET parameters: " . print_r($_GET, true));
error_log("World Map Debug: Map radiusX: $viewRadiusX, radiusY: $viewRadiusY");
error_log("World Map Debug: Map size: " . $mapGenerator->getMapSize());
error_log("World Map Debug: Map data count: " . count($mapData));
?>

<div class="main">
    <div class="container my-4">
        <div class="game-container">
            <div class="game-panel">
                <div class="game-panel-header">
                    <h3><i class="fas fa-globe-americas"></i> World Map</h3>
                    <div class="coordinates-display">
                        <span class="coords-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <span class="coords-icon"><i class="fas fa-city"></i></span>
                        <span id="header-city-coordinates">City: <?= $cityX ?>, <?= $cityY ?></span>
                    </div>
                </div>
                <div class="world-map-container">
                    <div class="map-position-display">
                        <i class="fas fa-map-marker-alt"></i>
                        <span id="dynamic-coordinates" class="badge">Dynamic Position: <?= $playerX ?>, <?= $playerY ?></span>
                    </div>

                    <div class="centered-map-wrapper"> <button id="map-north" class="map-nav-button map-nav-north"><i class="fas fa-chevron-up"></i></button>
                        <button id="map-west" class="map-nav-button map-nav-west"><i class="fas fa-chevron-left"></i></button>
                        <button id="map-east" class="map-nav-button map-nav-east"><i class="fas fa-chevron-right"></i></button>
                        <button id="map-south" class="map-nav-button map-nav-south"><i class="fas fa-chevron-down"></i></button>
                        <div class="map-grid-container">
                            <div id="map-grid" class="map-grid" style="--map-size: <?= (2 * $viewRadiusX) + 1 ?>">
                                <!-- Map grid will be dynamically loaded here -->
                            </div>
                        </div>
                    </div>

                    <div class="map-legend mt-3">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="fas fa-mountain"></i> Terrain Types</h5>
                                <div class="d-flex flex-wrap">
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-plains d-inline-block"></div>
                                        <span>Plains</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-forest d-inline-block"></div>
                                        <span>Forest</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-hills d-inline-block"></div>
                                        <span>Hills</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-mountains d-inline-block"></div>
                                        <span>Mountains</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-water d-inline-block"></div>
                                        <span>Water</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-desert d-inline-block"></div>
                                        <span>Desert</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="fas fa-city"></i> Cities & Resources</h5>
                                <div class="d-flex flex-wrap">
                                    <div class="legend-item">
                                        <div class="map-legend-tile player-legend-city d-inline-block"><span>P</span></div>
                                        <span>Your City</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile city-legend-tile d-inline-block"><span>A</span></div>
                                        <span>AI City</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-desert d-inline-block">
                                            <div class="tile-legend-resource resource-legend-oil"></div>
                                        </div>
                                        <span>Oil</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-forest d-inline-block">
                                            <div class="tile-legend-resource resource-legend-wood"></div>
                                        </div>
                                        <span>Wood</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-hills d-inline-block">
                                            <div class="tile-legend-resource resource-legend-stone"></div>
                                        </div>
                                        <span>Stone</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-plains d-inline-block">
                                            <div class="tile-legend-resource resource-legend-food"></div>
                                        </div>
                                        <span>Food</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="map-legend-tile terrain-legend-mountains d-inline-block">
                                            <div class="tile-legend-resource resource-legend-iron"></div>
                                        </div>
                                        <span>Iron</span>
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
<script>
    // Trigger initial map load using ajax-map-loader.js
    document.addEventListener('DOMContentLoaded', () => {
        const positionDisplay = document.querySelector('.map-position-display .badge');
        const playerX = <?= $playerX ?>; // Default player X coordinate
        const playerY = <?= $playerY ?>; // Default player Y coordinate

        // Set initial position in the badge
        if (positionDisplay) {
            positionDisplay.textContent = `Position: ${playerX}, ${playerY}`;
        }

        // Trigger map navigation to load the initial map
        handleMapNavigation({
            preventDefault: () => {}
        }, 0, 0);
    });
</script>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>