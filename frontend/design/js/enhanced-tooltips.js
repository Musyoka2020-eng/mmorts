/**
 * Enhanced tooltip system for world map tiles
 * This handles scrolling issues and ensures tooltips are properly positioned
 * without interfering with click events on map tiles
 */

// Execute when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('Enhanced tooltips loading...');
    // Add a small delay to ensure world-map.js has run first
    setTimeout(() => {
        initEnhancedTooltips();
    }, 100);
});

// Make initEnhancedTooltips globally accessible so it can be called after AJAX updates
function initEnhancedTooltips() {
    console.log('Initializing Enhanced Tooltips');
    
    // Create tooltip element if it doesn't already exist
    let tooltip = document.getElementById('enhanced-map-tooltip');
    
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'enhanced-map-tooltip';
        tooltip.className = 'tile-popup'; // Reuse existing style
        tooltip.innerHTML = `
            <div class="popup-title"></div>
            <div class="popup-content"></div>
        `;
        document.body.appendChild(tooltip);
        
        // Add style to ensure fixed positioning
        const styleEl = document.getElementById('enhanced-tooltip-style');
        if (!styleEl) {
            const newStyleEl = document.createElement('style');
            newStyleEl.id = 'enhanced-tooltip-style';
            newStyleEl.textContent = `
                #enhanced-map-tooltip {
                    position: fixed;
                    z-index: 9999;
                    pointer-events: none;
                }
            `;
            document.head.appendChild(newStyleEl);
        }
    }    // Get all map tiles
    const mapTiles = document.querySelectorAll('.map-tile');
    console.log(`Found ${mapTiles.length} map tiles for tooltips`);
    
    // Create handler functions
    const handleMouseEnter = function() {
        showTooltip(this, tooltip);
    };
    
    const handleMouseLeave = () => {
        hideTooltip(tooltip);
    };
    
    // DO NOT remove existing click handlers!
    // Just attach mouseenter/mouseleave for tooltips
    for (const tile of mapTiles) {
        // Skip tiles that already have tooltip handlers
        if (tile.hasAttribute('data-tooltip-initialized')) {
            continue;
        }
        
        // Mark this tile as tooltip-initialized
        tile.setAttribute('data-tooltip-initialized', 'true');
        
        // First make sure the tile has the necessary data attributes
        if (!tile.dataset.content && tile.title) {
            tile.dataset.content = tile.title;
        }
        
        // Add mouseenter/mouseleave listeners for tooltips ONLY
        // These won't interfere with click event handlers
        tile.addEventListener('mouseenter', handleMouseEnter);
        tile.addEventListener('mouseleave', handleMouseLeave);
    }    // Also update on scroll
    window.addEventListener('scroll', () => {
        // Check if the tooltip is visible
        if (tooltip.classList.contains('active')) {
            // Find which tile is currently being hovered
            const hoveredTile = document.querySelector('.map-tile:hover');
            if (hoveredTile) {
                showTooltip(hoveredTile, tooltip);
            } else {
                hideTooltip(tooltip);
            }
        }
    }, { passive: true });
    
    // Re-initialize on window resize for correct positioning
    window.addEventListener('resize', () => {
        const hoveredTile = document.querySelector('.map-tile:hover');
        if (hoveredTile && tooltip.classList.contains('active')) {
            showTooltip(hoveredTile, tooltip);
        }
    }, { passive: true });
    
    // Log successful initialization
    console.log('Enhanced tooltips initialized successfully');
}

/**
 * Show tooltip for a specific tile
 */
function showTooltip(tile, tooltip) {
    // Get content from tile attributes
    const title = tile.getAttribute('data-title') || '';
    const content = tile.getAttribute('data-content') || tile.getAttribute('title') || '';
    
    // Skip if no content
    if (!content && !title) return;
    
    // Parse the content - it can be a multi-line string with terrain type and coordinates
    const contentLines = content.split('\n');
    const titleText = contentLines[0] || '';
    const contentText = contentLines.slice(1).join('<br>');
    
    // Set tooltip content
    tooltip.querySelector('.popup-title').textContent = title || titleText;
    tooltip.querySelector('.popup-content').innerHTML = content ? contentText : '';
    
    // Make tooltip visible to calculate size
    tooltip.style.display = 'block';
    tooltip.classList.add('active');
    
    // Get dimensions
    const tooltipWidth = tooltip.offsetWidth;
    const tooltipHeight = tooltip.offsetHeight;
    
    // Get tile position relative to viewport
    const rect = tile.getBoundingClientRect();
    
    // Default position (above the tile)
    let top = rect.top - tooltipHeight - 10;
    let left = rect.left + (rect.width / 2) - (tooltipWidth / 2);
    
    // Adjust position if tooltip would be off screen
    // If too close to the top, position below
    if (top < 10) {
        top = rect.bottom + 10;
    }
    
    // If too close to the right edge
    if (left + tooltipWidth > window.innerWidth - 10) {
        left = window.innerWidth - tooltipWidth - 10;
    }
    
    // If too close to the left edge
    if (left < 10) {
        left = 10;
    }
    
    // Set position
    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;
}

/**
 * Hide the tooltip
 */
function hideTooltip(tooltip) {
    tooltip.classList.remove('active');
}
