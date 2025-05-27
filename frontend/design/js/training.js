/**
 * Training System - Professional & User-Friendly Interface
 * Manages unit training, resource calculation, and user interactions
 */

class TrainingSystem {
    constructor() {
        this.selectedUnits = {};
        this.totalCost = {
            wood: 0,
            iron: 0,
            food: 0,
            oil: 0,
            stone: 0
        };
        this.maxAffordable = {};
        this.activeTab = 'basic';
        this.resources = {};
        this.unitCosts = {};
        this.unitStats = {};
        this.unitDisplay = {};
        this.unitCategories = {};

        // Animation and feedback systems
        this.isCalculating = false;
        this.updateTimeout = null;
        this.feedbackQueue = [];

        this.init();
    }    /**
     * Initialize the training system
     */
    init() {
        console.log('Initializing Training System...');
        console.log('Window gameConfig:', window.gameConfig);
        console.log('Available window properties:', Object.keys(window).filter(key => key.includes('unit') || key.includes('game') || key.includes('config')));
        
        // Wait for configuration to load if it's not ready yet
        if (typeof window.gameConfig === 'undefined' && typeof window.unitCosts === 'undefined') {
            console.log('Configuration not ready, waiting...');
            let attempts = 0;
            const checkConfig = () => {
                attempts++;
                if (typeof window.gameConfig !== 'undefined' || typeof window.unitCosts !== 'undefined') {
                    console.log(`Configuration loaded after ${attempts} attempts`);
                    this.loadConfiguration();
                    this.continueInitialization();
                } else if (attempts < 10) {
                    setTimeout(checkConfig, 100);
                } else {
                    console.error('Configuration failed to load after 10 attempts');
                    this.showConfigurationError();
                }
            };
            setTimeout(checkConfig, 100);
            return;
        }
        
        this.loadConfiguration();
        this.continueInitialization();
    }

    /**
     * Load game configuration from window object
     */
    loadConfiguration() {
        // Load game configuration with multiple fallback options
        if (typeof window.gameConfig !== 'undefined') {
            this.unitCosts = window.gameConfig.unitCosts || {};
            this.unitStats = window.gameConfig.unitStats || {};
            this.unitDisplay = window.gameConfig.unitDisplay || {};
            this.unitCategories = window.gameConfig.unitCategories || {};
            console.log('Loaded config from window.gameConfig');
        } else if (typeof window.unitCosts !== 'undefined') {
            // Legacy fallback
            this.unitCosts = window.unitCosts || {};
            this.unitStats = window.unitStats || {};
            this.unitDisplay = window.unitDisplay || {};
            this.unitCategories = window.unitCategories || {};
            console.log('Loaded config from legacy window variables');
        } else {
            console.error('No game configuration found! Please ensure game_config.js.php is loaded.');
            this.showConfigurationError();
            return false;
        }

        console.log('Loaded configuration:');
        console.log('- Unit costs:', Object.keys(this.unitCosts));
        console.log('- Unit stats:', Object.keys(this.unitStats));
        console.log('- Unit display:', Object.keys(this.unitDisplay));
        console.log('- Unit categories:', Object.keys(this.unitCategories));
        
        return true;
    }

    /**
     * Continue initialization after configuration is loaded
     */
    continueInitialization() {
        this.loadCurrentResources();
        this.bindEvents();
        this.initializeTabs();
        this.calculateMaxAffordable();
        this.showWelcomeMessage();

        console.log('Training System initialized successfully');
    }

    /**
     * Load current player resources from the DOM
     */
    loadCurrentResources() {
        // FIXED: Updated selectors to match the actual DOM structure
        const resourceElements = {
            wood: document.getElementById('training-wood_available') || document.querySelector('[data-resource="wood"] .resource-value'),
            iron: document.getElementById('training-iron_available') || document.querySelector('[data-resource="iron"] .resource-value'),
            food: document.getElementById('training-food_available') || document.querySelector('[data-resource="food"] .resource-value'),
            oil: document.getElementById('training-oil_available') || document.querySelector('[data-resource="oil"] .resource-value'),
            stone: document.getElementById('training-stone_available') || document.querySelector('[data-resource="stone"] .resource-value')
        };

        for (const [resource, element] of Object.entries(resourceElements)) {
            if (element) {
                const value = element.textContent.replace(/[,\s]/g, '');
                this.resources[resource] = Number.parseInt(value) || 0;
            } else {
                console.warn(`Resource element not found for: ${resource}`);
                this.resources[resource] = 0;
            }
        }

        console.log('Loaded resources:', this.resources);
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Tab switching
        const unitTabs = document.querySelectorAll('.units-tab');
        for (const tab of unitTabs) {
            tab.addEventListener('click', (e) => this.switchTab(e));
        }

        // Quantity controls
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('quantity-btn')) {
                this.handleQuantityButton(e);
            }
            if (e.target.closest('.train-btn[data-action="max"]')) {
                this.setMaxQuantity(e);
            }
        });

        // Input changes
        document.addEventListener('input', (e) => {
            if (e.target.classList.contains('quantity-input')) {
                this.handleQuantityInput(e);
            }
        });

        // Form submission
        const form = document.getElementById('training-form');
        if (form) {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));

        // Auto-save functionality
        setInterval(() => this.autoSave(), 30000); // Auto-save every 30 seconds
    }

    /**
     * Initialize tab system
     */
    initializeTabs() {
        const firstTab = document.querySelector('.units-tab');
        if (firstTab) {
            this.activeTab = firstTab.dataset.tab;
            this.showTab(this.activeTab);
        }
    }

    /**
     * Switch between unit category tabs
     */
    switchTab(e) {
        e.preventDefault();
        const targetTab = e.target.dataset.tab;

        if (targetTab === this.activeTab) return;

        // Add transition effects
        const currentContent = document.querySelector('.unit-tab-content.active');
        if (currentContent) {
            currentContent.style.opacity = '0';
            currentContent.style.transform = 'translateX(-20px)';

            setTimeout(() => {
                currentContent.classList.remove('active');
                this.showTab(targetTab);
            }, 150);
        } else {
            this.showTab(targetTab);
        }

        // Update tab states
        const unitTabs = document.querySelectorAll('.units-tab');
        for (const tab of unitTabs) {
            tab.classList.remove('active');
        }
        e.target.classList.add('active');

        this.activeTab = targetTab;
        this.addFeedback(`Switched to ${e.target.textContent} units`, 'info');
    }

    /**
     * Show specific tab content
     */
    showTab(tabName) {
        const targetContent = document.getElementById(`${tabName}-tab`);
        if (targetContent) {
            targetContent.classList.add('active');
            targetContent.style.opacity = '1';
            targetContent.style.transform = 'translateX(0)';

            // Animate unit cards entrance
            const unitCards = targetContent.querySelectorAll('.unit-card');
            let index = 0;
            for (const card of unitCards) {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.3s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
                index++;
            }
        }
    }

    /**
     * Handle quantity button clicks (+ and -)
     */
    handleQuantityButton(e) {
        e.preventDefault();
        const button = e.target;
        const action = button.dataset.action;
        const selector = button.closest('.quantity-selector');
        const input = selector.querySelector('.quantity-input');
        const unitType = this.extractUnitType(input.name);

        const currentValue = Number.parseInt(input.value) || 0;
        let newValue = currentValue;

        if (action === 'increase') {
            const maxAffordable = this.maxAffordable[unitType] || 0;
            const remaining = maxAffordable - (this.selectedUnits[unitType] || 0);
            newValue = Math.min(currentValue + 1, remaining + currentValue);
        } else if (action === 'decrease') {
            newValue = Math.max(0, currentValue - 1);
        }

        if (newValue !== currentValue) {
            input.value = newValue;
            this.updateUnitSelection(unitType, newValue);
            this.animateButton(button);
        }
    }

    /**
     * Handle direct input changes
     */
    handleQuantityInput(e) {
        const input = e.target;
        const unitType = this.extractUnitType(input.name);
        let value = Number.parseInt(input.value) || 0;

        // Validate against available resources
        const maxAffordable = this.maxAffordable[unitType] || 0;
        if (value > maxAffordable) {
            value = maxAffordable;
            input.value = value;
            this.addFeedback(`Cannot afford more than ${maxAffordable} ${unitType}`, 'warning');
        }

        // Validate against negative values
        if (value < 0) {
            value = 0;
            input.value = value;
        }

        this.updateUnitSelection(unitType, value);
    }

    /**
     * Set maximum affordable quantity for a unit
     */
    setMaxQuantity(e) {
        e.preventDefault();
        const button = e.target.closest('.train-btn[data-action="max"]');
        const unitCard = button.closest('.unit-card');
        const unitType = unitCard.dataset.unit;
        const input = unitCard.querySelector('.quantity-input');

        const maxAffordable = this.maxAffordable[unitType] || 0;

        if (maxAffordable > 0) {
            input.value = maxAffordable;
            this.updateUnitSelection(unitType, maxAffordable);
            this.animateButton(button);
            this.addFeedback(`Set maximum ${unitType}: ${maxAffordable}`, 'success');
        } else {
            this.addFeedback(`Cannot afford any ${unitType}`, 'warning');
        }
    }

    /**
     * Update unit selection and recalculate costs
     */
    updateUnitSelection(unitType, quantity) {
        this.selectedUnits[unitType] = quantity;

        // Clear existing timeout to debounce calculations
        if (this.updateTimeout) {
            clearTimeout(this.updateTimeout);
        }

        // Debounce expensive calculations
        this.updateTimeout = setTimeout(() => {
            this.calculateTotalCost();
            this.updateResourceDisplay();
            this.updateUnitCardVisuals(unitType, quantity);
            this.validateFormState();
        }, 100);
    }

    /**
     * Calculate total resource cost for all selected units
     */
    calculateTotalCost() {
        if (this.isCalculating) return;
        this.isCalculating = true;

        // Reset costs
        for (const resource in this.totalCost) {
            this.totalCost[resource] = 0;
        }

        // Calculate costs for each selected unit
        for (const [unitType, quantity] of Object.entries(this.selectedUnits)) {
            if (quantity > 0 && this.unitCosts[unitType]) {
                for (const [resource, cost] of Object.entries(this.unitCosts[unitType])) {
                    this.totalCost[resource] += cost * quantity;
                }
            }
        }
        this.isCalculating = false;
    }

    /**
     * Calculate maximum affordable units for each type
     */
    calculateMaxAffordable() {
        for (const [unitType, costs] of Object.entries(this.unitCosts)) {
            let maxAffordable = Number.POSITIVE_INFINITY;

            for (const [resource, cost] of Object.entries(costs)) {
                if (cost > 0) {
                    const availableForResource = Math.floor(this.resources[resource] / cost);
                    maxAffordable = Math.min(maxAffordable, availableForResource);
                }
            }

            this.maxAffordable[unitType] = maxAffordable === Number.POSITIVE_INFINITY ? 0 : Math.max(0, maxAffordable);
        }

        console.log('Max affordable units:', this.maxAffordable);
    }

    /**
     * Update resource display with remaining amounts
     */
    updateResourceDisplay() {
        for (const [resource, totalCost] of Object.entries(this.totalCost)) {
            const element = document.getElementById(`training-${resource}_available`);
            if (element) {
                const remaining = this.resources[resource] - totalCost;
                const originalValue = this.resources[resource];

                // Update display
                element.textContent = this.formatNumber(Math.max(0, remaining));

                // Add visual feedback based on resource status
                element.classList.remove('training-resource-low', 'training-resource-critical', 'training-resource-updated');

                if (remaining < 0) {
                    element.classList.add('training-resource-critical');
                    element.parentElement.style.borderColor = 'var(--training-danger)';
                } else if (remaining < originalValue * 0.2) {
                    element.classList.add('training-resource-low');
                    element.parentElement.style.borderColor = 'var(--training-warning)';
                } else {
                    element.parentElement.style.borderColor = 'var(--training-border)';
                }

                // Animate resource update
                if (totalCost > 0) {
                    element.classList.add('training-resource-updated');
                    setTimeout(() => element.classList.remove('training-resource-updated'), 600);
                }
            }
        }
    }

    /**
     * Update visual feedback for unit cards
     */
    updateUnitCardVisuals(unitType, quantity) {
        const unitCard = document.querySelector(`[data-unit="${unitType}"]`);
        if (unitCard) {
            // Update selected state
            if (quantity > 0) {
                unitCard.classList.add('selected');
            } else {
                unitCard.classList.remove('selected');
            }

            // Update quantity display in input
            const input = unitCard.querySelector('.quantity-input');
            if (input && input.value !== quantity.toString()) {
                input.value = quantity;
            }

            // Add pulse animation for changes
            unitCard.style.transform = 'scale(1.02)';
            setTimeout(() => {
                unitCard.style.transform = 'scale(1)';
            }, 150);
        }
    }

    /**
     * Validate form state and enable/disable submit button
     */
    validateFormState() {
        const submitButton = document.querySelector('button[name="train_units"]');
        if (!submitButton) return;

        const hasSelection = Object.values(this.selectedUnits).some(qty => qty > 0);
        const canAfford = Object.entries(this.totalCost).every(
            ([resource, cost]) => cost <= this.resources[resource]
        );

        submitButton.disabled = !hasSelection || !canAfford;

        // Update button appearance
        if (!hasSelection) {
            submitButton.textContent = '🏗️ Select Units to Train';
            submitButton.style.opacity = '0.6';
        } else if (!canAfford) {
            submitButton.textContent = '💰 Insufficient Resources';
            submitButton.style.opacity = '0.6';
        } else {
            const totalUnits = Object.values(this.selectedUnits).reduce((sum, qty) => sum + qty, 0);
            submitButton.textContent = `🏗️ Train ${totalUnits} Units`;
            submitButton.style.opacity = '1';
        }
    }    /**
     * Handle form submission with AJAX
     */
    handleFormSubmit(e) {
        e.preventDefault(); // Always prevent default form submission
        
        const hasSelection = Object.values(this.selectedUnits).some(qty => qty > 0);
        const canAfford = Object.entries(this.totalCost).every(
            ([resource, cost]) => cost <= this.resources[resource]
        );

        if (!hasSelection) {
            this.addFeedback('Please select units to train', 'warning');
            return false;
        }

        if (!canAfford) {
            this.addFeedback('Insufficient resources for training', 'error');
            return false;
        }

        // Submit via AJAX
        this.submitTraining();
        return false;
    }

    /**
     * Submit training request via AJAX
     */
    async submitTraining() {
        const submitButton = document.querySelector('button[name="train_units"]');
        
        try {
            // Show loading state
            this.setLoadingState(true);
            this.addFeedback('Training units...', 'info');

            // Prepare request data
            const requestData = {
                units: { ...this.selectedUnits }
            };

            // Remove zero quantities
            for (const unitType of Object.keys(requestData.units)) {
                if (requestData.units[unitType] <= 0) {
                    delete requestData.units[unitType];
                }
            }

            // Make AJAX request
            const response = await fetch('backend/scripts/train_units_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(requestData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                // Handle successful training
                this.handleTrainingSuccess(result);
            } else {
                // Handle training error
                this.handleTrainingError(result.message);
            }

        } catch (error) {
            console.error('Training request failed:', error);
            this.handleTrainingError('Network error: Could not submit training request. Please check your connection and try again.');
        } finally {
            // Always restore button state
            this.setLoadingState(false);
        }
    }

    /**
     * Handle successful training response
     */
    handleTrainingSuccess(result) {
        const { data } = result;
        
        // Update local resources
        if (data.updatedResources) {
            Object.assign(this.resources, data.updatedResources);
            this.updateResourceDisplayElements();
        }

        // Clear selections
        this.selectedUnits = {};
        this.clearAllSelections();

        // Recalculate maximum affordable units with new resources
        this.calculateMaxAffordable();

        // Show success message with details
        const trainedCount = Object.values(data.trainedUnits || {}).reduce((sum, count) => sum + count, 0);
        const unitsList = Object.entries(data.trainedUnits || {})
            .map(([unit, count]) => `${count} ${unit}`)
            .join(', ');

        this.addFeedback(`✅ Successfully trained: ${unitsList}`, 'success');

        // Clear auto-save since training is complete
        localStorage.removeItem('training_autosave');

        // Optional: Update army display if it exists on the page
        this.updateArmyDisplay(data.updatedArmy);

        // Add a celebration effect
        this.showTrainingCelebration();
    }

    /**
     * Handle training error
     */
    handleTrainingError(message) {
        this.addFeedback(`❌ Training failed: ${message}`, 'error');
        
        // Reload resources in case they changed
        this.loadCurrentResources();
        this.calculateMaxAffordable();
    }

    /**
     * Set loading state for the submit button
     */
    setLoadingState(isLoading) {
        const submitButton = document.querySelector('button[name="train_units"]');
        if (!submitButton) return;

        if (isLoading) {
            submitButton.classList.add('loading');
            submitButton.disabled = true;
            submitButton.style.opacity = '0.7';
            submitButton.innerHTML = '⏳ Training Units...';
        } else {
            submitButton.classList.remove('loading');
            submitButton.disabled = false;
            submitButton.style.opacity = '1';
            this.validateFormState(); // Restore proper button text
        }
    }

    /**
     * Update resource display elements with current values
     */
    updateResourceDisplayElements() {
        const resourceTypes = ['wood', 'iron', 'food', 'oil', 'stone'];
        
        for (const resource of resourceTypes) {
            // Update main resource display
            const element = document.getElementById(`training-${resource}_available`);
            if (element) {
                element.textContent = this.formatNumber(this.resources[resource]);
                
                // Remove any previous styling
                element.classList.remove('training-resource-low', 'training-resource-critical', 'training-resource-updated');
                element.parentElement.style.borderColor = 'var(--training-border)';
                
                // Add update animation
                element.classList.add('training-resource-updated');
                setTimeout(() => element.classList.remove('training-resource-updated'), 1000);
            }

            // Update top navigation resource bar if it exists
            const topBarElement = document.querySelector(`[data-resource="${resource}"] .resource-value`);
            if (topBarElement) {
                topBarElement.textContent = this.formatNumber(this.resources[resource]);
            }
        }
    }

    /**
     * Update army display if present on page
     */
    updateArmyDisplay(armyData) {
        if (!armyData) return;

        // Look for army display elements and update them
        for (const [unitType, count] of Object.entries(armyData)) {
            const armyElement = document.querySelector(`[data-army-unit="${unitType}"]`);
            if (armyElement) {
                armyElement.textContent = this.formatNumber(count);
                
                // Add visual feedback for updated units
                armyElement.style.background = 'var(--training-success)';
                armyElement.style.color = 'white';
                armyElement.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    armyElement.style.background = '';
                    armyElement.style.color = '';
                }, 2000);
            }
        }
    }

    /**
     * Show celebration effect for successful training
     */
    showTrainingCelebration() {
        // Create celebration overlay
        const celebration = document.createElement('div');
        celebration.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
            background: radial-gradient(circle, rgba(76, 175, 80, 0.1) 0%, transparent 70%);
        `;

        // Add confetti-like effect
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.style.cssText = `
                position: absolute;
                width: 10px;
                height: 10px;
                background: hsl(${Math.random() * 360}, 70%, 60%);
                border-radius: 50%;
                top: -10px;
                left: ${Math.random() * 100}%;
                animation: confettiFall ${2 + Math.random() * 3}s linear forwards;
            `;
            celebration.appendChild(confetti);
        }

        // Add CSS animation for confetti
        if (!document.getElementById('confetti-style')) {
            const style = document.createElement('style');
            style.id = 'confetti-style';
            style.textContent = `
                @keyframes confettiFall {
                    to {
                        transform: translateY(100vh) rotate(720deg);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(celebration);

        // Remove celebration after animation
        setTimeout(() => {
            if (celebration.parentNode) {
                celebration.parentNode.removeChild(celebration);
            }
        }, 5000);
    }

    /**
     * Handle keyboard shortcuts
     */
    handleKeyboard(e) {
        // Ctrl/Cmd + Number keys for quick tab switching
        if ((e.ctrlKey || e.metaKey) && e.key >= '1' && e.key <= '3') {
            e.preventDefault();
            const tabIndex = Number.parseInt(e.key) - 1;
            const tabs = document.querySelectorAll('.units-tab');
            if (tabs[tabIndex]) {
                tabs[tabIndex].click();
            }
        }

        // Escape key to clear all selections
        if (e.key === 'Escape') {
            this.clearAllSelections();
        }

        // Enter key to submit form if valid
        if (e.key === 'Enter' && e.target.classList.contains('quantity-input')) {
            e.preventDefault();
            const form = document.getElementById('training-form');
            if (form && !document.querySelector('button[name="train_units"]').disabled) {
                form.submit();
            }
        }
    }

    /**
     * Clear all unit selections
     */
    clearAllSelections() {
        this.selectedUnits = {};

        const quantityInputs = document.querySelectorAll('.quantity-input');
        for (const input of quantityInputs) {
            input.value = 0;
        }

        for (const card of document.querySelectorAll('.unit-card.selected')) {
            card.classList.remove('selected');
        }

        this.calculateTotalCost();
        this.updateResourceDisplay();
        this.validateFormState();

        this.addFeedback('Cleared all selections', 'info');
    }

    /**
     * Auto-save functionality (save to localStorage)
     */
    autoSave() {
        try {
            const saveData = {
                selectedUnits: this.selectedUnits,
                timestamp: Date.now()
            };
            localStorage.setItem('training_autosave', JSON.stringify(saveData));
        } catch (error) {
            console.warn('Auto-save failed:', error);
        }
    }

    /**
     * Load auto-saved data
     */
    loadAutoSave() {
        try {
            const saveData = JSON.parse(localStorage.getItem('training_autosave') || '{}');
            if (saveData.selectedUnits && Date.now() - saveData.timestamp < 3600000) { // 1 hour
                this.selectedUnits = saveData.selectedUnits;

                // Restore UI state
                for (const [unitType, quantity] of Object.entries(this.selectedUnits)) {
                    const input = document.querySelector(`input[name="train_${unitType}"]`);
                    if (input) {
                        input.value = quantity;
                        this.updateUnitCardVisuals(unitType, quantity);
                    }
                }

                this.calculateTotalCost();
                this.updateResourceDisplay();
                this.validateFormState();

                this.addFeedback('Restored previous session', 'success');
            }
        } catch (error) {
            console.warn('Failed to load auto-save:', error);
        }
    }

    /**
     * Add user feedback message
     */
    addFeedback(message, type = 'info') {
        this.feedbackQueue.push({ message, type, timestamp: Date.now() });
        this.showFeedback();
    }

    /**
     * Show feedback message to user
     */
    showFeedback() {
        if (this.feedbackQueue.length === 0) return;

        const feedback = this.feedbackQueue.shift();

        // Create feedback element
        const feedbackEl = document.createElement('div');
        feedbackEl.className = `training-feedback training-feedback-${feedback.type}`;
        feedbackEl.textContent = feedback.message;

        // Style the feedback
        Object.assign(feedbackEl.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '1rem 1.5rem',
            borderRadius: '6px',
            color: 'white',
            fontWeight: '600',
            zIndex: '10000',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease-out',
            maxWidth: '300px',
            boxShadow: '0 4px 16px rgba(0, 0, 0, 0.3)'
        });

        // Set background color based on type
        const colors = {
            success: '#27ae60',
            error: '#e74c3c',
            warning: '#f39c12',
            info: '#3498db'
        };
        feedbackEl.style.background = colors[feedback.type] || colors.info;

        document.body.appendChild(feedbackEl);

        // Animate in
        setTimeout(() => {
            feedbackEl.style.transform = 'translateX(0)';
        }, 50);

        // Animate out and remove
        setTimeout(() => {
            feedbackEl.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (feedbackEl.parentNode) {
                    feedbackEl.parentNode.removeChild(feedbackEl);
                }
            }, 300);
        }, 3000);
    }

    /**
     * Show welcome message with helpful tips
     */
    showWelcomeMessage() {
        setTimeout(() => {
            this.addFeedback('Welcome to Unit Training! Use keyboard shortcuts: Ctrl+1-3 for tabs, ESC to clear', 'info');
        }, 1000);
    }

    /**
     * Utility: Extract unit type from form field name
     */
    extractUnitType(fieldName) {
        return fieldName.replace('train_', '');
    }

    /**
     * Utility: Format numbers with commas
     */
    formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /**
     * Utility: Animate button click feedback
     */
    animateButton(button) {
        button.style.transform = 'scale(0.95)';
        button.style.background = 'var(--training-primary)';

        setTimeout(() => {
            button.style.transform = 'scale(1)';
            button.style.background = '';
        }, 150);
    }

    /**
     * Get training summary for display
     */
    getTrainingSummary() {
        const summary = {
            totalUnits: Object.values(this.selectedUnits).reduce((sum, qty) => sum + qty, 0),
            totalCost: { ...this.totalCost },
            selectedUnits: { ...this.selectedUnits },
            canAfford: Object.entries(this.totalCost).every(
                ([resource, cost]) => cost <= this.resources[resource]
            )
        };

        return summary;
    }    /**
     * Reset the training system
     */
    reset() {
        this.selectedUnits = {};
        this.clearAllSelections();
        localStorage.removeItem('training_autosave');
        this.addFeedback('Training system reset', 'info');
    }

    /**
     * Show configuration error message
     */
    showConfigurationError() {
        const container = document.querySelector('.training-container');
        if (container) {
            container.innerHTML = `
                <div style="padding: 2rem; text-align: center; background: rgba(220, 53, 69, 0.1); border: 1px solid #dc3545; border-radius: 8px; margin: 2rem;">
                    <h3 style="color: #dc3545; margin-bottom: 1rem;">⚠️ Configuration Error</h3>
                    <p style="color: #721c24; margin-bottom: 1rem;">Game configuration could not be loaded. Please refresh the page or contact support.</p>
                    <button onclick="window.location.reload()" style="padding: 0.5rem 1rem; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        🔄 Refresh Page
                    </button>
                </div>
            `;
        }
    }
}

// Initialize training system when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing Training System...');

    // Check if we're on the training page
    if (document.querySelector('.training-container')) {
        window.trainingSystem = new TrainingSystem();

        // Load auto-saved data after initialization
        setTimeout(() => {
            window.trainingSystem.loadAutoSave();
        }, 500);
    }
});

// Export for potential module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TrainingSystem;
}
