/**
 * Mini-map functionality for home page
 * A simplified version of the world map that shows surrounding area
 */

document.addEventListener('DOMContentLoaded', () => {
    initializeMiniMap();
});

/**
 * Initialize mini-map interaction and tooltips
 */
function initializeMiniMap() {
    const miniMapTiles = document.querySelectorAll('.mini-map-tile');
    
    // Create tooltip element for mini-map if it doesn't already exist
    let tooltip = document.getElementById('mini-map-tooltip');
    
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'mini-map-tooltip';
        tooltip.className = 'mini-map-tooltip-popup';
        document.body.appendChild(tooltip);
    }
    
    // Add interaction to mini-map tiles
    for (const tile of miniMapTiles) {
        // Show tooltip on hover
        tile.addEventListener('mouseenter', function() {
            const tooltipText = this.querySelector('.mini-map-tooltip').textContent;
            const rect = this.getBoundingClientRect();
            
            // Position tooltip
            tooltip.textContent = tooltipText;
            tooltip.style.display = 'block';
            
            // Calculate position (above the tile)
            let top = rect.top - tooltip.offsetHeight - 5;
            let left = rect.left;
            
            // If too close to the top, position below
            if (top < 5) {
                top = rect.bottom + 5;
            }
            
            // If too close to the right edge
            if (left + tooltip.offsetWidth > window.innerWidth - 5) {
                left = window.innerWidth - tooltip.offsetWidth - 5;
            }
            
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
        });
        
        // Hide tooltip when not hovering
        tile.addEventListener('mouseleave', () => {
            tooltip.style.display = 'none';
        });
        
        // Add click interaction for resources and cities
        tile.addEventListener('click', function() {
            const tooltipText = this.querySelector('.mini-map-tooltip').textContent;
            
            // Get coordinates from tooltip
            const coordMatch = tooltipText.match(/Coordinates: \((\d+), (\d+)\)/);
            if (!coordMatch) return;
            
            const x = coordMatch[1];
            const y = coordMatch[2];
            
            // Add flash effect on click
            this.classList.add('mini-tile-flash');
            setTimeout(() => {
                this.classList.remove('mini-tile-flash');
            }, 300);
            
            // Check if it's a resource tile
            if (this.querySelector('.mini-resource')) {
                // Extract resource type
                let resourceType = "";
                if (tooltipText.includes("Iron")) resourceType = "iron";
                else if (tooltipText.includes("Wood")) resourceType = "wood";
                else if (tooltipText.includes("Stone")) resourceType = "stone";
                else if (tooltipText.includes("Food")) resourceType = "food";
                
                // Show action dialog
                if (confirm(`Do you want to gather ${resourceType} at coordinates (${x}, ${y})?`)) {
                    window.location.href = `index.php?page=gather&target_x=${x}&target_y=${y}`;
                }
            }
            // Check if it's an AI city
            else if (tooltipText.includes("AI City")) {
                if (confirm(`Do you want to attack the AI city at coordinates (${x}, ${y})?`)) {
                    window.location.href = `index.php?page=battle&target_x=${x}&target_y=${y}`;
                }
            }
            // Handle other tile types as needed
        });
    }
    
    // Add scroll handling for tooltip
    document.addEventListener('scroll', () => {
        const hoveredTile = document.querySelector('.mini-map-tile:hover');
        if (hoveredTile && tooltip.style.display === 'block') {
            const tooltipText = hoveredTile.querySelector('.mini-map-tooltip').textContent;
            const rect = hoveredTile.getBoundingClientRect();
            
            // Reposition tooltip
            let top = rect.top - tooltip.offsetHeight - 5;
            let left = rect.left;
            
            if (top < 5) {
                top = rect.bottom + 5;
            }
            
            if (left + tooltip.offsetWidth > window.innerWidth - 5) {
                left = window.innerWidth - tooltip.offsetWidth - 5;
            }
            
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
        }
    }, { passive: true });
}
