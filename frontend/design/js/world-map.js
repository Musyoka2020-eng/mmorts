/**
 * World Map Game UI Enhancement Script
 * Adds interactive elements and effects to the world map
 */

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
 * Add interactive behaviors to map tiles
 */
function initializeTileInteractions() {
    // Make this function globally accessible
    window.initializeTileInteractions = initializeTileInteractions;

    console.log('Initializing tile interactions for map clicks');

    // Get all map tiles
    const mapTiles = document.querySelectorAll('.map-tile');
    console.log(`Found ${mapTiles.length} map tiles for click interactions`);

    // DO NOT clone the tiles here as it would remove tooltip event listeners
    // Just add click handlers directly

    for (const tile of mapTiles) {
        // Skip if the tile already has click handlers
        if (tile.hasAttribute('data-click-initialized')) {
            continue;
        }

        // Mark this tile as click-initialized
        tile.setAttribute('data-click-initialized', 'true');

        // Add hover effect
        tile.addEventListener('mouseenter', function () {
            this.style.zIndex = '10';

            // Add small shake animation to resource tiles
            if (this.querySelector('.tile-resource')) {
                this.querySelector('.tile-resource').classList.add('resource-shake');
            }
        });

        tile.addEventListener('mouseleave', function () {
            this.style.zIndex = '1';

            // Remove shake animation
            if (this.querySelector('.tile-resource')) {
                this.querySelector('.tile-resource').classList.remove('resource-shake');
            }
        });        // Add click interaction
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
    controlsContainer.className = 'map-controls';
    controlsContainer.innerHTML = `
        <div class="control-group">
            <button id="zoom-in"><i class="fas fa-search-plus"></i> Zoom In</button>
            <button id="zoom-out"><i class="fas fa-search-minus"></i> Zoom Out</button>
        </div>
        <div class="control-group view-toggle">
            <button id="view-terrain" class="active"><i class="fas fa-mountain"></i> Terrain</button>
            <button id="view-resources"><i class="fas fa-coins"></i> Resources</button>
            <button id="view-political"><i class="fas fa-flag"></i> Political</button>
        </div>
        <div class="control-group">
            <button id="center-map"><i class="fas fa-crosshairs"></i> Center</button>
        </div>
    `;

    // Insert controls before the map grid
    const mapGrid = document.querySelector('.map-grid');
    mapGrid.parentNode.insertBefore(controlsContainer, mapGrid);

    // Add event listeners for controls
    // Note: Zoom buttons are now handled by zoom.js

    document.getElementById('center-map').addEventListener('click', () => {
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
        }
    });

    // View toggle buttons
    const viewButtons = document.querySelectorAll('.view-toggle button');
    for (const button of viewButtons) {
        button.addEventListener('click', function () {
            // Remove active class from all buttons
            for (const btn of viewButtons) btn.classList.remove('active');
            // Add active class to clicked button
            this.classList.add('active');

            // Handle view changes
            const viewType = this.id;
            const mapTiles = document.querySelectorAll('.map-tile');

            if (viewType === 'view-terrain') {
                for (const tile of mapTiles) {
                    tile.classList.remove('resource-view', 'political-view');
                }
            } else if (viewType === 'view-resources') {
                for (const tile of mapTiles) {
                    tile.classList.remove('political-view');
                    tile.classList.add('resource-view');
                }
            } else if (viewType === 'view-political') {
                for (const tile of mapTiles) {
                    tile.classList.remove('resource-view');
                    tile.classList.add('political-view');
                }
            }
        });
    }

    // Add CSS for highlight effect
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
