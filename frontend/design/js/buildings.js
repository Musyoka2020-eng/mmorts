/**
 * Buildings System JavaScript
 * Handles all building management functionality
 */

class BuildingSystem {
    constructor() {
        this.currentTab = 'my-buildings';
        this.buildings = [];
        this.availableBuildings = {};
        this.constructionQueue = [];
        this.updateIntervals = [];
        this.init();
    }
    
    /**
     * Initialize the building system
     */
    init() {
        this.setupEventListeners();
        this.loadBuildingData();
        this.startQueueUpdates();
        console.log('🏭 Building System initialized');
    }
    
    /**
     * Set up event listeners for UI interactions
     */
    setupEventListeners() {
        // Tab switching
        document.querySelectorAll('.building-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                this.switchTab(e.target.dataset.tab);
            });
            
            // Keyboard navigation
            tab.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.switchTab(e.target.dataset.tab);
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey) {
                switch(e.key) {
                    case '1':
                        e.preventDefault();
                        this.switchTab('my-buildings');
                        break;
                    case '2':
                        e.preventDefault();
                        this.switchTab('resource');
                        break;
                    case '3':
                        e.preventDefault();
                        this.switchTab('military');
                        break;
                    case '4':
                        e.preventDefault();
                        this.switchTab('defense');
                        break;
                }
            }
        });
    }
    
    /**
     * Switch between building tabs
     * @param {string} tabName - Name of tab to switch to
     */
    switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.building-tab').forEach(tab => {
            tab.classList.remove('active');
            tab.setAttribute('aria-selected', 'false');
        });
        
        const activeTab = document.querySelector(`[data-tab="${tabName}"]`);
        if (activeTab) {
            activeTab.classList.add('active');
            activeTab.setAttribute('aria-selected', 'true');
        }
        
        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        const activeContent = document.getElementById(tabName);
        if (activeContent) {
            activeContent.classList.add('active');
        }
        
        this.currentTab = tabName;
        
        // Load data for the tab
        if (tabName === 'my-buildings') {
            this.loadExistingBuildings();
        } else {
            this.loadAvailableBuildings(tabName);
        }
    }
    
    /**
     * Load all building data
     */
    async loadBuildingData() {
        try {
            await this.loadExistingBuildings();
            await this.loadConstructionQueue();
        } catch (error) {
            console.error('Error loading building data:', error);
            this.showMessage('Failed to load building data', 'error');
        }
    }
    
    /**
     * Load existing buildings in player's city
     */
    async loadExistingBuildings() {
        try {
            const response = await fetch('backend/scripts/building_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_city_buildings'
            });
            
            const data = await response.json();
            if (data.success) {
                this.buildings = data.buildings;
                this.constructionQueue = data.construction_queue;
                this.renderExistingBuildings();
                this.renderConstructionQueue();
            } else {
                this.showMessage(data.message || 'Failed to load buildings', 'error');
            }
        } catch (error) {
            console.error('Error loading buildings:', error);
            this.showMessage('Network error loading buildings', 'error');
        }
    }
    
    /**
     * Load available buildings for construction
     * @param {string} category - Building category
     */
    async loadAvailableBuildings(category) {
        if (this.availableBuildings[category]) {
            this.renderAvailableBuildings(category);
            return;
        }
        
        try {
            this.setLoadingState(`${category}-buildings-grid`, true);
            
            const response = await fetch(`backend/scripts/building_api.php?action=get_available_buildings&category=${category}`);
            const data = await response.json();
            
            if (data.success) {
                this.availableBuildings[category] = data.buildings;
                this.renderAvailableBuildings(category);
            } else {
                this.showMessage(data.message || 'Failed to load available buildings', 'error');
            }
        } catch (error) {
            console.error('Error loading available buildings:', error);
            this.showMessage('Network error loading buildings', 'error');
        } finally {
            this.setLoadingState(`${category}-buildings-grid`, false);
        }
    }
    
    /**
     * Load construction queue
     */
    async loadConstructionQueue() {
        try {
            const response = await fetch('backend/scripts/building_api.php?action=get_construction_queue');
            const data = await response.json();
            
            if (data.success) {
                this.constructionQueue = data.queue;
                this.renderConstructionQueue();
            }
        } catch (error) {
            console.error('Error loading construction queue:', error);
        }
    }
    
    /**
     * Render existing buildings in city
     */
    renderExistingBuildings() {
        const container = document.getElementById('existing-buildings-grid');
        
        if (this.buildings.length === 0) {
            container.innerHTML = '<div class="empty-state">No buildings constructed yet</div>';
            return;
        }
        
        container.innerHTML = this.buildings.map(building => `
            <div class="building-card" 
                 onclick="buildingSystem.showBuildingDetails(${building.id})"
                 tabindex="0"
                 role="button"
                 aria-label="View details for ${building.display_name}">
                <div class="building-icon">${building.icon}</div>
                <div class="building-name">${building.display_name}</div>
                <div class="building-level">Level ${building.level}/${building.max_level}</div>
                <div class="building-description">${building.description}</div>
                ${building.level < building.max_level ? 
                    `<button class="btn-upgrade" 
                             onclick="event.stopPropagation(); buildingSystem.upgradeBuilding(${building.id})"
                             aria-label="Upgrade ${building.display_name}">
                        Upgrade
                    </button>` : 
                    '<span style="color: #ffd700;" aria-label="Building at maximum level">Max Level</span>'
                }
            </div>
        `).join('');
    }
    
    /**
     * Render available buildings for construction
     * @param {string} category - Building category
     */
    renderAvailableBuildings(category) {
        const container = document.getElementById(`${category}-buildings-grid`);
        const buildings = this.availableBuildings[category] || [];
        
        if (buildings.length === 0) {
            container.innerHTML = '<div class="empty-state">No buildings available in this category</div>';
            return;
        }
        
        container.innerHTML = buildings.map(building => {
            const disabled = !building.prerequisites_met;
            const costs = Object.entries(building.costs || {})
                .filter(([_, cost]) => cost > 0)
                .map(([resource, cost]) => 
                    `<span class="cost-item">
                        <span class="resource-icon">${this.getResourceIcon(resource)}</span>${cost}
                    </span>`
                ).join('');
            
            const action = building.existing ? 'upgrade' : 'construct';
            const buttonText = building.existing ? `Upgrade to Lv${building.target_level}` : 'Construct';
            const timeText = this.formatTime(building.construction_time || 0);
            const actionId = building.existing?.id || building.id;
            
            return `
                <div class="building-card ${disabled ? 'disabled' : ''}"
                     ${!disabled ? `onclick="buildingSystem.${action}Building(${actionId})"` : ''}
                     tabindex="${disabled ? -1 : 0}"
                     role="button"
                     aria-label="${disabled ? building.prerequisite_reason : `${buttonText} ${building.display_name}`}">
                    <div class="building-icon">${building.icon}</div>
                    <div class="building-name">${building.display_name}</div>
                    ${building.existing ? 
                        `<div class="building-level">Level ${building.existing.level} → ${building.target_level}</div>` :
                        `<div class="building-level">New Building</div>`
                    }
                    <div class="building-description">${building.description}</div>
                    <div class="building-costs">${costs}</div>
                    <div style="color: #ffd700; font-size: 0.8rem; margin-top: 5px;">⏰ ${timeText}</div>
                    ${disabled ? 
                        `<div style="color: #ff6b6b; font-size: 0.8rem; margin-top: 5px;">${building.prerequisite_reason}</div>` :
                        `<button class="btn-construct" 
                                 onclick="event.stopPropagation(); buildingSystem.${action}Building(${actionId})"
                                 aria-label="${buttonText} ${building.display_name}">${buttonText}</button>`
                    }
                </div>
            `;
        }).join('');
    }
    
    /**
     * Render construction queue
     */
    renderConstructionQueue() {
        const container = document.getElementById('construction-queue-container');
        const noConstruction = document.getElementById('no-construction');
        
        if (this.constructionQueue.length === 0) {
            if (noConstruction) noConstruction.style.display = 'block';
            container.innerHTML = '';
            return;
        }
        
        if (noConstruction) noConstruction.style.display = 'none';
        
        container.innerHTML = this.constructionQueue.map(item => `
            <div class="queue-item" role="listitem" aria-label="Construction: ${item.display_name}">
                <div class="queue-info">
                    <div class="building-icon" style="font-size: 1.5rem;">${item.icon}</div>
                    <div>
                        <div style="color: #66bbff; font-weight: bold;">${item.display_name}</div>
                        <div style="color: #ffd700; font-size: 0.9rem;">
                            ${item.queue_type === 'build' ? 'Building' : 'Upgrading to'} Level ${item.target_level}
                        </div>
                    </div>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${item.progress_percent}">
                        <div class="progress-fill" style="width: ${item.progress_percent}%"></div>
                    </div>
                    <div class="time-remaining" id="time-${item.id}">
                        ${item.remaining_time > 0 ? this.formatTime(item.remaining_time) : 'Completing...'}
                    </div>
                </div>
                <button class="btn-cancel" 
                        onclick="buildingSystem.cancelConstruction(${item.id})"
                        aria-label="Cancel construction of ${item.display_name}">
                    Cancel
                </button>
            </div>
        `).join('');
    }
    
    /**
     * Start building construction
     * @param {number} buildingTypeId - Building type ID
     */
    async constructBuilding(buildingTypeId) {
        if (!confirm('Start construction of this building?')) {
            return;
        }
        
        try {
            this.setButtonLoading(event.target, true);
            
            const response = await fetch('backend/scripts/building_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=start_construction&building_type_id=${buildingTypeId}&position_x=0&position_y=0`
            });
            
            const data = await response.json();
            this.showMessage(data.message, data.success ? 'success' : 'error');
            
            if (data.success) {
                await this.loadBuildingData();
                // Clear cached available buildings to refresh costs/availability
                this.availableBuildings = {};
                
                // Switch to construction queue to show progress
                if (this.currentTab !== 'my-buildings') {
                    document.getElementById('construction-queue-section').scrollIntoView({behavior: 'smooth'});
                }
            }
        } catch (error) {
            console.error('Error constructing building:', error);
            this.showMessage('Error starting construction', 'error');
        } finally {
            this.setButtonLoading(event.target, false);
        }
    }
    
    /**
     * Start building upgrade
     * @param {number} cityBuildingId - City building ID
     */
    async upgradeBuilding(cityBuildingId) {
        if (!confirm('Start upgrade of this building?')) {
            return;
        }
        
        try {
            this.setButtonLoading(event.target, true);
            
            const response = await fetch('backend/scripts/building_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=start_upgrade&city_building_id=${cityBuildingId}`
            });
            
            const data = await response.json();
            this.showMessage(data.message, data.success ? 'success' : 'error');
            
            if (data.success) {
                await this.loadBuildingData();
                this.availableBuildings = {};
            }
        } catch (error) {
            console.error('Error upgrading building:', error);
            this.showMessage('Error starting upgrade', 'error');
        } finally {
            this.setButtonLoading(event.target, false);
        }
    }
    
    /**
     * Cancel construction
     * @param {number} queueId - Queue item ID
     */
    async cancelConstruction(queueId) {
        if (!confirm('Are you sure you want to cancel this construction? You will receive a partial refund.')) {
            return;
        }
        
        try {
            const response = await fetch('backend/scripts/building_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=cancel_construction&queue_id=${queueId}&refund=true`
            });
            
            const data = await response.json();
            this.showMessage(data.message, data.success ? 'success' : 'error');
            
            if (data.success) {
                await this.loadBuildingData();
            }
        } catch (error) {
            console.error('Error cancelling construction:', error);
            this.showMessage('Error cancelling construction', 'error');
        }
    }
    
    /**
     * Show building details in modal
     * @param {number} buildingId - Building ID
     */
    async showBuildingDetails(buildingId) {
        try {
            const response = await fetch(`backend/scripts/building_api.php?action=get_building_details&building_id=${buildingId}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayBuildingModal(data.building, data.current_effects, data.next_level_effects);
            } else {
                this.showMessage(data.message, 'error');
            }
        } catch (error) {
            console.error('Error loading building details:', error);
            this.showMessage('Error loading building details', 'error');
        }
    }
    
    /**
     * Display building details in modal
     * @param {Object} building - Building data
     * @param {Array} currentEffects - Current level effects
     * @param {Array} nextLevelEffects - Next level effects
     */
    displayBuildingModal(building, currentEffects, nextLevelEffects) {
        const modal = document.getElementById('buildingModal');
        const title = document.getElementById('buildingModalTitle');
        const body = document.getElementById('buildingModalBody');
        
        if (!modal || !title || !body) {
            console.error('Modal elements not found');
            return;
        }
        
        title.innerHTML = `${building.icon} ${building.display_name}`;
        
        const currentEffectsHtml = currentEffects.length > 0 ? 
            '<div class="effects-list"><h6 style="color: #66bbff;">Current Effects:</h6>' +
            currentEffects.map(effect => 
                `<div class="effect-item">
                    <span>+${effect.effect_value} ${effect.effect_target} ${effect.effect_type}</span>
                </div>`
            ).join('') +
            '</div>' : '<p style="color: #ccc;">No special effects at this level</p>';
        
        const nextLevelHtml = building.level < building.max_level && nextLevelEffects.length > 0 ?
            '<div class="effects-list"><h6 style="color: #ffd700;">Next Level Effects:</h6>' +
            nextLevelEffects.map(effect => 
                `<div class="effect-item">
                    <span>+${effect.effect_value} ${effect.effect_target} ${effect.effect_type}</span>
                </div>`
            ).join('') +
            '</div>' : '';
        
        const upgradeCostHtml = building.upgrade_cost ? 
            '<h6 style="color: #ffd700; margin-top: 20px;">Upgrade Cost:</h6>' +
            '<div style="display: flex; gap: 10px; flex-wrap: wrap;">' +
            Object.entries(building.upgrade_cost)
                .filter(([_, cost]) => cost > 0)
                .map(([resource, cost]) => 
                    `<span class="cost-item">${this.getResourceIcon(resource)} ${cost}</span>`
                ).join('') +
            '</div>' +
            `<p style="color: #ccc; margin-top: 10px;">⏰ Upgrade time: ${this.formatTime(building.upgrade_time)}</p>` : '';
        
        body.innerHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="font-size: 3rem; margin-bottom: 10px;">${building.icon}</div>
                <h4 style="color: #66bbff;">${building.display_name}</h4>
                <p style="color: #ffd700;">Level ${building.level}/${building.max_level}</p>
                <p style="color: #ccc;">${building.description}</p>
            </div>
            
            ${currentEffectsHtml}
            ${nextLevelHtml}
            ${upgradeCostHtml}
            
            ${building.level < building.max_level ? 
                `<div class="text-center mt-3">
                    <button class="btn-upgrade" 
                            onclick="buildingSystem.upgradeBuilding(${building.id}); bootstrap.Modal.getInstance(document.getElementById('buildingModal')).hide();"
                            aria-label="Upgrade ${building.display_name}">
                        Upgrade Building
                    </button>
                </div>` : 
                '<div class="text-center mt-3"><span style="color: #ffd700;">Building at maximum level</span></div>'
            }
        `;
        
        // Show modal using Bootstrap
        if (typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(modal).show();
        }
    }
    
    /**
     * Start queue update intervals
     */
    startQueueUpdates() {
        // Clear existing intervals
        this.updateIntervals.forEach(interval => clearInterval(interval));
        this.updateIntervals = [];
        
        // Update construction queue every 30 seconds
        const queueInterval = setInterval(() => {
            if (this.constructionQueue.length > 0) {
                this.loadConstructionQueue();
            }
        }, 30000);
        this.updateIntervals.push(queueInterval);
        
        // Update progress bars every second
        const progressInterval = setInterval(() => {
            this.updateQueueProgress();
        }, 1000);
        this.updateIntervals.push(progressInterval);
    }
    
    /**
     * Update construction queue progress bars
     */
    updateQueueProgress() {
        this.constructionQueue.forEach((item, index) => {
            const currentTime = Math.floor(Date.now() / 1000);
            const remainingTime = Math.max(0, item.completed_timestamp - currentTime);
            const progress = Math.min(100, ((currentTime - item.started_timestamp) / (item.completed_timestamp - item.started_timestamp)) * 100);
            
            const progressBar = document.querySelectorAll('.progress-fill')[index];
            const timeDisplay = document.getElementById(`time-${item.id}`);
            
            if (progressBar) {
                progressBar.style.width = Math.round(progress) + '%';
                progressBar.parentElement.setAttribute('aria-valuenow', Math.round(progress));
            }
            
            if (timeDisplay) {
                timeDisplay.textContent = remainingTime > 0 ? this.formatTime(remainingTime) : 'Completing...';
            }
            
            // Update the queue item data
            this.constructionQueue[index].remaining_time = remainingTime;
            this.constructionQueue[index].progress_percent = progress;
        });
    }
    
    /**
     * Set loading state for elements
     * @param {string} elementId - Element ID
     * @param {boolean} loading - Loading state
     */
    setLoadingState(elementId, loading) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        if (loading) {
            element.classList.add('loading');
            element.innerHTML = '<div class="empty-state">Loading...</div>';
        } else {
            element.classList.remove('loading');
        }
    }
    
    /**
     * Set button loading state
     * @param {Element} button - Button element
     * @param {boolean} loading - Loading state
     */
    setButtonLoading(button, loading) {
        if (!button) return;
        
        if (loading) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = 'Loading...';
            button.classList.add('loading');
        } else {
            button.disabled = false;
            button.textContent = button.dataset.originalText || button.textContent;
            button.classList.remove('loading');
        }
    }
    
    /**
     * Get resource icon
     * @param {string} resource - Resource name
     * @returns {string} Resource icon
     */
    getResourceIcon(resource) {
        const icons = {
            wood: '🪵',
            iron: '⚙️',
            stone: '🗿',
            food: '🍞',
            oil: '🛢️'
        };
        return icons[resource] || '❓';
    }
    
    /**
     * Format time in seconds to readable format
     * @param {number} seconds - Time in seconds
     * @returns {string} Formatted time
     */
    formatTime(seconds) {
        if (seconds < 60) return `${seconds}s`;
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }
    
    /**
     * Show message notification
     * @param {string} message - Message text
     * @param {string} type - Message type (success, error, info)
     */
    showMessage(message, type = 'info') {
        // Remove existing toasts
        document.querySelectorAll('.toast-notification').forEach(toast => toast.remove());
        
        // Create new toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} position-fixed toast-notification`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; opacity: 0; transform: translateX(100%); transition: all 0.3s ease;';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        
        // Icon based on type
        const icons = {
            success: '✅',
            error: '❌',
            info: 'ℹ️'
        };
        
        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <span class="me-2" style="font-size: 1.2rem;">${icons[type] || icons.info}</span>
                <span class="flex-grow-1">${message}</span>
                <button type="button" class="btn-close ms-2" onclick="this.parentElement.parentElement.remove()" aria-label="Close notification"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }
    
    /**
     * Cleanup intervals when page unloads
     */
    destroy() {
        this.updateIntervals.forEach(interval => clearInterval(interval));
        this.updateIntervals = [];
    }
}

// Initialize building system when page loads
let buildingSystem;
document.addEventListener('DOMContentLoaded', () => {
    buildingSystem = new BuildingSystem();
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        if (buildingSystem) {
            buildingSystem.destroy();
        }
    });
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BuildingSystem;
}
