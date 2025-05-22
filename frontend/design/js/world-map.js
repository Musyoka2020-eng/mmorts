/**
 * World Map Game UI Enhancement Script
 * Adds interactive elements and effects to the world map
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialize popup tooltip system
    initializeTooltips();
    
    // Add tile interactions and animations
    initializeTileInteractions();
    
    // Setup map controls and view toggles
    initializeMapControls();
    
    // Add smooth transition for map navigation
    initializeMapNavigation();
    
    // Water animation effect
    animateWaterTiles();
});

/**
 * Initialize custom tooltips for map tiles
 */
function initializeTooltips() {
    // Create popup element for tooltips
    const popup = document.createElement('div');
    popup.className = 'tile-popup';
    popup.innerHTML = `
        <div class="popup-title"></div>
        <div class="popup-content"></div>
    `;
    document.body.appendChild(popup);
    
    // Add event listeners to map tiles
    const mapTiles = document.querySelectorAll('.map-tile');
    
    for (const tile of mapTiles) {
        tile.addEventListener('mouseenter', function() {
            const title = this.getAttribute('data-title') || this.getAttribute('title') || '';
            const content = this.getAttribute('data-content') || '';
            
            // Set popup content
            popup.querySelector('.popup-title').textContent = title.split(':')[0] || '';
            
            // Process content with line breaks
            const contentText = content || title.split(':').slice(1).join(':').trim();
            popup.querySelector('.popup-content').innerHTML = contentText.replace(/\n/g, '<br>');
            
            // Position popup near the tile
            const rect = this.getBoundingClientRect();
            popup.style.left = `${rect.left}px`;
            popup.style.top = `${rect.top - popup.offsetHeight - 10}px`;
            
            // Make sure popup stays in viewport
            const popupRect = popup.getBoundingClientRect();
            if (popupRect.left < 10) popup.style.left = '10px';
            if (popupRect.right > window.innerWidth - 10) {
                popup.style.left = `${window.innerWidth - popup.offsetWidth - 10}px`;
            }
            if (popupRect.top < 10) {
                popup.style.top = `${rect.bottom + 10}px`;
            }
            
            // Show popup with animation
            popup.classList.add('active');
        });
        
        tile.addEventListener('mouseleave', () => {
            popup.classList.remove('active');
        });
    }
}

/**
 * Add interactive behaviors to map tiles
 */
function initializeTileInteractions() {
    const mapTiles = document.querySelectorAll('.map-tile');
    
    for (const tile of mapTiles) {
        // Add hover glow effect
        tile.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
            
            // Add small shake animation to resource tiles
            if (this.querySelector('.tile-resource')) {
                this.querySelector('.tile-resource').classList.add('resource-shake');
            }
        });
        
        tile.addEventListener('mouseleave', function() {
            this.style.zIndex = '1';
            
            // Remove shake animation
            if (this.querySelector('.tile-resource')) {
                this.querySelector('.tile-resource').classList.remove('resource-shake');
            }
        });
        
        // Add click interaction
        tile.addEventListener('click', function() {
            // Get tile coordinates
            const x = this.getAttribute('data-x');
            const y = this.getAttribute('data-y');
            
            // Flash effect on click
            this.classList.add('tile-flash');
            setTimeout(() => {
                this.classList.remove('tile-flash');
            }, 300);
            
            // Handle different tile types
            if (this.classList.contains('city-tile') && this.innerText === 'A') {
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
            }
            else if (!this.classList.contains('player-city') && !this.classList.contains('city-tile')) {
                showMapActionDialog('Move To Location', 
                    `Do you want to move to coordinates (${x}, ${y})?`,
                    () => {
                        // Implement movement or settlement logic
                        alert('Movement not implemented yet');
                    });
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
    document.head.appendChild(style);
}

/**
 * Shows an action dialog for map interactions
 */
function showMapActionDialog(title, message, confirmCallback) {
    // Create overlay if it doesn't exist
    let overlay = document.querySelector('.map-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'map-overlay';
        document.body.appendChild(overlay);
    }
    
    // Create dialog
    overlay.innerHTML = `
        <div class="map-action-dialog">
            <h4>${title}</h4>
            <p>${message}</p>
            <div class="action-buttons">
                <button class="action-button confirm">Confirm</button>
                <button class="action-button cancel">Cancel</button>
            </div>
        </div>
    `;
    
    // Show overlay
    overlay.style.display = 'flex';
    
    // Add event listeners
    overlay.querySelector('.action-button.confirm').addEventListener('click', () => {
        overlay.style.display = 'none';
        if (typeof confirmCallback === 'function') {
            confirmCallback();
        }
    });
    
    overlay.querySelector('.action-button.cancel').addEventListener('click', () => {
        overlay.style.display = 'none';
    });
    
    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.style.display = 'none';
        }
    });
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
    document.getElementById('zoom-in').addEventListener('click', () => {
        const mapGrid = document.querySelector('.map-grid');
        const mapTiles = document.querySelectorAll('.map-tile');
        
        for (const tile of mapTiles) {
            const currentWidth = Number.parseInt(getComputedStyle(tile).width);
            tile.style.width = `${currentWidth + 5}px`;
            tile.style.height = `${currentWidth + 5}px`;
        }
    });
    
    document.getElementById('zoom-out').addEventListener('click', () => {
        const mapGrid = document.querySelector('.map-grid');
        const mapTiles = document.querySelectorAll('.map-tile');
        
        for (const tile of mapTiles) {
            const currentWidth = Number.parseInt(getComputedStyle(tile).width);
            if (currentWidth > 25) { // Minimum size
                tile.style.width = `${currentWidth - 5}px`;
                tile.style.height = `${currentWidth - 5}px`;
            }
        }
    });
    
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
        button.addEventListener('click', function() {
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
 * Add smooth transitions for map navigation
 */
function initializeMapNavigation() {
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
    const currentX = Number.parseInt(document.querySelector('.map-position .badge').textContent.split(',')[0].trim());
    const currentY = Number.parseInt(document.querySelector('.map-position .badge').textContent.split(',')[1].trim());
    
    // Set up navigation button events
    document.getElementById('map-north').addEventListener('click', () => {
        window.location.href = `index.php?page=world_map&y=${currentY - 1}&x=${currentX}`;
    });
    
    document.getElementById('map-south').addEventListener('click', () => {
        window.location.href = `index.php?page=world_map&y=${currentY + 1}&x=${currentX}`;
    });
    
    document.getElementById('map-west').addEventListener('click', () => {
        window.location.href = `index.php?page=world_map&y=${currentY}&x=${currentX - 1}`;
    });
    
    document.getElementById('map-east').addEventListener('click', () => {
        window.location.href = `index.php?page=world_map&y=${currentY}&x=${currentX + 1}`;
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
