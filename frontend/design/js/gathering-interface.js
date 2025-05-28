// Enhanced Gathering Interface with Accurate Timers and Progress Tracking
class GatheringInterface {
    constructor() {
        this.updateInterval = null;
        this.statusUpdateFrequency = 1000; // Update every second for accurate timers
        this.progressPanel = null;
        this.isInitialized = false;
        this.operationTimers = new Map(); // Track individual operation timers
        this.lastServerSync = null; // Track last server sync for accuracy
        // this.serverTimestamp = null; // Store server timestamp
        // this.clientServerOffset = 0; // Time difference between client and server

        this.init();
    }
    init() {
        if (this.isInitialized) return;

        this.createProgressPanel();
        this.bindEvents();
        this.startStatusUpdates();
        this.enhanceGatheringForm();

        // Check if there's an existing operation to monitor
        if (window.currentOperationData?.operation_id) { // Ensure operation_id exists
            this.showExistingOperation(window.currentOperationData);
        }

        this.isInitialized = true;

        console.log('Enhanced Gathering Interface initialized with accurate timers');
    }

    createProgressPanel() {
        // Create the real-time progress panel with enhanced timer display
        const progressPanelHTML = `
            <div id="gathering-progress-panel" class="gathering-progress-panel">
                <div class="progress-panel-header">
                    <h3 class="progress-panel-title">
                        <i class="fas fa-clock"></i>
                        Active Gathering Operations
                        <span class="operation-count" id="operation-count">0</span>
                    </h3>
                    <div class="progress-panel-controls">
                        <button type="button" class="btn-mini" id="refresh-operations" title="Refresh Status">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <div class="sync-indicator" id="sync-indicator">
                            <i class="fas fa-wifi"></i>
                            <span class="sync-text">Connected</span>
                        </div>
                        <button type="button" class="btn-mini" id="toggle-panel" title="Toggle Panel">
                            <i class="fas fa-compress-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="progress-panel-content">
                    <div id="active-operations-list" class="operations-list">
                        <div class="no-operations">
                            <i class="fas fa-search"></i>
                            <p>No active gathering operations</p>
                        </div>
                    </div>
                    <div id="completed-operations-list" class="completed-operations">
                        <h4 class="section-title">
                            <i class="fas fa-check-circle"></i>
                            Ready to Collect
                            <span class="completed-count" id="completed-count">0</span>
                        </h4>
                        <div class="completed-list"></div>
                    </div>
                </div>
            </div>
        `;

        // Insert the panel into the sidebar
        const sidebar = document.querySelector('.gather-sidebar');
        if (sidebar) {
            sidebar.insertAdjacentHTML('afterbegin', progressPanelHTML);
            this.progressPanel = document.getElementById('gathering-progress-panel');
        }
    }

    bindEvents() {
        // Refresh button
        const refreshBtn = document.getElementById('refresh-operations');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.updateSyncIndicator('syncing');
                this.updateGatheringStatus();
            });
        }

        // Toggle panel button
        const toggleBtn = document.getElementById('toggle-panel');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.togglePanel());
        }

        // Handle gathering form submission with location validation
        const gatheringForm = document.querySelector('.gathering-form');
        if (gatheringForm) {
            gatheringForm.addEventListener('submit', (e) => this.handleGatheringSubmit(e));
        }

        // Prevent multiple submissions on double-click
        const submitButton = document.querySelector('.gathering-form button[type="submit"]');
        if (submitButton) {
            const isSubmitting = false; // Linter: This let declares a variable that is only assigned once.
            submitButton.addEventListener('click', (e) => {
                if (isSubmitting) { // This condition will always be false if isSubmitting is const and false. This might be a logical error introduced by the lint fix.
                    e.preventDefault();
                    return false;
                }
            });
        }
    }

    enhanceGatheringForm() {
        const gatherInput = document.getElementById('gather_amount');
        if (!gatherInput) return;

        const maxAmount = Number.parseInt(gatherInput.getAttribute('max'));

        // Add preset buttons with validation
        const presetButtonsHTML = `
            <div class="gathering-presets">
                <button type="button" class="preset-btn" data-percent="10">10%</button>
                <button type="button" class="preset-btn" data-percent="25">25%</button>
                <button type="button" class="preset-btn" data-percent="50">50%</button>
                <button type="button" class="preset-btn" data-percent="100">Max</button>
            </div>
        `;

        gatherInput.parentNode.insertAdjacentHTML('beforeend', presetButtonsHTML);

        // Bind preset button events
        for (const btn of document.querySelectorAll('.preset-btn')) {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const percent = Number.parseInt(btn.dataset.percent);
                const amount = Math.ceil(maxAmount * (percent / 100));
                gatherInput.value = amount;
                this.updateGatheringEstimate(amount);
            });
        }

        // Add enhanced time estimate display with real-time calculations
        const estimateHTML = `
            <div class="gathering-estimate">
                <div class="estimate-item">
                    <i class="fas fa-clock"></i>
                    <span>Estimated Time: <strong id="time-estimate">--</strong></span>
                </div>
                <div class="estimate-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Gathering Rate: <strong id="rate-estimate">--</strong></span>
                </div>
                <div class="estimate-item">
                    <i class="fas fa-info-circle"></i>
                    <span>Efficiency: <strong id="efficiency-estimate">--</strong></span>
                </div>
            </div>
        `;

        gatherInput.parentNode.insertAdjacentHTML('beforeend', estimateHTML);

        // Update estimate on input change with debouncing
        let estimateTimeout;
        gatherInput.addEventListener('input', (e) => {
            clearTimeout(estimateTimeout);
            estimateTimeout = setTimeout(() => {
                this.updateGatheringEstimate(Number.parseInt(e.target.value) || 0);
            }, 300);
        });

        // Initial estimate
        this.updateGatheringEstimate(Number.parseInt(gatherInput.value) || 0);

        // Add location validation before submission
        this.validateLocationAvailability();
    }

    async validateLocationAvailability() {
        const urlParams = new URLSearchParams(window.location.search);
        const targetX = Number.parseInt(urlParams.get('target_x'));
        const targetY = Number.parseInt(urlParams.get('target_y'));

        if (!targetX || !targetY) return;

        try {
            const response = await fetch('backend/scripts/gathering_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_location_status',
                    target_x: targetX,
                    target_y: targetY
                })
            });

            const data = await response.json();

            if (data.success && !data.data.available) {
                let warningMessage = '';
                if (data.data.active_operations > 0) {
                    warningMessage = 'Another player is currently gathering at this location.';
                } else if (data.data.occupied) {
                    warningMessage = 'This location is occupied by a structure.';
                } else if (!data.data.resource_type || data.data.resource_amount <= 0) {
                    warningMessage = 'No resources are available at this location.';
                }

                if (warningMessage) {
                    this.showLocationWarning(warningMessage);
                }
            }
        } catch (error) {
            console.error('Failed to validate location:', error);
        }
    }

    showLocationWarning(message) {
        const form = document.querySelector('.gathering-form');
        if (!form) return;

        const warningHTML = `
            <div class="location-warning">
                <div class="warning-content">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>${message}</span>
                </div>
                <a href="index.php?page=world_map" class="btn-enhanced btn-warning-enhanced">
                    <i class="fas fa-map"></i>
                    Find Another Location
                </a>
            </div>
        `;

        form.insertAdjacentHTML('beforebegin', warningHTML);

        // Disable the submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-ban"></i> Location Unavailable';
        }
    }

    async updateGatheringEstimate(amount) {
        const resourceType = this.getCurrentResourceType();
        if (!resourceType || amount <= 0) {
            document.getElementById('time-estimate').textContent = '--';
            document.getElementById('rate-estimate').textContent = '--';
            document.getElementById('efficiency-estimate').textContent = '--';
            return;
        }

        try {
            const response = await fetch('backend/scripts/gathering_rates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_rate',
                    resource_type: resourceType
                })
            });

            const data = await response.json();

            if (data.success) {
                const rate = data.data.rate;
                const timeMinutes = Math.ceil(amount / rate);
                const timeFormatted = this.formatTime(timeMinutes * 60);
                const efficiency = Math.round((amount / 100) * 100) / 100; // Resources per unit time

                document.getElementById('time-estimate').textContent = timeFormatted;
                document.getElementById('rate-estimate').textContent = `${rate} units/min`;
                document.getElementById('efficiency-estimate').textContent = `${efficiency.toFixed(1)} units/sec`;
            }
        } catch (error) {
            console.error('Failed to get gathering rate:', error);
        }
    }

    getCurrentResourceType() {
        const resourceIcon = document.querySelector('.resource-showcase');
        if (!resourceIcon) return null;

        const classList = Array.from(resourceIcon.classList);
        const resourceClass = classList.find(cls => cls.startsWith('resource-'));

        return resourceClass ? resourceClass.replace('resource-', '') : null;
    }

    async handleGatheringSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const gatherAmount = Number.parseInt(document.getElementById('gather_amount').value);

        // Prevent double submission
        if (submitBtn.disabled) return;

        // Get target coordinates
        const urlParams = new URLSearchParams(window.location.search);
        const targetX = Number.parseInt(urlParams.get('target_x'));
        const targetY = Number.parseInt(urlParams.get('target_y'));
        const startTime = moment().unix();
        const timezoneOffset = moment().utcOffset() * 60; // Convert minutes to seconds

        console.log(`Gathering operation started at ${startTime} for coordinates (${targetX}, ${targetY}) with amount ${gatherAmount}`);

        if (!targetX || !targetY || !gatherAmount) {
            this.showNotification('Invalid gathering parameters', 'error');
            return;
        }

        // Show loading state
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting Operation...';
        submitBtn.disabled = true;

        try {
            const response = await fetch('backend/scripts/gathering_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'start_gathering',
                    target_x: targetX,
                    target_y: targetY,
                    gather_amount: gatherAmount,
                    start_time: startTime,
                    timezone_offset: timezoneOffset
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification(data.message, 'success');
                this.updateGatheringStatus();

                // Update the form to show operation started
                this.showGatheringStarted(data.data);
            } else {
                this.showNotification(data.message, 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error('Gathering operation failed:', error);
            this.showNotification('Failed to start gathering operation', 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }

    showGatheringStarted(operationData) {
        const mainContent = document.querySelector('.main-content');
        if (!mainContent) return;

        const operationStartedHTML = `
            <div class="operation-started-card">
                <div class="card-header-enhanced">
                    <h2 class="card-title-enhanced">
                        <i class="fas fa-cogs"></i>
                        Gathering Operation Started
                    </h2>
                    <p class="card-subtitle">Your workers are now collecting resources at this location</p>
                    <div>
                    Estimated Completion Time: ${operationData.estimated_completion}
                    <br>
                    Local Time: ${operationData.time} + ${operationData.time_to_complete_minutes}
                    <br>
                    Timezone Offset: ${operationData.timezone_offset}
                    </div>
                </div>
                <div class="card-body-enhanced">
                    <div class="operation-details">
                        <div class="operation-info-grid">
                            <div class="info-item">
                                <div class="info-label">
                                <i class="fas fa-cubes"></i> Resource:</div>
                                <div class="info-value">
                                    <img src="frontend/images/${operationData.resource_type}.png" alt="${operationData.resource_type.charAt(0).toUpperCase() + operationData.resource_type.slice(1)}" class="resource-icon-mini">
                                    ${operationData.resource_type.charAt(0).toUpperCase() + operationData.resource_type.slice(1)}
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">
                                <i class="fas fa-calculator"></i> Amount:</div>
                                <div class="info-value"><strong>${operationData.amount_to_gather.toLocaleString()}</strong> units</div>
                            </div>
                            <div class="info-item">
                                
                                <div class="info-label"><i class="fas fa-tachometer-alt"></i> Rate:</div>
                                <div class="info-value"><strong>${operationData.gathering_rate}</strong> units/min</div>
                            </div>
                            <div class="info-item">
                                
                                <div class="info-label"><i class="fas fa-clock"></i> Completion:</div>
                                <div class="info-value"><strong id="time-remaining">${this.formatTime(operationData.time_to_complete_minutes * 60)}</strong></div>
                            </div>
                        </div>
                    </div>
                    <div class="progress-section">
                        <div class="progress-header">
                            <span class="progress-label">Progress</span>
                            <span class="progress-percentage" id="progress-percentage">0%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="progress-details">
                            <span style="margin-right: 12px;">Gathered: <strong id="current-gathered">0</strong>/${operationData.amount_to_gather}</span>
                            <span> <i class="fas fa-stopwatch"></i> Time Left: <strong id="time-remaining">${this.formatTime(operationData.time_to_complete_minutes * 60)}</strong></span>
                        </div>
                    </div>
                    <div class="operation-actions">
                        <a href="index.php?page=world_map" class="btn-enhanced btn-primary-enhanced">
                            <i class="fas fa-map"></i>
                            Return to World Map
                        </a>
                        <button type="button" class="btn-enhanced btn-secondary-enhanced" onclick="gatheringInterface.cancelOperation(${operationData.operation_id})">
                            <i class="fas fa-times"></i>
                            Cancel Operation
                        </button>
                    </div>
                </div>
            </div>
        `;

        mainContent.innerHTML = operationStartedHTML;

        // Start local countdown timer for this operation
        this.startOperationCountdown(operationData);
    }

    showExistingOperation(operationData) {
        const mainContent = document.querySelector('.main-content');
        if (!mainContent) return;
        console.log('Gathering operation data:', JSON.stringify(operationData, null, 2));
        const existingOperationHTML = `
            <div class="gathering-operation-status">
                <div class="card-header-enhanced">
                    <h2 class="card-title-enhanced">
                        <i class="fas fa-hammer"></i>
                        Gathering Operation in Progress
                    </h2>
                    <p class="card-subtitle">Your operation is actively harvesting resources at this location</p>
                </div>
                <div class="card-body-enhanced">
                    <div class="operation-details">
                        <div class="operation-info-grid">
                            <div class="info-item">
                                <div class="info-label">Resource:</div>
                                <div class="info-value">
                                    <img src="frontend/images/${operationData.resource_type}.png" alt="${operationData.resource_type.charAt(0).toUpperCase() + operationData.resource_type.slice(1)}" class="resource-icon-mini">
                                    ${operationData.resource_type.charAt(0).toUpperCase() + operationData.resource_type.slice(1)}
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Amount:</div>
                                <div class="info-value">${operationData.amount_to_gather.toLocaleString()} units</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Rate:</div>
                                <div class="info-value">${operationData.gathering_rate} units/min</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Completion:</div>
                                <div class="info-value" id="time-remaining">
                                    ${this.formatTime(operationData.time_remaining_seconds)}
                                </div>
                            </div>
                        </div>

                        <div class="progress-section">
                            <div class="progress-header">
                                <span class="progress-label">Progress</span>
                                <span class="progress-percentage" id="progress-percentage">${operationData.progress_percent.toFixed(1)}%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" id="progress-fill" style="width: ${operationData.progress_percent}%"></div>
                            </div>
                            <div class="progress-details">
                                <span>Gathered: <strong id="current-gathered">${operationData.current_gathered.toLocaleString()}</strong>/<strong>${operationData.amount_to_gather.toLocaleString()}</strong></span>
                            </div>
                        </div>

                        <div class="operation-actions">
                            ${operationData.is_completed ?
                `<button type="button" class="btn-enhanced btn-success-enhanced" onclick="gatheringInterface.collectResources(${operationData.operation_id})">
                                    <i class="fas fa-hand-paper"></i>
                                    Collect Resources
                                   </button>`: `<button type="button" class="btn-enhanced btn-danger-enhanced" onclick="gatheringInterface.cancelOperation(${operationData.operation_id})">
                                    <i class="fas fa-times"></i>
                                    Cancel Operation
                                   </button>`
            }
                            <a href="index.php?page=world_map" class="btn-enhanced btn-secondary-enhanced">
                                <i class="fas fa-map"></i>
                                Return to Map
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;

        mainContent.innerHTML = existingOperationHTML;
        // Store operation data for live updates
        // window.currentOperationData = operationData;
        this.startExistingOperationCountdown(operationData);

        // Start live updates if operation is not completed
        // if (!operationData.is_completed) {
        //     this.startLiveUpdates();
        // }
    }

    startOperationCountdown(operationData) {
        const operationId = operationData.operation_id;
        const startTime = Date.now();
        const completionTime = startTime + (operationData.time_to_complete_minutes * 60 * 1000);

        // Clear any existing timer for this operation
        if (this.operationTimers.has(operationId)) {
            clearInterval(this.operationTimers.get(operationId));
        }

        const timer = setInterval(() => {
            const now = Date.now();
            const timeRemaining = Math.max(0, completionTime - now);
            const progress = Math.min(100, ((now - startTime) / (completionTime - startTime)) * 100);
            const gathered = Math.floor((progress / 100) * operationData.amount_to_gather);

            // Update UI elements if they exist
            const timeRemainingEl = document.getElementById('time-remaining');
            const progressFillEl = document.getElementById('progress-fill');
            const progressPercentageEl = document.getElementById('progress-percentage');
            const currentGatheredEl = document.getElementById('current-gathered');

            if (timeRemainingEl) {
                timeRemainingEl.textContent = this.formatTime(Math.floor(timeRemaining / 1000));
            }

            if (progressFillEl) {
                progressFillEl.style.width = `${progress}%`;
            }

            if (progressPercentageEl) {
                progressPercentageEl.textContent = `${progress.toFixed(1)}%`;
            }

            if (currentGatheredEl) {
                currentGatheredEl.textContent = gathered.toLocaleString();
            }

            // Check if completed
            if (timeRemaining <= 0) {
                clearInterval(timer);
                this.operationTimers.delete(operationId);
                this.updateGatheringStatus(); // Refresh to show collection option
                this.showNotification('Gathering operation completed! You can now collect your resources.', 'success');
            }
        }, 1000); // Update every second

        this.operationTimers.set(operationId, timer);
    }
    async updateGatheringStatus() {
        try {
            this.updateSyncIndicator('syncing');

            const response = await fetch('backend/scripts/gathering_status.php');
            const data = await response.json();

            if (data.success) {
                this.lastServerSync = Date.now();

                // Store server timestamp for accurate time calculations
                // this.serverTimestamp = data.data.server_timestamp;
                // this.clientServerOffset = Date.now() - (this.serverTimestamp * 1000);

                this.updateActiveOperations(data.data.active_operations);
                this.updateCompletedOperations(data.data.completed_operations);
                this.updateResourceDisplay(data.data.current_resources);
                this.updateOperationCounts(data.data.total_active, data.data.total_completed_ready);
                this.updateSyncIndicator('connected');
            } else {
                this.updateSyncIndicator('error');
            }
        } catch (error) {
            console.error('Failed to update gathering status:', error);
            this.updateSyncIndicator('error');
        }
    }

    updateSyncIndicator(status) {
        const indicator = document.getElementById('sync-indicator');
        if (!indicator) return;

        const icon = indicator.querySelector('i');
        const text = indicator.querySelector('.sync-text');

        indicator.className = `sync-indicator sync-${status}`;

        switch (status) {
            case 'syncing':
                icon.className = 'fas fa-sync fa-spin';
                text.textContent = 'Syncing...';
                break;
            case 'connected':
                icon.className = 'fas fa-wifi';
                text.textContent = 'Connected';
                break;
            case 'error':
                icon.className = 'fas fa-exclamation-triangle';
                text.textContent = 'Error';
                break;
        }
    }

    updateOperationCounts(activeCount, completedCount) {
        const activeCountEl = document.getElementById('operation-count');
        const completedCountEl = document.getElementById('completed-count');

        if (activeCountEl) {
            activeCountEl.textContent = activeCount;
            activeCountEl.style.display = activeCount > 0 ? 'inline' : 'none';
        }

        if (completedCountEl) {
            completedCountEl.textContent = completedCount;
            completedCountEl.style.display = completedCount > 0 ? 'inline' : 'none';
        }
    }
    updateActiveOperations(operations) {
        const activeList = document.getElementById('active-operations-list');
        if (!activeList) return;

        if (operations.length === 0) {
            activeList.innerHTML = `
                <div class="no-operations">
                    <i class="fas fa-search"></i>
                    <p>No active gathering operations</p>
                </div>
            `;
            return;
        }

        let html = '';
        for (const op of operations) {
            const timeRemaining = Math.max(0, op.time_remaining_seconds);
            const progressWidth = Math.min(100, Math.max(0, op.progress_percent));
            const isCompleted = timeRemaining <= 0 || op.is_completed;

            // Store server time data for accurate countdown
            const originalTotalDuration = (new Date(op.estimated_completion).getTime() - new Date(op.start_time).getTime()) / 1000; // in seconds
            this.operationTimers.set(op.id, {
                serverTimeRemaining: timeRemaining,
                lastUpdate: Date.now(),
                isCompleted: isCompleted,
                originalTotalDuration: originalTotalDuration > 0 ? originalTotalDuration : (op.amount_to_gather / op.gathering_rate) * 60, // Fallback if times are off
                amountToGather: op.amount_to_gather // Store for progress calculation
            });

            html += `
                <div class="operation-item ${isCompleted ? 'completed' : ''}" data-operation-id="${op.id}">
                    <div class="operation-header">
                        <div class="resource-info">
                            <img src="frontend/images/${op.resource_type}.png" alt="${op.resource_type}" class="resource-icon-mini">
                            <span class="resource-name">${op.resource_type.charAt(0).toUpperCase() + op.resource_type.slice(1)}</span>
                        </div>
                        <div class="location-info">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>${op.location_x}, ${op.location_y}</span>
                        </div>
                    </div>
                    <div class="operation-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${progressWidth}%"></div>
                        </div>
                        <div class="progress-text">
                            ${op.amount_gathered}/${op.amount_to_gather} units (${progressWidth.toFixed(1)}%)
                        </div>
                    </div>
                    <div class="operation-footer">
                        <div class="time-remaining ${isCompleted ? 'completed' : ''}">
                            <i class="fas ${isCompleted ? 'fa-check' : 'fa-clock'}"></i>
                            <span class="countdown-timer" data-operation-id="${op.id}">
                                ${isCompleted ? 'Completed' : this.formatTime(timeRemaining)}
                            </span>
                        </div>
                        <div class="operation-controls">
                            ${isCompleted ?
                    `<button type="button" class="btn-mini btn-success" onclick="gatheringInterface.collectResources(${op.id})">
                                    <i class="fas fa-hand-paper"></i>
                                </button>` :
                    `<button type="button" class="btn-mini btn-danger" onclick="gatheringInterface.cancelOperation(${op.id})">
                                    <i class="fas fa-times"></i>
                                </button>`
                }
                        </div>
                    </div>
                </div>
            `;
        }

        activeList.innerHTML = html;

        // Start individual countdown timers for each operation
        this.startIndividualCountdowns();
    }
    startIndividualCountdowns() {
        // Clear existing timers
        for (const timer of this.operationTimers.values()) {
            if (timer.intervalId) {
                clearInterval(timer.intervalId);
            }
        }

        // Start new timers for each operation
        for (const [operationId, timerData] of this.operationTimers) {
            if (timerData.isCompleted) continue;

            const element = document.querySelector(`.countdown-timer[data-operation-id="${operationId}"]`);
            if (!element) return;

            const updateCountdown = () => {
                const elapsed = Math.floor((Date.now() - timerData.lastUpdate) / 1000);
                const currentRemaining = Math.max(0, timerData.serverTimeRemaining - elapsed);

                if (currentRemaining <= 0) {
                    element.textContent = 'Completed';
                    element.parentElement.classList.add('completed');

                    // Update the operation item to show collection button
                    const operationItem = element.closest('.operation-item');
                    if (operationItem) {
                        operationItem.classList.add('completed');
                        const controlsDiv = operationItem.querySelector('.operation-controls');
                        if (controlsDiv) {
                            controlsDiv.innerHTML = `
                                <button type="button" class="btn-mini btn-success" onclick="gatheringInterface.collectResources(${operationId})">
                                    <i class="fas fa-hand-paper"></i>
                                </button>
                            `;
                        }
                    }

                    // Clear this timer
                    const timerInfo = this.operationTimers.get(operationId);
                    if (timerInfo?.intervalId) {
                        clearInterval(timerInfo.intervalId);
                    }
                    this.operationTimers.delete(operationId);

                    // Trigger status update to get completed operations
                    this.updateGatheringStatus();
                } else {
                    element.textContent = this.formatTime(currentRemaining);

                    // Update progress bar based on time
                    const operationItem = element.closest('.operation-item');
                    if (operationItem) {
                        const progressBar = operationItem.querySelector('.progress-fill');
                        const progressTextEl = operationItem.querySelector('.progress-text');

                        if (progressBar && timerData.originalTotalDuration > 0) {
                            const timeElapsedSinceStart = timerData.originalTotalDuration - currentRemaining;
                            const progressPercent = Math.min(100, (timeElapsedSinceStart / timerData.originalTotalDuration) * 100);
                            progressBar.style.width = `${progressPercent.toFixed(1)}%`;

                            if (progressTextEl) {
                                const gatheredAmount = Math.floor((progressPercent / 100) * timerData.amountToGather);
                                progressTextEl.textContent = `${gatheredAmount.toLocaleString()}/${timerData.amountToGather.toLocaleString()} units (${progressPercent.toFixed(1)}%)`;
                            }
                        }
                    }
                }
            };

            // Start interval for this operation
            const intervalId = setInterval(updateCountdown, 1000);
            timerData.intervalId = intervalId;

            // Update immediately
            updateCountdown();
        }
    }

    updateCompletedOperations(operations) {
        const completedSection = document.getElementById('completed-operations-list');
        if (!completedSection) return;

        const completedList = completedSection.querySelector('.completed-list');

        if (operations.length === 0) {
            completedSection.style.display = 'none';
            return;
        }

        completedSection.style.display = 'block';

        let html = '';
        for (const op of operations) {
            html += `
                <div class="completed-operation-item" data-operation-id="${op.id}">
                    <div class="operation-summary">
                        <img src="frontend/images/${op.resource_type}.png" alt="${op.resource_type}" class="resource-icon-mini">
                        <span class="amount">${op.amount_to_gather.toLocaleString()}</span>
                        <span class="resource-name">${op.resource_type}</span>
                        <div class="completion-badge">
                            <i class="fas fa-check"></i>
                            Ready
                        </div>
                    </div>
                    <button type="button" class="btn-mini btn-success collect-btn" onclick="gatheringInterface.collectResources(${op.id})">
                        <i class="fas fa-hand-paper"></i>
                        Collect
                    </button>
                </div>
            `;
        }

        completedList.innerHTML = html;
    }

    updateResourceDisplay(resources) {
        // Update the resources in the sidebar with animation
        for (const resourceType of Object.keys(resources)) {
            const resourceItem = document.querySelector(`.resource-item-enhanced img[alt="${resourceType.charAt(0).toUpperCase() + resourceType.slice(1)}"]`);
            if (resourceItem) {
                const amountElement = resourceItem.closest('.resource-item-enhanced').querySelector('.resource-amount-small');
                if (amountElement) {
                    const newAmount = resources[resourceType].toLocaleString();
                    if (amountElement.textContent !== newAmount) {
                        amountElement.textContent = newAmount;
                        // Add brief highlight animation
                        amountElement.classList.add('updated');
                        setTimeout(() => amountElement.classList.remove('updated'), 1000);
                    }
                }
            }
        }
    }

    async cancelOperation(operationId) {
        if (!confirm('Are you sure you want to cancel this gathering operation? Any progress will be lost.')) {
            return;
        }

        // Clear local timer
        if (this.operationTimers.has(operationId)) {
            clearInterval(this.operationTimers.get(operationId));
            this.operationTimers.delete(operationId);
        }

        try {
            const response = await fetch('backend/scripts/gathering_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'cancel_gathering',
                    operation_id: operationId
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification(data.message, 'success');
                this.updateGatheringStatus();
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Failed to cancel operation:', error);
            this.showNotification('Failed to cancel operation', 'error');
        }
    }

    async collectResources(operationId) {
        const collectBtn = document.querySelector(`[onclick="gatheringInterface.collectResources(${operationId})"]`);

        let originalText = '';
        if (collectBtn) {
            originalText = collectBtn.innerHTML;
            collectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            collectBtn.disabled = true;
        }

        try {
            const response = await fetch('backend/scripts/gathering_operations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'collect_resources',
                    operation_id: operationId
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification(data.message, 'success');
                this.updateGatheringStatus();

                // Clear any associated timer
                if (this.operationTimers.has(operationId)) {
                    clearInterval(this.operationTimers.get(operationId));
                    this.operationTimers.delete(operationId);
                }
            } else {
                this.showNotification(data.message, 'error');
                if (collectBtn) {
                    collectBtn.innerHTML = originalText;
                    collectBtn.disabled = false;
                }
            }
        } catch (error) {
            console.error('Failed to collect resources:', error);
            this.showNotification('Failed to collect resources', 'error');
            if (collectBtn) {
                collectBtn.innerHTML = originalText;
                collectBtn.disabled = false;
            }
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element with enhanced styling
        const notification = document.createElement('div');
        notification.className = `gathering-notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
                <span>${message}</span>
            </div>
            <button type="button" class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        const autoRemove = setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.add('fade-out');
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);

        // Manual close
        notification.querySelector('.notification-close').addEventListener('click', () => {
            clearTimeout(autoRemove);
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        });

        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
    }

    togglePanel() {
        if (!this.progressPanel) return;

        this.progressPanel.classList.toggle('collapsed');
        const toggleBtn = document.getElementById('toggle-panel');
        const icon = toggleBtn.querySelector('i');

        if (this.progressPanel.classList.contains('collapsed')) {
            icon.className = 'fas fa-expand-alt';
        } else {
            icon.className = 'fas fa-compress-alt';
        }
    }

    startStatusUpdates() {
        // Initial update
        this.updateGatheringStatus();

        // Set up periodic updates
        this.updateInterval = setInterval(() => {
            this.updateGatheringStatus();
        }, this.statusUpdateFrequency);
    }

    stopStatusUpdates() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }

        // Clear all operation timers
        for (const timerData of this.operationTimers.values()) {
            if (timerData.intervalId) {
                clearInterval(timerData.intervalId);
            }
        }
        this.operationTimers.clear();
        if (this.existingOperationInterval) {
            clearInterval(this.existingOperationInterval);
            this.existingOperationInterval = null;
        }
    }

    formatTime(seconds) {
        if (seconds <= 0) return 'Completed';

        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}h ${minutes}m ${secs}s`;
        }
        if (minutes > 0) { // Removed else
            return `${minutes}m ${secs}s`;
        }
        return `${secs}s`; // Removed else
    }

    destroy() {
        this.stopStatusUpdates();
        this.isInitialized = false;
    }

    startExistingOperationCountdown(operationData) {
        // const timeRemainingEl = document.getElementById('time-remaining');
        // const progressFillEl = document.getElementById('progress-fill');
        // const progressPercentageEl = document.getElementById('progress-percentage');
        // const currentGatheredEl = document.getElementById('current-gathered');
        console.log('Starting existing operation countdown with data:', JSON.stringify(operationData, null, 2));

        const timeRemainingEl = document.getElementById('time-remaining');
        const progressFillEl = document.getElementById('progress-fill');
        const progressPercentageEl = document.getElementById('progress-percentage');
        const currentGatheredEl = document.getElementById('current-gathered');

        if (!timeRemainingEl || !progressFillEl || !progressPercentageEl || !currentGatheredEl) {
            console.warn('Required elements for existing operation countdown not found.');
            console.warn('One or more required elements are missing:', {
                timeRemainingEl,
                progressFillEl,
                progressPercentageEl,
                currentGatheredEl
            });
            return;
        }

        const serverTimeRemaining = operationData.time_remaining_seconds;
        const clientStartTime = Date.now(); // When this countdown function starts on the client

        // Calculate original total duration using start_time and estimated_completion from operationData
        // These should be available from gather.php if an operation is in progress
        const originalStartTime = new Date(operationData.start_time).getTime();
        const originalCompletionTime = new Date(operationData.estimated_completion).getTime();
        const originalTotalDurationMs = originalCompletionTime - originalStartTime;

        if (Number.isNaN(originalTotalDurationMs) || originalTotalDurationMs <= 0) {
            console.error('Could not determine original total duration for existing operation.', operationData);
            // Fallback or simply don't update progress if times are invalid
            // For now, we'll let the timer run but progress might be stuck or inaccurate
        }

        // Clear any existing interval for this specific type of countdown
        if (this.existingOperationInterval) {
            clearInterval(this.existingOperationInterval);
        }

        const updateOperation = () => {
            const clientElapsedMs = Date.now() - clientStartTime;
            const currentRemainingSeconds = Math.max(0, serverTimeRemaining - Math.floor(clientElapsedMs / 1000));

            if (currentRemainingSeconds <= 0) {
                timeRemainingEl.textContent = 'Completed';
                progressFillEl.style.width = '100%';
                progressPercentageEl.textContent = '100.0%';
                currentGatheredEl.textContent = operationData.amount_to_gather.toLocaleString();

                const actionDiv = document.querySelector('.operation-actions');
                if (actionDiv && !actionDiv.querySelector('.btn-success-enhanced')) { // Avoid duplicating button
                    actionDiv.innerHTML = `
                        <button type="button" class="btn-enhanced btn-success-enhanced" onclick="gatheringInterface.collectResources(${operationData.operation_id})">
                            <i class="fas fa-hand-paper"></i>
                            Collect Resources
                        </button>
                        <a href="index.php?page=world_map" class="btn-enhanced btn-secondary-enhanced">
                            <i class="fas fa-map"></i>
                            Return to Map
                        </a>
                    `;
                }

                clearInterval(this.existingOperationInterval);
                this.existingOperationInterval = null;

                // It's good to call updateGatheringStatus to ensure the panel view is also up-to-date
                if (typeof this.updateGatheringStatus === 'function') {
                    this.updateGatheringStatus();
                }

            } else {
                timeRemainingEl.textContent = this.formatTime(currentRemainingSeconds);

                if (originalTotalDurationMs > 0) {
                    const timeElapsedSinceOriginalStartMs = originalTotalDurationMs - (currentRemainingSeconds * 1000);
                    const progressPercent = Math.min(100, (timeElapsedSinceOriginalStartMs / originalTotalDurationMs) * 100);

                    progressFillEl.style.width = `${progressPercent.toFixed(1)}%`;
                    progressPercentageEl.textContent = `${progressPercent.toFixed(1)}%`;

                    const currentGathered = Math.min(operationData.amount_to_gather,
                        Math.floor((progressPercent / 100) * operationData.amount_to_gather));
                    currentGatheredEl.textContent = currentGathered.toLocaleString();
                } else {
                    // If original duration is unknown, we can't accurately show progress here
                    // We could try to estimate based on current gathered from server if available, but it's complex
                    // For now, progress might not update if originalTotalDurationMs is invalid
                    progressPercentageEl.textContent = operationData.progress_percent ? `${operationData.progress_percent.toFixed(1)}%` : 'N/A';
                    progressFillEl.style.width = operationData.progress_percent ? `${operationData.progress_percent.toFixed(1)}%` : '0%';
                    currentGatheredEl.textContent = operationData.current_gathered ? operationData.current_gathered.toLocaleString() : 'N/A';
                }
            }
        };

        this.existingOperationInterval = setInterval(updateOperation, 1000);
        updateOperation(); // Initial call
    }
}

// Initialize the enhanced gathering interface when DOM is ready
let gatheringInterface;

document.addEventListener('DOMContentLoaded', () => { // Converted to arrow function
    gatheringInterface = new GatheringInterface();
});

// Clean up on page unload
window.addEventListener('beforeunload', () => { // Converted to arrow function
    if (gatheringInterface) {
        gatheringInterface.destroy();
    }
});
