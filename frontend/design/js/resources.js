// Resources Bar Timer and Updates

// Initialize the resources system when the page loads
document.addEventListener('DOMContentLoaded', () => {
    initResourcesSystem();
});

function initResourcesSystem() {
    // Start the countdown timer
    startResourceTimer();
    
    // Set up the progress bars
    initProgressBars();
    
    // Add animation to resource items
    animateResourceItems();
}

function startResourceTimer() {
    const timerElement = document.querySelector('.resource-update-timer');
    if (!timerElement) return;
    
    let secondsLeft = Number.parseInt(timerElement.getAttribute('data-seconds-left')) || 60;
    const timerValueElement = timerElement.querySelector('.timer-value');
    
    // Update timer every second
    const timerInterval = setInterval(() => {
        secondsLeft--;
        
        if (secondsLeft <= 0) {
            // Reset timer and update resources
            secondsLeft = 60;
            updateResources();
        }
        
        // Update the timer display
        const minutes = Math.floor(secondsLeft / 60);
        const seconds = secondsLeft % 60;
        timerValueElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        // Update progress bars based on time remaining
        updateProgressBars(60 - secondsLeft, 60);
        
    }, 1000);
}

function initProgressBars() {
    const resourceItems = document.querySelectorAll('.resource-item');
    
    for (const item of resourceItems) {
        const progressBar = item.querySelector('.resource-progress-bar');
        if (progressBar) {
            // Set initial width to 0
            progressBar.style.width = '0%';
        }
    }
}

function updateProgressBars(currentValue, maxValue) {
    const percentage = (currentValue / maxValue) * 100;
    
    const progressBars = document.querySelectorAll('.resource-progress-bar');
    for (const bar of progressBars) {
        bar.style.width = `${percentage}%`;
    }
}

function updateResources() {
    const resourceItems = document.querySelectorAll('.resource-item');
    
    for (const item of resourceItems) {
        const resourceType = item.getAttribute('data-resource');
        const valueElement = item.querySelector('.resource-value');
        const rateElement = item.querySelector('.rate-value');
        
        if (valueElement && rateElement) {
            const currentValue = Number.parseInt(valueElement.getAttribute('data-value')) || 0;
            const rate = Number.parseInt(rateElement.textContent) || 0;
            
            // Calculate new value
            const newValue = currentValue + rate;
            
            // Update data attribute
            valueElement.setAttribute('data-value', newValue);
            
            // Format for display
            valueElement.textContent = new Intl.NumberFormat().format(newValue);
            
            // Add animation class
            valueElement.classList.add('increasing');
            
            // Remove animation class after animation completes
            setTimeout(() => {
                valueElement.classList.remove('increasing');
            }, 500);
        }
    }
}

function animateResourceItems() {
    const resourceItems = document.querySelectorAll('.resource-item');
    
    let index = 0;
    for (const item of resourceItems) {
        // Add a slight delay to each item
        setTimeout(() => {
            item.style.transform = 'translateY(-3px)';
            setTimeout(() => {
                item.style.transform = 'translateY(0)';
            }, 200);
        }, index * 100);
        index++;
    }
}
