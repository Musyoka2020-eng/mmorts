// Production update script
const PRODUCTION_INTERVAL = 60; // seconds

function updateResourceDisplay(resourceType, newValue, rate) {
    console.log(`Updating ${resourceType} to ${newValue} with rate ${rate}`);
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
        .then(response => {
            // Add error checking for the response
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            console.log('Response:', response);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update to match the PHP column names
                updateResourceDisplay('Iron', data.resources.iron, data.productions.iron_rate);
                updateResourceDisplay('Oil', data.resources.oil, data.productions.oil_rate);
                updateResourceDisplay('Wood', data.resources.wood, data.productions.wood_rate);
                updateResourceDisplay('Stone', data.resources.stone, data.productions.stone_rate);
                updateResourceDisplay('Food', data.resources.food, data.productions.food_rate);

                // Reset and animate the progress bars
                const progressBars = document.querySelectorAll('.production-bar');
                for (const bar of progressBars) {
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = '100%';
                    }, 100);
                }
            } else {
                console.error('Resource update failed:', data.message);
            }
        })
        .catch(error => console.error('Error updating resources:', error));
}
// function fetchUpdatedResources() {
//     console.log('Fetching updated resources...');

//     fetch('backend/scripts/update_resources.php')
//         .then(async response => {
//             console.log('Response status:', response.status);
//             console.log('Response headers:', Object.fromEntries(response.headers.entries()));

//             // Clone the response so we can look at the raw text and also parse as JSON
//             const text = await response.text();
//             console.log('Raw response:', text);

//             try {
//                 // Try to parse as JSON to see where it fails
//                 const data = JSON.parse(text);
//                 return data;
//             } catch (error) {
//                 console.error('JSON parsing error:', error);
//                 console.error('First 100 characters of response:', text.substring(0, 100));
//                 throw new Error('Invalid JSON response from server');
//             }
//         })
//         .then(data => {
//             console.log('Parsed data:', data);

//             if (data.success) {
//                 console.log('Resources:', data.resources);
//                 console.log('Productions:', data.productions);

//                 // Proceed with updating the display
//                 updateResourceDisplay('Iron', data.resources.iron, data.productions.iron_rate || data.productions.iron_production);
//                 updateResourceDisplay('Oil', data.resources.oil, data.productions.oil_rate || data.productions.oil_production);
//                 updateResourceDisplay('Wood', data.resources.wood, data.productions.wood_rate || data.productions.wood_production);
//                 updateResourceDisplay('Stone', data.resources.stone, data.productions.stone_rate || data.productions.stone_production);
//                 updateResourceDisplay('Food', data.resources.food, data.productions.food_rate || data.productions.food_production);

//                 // Reset and animate the progress bars
//                 const progressBars = document.querySelectorAll('.production-bar');
//                 for (const bar of progressBars) {
//                     bar.style.width = '0%';
//                     setTimeout(() => {
//                         bar.style.width = '100%';
//                     }, 100);
//                 }
//             } else {
//                 console.error('Resource update failed:', data.message);
//             }
//         })
//         .catch(error => {
//             console.error('Error updating resources:', error);
//             console.error('Stack trace:', error.stack);
//         });
// }
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
