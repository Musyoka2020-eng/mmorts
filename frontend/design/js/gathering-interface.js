// NEW Simplified Gathering Interface - Pure rendering, minimal calculations
class NewGatheringInterface {
    constructor() {
        this.updateInterval = null;
        this.updateFrequency = 5000; // Update every 5 seconds instead of 2
        this.coordinates = null;
        this.navigationDebounce = null; // Add debounce for navigation
        this.init();
    }

    // COMMANDER UTILITY FUNCTIONS
    formatCommanderTime(seconds) {
        if (seconds <= 0) return "Ready for deployment, Commander!";
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        let timeStr = "";
        if (hours > 0) {
            timeStr += `${hours} hour${hours !== 1 ? 's' : ''} `;
        }
        if (minutes > 0) {
            timeStr += `${minutes} minute${minutes !== 1 ? 's' : ''} `;
        }
        if (secs > 0 || (hours === 0 && minutes === 0)) {
            timeStr += `${secs} second${secs !== 1 ? 's' : ''}`;
        }
        
        return timeStr.trim();
    }

    getCommanderMessage(type, data = {}) {
        const messages = {
            // Navigation messages
            navigate: `Roger that, Commander! Coordinates set for sector (${data.x}, ${data.y}). Moving into position...`,
            
            // Collection messages
            collect_success: `Excellent work, Commander! Your forces have secured ${data.amount || 0} units of ${data.resource || 'resources'}. Resources added to your war chest!`,
            collect_ready: `Commander, our ${data.resource || 'resource'} harvesting operation is complete. ${data.amount || 0} units ready for collection!`,
            
            // Operation management
            operation_started: `Operation ${(data.resource || 'RESOURCE').toUpperCase()} is now active, Commander! ETA: ${this.formatCommanderTime(data.duration || 0)}`,
            operation_cancelled: (data.collected || 0) > 0 ? 
                `Operation terminated as ordered, Commander! Salvaged ${data.collected} units of ${data.resource || 'resources'} (${data.progress || 0}% complete).` :
                `Operation cancelled, Commander. No resources were extracted.`,
            
            // Progress updates
            operation_progress: `Commander, ${data.resource || 'resource'} extraction is ${data.progress || 0}% complete. Estimated completion: ${this.formatCommanderTime(data.remaining || 0)}`,
            
            // Error messages
            error_general: `Commander, we've encountered a tactical issue: ${data.message}`,
            error_invalid_coords: `Commander, those coordinates are outside our operational zone. Please select a valid sector.`,
            error_no_resources: `Commander, this sector appears to be depleted. No viable resources detected.`,
            
            // Loading
            loading: `Scanning sector coordinates, Commander...`
        };
        
        return messages[type] || `Command acknowledged, Commander.`;
    }

    showCommanderNotification(type, data = {}, alertType = 'info', useToast = false) {
        const message = this.getCommanderMessage(type, data);
        
        // Use toast for simple notifications
        if (useToast) {
            this.showToastNotification(message, alertType);
            return;
        }
        
        // Use modal for important notifications
        const alertConfig = {
            title: 'Command Center',
            text: message,
            icon: alertType,
            confirmButtonText: 'Acknowledged',
            customClass: {
                popup: 'commander-alert',
                title: 'commander-alert-title',
                content: 'commander-alert-content',
                confirmButton: 'commander-alert-button'
            }
        };

        // Add specific styling for different alert types
        switch(alertType) {
            case 'success':
                alertConfig.icon = 'success';
                alertConfig.iconColor = '#27ae60';
                break;
            case 'warning':
                alertConfig.icon = 'warning';
                alertConfig.iconColor = '#f39c12';
                break;
            case 'error':
                alertConfig.icon = 'error';
                alertConfig.iconColor = '#e74c3c';
                break;
            default:
                alertConfig.icon = 'info';
                alertConfig.iconColor = '#3498db';
        }

        Swal.fire(alertConfig);
    }

    /**
     * Show a simple toast notification that doesn't require user interaction
     */
    showToastNotification(message, type = 'info') {
        // Use Sweet Alert's toast feature
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            customClass: {
                popup: 'commander-toast',
                title: 'commander-toast-title'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        const iconMap = {
            'success': 'success',
            'error': 'error', 
            'warning': 'warning',
            'info': 'info'
        };

        Toast.fire({
            icon: iconMap[type] || 'info',
            title: message
        });
    }

    init() {
        // Get coordinates from URL
        const urlParams = new URLSearchParams(window.location.search);
        this.coordinates = {
            x: parseInt(urlParams.get('target_x')),
            y: parseInt(urlParams.get('target_y'))
        };

        if (!this.coordinates.x || !this.coordinates.y) {
            this.showCommanderNotification('error_invalid_coords', {}, 'error');
            return;
        }

        // Load initial data and start updates
        this.loadPageData();
        this.startAutoUpdates();
        
        console.log('New Gathering Interface initialized for coordinates:', this.coordinates);
    }

    async loadPageData() {
        try {
            this.showLoading();
            
            const response = await fetch('backend/scripts/gathering_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_page_data',
                    target_x: this.coordinates.x,
                    target_y: this.coordinates.y
                })
            });

            const data = await response.json();

            if (data.success) {
                this.renderPage(data.data);
            } else {
                this.showCommanderNotification('error_general', { message: data.message }, 'error');
            }
        } catch (error) {
            this.showCommanderNotification('error_general', { message: 'Failed to load gathering data' }, 'error');
            console.error('Load error:', error);
        }
    }

    renderPage(data) {
        this.renderHeader(data.location);
        this.renderMainContent(data.location, data.current_operation);
        this.renderSidebar(data.all_operations, data.player_resources);
    }

    renderHeader(location) {
        const headerHTML = `
            <div class="gathering-header">
                <h1 class="gathering-title">
                    <i class="fas fa-hammer"></i>
                    Resource Extraction Operations
                </h1>
                <p class="gathering-subtitle">Deploy forces to secure strategic resources for the empire</p>
                <div class="gathering-location-badge">
                    <div class="gathering-location-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <span>Sector: ${location.x}, ${location.y}</span>
                </div>
            </div>
        `;
        
        document.querySelector('.gathering-container').innerHTML = headerHTML + '<div class="gathering-grid"><div class="gathering-briefing"></div><div class="main-content"></div><div class="gathering-sidebar"></div></div>';
    }

    renderMainContent(location, operation) {
        // Render the command briefing first
        this.renderCommandBriefing();
        
        const mainContent = document.querySelector('.main-content');
        
        if (!location.is_valid) {
            mainContent.innerHTML = this.getInvalidLocationHTML(location);
            return;
        }

        if (operation) {
            if (operation.can_collect) {
                mainContent.innerHTML = this.getCollectionHTML(operation);
            } else {
                mainContent.innerHTML = this.getProgressHTML(operation, location);
            }
        } else {
            mainContent.innerHTML = this.getStartGatheringHTML(location);
        }
    }

    getInvalidLocationHTML(location) {
        return `
            <div class="gathering-resource-discovery-card">
                <div class="gathering-card-header-enhanced">
                    <h2 class="gathering-card-title-enhanced">
                        <i class="fas fa-exclamation-circle"></i>
                        Location Unavailable
                    </h2>
                    <p class="gathering-card-subtitle">This location cannot be used for gathering</p>
                </div>
                <div class="gathering-card-body-enhanced">
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-map-marked-alt" style="font-size: 4rem; color: rgba(255, 255, 255, 0.3); margin-bottom: 1rem;"></i>
                        <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 2rem;">
                            ${location.error || 'This location may be occupied, depleted, or invalid.'}
                        </p>
                        <a href="index.php?page=world_map" class="gathering-btn-enhanced gathering-btn-primary-enhanced">
                            <i class="fas fa-map"></i>
                            Return to World Map
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    getStartGatheringHTML(location) {
        return `
            <div class="gathering-resource-discovery-card">
                <div class="gathering-card-header-enhanced">
                    <h2 class="gathering-card-title-enhanced">
                        <i class="fas fa-gem"></i>
                        Resource Discovery
                    </h2>
                    <p class="gathering-card-subtitle">Valuable resources have been located at this site</p>
                </div>
                <div class="gathering-card-body-enhanced">
                    <div class="gathering-resource-showcase resource-${location.resource_type}">
                        <div class="gathering-resource-main-info">
                            <div class="gathering-resource-icon-large">
                                <img src="frontend/images/${location.resource_type}.png" alt="${location.resource_type}">
                            </div>
                            <div class="gathering-resource-details-main">
                                <h3 class="gathering-resource-name-large">${location.resource_type.charAt(0).toUpperCase() + location.resource_type.slice(1)}</h3>
                                <div class="gathering-resource-amount-large">
                                    <i class="fas fa-cubes"></i>
                                    ${location.resource_amount.toLocaleString()} units available
                                </div>
                            </div>
                        </div>
                    </div>

                    <form class="gathering-form" onsubmit="gatheringInterface.startGathering(event)">
                        <div class="gathering-form-group-enhanced">
                            <label for="gather_amount" class="gathering-form-label-enhanced">
                                <i class="fas fa-sliders-h"></i>
                                Gathering Amount
                            </label>
                            <input type="number"
                                class="gathering-form-input-enhanced"
                                id="gather_amount"
                                name="gather_amount"
                                min="1"
                                max="${location.resource_amount}"
                                value="${Math.min(location.resource_amount, Math.ceil(location.resource_amount * 0.2))}"
                                placeholder="Enter amount to gather">
                            <div class="gathering-form-help-text">
                                <i class="fas fa-info-circle"></i>
                                Maximum available: ${location.resource_amount.toLocaleString()} units
                            </div>
                        </div>

                        <div class="gathering-form-actions">
                            <button type="submit" class="gathering-btn-enhanced gathering-btn-primary-enhanced">
                                <i class="fas fa-hammer"></i>
                                Start Gathering Operation
                            </button>
                            <a href="index.php?page=world_map" class="gathering-btn-enhanced gathering-btn-secondary-enhanced">
                                <i class="fas fa-times"></i>
                                Cancel & Return
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }

    getProgressHTML(operation, location) {
        return `
            <div class="gathering-resource-discovery-card">
                <div class="gathering-card-header-enhanced">
                    <h2 class="gathering-card-title-enhanced">
                        <i class="fas fa-cogs"></i>
                        Gathering In Progress
                    </h2>
                    <p class="gathering-card-subtitle">Your gathering operation is underway</p>
                </div>
                <div class="gathering-card-body-enhanced">
                    <div class="gathering-operation-progress">
                        <div class="gathering-resource-showcase resource-${operation.resource_type}">
                            <div class="gathering-resource-main-info">
                                <div class="gathering-resource-icon-large">
                                    <img src="frontend/images/${operation.resource_type}.png" alt="${operation.resource_type}">
                                </div>
                                <div class="gathering-resource-details-main">
                                    <h3 class="gathering-resource-name-large">${operation.resource_type.charAt(0).toUpperCase() + operation.resource_type.slice(1)}</h3>
                                    <div class="gathering-resource-amount-large">
                                        <i class="fas fa-hammer"></i>
                                        Gathering ${operation.amount_to_gather.toLocaleString()} units
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="gathering-progress-details">
                            <div class="gathering-progress-bar-container">
                                <div class="gathering-progress-bar">
                                    <div class="gathering-progress-fill" id="main-progress-fill" style="width: ${operation.progress_percent}%"></div>
                                </div>
                                <div class="gathering-progress-text">
                                    <span id="main-progress-text">${operation.progress_percent}%</span>
                                    <span id="main-time-remaining">${operation.time_remaining_formatted}</span>
                                </div>
                            </div>

                            <div class="gathering-operation-stats">
                                <div class="gathering-stat-item">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>Rate: ${operation.gathering_rate} units/min</span>
                                </div>
                                <div class="gathering-stat-item">
                                    <i class="fas fa-clock"></i>
                                    <span>Started: ${new Date(operation.start_time).toLocaleTimeString()}</span>
                                </div>
                            </div>
                        </div>

                        <div class="gathering-form-actions">
                            <button type="button" class="gathering-btn-enhanced gathering-btn-danger-enhanced" onclick="gatheringInterface.cancelOperation(${operation.id})">
                                <i class="fas fa-times"></i>
                                Cancel Operation
                            </button>
                            <a href="index.php?page=world_map" class="gathering-btn-enhanced gathering-btn-secondary-enhanced">
                                <i class="fas fa-map"></i>
                                Return to Map
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    getCollectionHTML(operation) {
        return `
            <div class="gathering-resource-discovery-card">
                <div class="gathering-card-header-enhanced">
                    <h2 class="gathering-card-title-enhanced">
                        <i class="fas fa-check-circle"></i>
                        Gathering Complete!
                    </h2>
                    <p class="gathering-card-subtitle">Your gathering operation has finished successfully</p>
                </div>
                <div class="gathering-card-body-enhanced">
                    <div class="gathering-completion-summary">
                        <div class="gathering-resource-showcase resource-${operation.resource_type}">
                            <div class="gathering-resource-main-info">
                                <div class="gathering-resource-icon-large">
                                    <img src="frontend/images/${operation.resource_type}.png" alt="${operation.resource_type}">
                                </div>
                                <div class="gathering-resource-details-main">
                                    <h3 class="gathering-resource-name-large">${operation.resource_type.charAt(0).toUpperCase() + operation.resource_type.slice(1)}</h3>
                                    <div class="gathering-resource-amount-large">
                                        <i class="fas fa-check"></i>
                                        ${operation.amount_gathered.toLocaleString()} units ready
                                    </div>
                                    <div class="gathering-completion-badge">
                                        <i class="fas fa-trophy"></i>
                                        Operation Completed Successfully
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="gathering-form-actions" style="margin-top: 2rem;">
                            <button type="button" class="gathering-btn-enhanced gathering-btn-success-enhanced" onclick="gatheringInterface.collectResources(${operation.id})">
                                <i class="fas fa-hand-paper"></i>
                                Collect Resources
                            </button>
                            <a href="index.php?page=world_map" class="gathering-btn-enhanced gathering-btn-secondary-enhanced">
                                <i class="fas fa-map"></i>
                                Return to Map
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderSidebar(operations, resources) {
        const sidebar = document.querySelector('.gathering-sidebar');
        
        sidebar.innerHTML = `
            ${this.getOperationsListHTML(operations)}
        `;
    }

    getOperationsListHTML(operations) {
        const activeOps = operations.active || [];
        const completedOps = operations.completed || [];
        
        return `
            <div class="gathering-progress-panel">
                <div class="gathering-progress-panel-header">
                    <h3 class="gathering-progress-panel-title">
                        <i class="fas fa-clock"></i>
                        Your Operations
                    </h3>
                </div>
                <div class="gathering-progress-panel-content">
                    ${activeOps.length > 0 ? `
                        <div class="gathering-operations-section">
                            <h4 class="gathering-section-title">
                                <i class="fas fa-cogs"></i>
                                Active Operations (${activeOps.length})
                            </h4>
                            <div class="gathering-operations-list">
                                ${activeOps.map(op => this.getOperationItemHTML(op, 'active')).join('')}
                            </div>
                        </div>
                    ` : ''}
                    
                    ${completedOps.length > 0 ? `
                        <div class="gathering-operations-section">
                            <h4 class="gathering-section-title">
                                <i class="fas fa-check-circle"></i>
                                Ready to Collect (${completedOps.length})
                            </h4>
                            <div class="gathering-operations-list">
                                ${completedOps.map(op => this.getOperationItemHTML(op, 'completed')).join('')}
                            </div>
                        </div>
                    ` : ''}
                    
                    ${activeOps.length === 0 && completedOps.length === 0 ? `
                        <div class="gathering-no-operations">
                            <i class="fas fa-search"></i>
                            <p>No gathering operations</p>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    getOperationItemHTML(operation, type) {
        const isCurrentLocation = operation.location_x === this.coordinates.x && operation.location_y === this.coordinates.y;
        
        return `
            <div class="gathering-operation-item ${isCurrentLocation ? 'current-location' : ''}" data-operation-id="${operation.id}">
                <div class="gathering-operation-summary" ${!isCurrentLocation ? `onclick="gatheringInterface.navigateToOperation(${operation.location_x}, ${operation.location_y})" style="cursor: pointer;"` : ''}>
                    <img src="frontend/images/${operation.resource_type}.png" alt="${operation.resource_type}" class="gathering-resource-icon-mini">
                    <div class="gathering-operation-details">
                        <div class="gathering-operation-amount">${operation.amount_to_gather.toLocaleString()} ${operation.resource_type}</div>
                        <div class="gathering-operation-location">
                            ${isCurrentLocation ? 
                                '<i class="fas fa-map-marker-alt"></i> Current Location' : 
                                `<i class="fas fa-external-link-alt"></i> Location: ${operation.location_x}, ${operation.location_y}`
                            }
                        </div>
                        ${type === 'active' ? `
                            <div class="gathering-operation-progress">
                                <div class="gathering-mini-progress-bar">
                                    <div class="gathering-mini-progress-fill" style="width: ${operation.progress_percent}%"></div>
                                </div>
                                <span class="gathering-operation-time" id="op-time-${operation.id}">${operation.time_remaining_formatted}</span>
                            </div>
                        ` : `
                            <div class="gathering-completion-badge">
                                <i class="fas fa-check"></i>
                                Ready
                            </div>
                        `}
                    </div>
                </div>
                <div class="gathering-operation-actions">
                    ${type === 'active' ? `
                        <button type="button" class="gathering-btn-mini gathering-btn-danger" onclick="gatheringInterface.cancelOperation(${operation.id})" title="Cancel Operation">
                            <i class="fas fa-times"></i>
                        </button>
                        ${!isCurrentLocation ? `
                            <button type="button" class="gathering-btn-mini gathering-btn-secondary" onclick="gatheringInterface.navigateToOperation(${operation.location_x}, ${operation.location_y})" title="Go to Location">
                                <i class="fas fa-map-marker-alt"></i>
                            </button>
                        ` : ''}
                    ` : `
                        <button type="button" class="gathering-btn-mini gathering-btn-success" onclick="gatheringInterface.collectResources(${operation.id})" title="Collect Resources">
                            <i class="fas fa-hand-paper"></i>
                            Collect
                        </button>
                        ${!isCurrentLocation ? `
                            <button type="button" class="gathering-btn-mini gathering-btn-secondary" onclick="gatheringInterface.navigateToOperation(${operation.location_x}, ${operation.location_y})" title="Go to Location">
                                <i class="fas fa-map-marker-alt"></i>
                            </button>
                        ` : ''}
                    `}
                </div>
            </div>
        `;
    }

    getResourcesHTML(resources) {
        return `
            <div class="gathering-current-resources-panel">
                <div class="gathering-card-header-enhanced">
                    <h3 class="gathering-card-title-enhanced">
                        <i class="fas fa-warehouse"></i>
                        Your Resources
                    </h3>
                </div>
                <div class="resources-list">
                    ${Object.entries(resources).map(([type, amount]) => `
                        <div class="gathering-resource-item-enhanced">
                            <div class="gathering-resource-icon-small">
                                <img src="frontend/images/${type}.png" alt="${type}">
                            </div>
                            <div class="gathering-resource-info-small">
                                <div class="gathering-resource-name-small">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
                                <div class="gathering-resource-amount-small" id="resource-${type}">${amount.toLocaleString()}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    getTipsHTML() {
        return `
            <div class="gathering-tips-panel">
                <div class="gathering-card-header-enhanced">
                    <h3 class="gathering-card-title-enhanced">
                        <i class="fas fa-star"></i>
                        Command Briefing
                    </h3>
                </div>
                <div class="gathering-tips-list">
                    <div class="gathering-tip-item">
                        <div class="gathering-tip-icon">1</div>
                        <span>Commander, scout various terrain sectors for strategic resource nodes.</span>
                    </div>
                    <div class="gathering-tip-item">
                        <div class="gathering-tip-icon">2</div>
                        <span>Resources regenerate naturally - tactical patience yields results.</span>
                    </div>
                    <div class="gathering-tip-item">
                        <div class="gathering-tip-icon">3</div>
                        <span>Deploy forces based on current construction objectives.</span>
                    </div>
                </div>
            </div>
        `;
    }

    renderCommandBriefing() {
        const briefingContainer = document.querySelector('.gathering-briefing');
        briefingContainer.innerHTML = this.getTipsHTML();
    }

    // ACTION METHODS
    async startGathering(event) {
        event.preventDefault();
        
        const amount = parseInt(document.getElementById('gather_amount').value);
        if (!amount || amount <= 0) {
            this.showCommanderNotification('error_general', { message: 'Please specify a valid resource amount, Commander' }, 'error');
            return;
        }

        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
        submitBtn.disabled = true;

        try {
            const response = await fetch('backend/scripts/gathering_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'start_gathering',
                    target_x: this.coordinates.x,
                    target_y: this.coordinates.y,
                    gather_amount: amount
                })
            });

            const data = await response.json();

            if (data.success) {
                // Extract operation details for commander message
                const operationData = {
                    resource: data.data?.resource_type || 'resources',
                    duration: data.data?.duration_seconds || 0
                };
                this.showCommanderNotification('operation_started', operationData, 'success', true); // Use toast for operation started
                this.loadPageData(); // Refresh page
            } else {
                this.showCommanderNotification('error_general', { message: data.message }, 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            this.showCommanderNotification('error_general', { message: 'Failed to start gathering operation' }, 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }

    async collectResources(operationId) {
        const collectBtn = document.querySelector(`[onclick="gatheringInterface.collectResources(${operationId})"]`);
        if (collectBtn) {
            const originalText = collectBtn.innerHTML;
            collectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            collectBtn.disabled = true;
        }

        try {
            const response = await fetch('backend/scripts/gathering_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'collect_resources',
                    operation_id: operationId
                })
            });

            const data = await response.json();

            if (data.success) {
                // Extract collection details for commander message
                const collectionData = {
                    resource: data.data?.resources_collected?.type || 'resources',
                    amount: data.data?.resources_collected?.amount || 0
                };
                this.showCommanderNotification('collect_success', collectionData, 'success', true); // Use toast for collection
                this.loadPageData(); // Refresh page
                this.refreshGlobalResources(); // Update resource bar
            } else {
                this.showCommanderNotification('error_general', { message: data.message }, 'error');
                if (collectBtn) {
                    collectBtn.innerHTML = originalText;
                    collectBtn.disabled = false;
                }
            }
        } catch (error) {
            this.showCommanderNotification('error_general', { message: 'Failed to collect resources' }, 'error');
            if (collectBtn) {
                collectBtn.innerHTML = originalText;
                collectBtn.disabled = false;
            }
        }
    }

    async cancelOperation(operationId) {
        // Use Sweet Alert for confirmation
        const result = await Swal.fire({
            title: 'Confirm Operation Termination',
            text: 'Commander, are you certain you wish to abort this operation? Any progress made will be salvaged and added to your resources.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Terminate Operation',
            cancelButtonText: 'Continue Operation',
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#95a5a6',
            customClass: {
                popup: 'commander-alert',
                title: 'commander-alert-title',
                content: 'commander-alert-content'
            }
        });

        if (!result.isConfirmed) {
            return;
        }

        try {
            const response = await fetch('backend/scripts/gathering_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'cancel_gathering',
                    operation_id: operationId
                })
            });

            const data = await response.json();

            if (data.success) {
                // Show detailed message about partial collection
                if (data.data.resources_collected && data.data.resources_collected.amount > 0) {
                    const { type, amount, progress_percent } = data.data.resources_collected;
                    const cancelData = {
                        resource: type,
                        collected: amount,
                        progress: progress_percent
                    };
                    this.showCommanderNotification('operation_cancelled', cancelData, 'warning', true); // Use toast for cancellation
                } else {
                    const cancelData = {
                        resource: 'operation',
                        collected: 0,
                        progress: 0
                    };
                    this.showCommanderNotification('operation_cancelled', cancelData, 'info', true); // Use toast for cancellation
                }
                this.loadPageData(); // Refresh page
            } else {
                this.showCommanderNotification('error_general', { message: data.message }, 'error');
            }
        } catch (error) {
            this.showCommanderNotification('error_general', { message: 'Failed to cancel operation' }, 'error');
        }
    }

    navigateToOperation(x, y) {
        // Clear any existing navigation debounce
        if (this.navigationDebounce) {
            clearTimeout(this.navigationDebounce);
        }
        
        // Debounce navigation to prevent rapid calls
        this.navigationDebounce = setTimeout(() => {
            console.log(`Navigating from (${this.coordinates.x}, ${this.coordinates.y}) to (${x}, ${y})`);
            
            // Check if we're already at this location
            if (this.coordinates.x === x && this.coordinates.y === y) {
                console.log('Already at target location');
                return;
            }
            
            // Update the gather interface coordinates and reload the page data
            this.coordinates.x = x;
            this.coordinates.y = y;
            
            // Update the URL parameters
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('target_x', x);
            currentUrl.searchParams.set('target_y', y);
            
            // Update browser history without page reload
            window.history.pushState({}, '', currentUrl.toString());
            
            // Show navigation notification
            this.showCommanderNotification('navigate', { x, y }, 'info', true); // Use toast for navigation
            
            // Reload the gather interface data for the new location
            this.loadPageData();
        }, 300); // 300ms debounce
    }

    // UPDATE METHODS
    startAutoUpdates() {
        this.updateInterval = setInterval(() => {
            this.updateOperationProgress();
        }, 5000); // Update every 5 seconds to match backend increment
    }

    async updateOperationProgress() {
        try {
            // First call update_progress to increment counters
            const updateResponse = await fetch('backend/scripts/gathering_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_progress'
                })
            });

            const updateData = await updateResponse.json();

            if (updateData.success) {
                // Then get current page data to refresh the display
                const response = await fetch('backend/scripts/gathering_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'get_page_data',
                        target_x: this.coordinates.x,
                        target_y: this.coordinates.y
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.updateProgressDisplays(data.data);
                }
            } else {
                console.error('Failed to update progress:', updateData.message);
            }
        } catch (error) {
            console.error('Update error:', error);
        }
    }

    updateProgressDisplays(data) {
        // Update main progress if showing
        const mainProgressFill = document.getElementById('main-progress-fill');
        const mainProgressText = document.getElementById('main-progress-text');
        const mainTimeRemaining = document.getElementById('main-time-remaining');

        if (data.current_operation && mainProgressFill) {
            const op = data.current_operation;
            mainProgressFill.style.width = `${op.progress_percent}%`;
            if (mainProgressText) mainProgressText.textContent = `${op.progress_percent}%`;
            
            // If operation completed, refresh page
            if (op.can_collect) {
                this.loadPageData();
                return;
            }
        }

        // Update sidebar operation timers AND main display timer using the SAME data source
        const allOps = [...(data.all_operations.active || []), ...(data.all_operations.completed || [])];
        allOps.forEach(op => {
            const timeElement = document.getElementById(`op-time-${op.id}`);
            if (timeElement) {
                timeElement.textContent = op.time_remaining_formatted;
            }
            
            // If this is the current operation, also update the main display with the same time
            if (data.current_operation && op.id === data.current_operation.id && mainTimeRemaining) {
                mainTimeRemaining.textContent = op.time_remaining_formatted;
            }
        });
    }

    // UTILITY METHODS
    showLoading() {
        document.querySelector('.gathering-container').innerHTML = `
            <div style="text-align: center; padding: 4rem;">
                <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: #60a5fa;"></i>
                <p style="color: white; margin-top: 1rem;">Loading gathering data...</p>
            </div>
        `;
    }

    showError(message) {
        document.querySelector('.gathering-container').innerHTML = `
            <div style="text-align: center; padding: 4rem;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ef4444;"></i>
                <p style="color: white; margin: 1rem 0;">${message}</p>
                <a href="index.php?page=world_map" class="gathering-btn-enhanced gathering-btn-primary-enhanced">
                    <i class="fas fa-map"></i>
                    Return to World Map
                </a>
            </div>
        `;
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `gathering-notification gathering-notification-${type}`;

        let iconClass = 'fa-info-circle';
        switch(type) {
            case 'success': iconClass = 'fa-check-circle'; break;
            case 'error': iconClass = 'fa-exclamation-triangle'; break;
            case 'warning': iconClass = 'fa-exclamation-circle'; break;
        }
        
        notification.innerHTML = `
            <div class="gathering-notification-content">
                <i class="fas ${iconClass}"></i>
                <span>${message}</span>
            </div>
            <button type="button" class="gathering-notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    /**
     * Refresh the global resource bar after collection
     */
    async refreshGlobalResources() {
        try {
            // Create a simple API call to get current resources
            const response = await fetch('backend/scripts/update_resources.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_resources' })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.resources) {
                    // Update each resource display
                    this.updateResourceDisplay('wood', data.resources.wood);
                    this.updateResourceDisplay('stone', data.resources.stone);
                    this.updateResourceDisplay('iron', data.resources.iron);
                    this.updateResourceDisplay('food', data.resources.food);
                    this.updateResourceDisplay('oil', data.resources.oil);
                }
            }
        } catch (error) {
            console.log('Could not refresh global resources:', error);
            // Silently fail - not critical
        }
    }

    /**
     * Update a specific resource display element
     */
    updateResourceDisplay(resourceType, newValue) {
        // Look for resource elements in the global resource bar
        const resourceElement = document.querySelector(`[data-resource="${resourceType}"] .resource-value`);
        if (resourceElement) {
            // Update the displayed value
            resourceElement.textContent = new Intl.NumberFormat().format(newValue);
            resourceElement.setAttribute('data-value', newValue);
            
            // Add animation to show the update
            resourceElement.classList.add('increasing');
            setTimeout(() => {
                resourceElement.classList.remove('increasing');
            }, 1000);
        }
    }

    cleanup() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    window.gatheringInterface = new NewGatheringInterface();
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.gatheringInterface) {
        window.gatheringInterface.cleanup();
    }
});
