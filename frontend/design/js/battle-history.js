// Modern Battle History JavaScript
document.addEventListener('DOMContentLoaded', () => {
    initializeBattleHistory();
});

function initializeBattleHistory() {
    // Initialize filters
    initializeFilters();
    
    // Initialize search
    initializeSearch();
    
    // Initialize expand/collapse functionality
    initializeExpandButtons();
    
    // Initialize animations
    initializeAnimations();
    
    // Auto-refresh every 30 seconds
    setInterval(checkForNewBattles, 30000);
}

// Filter functionality
function initializeFilters() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const battleEntries = document.querySelectorAll('.battle-entry');
    
    for (const button of filterButtons) {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            for (const btn of filterButtons) {
                btn.classList.remove('active');
            }
            
            // Add active class to clicked button
            this.classList.add('active');
            
            const filterType = this.dataset.filter;
            filterBattles(filterType, battleEntries);
        });
    }
}

function filterBattles(filterType, battleEntries) {
    let index = 0;
    for (const entry of battleEntries) {
        let shouldShow = true;
        
        switch(filterType) {
            case 'wins':
                shouldShow = entry.classList.contains('victory');
                break;
            case 'losses':
                shouldShow = entry.classList.contains('defeat');
                break;
            case 'recent': {
                // Show only battles from last 7 days
                const battleDate = new Date(entry.querySelector('.battle-date-modern').textContent);
                const daysDiff = (new Date() - battleDate) / (1000 * 60 * 60 * 24);
                shouldShow = daysDiff <= 7;
                break;
            }
            default:
                shouldShow = true;
                break;
        }
        
        if (shouldShow) {
            entry.style.display = 'block';
            entry.style.animationDelay = `${index * 0.1}s`;
            entry.classList.add('fade-in');
        } else {
            entry.style.display = 'none';
            entry.classList.remove('fade-in');
        }
        index++;
    }
    
    // Update visible count
    updateVisibleCount();
}

// Search functionality
function initializeSearch() {
    const searchInput = document.getElementById('battleSearch');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const battleEntries = document.querySelectorAll('.battle-entry');
            
            for (const entry of battleEntries) {
                const attackerName = entry.querySelector('.attacker .participant-name').textContent.toLowerCase();
                const defenderName = entry.querySelector('.defender .participant-name').textContent.toLowerCase();
                const battleDate = entry.querySelector('.battle-date-modern').textContent.toLowerCase();
                
                const matches = attackerName.includes(searchTerm) || 
                               defenderName.includes(searchTerm) || 
                               battleDate.includes(searchTerm);
                
                if (matches || searchTerm === '') {
                    entry.style.display = 'block';
                    entry.classList.add('fade-in');
                } else {
                    entry.style.display = 'none';
                    entry.classList.remove('fade-in');
                }
            }
            
            updateVisibleCount();
        });
    }
}

// Expand/Collapse functionality
function initializeExpandButtons() {
    const expandButtons = document.querySelectorAll('.expand-btn');
    
    for (const button of expandButtons) {
        button.addEventListener('click', function() {
            const battleId = this.dataset.battleId;
            const detailsSection = document.getElementById(`details-${battleId}`);
            const icon = this.querySelector('i');
            
            if (detailsSection.style.display === 'none' || detailsSection.style.display === '') {
                // Expand
                detailsSection.style.display = 'block';
                detailsSection.classList.add('slide-down');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
                this.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
                
                // Load additional battle details via AJAX if needed
                loadBattleDetails(battleId);
            } else {
                // Collapse
                detailsSection.style.display = 'none';
                detailsSection.classList.remove('slide-down');
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
                this.innerHTML = '<i class="fas fa-chevron-down"></i> View Details';
            }
        });
    }
}

// Animation initialization
function initializeAnimations() {
    // Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        }
    }, observerOptions);
    
    // Observe all battle entries
    const battleEntries = document.querySelectorAll('.battle-entry');
    for (const entry of battleEntries) {
        observer.observe(entry);
    }
    
    // Add hover effects to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    for (const card of statCards) {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.05)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    }
}

// Load additional battle details
function loadBattleDetails(battleId) {
    // Only load if not already loaded
    const detailsContainer = document.getElementById(`details-${battleId}`);
    if (detailsContainer.dataset.loaded === 'true') {
        return;
    }
    
    // Add loading indicator
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'loading-indicator';
    loadingIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading additional details...';
    detailsContainer.appendChild(loadingIndicator);
    
    // Simulate AJAX call (replace with actual implementation)
    setTimeout(() => {
        loadingIndicator.remove();
        detailsContainer.dataset.loaded = 'true';
        
        // Add advanced battle statistics
        addAdvancedStats(battleId, detailsContainer);
    }, 1000);
}

// Add advanced battle statistics
function addAdvancedStats(battleId, container) {
    const advancedStats = document.createElement('div');
    advancedStats.className = 'advanced-stats';
    advancedStats.innerHTML = `
        <div class="detail-section">
            <h4><i class="fas fa-chart-line"></i> Battle Analytics</h4>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-label">Battle Duration</span>
                    <span class="stat-value">${Math.floor(Math.random() * 10) + 1} minutes</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Damage Efficiency</span>
                    <span class="stat-value">${Math.floor(Math.random() * 30) + 70}%</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Units Deployed</span>
                    <span class="stat-value">${Math.floor(Math.random() * 50) + 20}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Experience Gained</span>
                    <span class="stat-value">+${Math.floor(Math.random() * 100) + 50} XP</span>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(advancedStats);
    
    // Animate the new content
    advancedStats.style.opacity = '0';
    advancedStats.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
        advancedStats.style.transition = 'all 0.5s ease';
        advancedStats.style.opacity = '1';
        advancedStats.style.transform = 'translateY(0)';
    }, 100);
}

// Update visible battle count
function updateVisibleCount() {
    const visibleBattles = document.querySelectorAll('.battle-entry[style*="block"], .battle-entry:not([style*="none"])').length;
    const totalBattles = document.querySelectorAll('.battle-entry').length;
    
    // Update count in header if element exists
    const countElement = document.getElementById('battle-count');
    if (countElement) {
        countElement.textContent = `Showing ${visibleBattles} of ${totalBattles} battles`;
    }
}

// Check for new battles
function checkForNewBattles() {
    // This would typically make an AJAX call to check for new battles
    // For now, just add a visual indicator that the page is being checked
    const header = document.querySelector('.battle-history-header');
    if (header) {
        header.style.borderColor = 'rgba(34, 197, 94, 0.3)';
        
        setTimeout(() => {
            header.style.borderColor = 'rgba(59, 130, 246, 0.2)';
        }, 1000);
    }
}

// Export battle history as JSON
function exportBattleHistory() {
    const battles = [];
    const entries = document.querySelectorAll('.battle-entry');
    for (const entry of entries) {
        const battleData = {
            id: entry.dataset.battleId,
            date: entry.querySelector('.battle-date-modern').textContent,
            time: entry.querySelector('.battle-time').textContent,
            result: entry.classList.contains('victory') ? 'Victory' : 'Defeat',
            attacker: entry.querySelector('.attacker .participant-name').textContent,
            defender: entry.querySelector('.defender .participant-name').textContent
        };
        battles.push(battleData);
    }
    
    const dataStr = JSON.stringify(battles, null, 2);
    const dataUri = `data:application/json;charset=utf-8,${encodeURIComponent(dataStr)}`;
    
    const exportFileDefaultName = `battle_history_${new Date().toISOString().split('T')[0]}.json`;
    
    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // Ctrl+F to focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('battleSearch');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    // Escape to clear search
    if (e.key === 'Escape') {
        const searchInput = document.getElementById('battleSearch');
        if (searchInput && searchInput === document.activeElement) {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.blur();
        }
    }
});

// Add CSS animations dynamically
const style = document.createElement('style');
style.textContent = `
    .fade-in {
        animation: fadeInUp 0.6s ease forwards;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .slide-down {
        animation: slideDown 0.4s ease forwards;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            max-height: 0;
        }
        to {
            opacity: 1;
            max-height: 500px;
        }
    }
    
    .animate-in {
        animation: bounceIn 0.6s ease forwards;
    }
    
    @keyframes bounceIn {
        0% {
            opacity: 0;
            transform: scale(0.3) translateY(50px);
        }
        50% {
            opacity: 1;
            transform: scale(1.05) translateY(-10px);
        }
        70% {
            transform: scale(0.9) translateY(0);
        }
        100% {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    .loading-indicator {
        text-align: center;
        padding: 2rem;
        color: #94a3b8;
        font-style: italic;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .stat-item {
        background: rgba(255, 255, 255, 0.05);
        padding: 1rem;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .stat-label {
        color: #94a3b8;
        font-size: 0.875rem;
    }
    
    .stat-value {
        color: #60a5fa;
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
`;

document.head.appendChild(style);
