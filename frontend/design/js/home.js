// Home page animations and enhancements
document.addEventListener('DOMContentLoaded', () => {
    // Fix CSS conflicts by ensuring proper class assignments
    const dashboardContainer = document.querySelector('.dashboard-container');
    if (dashboardContainer) {
        // Add higher specificity class to ensure our styles apply
        dashboardContainer.classList.add('mmorts-dashboard');
        
        // Ensure all resource cards have the right classes
        const cards = dashboardContainer.querySelectorAll('.resource-card');
        for (const card of cards) {
            card.classList.add('mmorts-card');
        }
    }
    
    // Add animations to resource cards
    const resourceCards = document.querySelectorAll('.resource-card');
    
    resourceCards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('resource-card-visible');
        }, 100 * index);
    });
    
    // Add animations to city overview
    const cityOverview = document.querySelector('.city-overview');
    if (cityOverview) {
        setTimeout(() => {
            cityOverview.classList.add('city-overview-visible');
        }, 300);
    }
    
    // Notifications system
    const notifications = document.querySelectorAll('.game-notification');
    
    let notifIndex = 0;
    for (const notification of notifications) {
        setTimeout(() => {
            notification.classList.add('notification-visible');
            
            // Auto hide notifications after some time
            setTimeout(() => {
                notification.classList.remove('notification-visible');
                setTimeout(() => {
                    notification.remove();
                }, 500);
            }, 5000 + (notifIndex * 1000));
        }, 500 + (notifIndex * 300));
        notifIndex++;
    }
    
    // City buildings interaction
    const buildingSlots = document.querySelectorAll('.building-slot');
    
    for (const slot of buildingSlots) {
        slot.addEventListener('mouseenter', function() {
            const buildingTooltip = this.querySelector('.building-tooltip');
            if (buildingTooltip) {
                buildingTooltip.classList.add('visible');
            }
        });
        
        slot.addEventListener('mouseleave', function() {
            const buildingTooltip = this.querySelector('.building-tooltip');
            if (buildingTooltip) {
                buildingTooltip.classList.remove('visible');
            }
        });
        
        // Building upgrade click effect
        slot.addEventListener('click', function() {
            if (!this.classList.contains('empty-slot')) {
                this.classList.add('building-clicked');
                setTimeout(() => {
                    this.classList.remove('building-clicked');
                }, 300);
            }
        });
    }
      // Resource production animation
    // function animateResourceProduction() {
    //     const resourceValues = document.querySelectorAll('.resource-value');
        
    //     for (const value of resourceValues) {
    //         // Store current value
    //         const currentValue = value.textContent;
            
    //         // Add update animation
    //         value.classList.add('resource-update');
            
    //         // Update with a slight increase for animation effect
    //         if (value.id) {
    //             const numericValue = Number.parseInt(currentValue.replace(/,/g, ''));
    //             if (!Number.isNaN(numericValue)) {
    //                 // Calculate production amount (this is just for animation)
    //                 const productionElement = value.closest('.resource-card').querySelector('.production-value');
    //                 let productionAmount = 1;
                    
    //                 if (productionElement) {
    //                     const productionText = productionElement.textContent;
    //                     const productionMatch = productionText.match(/\+(\d+)/);
    //                     if (productionMatch?.[1]) {
    //                         productionAmount = Number.parseInt(productionMatch[1]);
    //                     }
    //                 }
                    
    //                 const newValue = numericValue + productionAmount;
    //                 value.textContent = newValue.toLocaleString();
    //             }
    //         }
            
    //         setTimeout(() => {
    //             value.classList.remove('resource-update');
    //         }, 500);
    //     }
    // }
    
    // // Run resource animation periodically
    // setInterval(animateResourceProduction, 60000); // Every minute
    
    // Recent activities animation
    const activityItems = document.querySelectorAll('.activity-item');
    
    let activityIndex = 0;
    for (const item of activityItems) {
        setTimeout(() => {
            item.classList.add('activity-item-visible');
        }, 200 * activityIndex);
        activityIndex++;
    }
    
    // Quick action buttons effects
    const quickActions = document.querySelectorAll('.quick-action-btn');
    
    for (const btn of quickActions) {
        btn.addEventListener('mouseenter', function() {
            this.classList.add('quick-action-hover');
        });
        
        btn.addEventListener('mouseleave', function() {
            this.classList.remove('quick-action-hover');
        });
        
        btn.addEventListener('click', function() {
            this.classList.add('quick-action-click');
            setTimeout(() => {
                this.classList.remove('quick-action-click');
            }, 300);
        });
    }
    
    // Initialize the city view 3D effect if element exists
    const cityView = document.querySelector('.city-view-3d');
    if (cityView) {
        initCityView3D(cityView);
    }
    
    // Mini-map hover effect
    const miniMap = document.querySelector('.mini-map');
    if (miniMap) {
        const mapTiles = miniMap.querySelectorAll('.mini-map-tile');
        
        for (const tile of mapTiles) {
            tile.addEventListener('mouseenter', function() {
                const tooltip = this.querySelector('.mini-map-tooltip');
                if (tooltip) {
                    tooltip.classList.add('visible');
                }
            });
            
            tile.addEventListener('mouseleave', function() {
                const tooltip = this.querySelector('.mini-map-tooltip');
                if (tooltip) {
                    tooltip.classList.remove('visible');
                }
            });
        }
    }
    
    // Initialize resource timer
    // startResourceTimer();
});

// Add countdown timer for resources
// function startResourceTimer() {
//     const timerElement = document.getElementById('resource-timer');
//     if (!timerElement) return;
    
//     let secondsLeft = 60; // 1 minute countdown
    
//     function updateTimer() {
//         const minutes = Math.floor(secondsLeft / 60);
//         const seconds = secondsLeft % 60;
        
//         // Format with leading zeros
//         const formattedMinutes = String(minutes).padStart(2, '0');
//         const formattedSeconds = String(seconds).padStart(2, '0');
        
//         timerElement.textContent = `${formattedMinutes}:${formattedSeconds}`;
        
//         if (secondsLeft <= 10) {
//             timerElement.classList.add('timer-ending');
//         } else {
//             timerElement.classList.remove('timer-ending');
//         }
        
//         if (secondsLeft <= 0) {
//             // Reset timer and trigger resource update
//             secondsLeft = 60;
//             animateResourceProduction();
//         } else {
//             secondsLeft--;
//         }
//     }
    
//     // Initial update
//     updateTimer();
    
//     // Update every second
//     setInterval(updateTimer, 1000);
// }

// Simplified 3D city view effect
function initCityView3D(element) {
    if (!element) return;
    
    // Track mouse position
    let mouseX = 0;
    let mouseY = 0;
    
    // Listen for mouse movement
    element.addEventListener('mousemove', e => {
        // Get mouse position relative to the element
        const rect = element.getBoundingClientRect();
        mouseX = e.clientX - rect.left - rect.width / 2;
        mouseY = e.clientY - rect.top - rect.height / 2;
        
        // Calculate rotation based on mouse position
        const rotateY = (mouseX / rect.width) * 10; // Max 10 degrees
        const rotateX = (mouseY / rect.height) * -10; // Max 10 degrees
        
        // Apply rotation
        element.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    });
    
    // Reset when mouse leaves
    element.addEventListener('mouseleave', () => {
        element.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
    });
}
