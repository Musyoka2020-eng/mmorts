// AI Opponents page enhancements
document.addEventListener('DOMContentLoaded', () => {
    // Add animation to AI cards when page loads
    const aiCards = document.querySelectorAll('.ai-card');
    
    aiCards.forEach((card, index) => {
        // Add a slight delay for each card
        setTimeout(() => {
            card.classList.add('ai-card-visible');
        }, 100 * index);
    });
    
    // Click handler for the "View Details" buttons
    const viewDetailsButtons = document.querySelectorAll('.view-ai-details');
    
    for (const button of viewDetailsButtons) {
        button.addEventListener('click', function() {
            const aiId = this.getAttribute('data-ai-id');
            
            // Toggle the details section
            const detailsContainer = document.getElementById(`ai-details-${aiId}`);
            
            // First close any open details
            for (const container of document.querySelectorAll('.ai-details-container')) {
                // Skip the current one to avoid animation conflicts
                if (container.id !== `ai-details-${aiId}`) {
                    container.classList.remove('ai-details-visible');
                    setTimeout(() => {
                        container.classList.add('d-none');
                    }, 300);
                }
            }
            
            // Toggle the current one
            if (detailsContainer.classList.contains('d-none')) {
                detailsContainer.classList.remove('d-none');
                setTimeout(() => {
                    detailsContainer.classList.add('ai-details-visible');
                }, 10);
            } else {
                detailsContainer.classList.remove('ai-details-visible');
                setTimeout(() => {
                    detailsContainer.classList.add('d-none');
                }, 300);
            }
        });
    }
    
    // Tooltips for AI personality traits
    const personalityBadges = document.querySelectorAll('.personality-badge');
    
    for (const badge of personalityBadges) {
        const type = badge.getAttribute('data-personality');
        let tooltipText = '';
        
        switch (type) {
            case 'aggressive':
                tooltipText = 'Prefers attacking over building economy. Will attack frequently with medium-sized armies.';
                break;
            case 'defensive':
                tooltipText = 'Focuses on building strong defenses and large armies before attacking.';
                break;
            case 'balanced':
                tooltipText = 'Balances economy and military. Unpredictable attack patterns.';
                break;
            case 'economic':
                tooltipText = 'Prioritizes resource production and technology. Vulnerable early game.';
                break;
            case 'diplomatic':
                tooltipText = 'Seeks alliances and avoids conflict when possible. Strong late game.';
                break;
            default:
                tooltipText = 'Unknown personality type';
        }
        
        // Create and add tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'ai-tooltip';
        tooltip.textContent = tooltipText;
        badge.appendChild(tooltip);
        
        // Add events
        badge.addEventListener('mouseenter', () => {
            tooltip.classList.add('visible');
        });
        
        badge.addEventListener('mouseleave', () => {
            tooltip.classList.remove('visible');
        });
    }
    
    // Interactive difficulty stars
    const difficultyStars = document.querySelectorAll('.difficulty-stars');
    
    for (const starContainer of difficultyStars) {
        const stars = starContainer.querySelectorAll('.star');
        const level = Number.parseInt(starContainer.getAttribute('data-level'));
        
        // Add animation for stars
        let index = 0;
        for (const star of stars) {
            // Determine if star should be active
            if (index < level) {
                setTimeout(() => {
                    star.classList.add('star-active');
                }, 200 * index);
            }
            index++;
        }
    }
    
    // City strength visualization
    const cityStrengthBars = document.querySelectorAll('.city-strength-bar');
    
    for (const bar of cityStrengthBars) {
        const strength = Number.parseInt(bar.getAttribute('data-strength'));
        const maxStrength = 100; // Assuming 100 is the max
        const percent = (strength / maxStrength) * 100;
        
        // Start with 0 width and animate
        bar.style.width = '0%';
        
        setTimeout(() => {
            bar.style.width = `${percent}%`;
            
            // Set color based on strength
            if (percent < 30) {
                bar.style.backgroundColor = '#4CAF50'; // Green for weak
            } else if (percent < 70) {
                bar.style.backgroundColor = '#FFC107'; // Yellow for medium
            } else {
                bar.style.backgroundColor = '#F44336'; // Red for strong
            }
        }, 300);
    }
});

// Function to fetch AI details using AJAX (can be implemented if needed)
function loadAIDetails(aiId) {
    // This could be replaced with an actual AJAX call if needed
    console.log(`Loading details for AI ID: ${aiId}`);
    
    // For now, we're just showing the hidden div with the details
    // that were loaded with the page
}
