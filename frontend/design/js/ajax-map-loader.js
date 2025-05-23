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
 */
function handleMapNavigation(e, dx, dy) {
    e.preventDefault();
    
    // Get current position from the badge
    const positionDisplay = document.querySelector('.map-position-display .badge');
    const posText = positionDisplay.textContent.trim();
    const posMatch = posText.match(/Position:\s*(\d+),\s*(\d+)/i);
    
    if (!posMatch) {
        console.error('Could not parse position from:', posText);
        return;
    }
    
    const currentX = Number.parseInt(posMatch[1]);
    const currentY = Number.parseInt(posMatch[2]);
    const newX = currentX + dx;
    const newY = currentY + dy;
    
    console.log(`Navigating from (${currentX}, ${currentY}) to (${newX}, ${newY})`);
    
    // Determine direction name for animation
    let direction = '';
    if (dx === -1) direction = 'west';
    else if (dx === 1) direction = 'east';
    else if (dy === -1) direction = 'north';
    else if (dy === 1) direction = 'south';
    
    // Show loading overlay
    const loadingOverlay = document.querySelector('.map-loading-overlay');
    if (loadingOverlay) loadingOverlay.classList.add('active');
    
    // Animate the map movement
    const mapGrid = document.querySelector('.map-grid-container');
    if (mapGrid) {
        // Remove any existing animation classes
        mapGrid.classList.remove('map-slide-north', 'map-slide-south', 'map-slide-east', 'map-slide-west');
        
        // Add the new animation class
        mapGrid.classList.add(`map-slide-${direction}`);
    }
    
    // Make AJAX request to get new map data
    fetch(`backend/scripts/get_map_data.php?x=${newX}&y=${newY}`)
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
    if (mapGrid && mapData.tiles) {
        renderMapTiles(mapGrid, mapData.tiles);
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
    
    // Calculate view radius from the number of tiles
    const size = Math.sqrt(tiles.length);
    mapGrid.style.setProperty('--map-size', size);
    
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
            tileClass += ' city-tile';
            
            if (tile.occupierType === 'player') {
                tileText = '<span>P</span>';
                const cityName = tile.city ? tile.city.name : 'Unknown City';
                const ownerName = tile.city ? tile.city.owner : 'Unknown Player';
                tileTooltip = `Player City\nOwner: ${ownerName}\nCity: ${cityName}\nCoordinates: (${tile.x}, ${tile.y})`;
            } else if (tile.occupierType === 'ai') {
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
