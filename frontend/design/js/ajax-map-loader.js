/**
 * AJAX Map Loader - Loads map data without page refresh
 * This script handles loading new map data via AJAX
 */

document.addEventListener('DOMContentLoaded', () => {
    initAjaxMapNavigation();
});

/**
 * Initialize AJAX map navigation
 */
function initAjaxMapNavigation() {
    // Get navigation buttons
    const northBtn = document.getElementById('map-north');
    const southBtn = document.getElementById('map-south');
    const eastBtn = document.getElementById('map-east');
    const westBtn = document.getElementById('map-west');
    
    // Add event listeners for all buttons
    if (northBtn) northBtn.addEventListener('click', (e) => handleMapNavigation(e, 0, -1));
    if (southBtn) southBtn.addEventListener('click', (e) => handleMapNavigation(e, 0, 1));
    if (eastBtn) eastBtn.addEventListener('click', (e) => handleMapNavigation(e, 1, 0));
    if (westBtn) westBtn.addEventListener('click', (e) => handleMapNavigation(e, -1, 0));
}

/**
 * Handle map navigation with AJAX
 * @param {Event} e - The click event
 * @param {Number} dx - X direction (-1, 0, 1)
 * @param {Number} dy - Y direction (-1, 0, 1)
 * @param {Number} absoluteX - Optional: absolute X coordinate to navigate to
 * @param {Number} absoluteY - Optional: absolute Y coordinate to navigate to
 */
function handleMapNavigation(e, dx, dy, absoluteX = null, absoluteY = null) {
    e.preventDefault();
    
    let currentX;
    let currentY;
    let newX;
    let newY;
    
    if (absoluteX !== null && absoluteY !== null) {
        // Use absolute coordinates (for center button)
        newX = absoluteX;
        newY = absoluteY;
        console.log(`Navigating to absolute coordinates (${newX}, ${newY})`);
    } else {
        // Use relative navigation (for directional buttons)
        // Get current position from the badge
        const positionDisplay = document.querySelector('.map-position-display .badge');
        const posText = positionDisplay.textContent.trim();
        const posMatch = posText.match(/Position:\s*(\d+),\s*(\d+)/i);
        
        if (!posMatch) {
            console.error('Could not parse position from:', posText);
            return;
        }
        
        currentX = Number.parseInt(posMatch[1]);
        currentY = Number.parseInt(posMatch[2]);
        newX = currentX + dx;
        newY = currentY + dy;
        
        console.log(`Navigating from (${currentX}, ${currentY}) to (${newX}, ${newY})`);    }
    
    // Determine direction name for animation (only for relative movement)
    let direction = '';
    if (absoluteX === null && absoluteY === null) {
        if (dx === -1) direction = 'west';
        else if (dx === 1) direction = 'east';
        else if (dy === -1) direction = 'north';
        else if (dy === 1) direction = 'south';
    }
    
    // Show loading overlay
    const loadingOverlay = document.querySelector('.map-loading-overlay');
    if (loadingOverlay) loadingOverlay.classList.add('active');
    
    // Animate the map movement (only for directional navigation)
    const mapGrid = document.querySelector('.map-grid-container');
    if (mapGrid && direction) {
        // Remove any existing animation classes
        mapGrid.classList.remove('map-slide-north', 'map-slide-south', 'map-slide-east', 'map-slide-west');
        
        // Add the new animation class
        mapGrid.classList.add(`map-slide-${direction}`);
    }// Check if zoom is active and get zoom parameters
    let zoomParams = '';
    if (typeof currentZoomLevel !== 'undefined' && currentZoomLevel !== 1) {
        // Calculate zoom parameters like in world-map.js
        const baseRadiusX = 8;
        const baseRadiusY = 5;
        const newRadiusX = Math.round(baseRadiusX / currentZoomLevel);
        const newRadiusY = Math.round(baseRadiusY / currentZoomLevel);            // Use same minimum radius logic as world-map.js
            const minRadiusX = currentZoomLevel >= 2.0 ? 1 : (currentZoomLevel >= 1.5 ? 2 : 3);
            const minRadiusY = currentZoomLevel >= 2.0 ? 1 : (currentZoomLevel >= 1.5 ? 1 : 2);
        
        const clampedRadiusX = Math.max(minRadiusX, newRadiusX);
        const clampedRadiusY = Math.max(minRadiusY, newRadiusY);
        zoomParams = `&radiusX=${clampedRadiusX}&radiusY=${clampedRadiusY}`;
    }
    
    // Make AJAX request to get new map data with zoom parameters
    fetch(`backend/scripts/get_map_data.php?x=${newX}&y=${newY}${zoomParams}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Update the map after a short delay to let animation finish
            setTimeout(() => {
                updateMapDisplay(data, newX, newY);
                
                // Hide loading overlay
                if (loadingOverlay) loadingOverlay.classList.remove('active');
                
                // Update URL without page refresh
                const newUrl = `index.php?page=world_map&x=${newX}&y=${newY}`;
                window.history.pushState({x: newX, y: newY}, '', newUrl);
            }, 300);
        })
        .catch(error => {
            console.error('Error fetching map data:', error);
            
            // On error, fallback to traditional navigation
            window.location.href = `index.php?page=world_map&x=${newX}&y=${newY}`;
        });
}
/**
 * Update the map display with new data
 * @param {Object} mapData - The new map data
 * @param {Number} newX - New X coordinate
 * @param {Number} newY - New Y coordinate
 */
function updateMapDisplay(mapData, newX, newY) {
    console.log('Updating map display with new data');
    
    // Update position displays
    const positionDisplay = document.querySelector('.map-position-display .badge');
    if (positionDisplay) {
        positionDisplay.textContent = `Position: ${newX}, ${newY}`;
    }
    
    // Update header coordinates too
    // const headerCoords = document.getElementById('header-coordinates');
    // if (headerCoords) {
    //     headerCoords.textContent = `Position: ${newX}, ${newY}`;
    // }
      // Update map tiles
    const mapGrid = document.querySelector('.map-grid');
    if (mapGrid && mapData.tiles) {        // Check if zoom is active and need to preserve zoom
        if (typeof currentZoomLevel !== 'undefined' && currentZoomLevel !== 1) {
            // Re-apply zoom parameters instead of using default map size
            const baseRadiusX = 8;
            const baseRadiusY = 5;
            const newRadiusX = Math.round(baseRadiusX / currentZoomLevel);
            const newRadiusY = Math.round(baseRadiusY / currentZoomLevel);
              // Adjust minimum radius based on zoom level (same as world-map.js)
            const minRadiusX = currentZoomLevel >= 2.0 ? 1 : (currentZoomLevel >= 1.5 ? 2 : 3);
            const minRadiusY = currentZoomLevel >= 2.0 ? 1 : (currentZoomLevel >= 1.5 ? 1 : 2);
            
            const clampedRadiusX = Math.max(minRadiusX, newRadiusX);
            const clampedRadiusY = Math.max(minRadiusY, newRadiusY);
            
            const gridWidth = (clampedRadiusX * 2) + 1;
            const gridHeight = (clampedRadiusY * 2) + 1;
              // Calculate container dimensions (same as world-map.js)
            const CONTAINER_WIDTH = 17 * 45 + 16 * 3; // 813px
            const CONTAINER_HEIGHT = 11 * 45 + 10 * 3; // 525px
            
            const availableWidth = CONTAINER_WIDTH - ((gridWidth - 1) * 3);
            const availableHeight = CONTAINER_HEIGHT - ((gridHeight - 1) * 3);
            
            const tileWidth = Math.floor(availableWidth / gridWidth);
            const tileHeight = Math.floor(availableHeight / gridHeight);
            
            // Use maximum possible tile size that fits in the container (improved calculation)
            const maxPossibleWidth = Math.floor((CONTAINER_WIDTH - (gridWidth - 1) * 3) / gridWidth);
            const maxPossibleHeight = Math.floor((CONTAINER_HEIGHT - (gridHeight - 1) * 3) / gridHeight);
            const newTileSize = Math.min(maxPossibleWidth, maxPossibleHeight);
            
            // Apply zoom layout
            mapGrid.style.setProperty('--map-size', gridWidth.toString());
            mapGrid.style.gridTemplateColumns = `repeat(${gridWidth}, ${newTileSize}px)`;
            mapGrid.style.gridTemplateRows = `repeat(${gridHeight}, ${newTileSize}px)`;
            mapGrid.style.gap = '3px';
            
            // Render tiles
            renderMapTiles(mapGrid, mapData.tiles);
            
            // Apply tile sizes
            const mapTiles = mapGrid.querySelectorAll('.map-tile');
            for (const tile of mapTiles) {
                tile.style.width = `${newTileSize}px`;
                tile.style.height = `${newTileSize}px`;
                tile.style.fontSize = `${Math.round(16 * currentZoomLevel)}px`;
            }
            
            // Update resource icons
            const resourceElements = mapGrid.querySelectorAll('.tile-resource');
            for (const resource of resourceElements) {
                const resourceSize = Math.round(18 * currentZoomLevel);
                resource.style.width = `${resourceSize}px`;
                resource.style.height = `${resourceSize}px`;
            }
            
            console.log(`AJAX Navigation: Preserved zoom ${currentZoomLevel}x with ${gridWidth}x${gridHeight} grid`);
        } else {
            // Normal (non-zoomed) update
            if (mapData.mapSize?.width) {
                mapGrid.style.setProperty('--map-size', mapData.mapSize.width);
                console.log('Updated map size to:', mapData.mapSize.width);
            }
            renderMapTiles(mapGrid, mapData.tiles);
        }
    }
    
    // Reset animation classes
    const mapGridContainer = document.querySelector('.map-grid-container');
    if (mapGridContainer) {
        mapGridContainer.classList.remove('map-slide-north', 'map-slide-south', 'map-slide-east', 'map-slide-west');
    }
    
    // Order of initialization is important:
    // 1. First initialize tile interactions for click events
    // 2. Then initialize tooltips which work with the click events
    
    console.log('Reinitializing map interactions with delay...');
    
    // Give a small delay to ensure DOM is fully updated before reinitializing interactions
    setTimeout(() => {
        console.log('Now initializing tile interactions');
        
        // First initialize tile interactions (click handlers)
        if (typeof initializeTileInteractions === 'function') {
            initializeTileInteractions();
            console.log('Tile interactions initialized successfully');
        } else {
            console.error('initializeTileInteractions function not found');
        }
          // Add a small delay to ensure click handlers are fully set up
        setTimeout(() => {
            console.log('Now initializing tooltips');
            // Then initialize tooltips
            if (typeof initEnhancedTooltips === 'function') {
                initEnhancedTooltips();
                console.log('Enhanced tooltips initialized successfully');
            } else {
                console.error('initEnhancedTooltips function not found');
            }
            
            // Preserve zoom level after AJAX update
            if (typeof currentZoomLevel !== 'undefined' && typeof applyZoom === 'function' && currentZoomLevel !== 1) {
                console.log('Restoring zoom level:', currentZoomLevel);
                applyZoom(currentZoomLevel);
            }
        }, 50);
    }, 100);
}

/**
 * Render map tiles from data
 * @param {HTMLElement} mapGrid - The map grid element
 * @param {Array} tiles - The tiles data
 */
function renderMapTiles(mapGrid, tiles) {
    if (!mapGrid || !tiles || !Array.isArray(tiles)) {
        console.error('Invalid arguments for renderMapTiles:', { mapGrid, tilesLength: tiles ? tiles.length : 0 });
        return;
    }
    
    console.log('Rendering new map tiles:', tiles.length);
      // Clear existing map
    mapGrid.innerHTML = '';
    
    // Sort tiles by y, then x for proper rendering order
    tiles.sort((a, b) => {
        if (a.y === b.y) return a.x - b.x;
        return a.y - b.y;
    });
    
    // Render each tile
    for (const tile of tiles) {
        // Determine tile type and styling
        let tileClass = 'map-tile terrain-plains'; // Default
        let tileText = '';
        let tileTooltip = `${tile.terrain ? tile.terrain.charAt(0).toUpperCase() + tile.terrain.slice(1) : 'Grassland'}\nCoordinates: (${tile.x}, ${tile.y})`;
        let resourceHtml = '';
        
        // Set terrain class
        if (tile.terrain) {
            tileClass = `map-tile terrain-${tile.terrain}`;
        }
        
        // Handle player city position
        if (tile.isPlayerPosition) {
            tileClass += ' player-city';
            tileText = '<span>P</span>';
            tileTooltip = `Your City\nCoordinates: (${tile.x}, ${tile.y})`;
        }
        
        // Handle resources
        if (tile.resource) {
            const resourceClass = `resource-${tile.resource.type}`;
            resourceHtml = `<div class='tile-resource ${resourceClass}'></div>`;
            tileTooltip += `\n${tile.resource.type.charAt(0).toUpperCase() + tile.resource.type.slice(1)}: ${tile.resource.amount}`;
        }
        
        // Handle occupied tiles
        if (tile.occupied && !tile.isPlayerPosition) {
            tileClass = tileClass.replace(/terrain-\w+/g, ''); // remove the terrain class
            if (tile.occupierType === 'player') {
                tileClass += ' player-city';
                // resourceHtml = '';
                tileText = '<span>P</span>';
                const cityName = tile.city ? tile.city.name : 'Unknown City';
                const ownerName = tile.city ? tile.city.owner : 'Unknown Player';
                tileTooltip = `Player City\nOwner: ${ownerName}\nCity: ${cityName}\nCoordinates: (${tile.x}, ${tile.y})`;
            } else if (tile.occupierType === 'ai') {
                tileClass += ' city-tile';
                // resourceHtml = '';
                tileText = '<span>A</span>';
                const cityName = tile.city ? tile.city.name : 'Unknown City';
                const ownerName = tile.city ? tile.city.owner : 'Unknown AI';
                tileTooltip = `AI City\nController: ${ownerName}\nCity: ${cityName}\nCoordinates: (${tile.x}, ${tile.y})`;
            }
        }
          // Create tile element
        const tileElement = document.createElement('div');
        tileElement.className = tileClass;
        tileElement.dataset.x = tile.x;
        tileElement.dataset.y = tile.y;
        tileElement.dataset.content = tileTooltip;
        tileElement.title = tileTooltip;
        
        // Add data attribute for tiles with resources (for CSS targeting)
        if (tile.resource) {
            tileElement.dataset.hasResource = 'true';
        }
        
        tileElement.innerHTML = `${tileText}${resourceHtml}`;
        
        // Add to grid
        mapGrid.appendChild(tileElement);
    }    // Initialization is now handled in updateMapDisplay with a small delay
    // to ensure DOM is fully updated
}

// Handle browser back/forward navigation
window.addEventListener('popstate', (event) => {
    if (event.state && typeof event.state.x !== 'undefined' && typeof event.state.y !== 'undefined') {
        // Reload the map with the coordinates from history state
        window.location.href = `index.php?page=world_map&x=${event.state.x}&y=${event.state.y}`;
    }
});
