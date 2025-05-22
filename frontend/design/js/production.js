// Production update script
const PRODUCTION_INTERVAL = 60; // seconds

function updateResourceDisplay(resourceType, newValue, rate) {
    const resourceElement = document.querySelector(`.resource-item img[alt="${resourceType}"]`).nextElementSibling;
    if (resourceElement) {
        resourceElement.textContent = formatNumber(newValue);
        
        // Animate the update
        resourceElement.classList.add('updated');
        setTimeout(() => {
            resourceElement.classList.remove('updated');
        }, 1000);
    }
}

function formatNumber(num) {
    return new Intl.NumberFormat().format(num);
}

// Function to fetch updated resources
function fetchUpdatedResources() {
    fetch('backend/scripts/update_resources.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the resource displays with the current database structure
                updateResourceDisplay('Iron', data.resources.iron, data.productions.iron_production);
                updateResourceDisplay('Oil', data.resources.oil, data.productions.oil_production);
                updateResourceDisplay('Wood', data.resources.wood, data.productions.wood_production);
                updateResourceDisplay('Stone', data.resources.stone, data.productions.stone_production);
                updateResourceDisplay('Food', data.resources.food, data.productions.food_production);
                
                // Reset and animate the progress bars
                const progressBars = document.querySelectorAll('.production-bar');
                for (const bar of progressBars) {
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = '100%';
                    }, 100);
                }
            }
        })
        .catch(error => console.error('Error updating resources:', error));
}

// Initialize resource update timer
document.addEventListener('DOMContentLoaded', () => {
    // Update resources immediately
    fetchUpdatedResources();
    
    // Set up automatic updates
    setInterval(fetchUpdatedResources, PRODUCTION_INTERVAL * 1000);
    
    // Initialize progress bars
    const progressBars = document.querySelectorAll('.production-bar');
    for (const bar of progressBars) {
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = '100%';
        }, 100);
    }
});
