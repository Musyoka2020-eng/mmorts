/**
 * Dynamic Map Zoom Functionality
 * Adjusts tile size while maintaining fixed container dimensions
 */

document.addEventListener('DOMContentLoaded', () => {
    initializeZoomFunctionality();
});

// Constants for zoom settings
const ZOOM_SETTINGS = {
    MIN_TILE_SIZE: 25,
    MAX_TILE_SIZE: 60,
    ZOOM_STEP: 5,
    TILE_GAP: 3
};

/**
 * Initialize enhanced zoom functionality
 */
function initializeZoomFunctionality() {
    // Get zoom buttons
    const zoomInBtn = document.getElementById('zoom-in');
    const zoomOutBtn = document.getElementById('zoom-out');
    
    // Remove any existing event listeners by cloning and replacing
    if (zoomInBtn) {
        const newZoomIn = zoomInBtn.cloneNode(true);
        zoomInBtn.parentNode.replaceChild(newZoomIn, zoomInBtn);
        newZoomIn.addEventListener('click', handleZoomIn);
    } else {
        console.warn('Zoom In button not found');
    }
    
    if (zoomOutBtn) {
        const newZoomOut = zoomOutBtn.cloneNode(true);
        zoomOutBtn.parentNode.replaceChild(newZoomOut, zoomOutBtn);
        newZoomOut.addEventListener('click', handleZoomOut);
    } else {
        console.warn('Zoom Out button not found');
    }
    
    console.log('Dynamic zoom functionality initialized');
}

/**
 * Get current map position and tile size
 */
function getCurrentMapState() {
    const positionDisplay = document.querySelector('.map-position-display .badge');
    const posText = positionDisplay.textContent.trim();
    const posMatch = posText.match(/Position:\s*(\d+),\s*(\d+)/i);

    if (!posMatch) {
        console.error('Could not parse position');
        return null;
    }

    const mapTiles = document.querySelectorAll('.map-tile');
    if (!mapTiles.length) {
        console.error('No map tiles found');
        return null;
    }

    return {
        x: Number.parseInt(posMatch[1]),
        y: Number.parseInt(posMatch[2]),
        tileSize: Number.parseInt(getComputedStyle(mapTiles[0]).width)
    };
}

/**
 * Calculate new map parameters based on container size and tile size
 */
function calculateMapParameters(tileSize) {
    const container = document.querySelector('.map-grid-container');
    if (!container) return null;

    // Use container dimensions minus padding and gap for tiles
    const fullTileSize = tileSize + ZOOM_SETTINGS.TILE_GAP;
    const availableWidth = 800 - (2 * 15); // Container width minus padding
    const availableHeight = 600 - (2 * 15); // Container height minus padding

    // Calculate number of tiles that will fit
    const tilesX = Math.floor(availableWidth / fullTileSize);
    const tilesY = Math.floor(availableHeight / fullTileSize);

    // Ensure odd number of tiles for centered map
    const adjustedTilesX = tilesX % 2 === 0 ? tilesX - 1 : tilesX;
    const adjustedTilesY = tilesY % 2 === 0 ? tilesY - 1 : tilesY;

    return {
        tilesX: adjustedTilesX,
        tilesY: adjustedTilesY,
        radiusX: Math.floor(adjustedTilesX / 2),
        radiusY: Math.floor(adjustedTilesY / 2)
    };
}

   // Fixed grid size based on your world map logic
    // const baseGrid = {
    //     x: 17, // Base number of tiles horizontally
    //     y: 11  // Base number of tiles vertically
    // };
    
    // // Always maintain the same number of tiles but adjust their size
    // return {
    //     tilesX: baseGrid.x,
    //     tilesY: baseGrid.y,
    //     radiusX: Math.floor(baseGrid.x / 2),
    //     radiusY: Math.floor(baseGrid.y / 2)
    // };

/**
 * Handle zoom in - increase tile size
 */
function handleZoomIn() {
    const currentState = getCurrentMapState();
    if (!currentState) return;

    const newTileSize = Math.min(
        currentState.tileSize + ZOOM_SETTINGS.ZOOM_STEP,
        ZOOM_SETTINGS.MAX_TILE_SIZE
    );

    if (newTileSize === currentState.tileSize) {
        console.log('Already at maximum zoom level');
        return;
    }

    const params = calculateMapParameters(newTileSize);
    if (!params) return;

    console.log(`Zooming in: New tile size: ${newTileSize}px, Grid: ${params.tilesX}x${params.tilesY}`);
    fetchMapWithNewParameters(currentState.x, currentState.y, params.radiusX, params.radiusY, newTileSize);
}

/**
 * Handle zoom out - decrease tile size
 */
function handleZoomOut() {
    const currentState = getCurrentMapState();
    if (!currentState) return;

    const newTileSize = Math.max(
        currentState.tileSize - ZOOM_SETTINGS.ZOOM_STEP,
        ZOOM_SETTINGS.MIN_TILE_SIZE
    );

    if (newTileSize === currentState.tileSize) {
        console.log('Already at minimum zoom level');
        return;
    }

    const params = calculateMapParameters(newTileSize);
    if (!params) return;

    console.log(`Zooming out: New tile size: ${newTileSize}px, Grid: ${params.tilesX}x${params.tilesY}`);
    fetchMapWithNewParameters(currentState.x, currentState.y, params.radiusX, params.radiusY, newTileSize);
}

/**
 * Fetch map data with new parameters and update the map
 */
function fetchMapWithNewParameters(x, y, radiusX, radiusY, tileSize) {
    const loadingOverlay = document.querySelector('.map-loading-overlay');
    if (loadingOverlay) loadingOverlay.classList.add('active');

    console.log(`Fetching map data with radiusX: ${radiusX}, radiusY: ${radiusY}, tileSize: ${tileSize}`);

    fetch(`backend/scripts/get_map_data.php?x=${x}&y=${y}&radiusX=${radiusX}&radiusY=${radiusY}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (typeof updateMapDisplay === 'function') {
                updateMapDisplay(data, x, y);

                setTimeout(() => {
                    const newMapTiles = document.querySelectorAll('.map-tile');
                    const mapGrid = document.querySelector('.map-grid');
                    const mapContainer = document.querySelector('.map-grid-container');

                    if (mapGrid && mapContainer) {
                        const totalTilesX = (2 * radiusX) + 1;
                        const totalTilesY = (2 * radiusY) + 1;
                        
                        // Update CSS custom properties
                        mapGrid.style.setProperty('--map-size-x', totalTilesX);
                        mapGrid.style.setProperty('--map-size-y', totalTilesY);
                          // Update the grid size with proper gap calculation
                        const fullTileSize = tileSize + ZOOM_SETTINGS.TILE_GAP;
                        const gridWidth = (totalTilesX * tileSize) + ((totalTilesX - 1) * ZOOM_SETTINGS.TILE_GAP);
                        const gridHeight = (totalTilesY * tileSize) + ((totalTilesY - 1) * ZOOM_SETTINGS.TILE_GAP);
                        
                        mapGrid.style.width = `${gridWidth}px`;
                        mapGrid.style.height = `${gridHeight}px`;
                        
                        // Apply tile sizes
                        for (const tile of newMapTiles) {
                            tile.style.width = `${tileSize}px`;
                            tile.style.height = `${tileSize}px`;
                        }
                    }

                    if (loadingOverlay) loadingOverlay.classList.remove('active');
                    console.log(`Zoom completed: ${tileSize}px tiles, ${totalTilesX}x${totalTilesY} grid`);
                }, 200);
            } else {
                console.error('updateMapDisplay function not found');
                if (loadingOverlay) loadingOverlay.classList.remove('active');
            }
        })
        .catch(error => {
            console.error('Error fetching map data:', error);
            if (loadingOverlay) loadingOverlay.classList.remove('active');
            alert('Failed to update the map. Please try again.');
        });
}
