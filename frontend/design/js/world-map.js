/**
 * World Map Game UI Enhancement Script
 * Adds interactive elements and effects to the world map
 */

// Global zoom level variable
let currentZoomLevel = 1;
const MIN_ZOOM = 0.5;
const MAX_ZOOM = 2.0;
const ZOOM_STEP = 0.25;

// Container dimensions (these stay constant)
const CONTAINER_WIDTH = 17 * 45 + 16 * 3; // 17 tiles * 45px + 16 gaps * 3px = 813px
const CONTAINER_HEIGHT = 11 * 45 + 10 * 3; // 11 tiles * 45px + 10 gaps * 3px = 525px

document.addEventListener('DOMContentLoaded', () => {
    console.log('World map initializing...');

    // Add tile interactions and animations FIRST
    initializeTileInteractions();

    // THEN initialize tooltip system (must be after click events)
    // We let the enhanced-tooltips.js handle this on its own
    // Don't call initializeTooltips() here as it conflicts

    // Setup map controls and view toggles
    initializeMapControls();

    // Water animation effect
    animateWaterTiles();

    console.log('World map initialized');
});

/**
 * Apply zoom to the map by requesting different number of tiles
 * @param {number} zoomLevel - The zoom level to apply
 */
function applyZoom(zoomLevel) {
    // Clamp zoom level
    const clampedZoomLevel = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, zoomLevel));
    currentZoomLevel = clampedZoomLevel;
    
    // Calculate how many tiles we need based on zoom level
    // Zoom 0.5 = more tiles (smaller), Zoom 2.0 = fewer tiles (bigger)
    const baseRadiusX = 8; // Default from PHP: viewRadiusX = 8 -> 17 tiles width
    const baseRadiusY = 5; // Default from PHP: viewRadiusY = 5 -> 11 tiles height
      // Inverse relationship: higher zoom = smaller radius = fewer tiles
    const newRadiusX = Math.round(baseRadiusX / clampedZoomLevel);
    const newRadiusY = Math.round(baseRadiusY / clampedZoomLevel);
    
    // Dynamic minimum radius based on zoom level for optimal container usage
    let clampedRadiusX;
    let clampedRadiusY;
    
    if (clampedZoomLevel >= 2.0) {
        // At max zoom, test different small grid configurations to find optimal container usage
        const gridOptions = [
            { radiusX: 1, radiusY: 1 }, // 3x3 grid
            { radiusX: 2, radiusY: 1 }, // 5x3 grid  
            { radiusX: 1, radiusY: 2 }, // 3x5 grid
            { radiusX: 3, radiusY: 1 }, // 7x3 grid
            { radiusX: 1, radiusY: 3 }  // 3x7 grid
        ];
        
        let bestOption = gridOptions[0];
        let bestUsage = 0;
        
        for (const option of gridOptions) {
            const testGridWidth = (option.radiusX * 2) + 1;
            const testGridHeight = (option.radiusY * 2) + 1;
            const testAvailableWidth = CONTAINER_WIDTH - ((testGridWidth - 1) * 3);
            const testAvailableHeight = CONTAINER_HEIGHT - ((testGridHeight - 1) * 3);
            const testTileSize = Math.min(
                Math.floor(testAvailableWidth / testGridWidth), 
                Math.floor(testAvailableHeight / testGridHeight)
            );
            const testActualWidth = testGridWidth * testTileSize + (testGridWidth - 1) * 3;
            const testActualHeight = testGridHeight * testTileSize + (testGridHeight - 1) * 3;
            const testUsage = Math.min(
                testActualWidth / CONTAINER_WIDTH,
                testActualHeight / CONTAINER_HEIGHT
            );
            
            if (testUsage > bestUsage) {
                bestUsage = testUsage;
                bestOption = option;
            }
        }
        
        clampedRadiusX = bestOption.radiusX;
        clampedRadiusY = bestOption.radiusY;
        
    } else {
        // For other zoom levels, use the previous logic with minimum constraints
        let minRadiusX;
        let minRadiusY;
        if (clampedZoomLevel >= 1.75) {
            minRadiusX = 1; // 3x3 grid minimum
            minRadiusY = 1; // 3x3 grid minimum
        } else if (clampedZoomLevel >= 1.5) {
            minRadiusX = 2; // 5x5 grid minimum  
            minRadiusY = 1; // 3x3 height minimum
        } else {
            minRadiusX = 3; // 7x7 grid minimum
            minRadiusY = 2; // 5x5 height minimum
        }
        
        clampedRadiusX = Math.max(minRadiusX, newRadiusX);
        clampedRadiusY = Math.max(minRadiusY, newRadiusY);    }
    
    // Calculate grid dimensions
    const gridWidth = (clampedRadiusX * 2) + 1;
    const gridHeight = (clampedRadiusY * 2) + 1;
    
    // Calculate the optimal tile size that maximizes container usage
    const availableWidth = CONTAINER_WIDTH - ((gridWidth - 1) * 3); // Subtract gaps
    const availableHeight = CONTAINER_HEIGHT - ((gridHeight - 1) * 3); // Subtract gaps
    
    const maxTileWidth = Math.floor(availableWidth / gridWidth);
    const maxTileHeight = Math.floor(availableHeight / gridHeight);
    
    // Use the maximum possible tile size that fits both dimensions
    const newTileSize = Math.min(maxTileWidth, maxTileHeight);
    
    // Calculate actual container usage for logging
    const actualWidth = gridWidth * newTileSize + (gridWidth - 1) * 3;
    const actualHeight = gridHeight * newTileSize + (gridHeight - 1) * 3;
    const widthUsage = (actualWidth / CONTAINER_WIDTH * 100).toFixed(1);
    const heightUsage = (actualHeight / CONTAINER_HEIGHT * 100).toFixed(1);
    
    console.log(`Zoom ${clampedZoomLevel}x: ${gridWidth}x${gridHeight} grid, ${newTileSize}px tiles`);
    console.log(`Container usage: ${actualWidth}x${actualHeight} (${widthUsage}% x ${heightUsage}% of ${CONTAINER_WIDTH}x${CONTAINER_HEIGHT})`);
    
    // Request new map data with the calculated radius
    requestNewMapData(clampedRadiusX, clampedRadiusY, newTileSize, gridWidth, gridHeight);
}

/**
 * Request new map data with different radius and update display
 */
function requestNewMapData(radiusX, radiusY, tileSize, gridWidth, gridHeight) {
    // Get current position
    const positionDisplay = document.querySelector('.map-position-display .badge');
    const posText = positionDisplay.textContent.trim();
    const posMatch = posText.match(/Position:\s*(\d+),\s*(\d+)/i);
    
    if (!posMatch) {
        console.error('Could not parse position from:', posText);
        return;    }
    
    const currentX = Number.parseInt(posMatch[1]);
    const currentY = Number.parseInt(posMatch[2]);
    
    // Show loading overlay
    const loadingOverlay = document.querySelector('.map-loading-overlay');
    if (loadingOverlay) loadingOverlay.classList.add('active');
    
    // Request new map data with custom radius
    fetch(`backend/scripts/get_map_data.php?x=${currentX}&y=${currentY}&radiusX=${radiusX}&radiusY=${radiusY}`)
        .then(response => response.json())
        .then(data => {
            updateMapDisplayWithZoom(data, currentX, currentY, tileSize, gridWidth, gridHeight);
            // Hide loading overlay
            if (loadingOverlay) loadingOverlay.classList.remove('active');
        })
        .catch(error => {
            console.error('Error fetching zoomed map data:', error);
            // Hide loading overlay
            if (loadingOverlay) loadingOverlay.classList.remove('active');
        });
}

/**
 * Update map display with zoom parameters
 */
function updateMapDisplayWithZoom(mapData, newX, newY, tileSize, gridWidth, gridHeight) {
    const mapGrid = document.querySelector('.map-grid');
    if (!mapGrid) return;
    
    // Update grid layout
    mapGrid.style.setProperty('--map-size', gridWidth.toString());
    mapGrid.style.gridTemplateColumns = `repeat(${gridWidth}, ${tileSize}px)`;
    mapGrid.style.gridTemplateRows = `repeat(${gridHeight}, ${tileSize}px)`;
    mapGrid.style.gap = '3px';
    
    // Clear and render tiles
    if (typeof renderMapTiles === 'function') {
        renderMapTiles(mapGrid, mapData.tiles);    }
    
    // Apply tile sizes
    const mapTiles = mapGrid.querySelectorAll('.map-tile');
    for (const tile of mapTiles) {
        tile.style.width = `${tileSize}px`;
        tile.style.height = `${tileSize}px`;
        tile.style.fontSize = `${Math.round(16 * currentZoomLevel)}px`;
    }
    
    // Update resource icons
    const resourceElements = mapGrid.querySelectorAll('.tile-resource');
    for (const resource of resourceElements) {
        const resourceSize = Math.round(18 * currentZoomLevel);
        resource.style.width = `${resourceSize}px`;
        resource.style.height = `${resourceSize}px`;
    }
    
    // Update position display
    const positionDisplay = document.querySelector('.map-position-display .badge');
    if (positionDisplay) {
        positionDisplay.textContent = `Position: ${newX}, ${newY}`;
    }
    
    // Reinitialize interactions
    setTimeout(() => {
        if (typeof initializeTileInteractions === 'function') {
            initializeTileInteractions();
        }
        if (typeof initEnhancedTooltips === 'function') {
            initEnhancedTooltips();
        }
    }, 100);
    
    // Update zoom button states
    updateZoomButtons();
    
    console.log(`Map updated: ${gridWidth}x${gridHeight} grid, ${tileSize}px tiles, zoom ${currentZoomLevel}x`);
}

/**
 * Update zoom button states based on current zoom level
 */
function updateZoomButtons() {
    const zoomInBtn = document.getElementById('zoom-in');
    const zoomOutBtn = document.getElementById('zoom-out');
    const zoomLevelText = document.getElementById('zoom-level-text');
    
    if (zoomInBtn && zoomOutBtn) {
        // Disable zoom in if at max zoom
        if (currentZoomLevel >= MAX_ZOOM) {
            zoomInBtn.disabled = true;
            zoomInBtn.style.opacity = '0.5';
        } else {
            zoomInBtn.disabled = false;
            zoomInBtn.style.opacity = '1';
        }
        
        // Disable zoom out if at min zoom
        if (currentZoomLevel <= MIN_ZOOM) {
            zoomOutBtn.disabled = true;
            zoomOutBtn.style.opacity = '0.5';
        } else {
            zoomOutBtn.disabled = false;
            zoomOutBtn.style.opacity = '1';
        }
    }
    
    // Update zoom level display
    if (zoomLevelText) {
        zoomLevelText.textContent = `${currentZoomLevel.toFixed(2)}x`;
    }
}

/**
 * Zoom in the map
 */
function zoomIn() {
    applyZoom(currentZoomLevel + ZOOM_STEP);
}

/**
 * Zoom out the map
 */
function zoomOut() {
    applyZoom(currentZoomLevel - ZOOM_STEP);
}

/**
 * Add interactive behaviors to map tiles
 */
function initializeTileInteractions() {
    // Make this function globally accessible
    window.initializeTileInteractions = initializeTileInteractions;

    console.log('Initializing tile interactions for map clicks');

    // Get all map tiles
    const mapTiles = document.querySelectorAll('.map-tile');
    console.log(`Found ${mapTiles.length} map tiles for click interactions`);

    // Throttle hover effects to prevent excessive animations
    const hoverThrottle = new Map();

    // DO NOT clone the tiles here as it would remove tooltip event listeners
    // Just add click handlers directly

    for (const tile of mapTiles) {
        // Skip if the tile already has click handlers
        if (tile.hasAttribute('data-click-initialized')) {
            continue;
        }

        // Mark this tile as click-initialized
        tile.setAttribute('data-click-initialized', 'true');

        // Add throttled hover effect
        tile.addEventListener('mouseenter', function () {
            const tileKey = `${this.getAttribute('data-x')},${this.getAttribute('data-y')}`;
            
            // Throttle hover effects to prevent excessive triggering
            if (hoverThrottle.get(tileKey)) return;
            hoverThrottle.set(tileKey, true);
            
            setTimeout(() => {
                hoverThrottle.delete(tileKey);
            }, 100); // 100ms throttle
            
            try {
                this.style.zIndex = '10';

                // Add small shake animation to resource tiles
                const resourceElement = this.querySelector('.tile-resource');
                if (resourceElement) {
                    resourceElement.classList.add('resource-shake');
                }
            } catch (error) {
                console.warn('Error in hover effect:', error);
            }
        });

        tile.addEventListener('mouseleave', function () {
            try {
                this.style.zIndex = '1';

                // Remove shake animation
                const resourceElement = this.querySelector('.tile-resource');
                if (resourceElement) {
                    resourceElement.classList.remove('resource-shake');
                }
            } catch (error) {
                console.warn('Error in hover leave effect:', error);
            }
        });// Add click interaction
        tile.addEventListener('click', function () {
            // Get tile coordinates
            const x = this.getAttribute('data-x');
            const y = this.getAttribute('data-y');

            console.log(`Tile clicked at (${x}, ${y})`);

            // Flash effect on click
            this.classList.add('tile-flash');
            setTimeout(() => {
                this.classList.remove('tile-flash');
            }, 300);

            // Handle different tile types
            if (this.classList.contains('city-tile') && this.textContent.includes('A')) {
                showMapActionDialog('Attack AI City',
                    `Do you want to attack the AI city at coordinates (${x}, ${y})?`,
                    () => {
                        window.location.href = `index.php?page=battle&target_x=${x}&target_y=${y}`;
                    });
            }
            else if (this.querySelector('.tile-resource')) {
                // Determine resource type
                let resourceType = '';
                const resourceElement = this.querySelector('.tile-resource');
                if (resourceElement.classList.contains('resource-diamond')) resourceType = 'diamond';
                else if (resourceElement.classList.contains('resource-wood')) resourceType = 'wood';
                else if (resourceElement.classList.contains('resource-stone')) resourceType = 'stone';
                else if (resourceElement.classList.contains('resource-food')) resourceType = 'food';
                else if (resourceElement.classList.contains('resource-iron')) resourceType = 'iron';
                else if (resourceElement.classList.contains('resource-oil')) resourceType = 'oil';

                showMapActionDialog('Gather Resources',
                    `Do you want to send troops to gather ${resourceType} from location (${x}, ${y})?`,
                    () => {
                        window.location.href = `index.php?page=gather&target_x=${x}&target_y=${y}`;
                    });
            }            else if (!this.classList.contains('player-city') && !this.classList.contains('city-tile')) {
                // Use a more appropriate GameAlerts.moveCity function for city movement
                GameAlerts.moveCity(
                    'Relocate Your City',
                    `Are you sure you want to teleport your city to coordinates (${x}, ${y})? This will use 1 teleport crystal.`,
                    () => {
                        console.log(`Moving to coordinates (${x}, ${y})`);
                        movePlayerCityToCoordinates(x, y);
                    }
                );
            }
        });
    }

    // Add CSS for flash effect
    const style = document.createElement('style');
    style.textContent = `
        @keyframes tile-flash {
            0% { filter: brightness(1); }
            50% { filter: brightness(2); }
            100% { filter: brightness(1); }
        }
        .tile-flash {
            animation: tile-flash 0.3s ease;
        }
        @keyframes resource-shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(2px); }
            50% { transform: translateX(-2px); }
            75% { transform: translateX(2px); }
            100% { transform: translateX(0); }
        }
        .resource-shake {
            animation: resource-shake 0.5s ease;
        }
    `;

    // Only add the style once
    if (!document.getElementById('map-tile-animations')) {
        style.id = 'map-tile-animations';
        document.head.appendChild(style);
    }

    // Log successful initialization
    console.log('Tile interactions initialized');
}

/**
 * Move the player's city to the specified coordinates
 */
function movePlayerCityToCoordinates(target_X, target_Y) {
    // Show loading indicator
    console.log(`Attempting to move city to coordinates (${target_X}, ${target_Y})`);
    
    $.ajax({
        url: '/mmorts/backend/scripts/move-city.php',
        type: 'POST',
        data: {
            target_x: target_X,
            target_y: target_Y
        },
        dataType: 'json',        success: (response) => {
            if (response.status === 'success') {
                GameAlerts.success(
                    'City Relocated', 
                    `City moved successfully to (${target_X}, ${target_Y})! Remaining teleports: ${response.remaining_teleports}`
                );
                // Reload the page to refresh the map after a short delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                GameAlerts.error('Move Failed', `${response.message}`);
            }
        },
        error: (xhr, status, error) => {
            console.error("AJAX Error:", xhr.responseText);
            try {
                const response = JSON.parse(xhr.responseText);
                GameAlerts.error('Server Error', `${response.message}`);
            } catch (e) {
                GameAlerts.error('Connection Error', `Failed to move city: ${error}`);
            }
        }
    });
}

/**
 * Shows an action dialog for map interactions
 */
function showMapActionDialog(title, message, confirmCallback) {
    // Use GameAlerts confirm instead of custom dialog
    GameAlerts.confirm(
        title,
        message,
        confirmCallback,
        'Proceed',
        'Cancel'
    );
}

/**
 * Initialize map controls
 */
function initializeMapControls() {
    // Create map controls container
    const controlsContainer = document.createElement('div');
    controlsContainer.className = 'map-controls';    controlsContainer.innerHTML = `
        <div class="control-group">
            <button id="zoom-in"><i class="fas fa-search-plus"></i> Zoom In</button>
            <button id="zoom-out"><i class="fas fa-search-minus"></i> Zoom Out</button>
            <div class="zoom-level-display">
                <i class="fas fa-search"></i> 
                <span id="zoom-level-text">1.0x</span>
            </div>
        </div>
        <div class="control-group view-toggle">
            <button id="view-terrain" class="active"><i class="fas fa-mountain"></i> Terrain</button>
            <button id="view-resources"><i class="fas fa-coins"></i> Resources</button>
            <button id="view-political"><i class="fas fa-flag"></i> Political</button>
        </div>
        <div class="control-group">
            <button id="center-map"><i class="fas fa-crosshairs"></i> Center</button>
        </div>
    `;    // Insert controls before the map grid
    const mapGrid = document.querySelector('.map-grid');
    mapGrid.parentNode.insertBefore(controlsContainer, mapGrid);
    
    // Add event listeners for controls
    document.getElementById('zoom-in').addEventListener('click', () => {
        zoomIn();
    });

    document.getElementById('zoom-out').addEventListener('click', () => {
        zoomOut();
    });

    // Initialize zoom button states and level display
    updateZoomButtons();    document.getElementById('center-map').addEventListener('click', () => {
        // Get the player's actual city coordinates from the header
        const cityCoordinates = document.getElementById('header-city-coordinates');
        if (cityCoordinates) {
            const coordText = cityCoordinates.textContent;
            const match = coordText.match(/City:\s*(\d+),\s*(\d+)/);
            
            if (match) {                const cityX = Number.parseInt(match[1]);
                const cityY = Number.parseInt(match[2]);
                
                console.log(`Centering map on player city at (${cityX}, ${cityY})`);
                
                // Navigate to the player's city coordinates using the existing navigation system
                handleMapNavigation({
                    preventDefault: () => {}
                }, 0, 0, cityX, cityY);
                
                // Add highlight effect after a short delay to allow map to load
                setTimeout(() => {
                    const playerCity = document.querySelector('.player-city');
                    if (playerCity) {
                        playerCity.classList.add('highlight-pulse');
                        setTimeout(() => {
                            playerCity.classList.remove('highlight-pulse');
                        }, 2000);
                    }
                }, 500);
            }
        } else {
            // Fallback: try to scroll to visible player city
            const playerCity = document.querySelector('.player-city');
            if (playerCity) {
                playerCity.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'center'
                });

                // Add highlight effect
                playerCity.classList.add('highlight-pulse');
                setTimeout(() => {
                    playerCity.classList.remove('highlight-pulse');
                }, 2000);
            } else {
                console.warn('Player city not found on current map view and no coordinates available');
            }
        }
    });    // View toggle buttons with debouncing and error handling
    let viewToggleTimeout = null;
    const viewButtons = document.querySelectorAll('.view-toggle button');
    
    for (const button of viewButtons) {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            
            // Prevent rapid clicking
            if (this.disabled) return;
            
            // Disable button temporarily to prevent rapid clicks
            this.disabled = true;
            
            try {
                // Clear any existing timeout
                if (viewToggleTimeout) {
                    clearTimeout(viewToggleTimeout);
                }
                  // Debounce the view toggle operation
                const self = this;
                viewToggleTimeout = setTimeout(() => {
                    try {
                        // Remove active class from all buttons
                        for (const btn of viewButtons) btn.classList.remove('active');
                        // Add active class to clicked button
                        self.classList.add('active');
                          // Handle view changes - apply to the map container, not individual tiles
                        const viewType = self.id;
                        const mapGrid = document.querySelector('.map-grid');
                        
                        if (!mapGrid) {
                            console.error('Map grid not found');
                            return;
                        }
                        console.log('Switching to view mode:', viewType);
                        console.log('Current classes before change:', mapGrid.className);

                        // Apply view mode changes with smooth transitions
                        mapGrid.style.transition = 'opacity 0.3s ease, filter 0.3s ease';
                        
                        if (viewType === 'view-terrain') {
                            // Remove all view classes to show default terrain view
                            mapGrid.classList.remove('resource-view', 'political-view');
                            console.log('Terrain view activated - showing default terrain');
                        } else if (viewType === 'view-resources') {
                            // Show resource view
                            mapGrid.classList.remove('political-view');
                            mapGrid.classList.add('resource-view');
                            console.log('Resource view activated - highlighting resources');
                            
                            // Force a reflow to ensure animations work properly
                            mapGrid.offsetHeight;
                        } else if (viewType === 'view-political') {
                            // Show political view
                            mapGrid.classList.remove('resource-view');
                            mapGrid.classList.add('political-view');
                            console.log('Political view activated - highlighting cities and territories');
                            
                            // Force a reflow to ensure animations work properly
                            mapGrid.offsetHeight;
                        }
                        
                        console.log('Current classes after change:', mapGrid.className);
                        
                        // Debug: Check how many tiles have resources
                        const tilesWithResources = mapGrid.querySelectorAll('.map-tile[data-has-resource="true"]');
                        console.log(`Found ${tilesWithResources.length} tiles with resources`);
                        
                        // Debug: Check resource icons
                        const resourceIcons = mapGrid.querySelectorAll('.tile-resource');
                        console.log(`Found ${resourceIcons.length} resource icons`);
                        
                    } catch (error) {
                        console.error('Error in view toggle operation:', error);
                    } finally {                        // Re-enable button after a short delay
                        setTimeout(() => {
                            self.disabled = false;
                        }, 100);
                    }
                }, 100); // 100ms debounce delay
                
            } catch (error) {
                console.error('Error setting up view toggle:', error);
                this.disabled = false;
            }
        });
    }// Add CSS for highlight effect
    const style = document.createElement('style');
    style.textContent = `
        @keyframes highlight-pulse {
            0% { box-shadow: 0 0 0 0 rgba(76, 201, 240, 0.7); }
            70% { box-shadow: 0 0 0 20px rgba(76, 201, 240, 0); }
            100% { box-shadow: 0 0 0 0 rgba(76, 201, 240, 0); }
        }
        .highlight-pulse {
            animation: highlight-pulse 1s 2;
            z-index: 10;
        }
        .resource-view .tile-resource {
            width: 25px;
            height: 25px;
            transform: scale(1.5);
        }
        .political-view .city-tile, .political-view .player-city {
            transform: scale(1.1);
            z-index: 5;
        }
        .zoom-level-display {
            display: inline-flex;
            align-items: center;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 8px 12px;
            border-radius: 4px;
            margin-left: 10px;
            font-size: 14px;
            font-weight: bold;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .zoom-level-display i {
            margin-right: 6px;
            color: #4CAF50;
        }
        #zoom-level-text {
            color: #4CAF50;
            font-family: 'Courier New', monospace;
            min-width: 45px;
            text-align: center;
        }
    `;
    document.head.appendChild(style);
}

/**
 * Add smooth transitions for map navigation (LEGACY - now using smooth-map-navigation.js)
 */
function _legacyInitializeMapNavigation() {
    // Add font awesome icons to navigation buttons
    document.getElementById('map-north').innerHTML = '<i class="fas fa-chevron-up"></i> North';
    document.getElementById('map-south').innerHTML = '<i class="fas fa-chevron-down"></i> South';
    document.getElementById('map-west').innerHTML = '<i class="fas fa-chevron-left"></i> West';
    document.getElementById('map-east').innerHTML = '<i class="fas fa-chevron-right"></i> East';

    // Add coordinates icon
    const coordsSpan = document.querySelector('.map-position .badge');
    if (coordsSpan) {
        const coordsIcon = document.createElement('i');
        coordsIcon.className = 'fas fa-map-marker-alt';
        coordsIcon.style.marginRight = '5px';
        coordsSpan.prepend(coordsIcon);
    }

    // Get current coordinates from URL or page element
    const coordsText = document.querySelector('.map-position .badge').textContent.trim();
    const coordParts = coordsText.split(',');
    let currentX = 25; // Default
    let currentY = 25; // Default

    // Try to parse coordinates from the badge text
    if (coordParts.length === 2) {
        currentX = Number.parseInt(coordParts[0].trim());
        currentY = Number.parseInt(coordParts[1].trim());

        // If parsing fails, use defaults
        if (Number.isNaN(currentX) || Number.isNaN(currentY)) {
            currentX = 25;
            currentY = 25;
            console.error(`Failed to parse coordinates from badge text: ${coordsText}`);
        }
    }
    console.log('Current map coordinates:', currentX, currentY);

    // Set up navigation button events with proper event prevention
    document.getElementById('map-north').addEventListener('click', (e) => {
        e.preventDefault();
        console.log('Navigating North from', currentX, currentY, 'to', currentX, currentY - 1);
        window.location.href = `debug_map_navigation.php?x=${currentX}&y=${currentY - 1}`;
    });

    document.getElementById('map-south').addEventListener('click', (e) => {
        e.preventDefault();
        console.log('Navigating South from', currentX, currentY, 'to', currentX, currentY + 1);
        window.location.href = `debug_map_navigation.php?x=${currentX}&y=${currentY + 1}`;
    });

    document.getElementById('map-west').addEventListener('click', (e) => {
        e.preventDefault();
        console.log('Navigating West from', currentX, currentY, 'to', currentX - 1, currentY);
        window.location.href = `debug_map_navigation.php?x=${currentX - 1}&y=${currentY}`;
    });

    document.getElementById('map-east').addEventListener('click', (e) => {
        e.preventDefault();
        console.log('Navigating East from', currentX, currentY, 'to', currentX + 1, currentY);
        window.location.href = `debug_map_navigation.php?x=${currentX + 1}&y=${currentY}`;
    });
}

/**
 * Add subtle animation to water tiles
 */
function animateWaterTiles() {
    const waterTiles = document.querySelectorAll('.terrain-water');

    let index = 0;
    for (const tile of waterTiles) {
        // Add slightly different animation delay to each water tile
        tile.style.animationDelay = `${(index % 5) * 0.2}s`;
        index++;
    }
}

/**
 * Add resource amount visualization to resource tiles
 */
function addResourceVisualization() {
    const resourceTiles = document.querySelectorAll('.map-tile .tile-resource');

    for (const resourceElement of resourceTiles) {
        const tile = resourceElement.closest('.map-tile');
        const tooltipText = tile.getAttribute('title') || '';

        // Extract resource amount from tooltip
        const resourceMatch = tooltipText.match(/(\w+):\s*(\d+)/);

        if (resourceMatch && resourceMatch.length >= 3) {
            const resourceType = resourceMatch[1].toLowerCase();
            const amount = resourceMatch[2];

            // Create resource amount indicator
            const resourceDetails = document.createElement('div');
            resourceDetails.className = 'tile-resource-details';
            resourceDetails.textContent = amount;
            tile.appendChild(resourceDetails);
        }
    }
}

// Call this function after the page loads
document.addEventListener('DOMContentLoaded', () => {
    // Add a slight delay to ensure all elements are properly loaded
    setTimeout(addResourceVisualization, 100);
});
