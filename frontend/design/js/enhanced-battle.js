/**
 * Enhanced Battle System JavaScript
 * Includes paced battle outcome visualization and improved UI
 */

class EnhancedBattleSystem {
    constructor() {
        this.selectedUnits = {};
        this.totalSelected = 0;
        this.totalPower = 0;
        this.currentStrategy = 'balanced';
        this.battleSpeed = 1; // Affects paced visualization speed

        this.battleInProgress = false;
        this.battlePaused = false; // For pausing the paced visualization
        this.battleData = null;    // Stores initial player/enemy army setup for display
        this.serverResponse = null; // Stores the actual result from the server
        this.processingServerOutcome = false; // Flag for managing display state

        this.battleTimer = 0; // General timer for modal display duration
        this.battleInterval = null; // For the "waiting for server" animation
        
        this.pacedVisualizationInterval = null; // For the new paced display of results
        this.animationDisplayTimerInterval = null; // For timing the frontend animation
        this.visualEventQueue = []; // Queue for loss events, plunder, etc.

        this.currentRoundDisplay = 1; // For visual fluff during "waiting" phase
        this.totalRoundsDisplay = 5;  // For visual fluff

        // Overall army health/morale - aggregates of unit type pools
        this.playerHealth = { current: 0, max: 0 };
        this.enemyHealth = { current: 0, max: 0 };
        this.playerMorale = 100; // Overall army morale percentage
        this.enemyMorale = 100; // Overall army morale percentage

        // Unit type specific health and morale pools
        this.playerUnitTypeHealth = {}; // { fighters: {current, max, count}, ... }
        this.enemyUnitTypeHealth = {};
        this.playerUnitTypeMorale = {}; // { fighters: currentMorale, ... }
        this.enemyUnitTypeMorale = {};

        this.playerArmyDisplay = {}; // For tracking displayed unit counts during animation
        this.enemyArmyDisplay = {};  // For tracking displayed unit counts during animation
        this.animationDisplayTimerInterval = null; // For timing the frontend animation duration

        this.currentVisualRoundIndex = 0; // To track which round from battle_log we are visualizing
        this.currentBattleLog = null; // To store the battle_log from server

        this.unitStats = {
            'fighters':    { icon: 'fa-user-shield', attack: 15, defense: 12, basePower: 1.0, baseHealth: 50, baseMorale: 100, name: 'Fighters',    color: '#3b82f6' },
            'shooters':    { icon: 'fa-crosshairs', attack: 20, defense: 8,  basePower: 1.2, baseHealth: 35, baseMorale: 100, name: 'Shooters',    color: '#ef4444' },
            'vehicles':    { icon: 'fa-truck-monster', attack: 25, defense: 20, basePower: 3.0, baseHealth: 100,baseMorale: 100, name: 'Vehicles',    color: '#8b5cf6' },
            'riders':      { icon: 'fa-horse', attack: 18, defense: 10, basePower: 2.0, baseHealth: 60, baseMorale: 100, name: 'Riders',      color: '#f59e0b' },
            'canons':      { icon: 'fa-bomb', attack: 35, defense: 5,  basePower: 5.0, baseHealth: 40, baseMorale: 100, name: 'Cannons',     color: '#dc2626' },
            'skirmishers': { icon: 'fa-running', attack: 12, defense: 15, basePower: 0.8, baseHealth: 45, baseMorale: 100, name: 'Skirmishers', color: '#059669' },
            'jets':        { icon: 'fa-fighter-jet', attack: 40, defense: 8,  basePower: 4.0, baseHealth: 70, baseMorale: 100, name: 'Jets',        color: '#0891b2' },
            'archers':     { icon: 'fa-bullseye', attack: 16, defense: 9,  basePower: 1.5, baseHealth: 30, baseMorale: 100, name: 'Archers',     color: '#7c3aed' },
            'marauders':   { icon: 'fa-mask', attack: 22, defense: 14, basePower: 2.5, baseHealth: 55, baseMorale: 100, name: 'Marauders',   color: '#be123c' }
        };

        this.strategies = {
            balanced: { name: 'Balanced', recommendations: ['fighters', 'shooters', 'vehicles'], description: 'Equal focus on offense and defense' },
            aggressive: { name: 'Aggressive', recommendations: ['shooters', 'canons', 'jets'], description: 'Maximum attack power, high risk' },
            defensive: { name: 'Defensive', recommendations: ['fighters', 'vehicles', 'skirmishers'], description: 'Minimize losses, steady advance' }
        };

        this.init();
    }

    init() {
        this.bindEvents();
        this.updateStrategyRecommendations();
        this.updateTotalInfo();
        // this.createParticleEffect(); // Optional: can be re-enabled if CSS/HTML support it
    }

    bindEvents() {
        for (const btn of document.querySelectorAll('.btn-increase')) {
            btn.addEventListener('click', (e) => this.increaseUnit(e));
        }
        for (const btn of document.querySelectorAll('.btn-decrease')) {
            btn.addEventListener('click', (e) => this.decreaseUnit(e));
        }
        for (const btn of document.querySelectorAll('.btn-select-all')) {
            btn.addEventListener('click', (e) => this.selectAllUnits(e));
        }
        for (const btn of document.querySelectorAll('.btn-select-none')) {
            btn.addEventListener('click', (e) => this.selectNoneUnits(e));
        }
        for (const radio of document.querySelectorAll('input[name="strategy"]')) {
            radio.addEventListener('change', (e) => this.handleStrategyChange(e));
        }
        for (const input of document.querySelectorAll('.unit-input')) {
            input.addEventListener('input', (e) => this.handleUnitChange(e));
        }

        const autoSelectBtn = document.getElementById('autoSelectBtn');
        if (autoSelectBtn) autoSelectBtn.addEventListener('click', () => this.autoSelectUnits());
        const quickSelectBtn = document.getElementById('quickSelect');
        if (quickSelectBtn) quickSelectBtn.addEventListener('click', () => this.quickSelectUnits());

        const battleModal = document.getElementById('battleModal');
        if (battleModal) {
            const speedControl = battleModal.querySelector('#battleSpeed'); 
            if (speedControl) speedControl.addEventListener('input', (e) => { 
                this.battleSpeed = Number.parseFloat(e.target.value) || 1; 
                if (this.battleInProgress && !this.battlePaused && (this.battleInterval || this.pacedVisualizationInterval)) {
                    if(this.pacedVisualizationInterval) {
                        clearInterval(this.pacedVisualizationInterval);
                        this.pacedVisualizationInterval = setInterval(() => this.processNextVisualEvent(), 1000 / this.battleSpeed);
                    } else if (this.battleInterval) {
                        clearInterval(this.battleInterval);
                        this.battleInterval = setInterval(() => this.updateWaitingAnimation(), 1000 / this.battleSpeed);
                    }
                }
            });
            
            const pauseBtn = battleModal.querySelector('#pauseBattle');
            if (pauseBtn) pauseBtn.addEventListener('click', () => this.pauseVisualization());
            const resumeBtn = battleModal.querySelector('#resumeBattle'); 
            if (resumeBtn) resumeBtn.addEventListener('click', () => this.resumeVisualization());

            const speedUpBtn = battleModal.querySelector('#speedUp');
            if (speedUpBtn) speedUpBtn.addEventListener('click', () => this.adjustVisualizationSpeed(0.5)); 
            
            const skipBtn = battleModal.querySelector('#skipBattle');
            if (skipBtn) skipBtn.addEventListener('click', () => this.skipVisualization());
        }
        const battleForm = document.getElementById('battleForm');
        if (battleForm) battleForm.addEventListener('submit', (e) => this.handleBattleSubmit(e));
    }

    increaseUnit(e) {
        const btn = e.target.closest('.btn-increase');
        const unitCard = btn.closest('.unit-card');
        const input = unitCard.querySelector('input[type="number"]');
        const max = Number.parseInt(input.getAttribute('data-max'));
        const current = Number.parseInt(input.value) || 0;
        if (current < max) {
            input.value = current + 1;
            input.dispatchEvent(new Event('input'));
        }
    }

    decreaseUnit(e) {
        const btn = e.target.closest('.btn-decrease');
        const unitCard = btn.closest('.unit-card');
        const input = unitCard.querySelector('input[type="number"]');
        const current = Number.parseInt(input.value) || 0;
        if (current > 0) {
            input.value = current - 1;
            input.dispatchEvent(new Event('input'));
        }
    }

    selectAllUnits(e) {
        const btn = e.target.closest('.btn-select-all');
        const unitCard = btn.closest('.unit-card');
        const input = unitCard.querySelector('input[type="number"]');
        const max = Number.parseInt(input.getAttribute('data-max'));
        input.value = max;
        input.dispatchEvent(new Event('input'));
    }

    selectNoneUnits() { 
        for (const input of document.querySelectorAll('.unit-input')) {
            input.value = 0;
            input.dispatchEvent(new Event('input'));
        }
    }

    handleUnitChange(e) {
        const input = e.target;
        const unitType = input.name.replace('units[', '').replace(']', '');
        let value = Number.parseInt(input.value) || 0;
        const max = Number.parseInt(input.getAttribute('max'));

        if (value > max) value = max;
        if (value < 0) value = 0;
        input.value = value;

        this.selectedUnits[unitType] = value;
        this.updateTotalInfo();
        this.updateUnitDisplay(unitType, value);
    }

    handleStrategyChange(e) {
        this.currentStrategy = e.target.value;
        const strategyInput = document.getElementById('strategyInput');
        if (strategyInput) strategyInput.value = this.currentStrategy;
        this.updateStrategyRecommendations();
        this.updateTotalInfo();
    }

    autoSelectUnits() {
        const strategy = this.strategies[this.currentStrategy];
        if (!strategy || !strategy.recommendations) return;
        this.selectNoneUnits();
        strategy.recommendations.forEach((unitType, index) => {
            const input = document.querySelector(`input[name="units[${unitType}]"]`);
            if (input) {
                const max = Number.parseInt(input.getAttribute('max'));
                const percentage = index === 0 ? 0.4 : (index === 1 ? 0.35 : 0.25); 
                const suggested = Math.max(0, Math.min(max, Math.floor(max * percentage)));
                if (suggested > 0) {
                    input.value = suggested;
                    input.dispatchEvent(new Event('input'));
                }
            }
        });
        this.showNotification(`Auto-selected units for ${strategy.name} strategy!`, 'success');
    }

    quickSelectUnits() {
        this.selectNoneUnits();
        for (const input of document.querySelectorAll('.unit-input')) {
            const max = Number.parseInt(input.getAttribute('max'));
            if (max > 0) {
                const suggested = Math.max(0, Math.min(max, Math.floor(max * 0.5)));
                 if (suggested > 0) {
                    input.value = suggested;
                    input.dispatchEvent(new Event('input'));
                }
            }
        }
        this.showNotification('Quick selected 50% of available units!', 'success');
    }

    updateTotalInfo() {
        this.totalSelected = 0;
        this.totalPower = 0;
        for (const [unitType, count] of Object.entries(this.selectedUnits)) {
            this.totalSelected += count;
            if (this.unitStats?.[unitType]?.basePower) {
                let unitPower = count * this.unitStats[unitType].basePower;
                switch (this.currentStrategy) {
                    case 'aggressive': unitPower *= 1.25; break;
                    case 'defensive': unitPower *= 0.90; break;
                    case 'balanced': unitPower *= 1.10; break;
                }
                this.totalPower += unitPower;
            }
        }
        this.totalPower = Math.round(this.totalPower);
        const totalSelectedEl = document.getElementById('totalSelected');
        if (totalSelectedEl) totalSelectedEl.textContent = this.totalSelected;
        const totalPowerEl = document.getElementById('totalPower');
        if (totalPowerEl) totalPowerEl.textContent = this.totalPower;
        const startBattleBtn = document.getElementById('startBattle');
        if (startBattleBtn) startBattleBtn.disabled = this.totalSelected === 0;
    }

    updateUnitDisplay(unitType, count) { 
        const unitCard = document.querySelector(`.unit-card[data-unit="${unitType}"]`);
        if (unitCard) {
            if (count > 0) unitCard.classList.add('selected');
            else unitCard.classList.remove('selected');
        }
    }

    updateStrategyRecommendations() {
        const strategy = this.strategies[this.currentStrategy];
        const recommendationsContainer = document.getElementById('unitRecommendations');
        if (recommendationsContainer && strategy) {
            recommendationsContainer.innerHTML = `
                <div class="strategy-info">
                    <h5><i class="fas fa-lightbulb me-2"></i>${strategy.name} Strategy</h5>
                    <p class="strategy-description">${strategy.description}</p>
                    <div class="recommended-units">
                        <strong>Recommended:</strong>
                        <div class="unit-recommendations-list">
                            ${strategy.recommendations.map(unitType => {
                                const unit = this.unitStats[unitType];
                                return unit ? `<div class="recommendation-item"><i class="fas ${unit.icon} me-1"></i>${unit.name}</div>` : '';
                            }).join('')}
                        </div>
                    </div>
                </div>`;
        }
    }

    async handleBattleSubmit(e) {
        if (e) e.preventDefault();
        if (this.totalSelected === 0) { this.showNotification('Please select at least one unit!', 'error'); return; }
        if (this.battleInProgress) { this.showNotification('Battle is already in progress!', 'warning'); return; }

        const form = document.getElementById('battleForm');
        if (!form) { this.showNotification('Battle form not found!', 'error'); return; }

        const formData = new FormData(form); // Captures target_id, target_type if they are in the form

        this.prepareBattleData(); // This populates this.battleData.playerArmy with selected units

        // Explicitly add selected units to formData
        const unitsToSubmit = this.battleData.playerArmy;
        let unitsActuallySelected = false;
        for (const unitType in unitsToSubmit) {
            if (Object.prototype.hasOwnProperty.call(unitsToSubmit, unitType) && unitsToSubmit[unitType] > 0) {
                formData.append(`units[${unitType}]`, unitsToSubmit[unitType]);
                unitsActuallySelected = true;
            }
        }

        // Double check if any units were actually appended, matching PHP's server-side check
        if (!unitsActuallySelected && this.totalSelected > 0) {
            // This case might indicate a discrepancy if totalSelected > 0 but unitsToSubmit was empty.
            // However, the primary check this.totalSelected === 0 should catch it earlier.
            // For safety, ensure PHP doesn't get an empty 'units' array if it expects content.
        }


        formData.append('attack', '1'); // This flag might be used by the backend or is a remnant

        this.submitBattleAjax(formData);
    }

    prepareBattleData() {
        const selectedUnits = {};
        for (const input of document.querySelectorAll('.unit-input')) {
            const unitType = input.name.replace('units[', '').replace(']', '');
            const count = Number.parseInt(input.value) || 0;
            if (count > 0) selectedUnits[unitType] = count;
        }
        this.battleData = {
            playerArmy: selectedUnits,
            enemyArmy: this.getEnemyArmyData(), 
            strategy: this.currentStrategy
        };
    }

    getEnemyArmyData() {
        if (window.targetArmies && typeof window.targetArmies === 'object') {
            const enemyArmy = {};
            for (const [unitType, count] of Object.entries(window.targetArmies)) {
                if (this.unitStats[unitType]) { 
                    const numericCount = Number.parseInt(count) || 0;
                    if (numericCount > 0) enemyArmy[unitType] = numericCount;
                }
            }
            if (Object.keys(enemyArmy).length > 0) return enemyArmy;
            console.warn('No valid units found in AI army data from window.targetArmies.');
        }
        console.warn('No real AI army data available for display, enemy side will appear empty/unknown.');
        return {}; 
    }
    
    startBattle() { 
        this.battleInProgress = true;
        this.battlePaused = false;
        this.processingServerOutcome = false;
        this.serverResponse = null;
        this.visualEventQueue = [];
        this.currentRoundDisplay = 1; // Reset visual round counter
        this.battleTimer = 0;

        this.showBattleModal(); 
        this.initializeBattleUI(); 

        const statusElement = document.querySelector('#battleStatus');
        if (statusElement) statusElement.textContent = 'Connecting to server...';
        
        if (this.battleInterval) clearInterval(this.battleInterval);
        const intervalSpeed = (this.battleSpeed && this.battleSpeed > 0) ? this.battleSpeed : 1; // Ensure battleSpeed is valid
        this.battleInterval = setInterval(() => this.updateWaitingAnimation(), 1000 / intervalSpeed);

        this.showNotification('Battle initiated! Awaiting server response...', 'info');
    }

    initializeBattleUI() {
        if (!this.battleData) { console.error("initializeBattleUI: battleData is not set."); return; }
        const modal = document.getElementById('battleModal');
        if (!modal) return;

        this.playerMorale = 100;
        this.enemyMorale = 100;
        this.playerUnitTypeHealth = {};
        this.enemyUnitTypeHealth = {};
        this.playerUnitTypeMorale = {};
        this.enemyUnitTypeMorale = {};
        this.playerArmyDisplay = {}; // Reset for new battle
        this.enemyArmyDisplay = {};  // Reset for new battle
        let totalPlayerArmyHealth = 0;
        let totalEnemyArmyHealth = 0;

        for (const unitType in this.battleData.playerArmy) {
            const count = this.battleData.playerArmy[unitType];
            const stats = this.unitStats[unitType];
            if (stats) {
                if (stats.baseHealth) {
                    this.playerUnitTypeHealth[unitType] = { current: count * stats.baseHealth, max: count * stats.baseHealth, initialCount: count };
                    totalPlayerArmyHealth += this.playerUnitTypeHealth[unitType].max;
                }
                if (stats.baseMorale) this.playerUnitTypeMorale[unitType] = stats.baseMorale;
                // Initialize playerArmyDisplay
                this.playerArmyDisplay[unitType] = { current: count, max: count };
            }
        }
        for (const unitType in this.battleData.enemyArmy) {
            const count = this.battleData.enemyArmy[unitType];
            const stats = this.unitStats[unitType];
            if (stats) {
                if (stats.baseHealth) {
                    this.enemyUnitTypeHealth[unitType] = { current: count * stats.baseHealth, max: count * stats.baseHealth, initialCount: count };
                    totalEnemyArmyHealth += this.enemyUnitTypeHealth[unitType].max;
                }
                if (stats.baseMorale) this.enemyUnitTypeMorale[unitType] = stats.baseMorale;
                // Initialize enemyArmyDisplay
                this.enemyArmyDisplay[unitType] = { current: count, max: count };
            }
        }
        this.playerHealth = { current: totalPlayerArmyHealth, max: totalPlayerArmyHealth };
        this.enemyHealth = { current: totalEnemyArmyHealth, max: totalEnemyArmyHealth };

        this.updateArmyDisplay('player');
        this.updateArmyDisplay('enemy');
        this.updateHealthBar('player', this.playerHealth.current, this.playerHealth.max);
        this.updateHealthBar('enemy', this.enemyHealth.current, this.enemyHealth.max);
        this.updateMoraleBar('player', this.playerMorale);
        this.updateMoraleBar('enemy', this.enemyMorale);

        const battleLog = modal.querySelector('#battleLog');
        if (battleLog) battleLog.innerHTML = '<div class="log-entry system"><i class="fas fa-info-circle me-2"></i><span>Armies deployed. Awaiting engagement...</span></div>';
        this.updateRoundDisplay(); 
    }

    updateArmyDisplay(side) { 
        const armyData = side === 'player' ? this.battleData.playerArmy : this.battleData.enemyArmy;
        const unitTypeHealthData = side === 'player' ? this.playerUnitTypeHealth : this.enemyUnitTypeHealth;
        const unitTypeMoraleData = side === 'player' ? this.playerUnitTypeMorale : this.enemyUnitTypeMorale;
        const containerId = side === 'player' ? 'attackerUnits' : 'defenderUnits';
        const armyContainer = document.getElementById(containerId);

        if (!armyContainer) return;
        if (!armyData || Object.keys(armyData).length === 0) {
            armyContainer.innerHTML = `<div class="no-units p-3 text-white-50 small">${side === 'player' ? 'Your forces' : 'Enemy forces'} are not specified or visible.</div>`;
            return;
        }
        armyContainer.innerHTML = '';
        let unitsDisplayed = 0;

        for (const [unitType, initialCount] of Object.entries(armyData)) { // Use initialCount from battleData
            if (initialCount > 0) {
                const unit = this.unitStats[unitType];
                if (unit) {
                    const unitElement = document.createElement('div');
                    unitElement.className = 'battle-unit';
                    unitElement.setAttribute('data-unit', unitType);
                    
                    const healthInfo = unitTypeHealthData?.[unitType];
                    // Calculate current display count based on current health vs base health of one unit
                    const currentDisplayCount = healthInfo && unit.baseHealth > 0 ? Math.round(healthInfo.current / unit.baseHealth) : initialCount;
                    const healthPercentage = healthInfo && healthInfo.max > 0 ? (healthInfo.current / healthInfo.max) * 100 : (initialCount > 0 ? 100 : 0);
                    const moraleValue = unitTypeMoraleData?.[unitType] ?? unit.baseMorale;

                    unitElement.innerHTML = `
                        <div class="unit-icon" style="color: ${unit.color};"><i class="fas ${unit.icon}"></i></div>
                        <div class="unit-details">
                            <div class="unit-name">${unit.name}</div>
                            <div class="unit-count-display"><i class="fas fa-users me-1"></i> <span class="current-unit-count">${currentDisplayCount}</span></div>
                            <div class="unit-type-health-bar-container" title="Health: ${Math.round(healthInfo?.current || 0)}/${Math.round(healthInfo?.max || 0)}">
                                <div class="unit-type-health-bar-fill" style="width: ${healthPercentage.toFixed(0)}%;"></div>
                            </div>
                            <div class="unit-type-morale-bar-container" title="Morale: ${moraleValue.toFixed(0)}%">
                                <div class="unit-type-morale-bar-fill" style="width: ${moraleValue.toFixed(0)}%; background-color: ${this.getMoraleColor(moraleValue)};"></div>
                            </div>
                        </div>`;
                    armyContainer.appendChild(unitElement);
                    unitsDisplayed++;
                }
            }
        }
        if (unitsDisplayed === 0) {
            armyContainer.innerHTML = `<div class="no-units p-3 text-white-50 small">${side === 'player' ? 'No player units' : 'No enemy units'} deployed.</div>`;
        }
    }
    
    updateWaitingAnimation() { 
        if (this.serverResponse && !this.processingServerOutcome) {
            if (this.battleInterval) clearInterval(this.battleInterval);
            this.battleInterval = null;
            this.displayServerOutcome(this.serverResponse);
            return;
        }
        if (!this.battleInProgress || this.processingServerOutcome) {
            if (this.battleInterval) clearInterval(this.battleInterval);
            this.battleInterval = null;
            return;
        }

        this.battleTimer++;
        const timerEl = document.querySelector('#battleTimer');
        if (timerEl) timerEl.textContent = `${Math.floor(this.battleTimer / 60).toString().padStart(2, '0')}:${(this.battleTimer % 60).toString().padStart(2, '0')}`;

        const progressBar = document.querySelector('#battleProgress');
        if (progressBar) {
            const estimatedTime = 15; 
            const progress = Math.min(99, (this.battleTimer / estimatedTime) * 100);
            progressBar.style.width = `${progress}%`;
        }

        const statusEl = document.querySelector('#battleStatus');
        if (statusEl) {
            const messages = ['Contacting command...', 'Awaiting field report...', 'Server processing...'];
            statusEl.textContent = messages[Math.floor(this.battleTimer / 2) % messages.length];
        }
        if (this.battleTimer > 0 && (this.battleTimer % 5 === 0)) { 
             if(this.currentRoundDisplay < this.totalRoundsDisplay) this.currentRoundDisplay++;
             this.updateRoundDisplay();
        }
    }

    updateHealthBar(side, currentHealth, maxHealth) {
        const barId = side === 'player' ? 'attackerHealth' : 'defenderHealth';
        const textId = side === 'player' ? 'attackerHealthText' : 'defenderHealthText';
        const barFill = document.getElementById(barId);
        const textEl = document.getElementById(textId);

        if (barFill) {
            const percentage = maxHealth > 0 ? Math.max(0, (currentHealth / maxHealth) * 100) : 0;
            if (barFill.style.width !== `${percentage}%`) {
                barFill.style.transition = 'width 0.5s ease-out, background-color 0.5s ease-out';
                barFill.style.width = `${percentage}%`;
            }
            if (percentage > 60) barFill.style.backgroundColor = '#10b981';
            else if (percentage > 30) barFill.style.backgroundColor = '#f59e0b';
            else barFill.style.backgroundColor = '#ef4444';
            percentage <= 20 && percentage > 0 ? barFill.classList.add('low-health-pulse') : barFill.classList.remove('low-health-pulse');
        }
        if (textEl) textEl.textContent = `${Math.round(currentHealth)} / ${Math.round(maxHealth)} HP`;
    }

    updateMoraleBar(side, value) { 
        const moraleBarId = side === 'player' ? 'attackerMorale' : 'defenderMorale';
        const moraleBarFill = document.getElementById(moraleBarId);
        if (moraleBarFill) {
            const clampedValue = Math.max(0, Math.min(100, value));
            moraleBarFill.style.width = `${clampedValue}%`;
            moraleBarFill.style.backgroundColor = this.getMoraleColor(clampedValue);
            moraleBarFill.style.transition = 'width 0.5s ease-out, background-color 0.5s ease-out';
        }
    }
    
    updateRoundDisplay() {
        const currentRoundEl = document.getElementById('currentRound'); 
        if (currentRoundEl) currentRoundEl.textContent = this.currentRoundDisplay;
        const totalRoundsEl = document.getElementById('totalRounds');
        if (totalRoundsEl) totalRoundsEl.textContent = this.totalRoundsDisplay;
    }

    addBattleLogEntry(message, side, type = 'normal') {
        const battleLog = document.getElementById('battleLog'); 
        if (!battleLog) return;
        const entry = document.createElement('div');
        entry.className = `log-entry log-${side} log-${type}`; 
        entry.innerHTML = `<i class="fas ${this.getLogIcon(side, type)} me-2"></i><span>${message}</span>`;
        battleLog.appendChild(entry);
        battleLog.scrollTop = battleLog.scrollHeight;
        if (battleLog.children.length > 30) battleLog.firstChild.remove();
    }

    getLogIcon(side, type) {
        if (type === 'critical' || type === 'error') return 'fa-exclamation-triangle text-danger';
        if (type === 'loss') return 'fa-skull-crossbones text-warning';
        if (type === 'system') return 'fa-info-circle text-info';
        if (type === 'success') return 'fa-check-circle text-success';
        if (side === 'player') return 'fa-khanda text-primary'; 
        if (side === 'enemy') return 'fa-shield-virus text-danger'; 
        return 'fa-comment-dots text-secondary';
    }

    animateUnitDamage(side, unitType, damageCount, isFinalLoss = false) { // damageCount is number of units lost
        const containerId = side === 'player' ? 'attackerUnits' : 'defenderUnits';
        const unitCard = document.querySelector(`#${containerId} [data-unit="${unitType}"]`);

        if (unitCard) {
            const effectText = isFinalLoss ? `-${damageCount} Lost!` : (damageCount > 0 ? `-${damageCount} Hit!` : 'Hit!');
            const damageNumber = document.createElement('div');
            damageNumber.className = 'damage-number-effect'; 
            damageNumber.textContent = effectText;
            damageNumber.style.color = isFinalLoss ? 'red' : 'orange';
            
            unitCard.appendChild(damageNumber); 

            if (isFinalLoss) unitCard.classList.add('unit-type-defeated-animation'); // CSS handles visual change
            else unitCard.classList.add('unit-type-hit-animation'); // CSS handles shake

            setTimeout(() => {
                damageNumber.remove();
                if (!isFinalLoss) unitCard.classList.remove('unit-type-hit-animation');
            }, 1500);
        }
    }
    
    endBattle(outcomeSource = 'server') {
        if (!this.battleInProgress && outcomeSource !== 'skipped_force_end' && outcomeSource !== 'error_force_end') return;
        this.battleInProgress = false;
        if (this.battleInterval) clearInterval(this.battleInterval); this.battleInterval = null;
        if (this.pacedVisualizationInterval) clearInterval(this.pacedVisualizationInterval); this.pacedVisualizationInterval = null;

        if (this.serverResponse && !this.processingServerOutcome) {
            this.displayServerOutcome(this.serverResponse);
        } else if (outcomeSource === 'skipped') {
            if (this.serverResponse && !this.processingServerOutcome) this.displayServerOutcome(this.serverResponse);
            else if (!this.serverResponse) {
                this.addBattleLogEntry('Battle skipped. Awaiting final results...', 'system');
                this.showNotification('Battle skipped. Final results pending.', 'warning');
                this.showBattleResultsOverlay('skipped');
            }
        } else if (outcomeSource === 'error_force_end') {
            this.addBattleLogEntry('Battle terminated due to an error.', 'system', 'error');
            this.showBattleResultsOverlay('error');
        } else if (!this.serverResponse && (outcomeSource === 'server' || outcomeSource === 'timeout')) {
            this.addBattleLogEntry('Awaiting server results...', 'system');
            this.showBattleResultsOverlay('pending');
        }
    }

    displayServerOutcome(serverResponse) {
        if (this.processingServerOutcome || !serverResponse) return;
        this.processingServerOutcome = true;

        if (this.battleInterval) clearInterval(this.battleInterval); this.battleInterval = null; // Clear waiting for server interval
        if (this.pacedVisualizationInterval) clearInterval(this.pacedVisualizationInterval); this.pacedVisualizationInterval = null;
        if (this.animationDisplayTimerInterval) clearInterval(this.animationDisplayTimerInterval); this.animationDisplayTimerInterval = null; // Clear previous animation timer
        
        this.battleInProgress = false; // Overall battle state

        if (!serverResponse.success) {
            this.handleBattleError(serverResponse.error || 'Battle processing failed on server.');
            return;
        }
        
        this.battleTimer = 0; // Reset timer for visualization duration
        const timerEl = document.querySelector('#battleTimer');
        if (timerEl) timerEl.textContent = '00:00'; // Reset display

        // Start a new timer for the visualization phase
        this.animationDisplayTimerInterval = setInterval(() => {
            this.battleTimer++;
            if (timerEl) timerEl.textContent = `${Math.floor(this.battleTimer / 60).toString().padStart(2, '0')}:${(this.battleTimer % 60).toString().padStart(2, '0')}`;
        }, 1000);

        this.addBattleLogEntry('Server response received. Processing battle log...', 'system');
        this.currentBattleLog = serverResponse.battle_log || [];
        this.currentVisualRoundIndex = 0;
        this.totalRoundsDisplay = this.currentBattleLog.length > 0 ? this.currentBattleLog.length : 1; // Update total rounds for display

        if (this.currentBattleLog.length > 0) {
            this.processNextRoundVisuals();
        } else {
            // No battle log, maybe a very old battle or an error in log generation
            this.addBattleLogEntry('No detailed round data found. Displaying final outcome.', 'system');
            // Fallback to old behavior: use total losses if battle_log is empty
            this.visualEventQueue = [];
            const { attacker_losses = {}, defender_losses = {} } = serverResponse;
            for (const unitType in attacker_losses) {
                for (let i = 0; i < attacker_losses[unitType]; i++) {
                    this.visualEventQueue.push({ side: 'player', unitType, event: 'loss' });
                }
            }
            for (const unitType in defender_losses) {
                for (let i = 0; i < defender_losses[unitType]; i++) {
                    this.visualEventQueue.push({ side: 'enemy', unitType, event: 'loss' });
                }
            }
            if (this.visualEventQueue.length > 0) {
                this.pacedVisualizationInterval = setInterval(() => this.processNextVisualEvent(), 1200 / this.battleSpeed);
            } else {
                this.finalizeBattleDisplay(serverResponse); // Pass serverResponse for final morale etc.
            }
        }
    }

    processNextRoundVisuals() {
        if (this.currentVisualRoundIndex >= this.currentBattleLog.length) {
            this.finalizeBattleDisplay(this.serverResponse); // Battle visualization complete
            return;
        }

        const roundData = this.currentBattleLog[this.currentVisualRoundIndex];
        this.currentRoundDisplay = roundData.round;
        this.updateRoundDisplay();
        this.addBattleLogEntry(`--- Round ${roundData.round} Begins ---`, 'system', 'round-start');

        // Update morale based on this round's data
        if (typeof roundData.attacker_morale !== 'undefined') {
            this.updateMoraleBar('player', roundData.attacker_morale);
            this.addBattleLogEntry(`Attacker morale: ${Math.round(roundData.attacker_morale)}%`, 'player', 'morale');
        }
        if (typeof roundData.defender_morale !== 'undefined') {
            this.updateMoraleBar('enemy', roundData.defender_morale);
            this.addBattleLogEntry(`Defender morale: ${Math.round(roundData.defender_morale)}%`, 'enemy', 'morale');
        }
        if (roundData.attacker_routed) this.addBattleLogEntry('Attacker forces are routed!', 'player', 'critical');
        if (roundData.defender_routed) this.addBattleLogEntry('Enemy forces are routed!', 'enemy', 'critical');


        this.visualEventQueue = [];
        const { attacker_losses_round = {}, defender_losses_round = {} } = roundData;

        // Populate visual event queue with consolidated losses per unit type
        for (const unitType in attacker_losses_round) {
            if (Object.prototype.hasOwnProperty.call(attacker_losses_round, unitType) && attacker_losses_round[unitType] > 0) {
                this.visualEventQueue.push({
                    side: 'player',
                    unitType: unitType,
                    event: 'losses', // Changed event type
                    count: attacker_losses_round[unitType] // Added count
                });
            }
        }
        for (const unitType in defender_losses_round) {
            if (Object.prototype.hasOwnProperty.call(defender_losses_round, unitType) && defender_losses_round[unitType] > 0) {
                this.visualEventQueue.push({
                    side: 'enemy',
                    unitType: unitType,
                    event: 'losses', // Changed event type
                    count: defender_losses_round[unitType] // Added count
                });
            }
        }
        
        // Shuffle the event queue for more dynamic visualization if desired, or keep ordered by attacker/defender
        // For now, it processes attacker losses first, then defender losses for the round.

        const statusEl = document.querySelector('#battleStatus');
        if (statusEl) statusEl.textContent = `Visualizing Round ${roundData.round}...`;
        
        if (this.visualEventQueue.length > 0) {
            if (this.pacedVisualizationInterval) clearInterval(this.pacedVisualizationInterval); // Clear previous if any
            this.pacedVisualizationInterval = setInterval(() => this.processNextVisualEvent(), 1200 / this.battleSpeed);
        } else {
            // No losses in this round, move to next round after a short delay
            setTimeout(() => {
                this.currentVisualRoundIndex++;
                this.processNextRoundVisuals();
            }, 1000 / this.battleSpeed); // Delay before next round
        }
    }

    processNextVisualEvent() {
        if (this.visualEventQueue.length === 0) {
            if (this.pacedVisualizationInterval) clearInterval(this.pacedVisualizationInterval);
            this.pacedVisualizationInterval = null;
            
            // Current round's visual events are done, move to the next round
            this.addBattleLogEntry(`--- Round ${this.currentBattleLog[this.currentVisualRoundIndex].round} Ends ---`, 'system', 'round-end');
            this.currentVisualRoundIndex++;
            // Add a slight delay before starting next round's visuals for better pacing
            setTimeout(() => {
                this.processNextRoundVisuals();
            }, 1500 / this.battleSpeed); // Delay before next round processing
            return;
        }

        const event = this.visualEventQueue.shift(); // Event is now { side, unitType, event: 'losses', count }
        const unitStats = this.unitStats[event.unitType];
        if (!unitStats) {
            console.warn(`processVisualEvent: No stats for ${event.unitType}`);
            return;
        }

        const lossesToProcess = event.count;
        if (lossesToProcess <= 0) return;

        const armyDisplay = event.side === 'player' ? this.playerArmyDisplay : this.enemyArmyDisplay;
        const unitTypeDisplay = armyDisplay[event.unitType];
        const unitTypeHealthPool = event.side === 'player' ? this.playerUnitTypeHealth : this.enemyUnitTypeHealth;
        const overallArmyHealth = event.side === 'player' ? this.playerHealth : this.enemyHealth;
        // Morale updates are now primarily driven by the per-round data from server, not individual visual losses.

        if (unitTypeDisplay && unitTypeDisplay.current > 0) {
            const actualLossesApplied = Math.min(lossesToProcess, unitTypeDisplay.current); // Don't lose more than displayed
            
            unitTypeDisplay.current -= actualLossesApplied;
            unitTypeDisplay.current = Math.max(0, unitTypeDisplay.current); // Ensure not negative

            this.updateUnitDisplay(event.side, event.unitType, unitTypeDisplay.current, unitTypeDisplay.max);

            // Update health pools based on consolidated losses
            if (unitTypeHealthPool[event.unitType]) {
                const healthPerUnit = unitStats.baseHealth || 1; // Health value of one unit
                const totalHealthLost = actualLossesApplied * healthPerUnit;
                
                unitTypeHealthPool[event.unitType].current = Math.max(0, unitTypeHealthPool[event.unitType].current - totalHealthLost);
                overallArmyHealth.current = Math.max(0, overallArmyHealth.current - totalHealthLost);

                this.updateUnitTypeHealthDisplay(event.side, event.unitType, unitTypeHealthPool[event.unitType].current, unitTypeHealthPool[event.unitType].max);
                this.updateHealthBar(event.side, overallArmyHealth.current, overallArmyHealth.max);
            }
            
            const isUnitTypeDepleted = unitTypeDisplay.current <= 0;
            // Perform one animation, maybe make it more intense if actualLossesApplied is large
            this.animateUnitDamage(event.side, event.unitType, actualLossesApplied, isUnitTypeDepleted);
            
            let lossMessage = `${event.side === 'player' ? 'Your' : 'Enemy'} ${unitStats.name} `;
            if (actualLossesApplied > 1) {
                lossMessage += `suffer ${actualLossesApplied} losses!`;
            } else {
                lossMessage += 'suffers a loss!'; // Changed to simple string
            }
            if (isUnitTypeDepleted) {
                lossMessage += ' (Contingent wiped out!)'; // Changed to simple string
            }
            this.addBattleLogEntry(lossMessage, event.side, 'loss');

        } else if (lossesToProcess > 0) {
            // This case means visualEventQueue had losses for a unit type already at 0 display count.
            // Log it for debugging, but visually nothing more to do for this unit type.
            console.warn(`Attempted to process ${lossesToProcess} losses for ${event.unitType} (${event.side}) but display count is already 0.`);
        }
    }
    
    updateUnitTypeHealthDisplay(side, unitType, current, max) {
        const barFill = document.querySelector(`#${side === 'player' ? 'attackerUnits' : 'defenderUnits'} [data-unit="${unitType}"] .unit-type-health-bar-fill`);
        if (barFill && max > 0) {
            const percentage = Math.max(0, (current / max) * 100);
            barFill.style.width = `${percentage.toFixed(0)}%`;
        }
        const healthTitle = document.querySelector(`#${side === 'player' ? 'attackerUnits' : 'defenderUnits'} [data-unit="${unitType}"] .unit-type-health-bar-container`);
        if(healthTitle) healthTitle.title = `Health: ${Math.round(current)}/${Math.round(max)}`;
    }

    updateUnitTypeMoraleDisplay(side, unitType, moraleValue) {
        const barFill = document.querySelector(`#${side === 'player' ? 'attackerUnits' : 'defenderUnits'} [data-unit="${unitType}"] .unit-type-morale-bar-fill`);
        if (barFill) {
            const clampedMorale = Math.max(0, Math.min(100, moraleValue));
            barFill.style.width = `${clampedMorale.toFixed(0)}%`;
            barFill.style.backgroundColor = this.getMoraleColor(clampedMorale);
        }
         const moraleTitle = document.querySelector(`#${side === 'player' ? 'attackerUnits' : 'defenderUnits'} [data-unit="${unitType}"] .unit-type-morale-bar-container`);
        if(moraleTitle) moraleTitle.title = `Morale: ${Math.round(moraleValue)}%`;
    }

    updateOverallArmyMorale(side) {
        const unitTypeMoralePool = side === 'player' ? this.playerUnitTypeMorale : this.enemyUnitTypeMorale;
        const unitTypeHealthPool = side === 'player' ? this.playerUnitTypeHealth : this.enemyUnitTypeHealth; // To get initial counts for weighting
        let totalWeightedMorale = 0;
        let totalInitialCountWeight = 0;

        for(const unitType in unitTypeMoralePool) {
            const initialData = this.battleData[side === 'player' ? 'playerArmy' : 'enemyArmy'];
            if (initialData && initialData[unitType] > 0) { // Consider units initially present
                 const weight = initialData[unitType]; 
                 totalWeightedMorale += (unitTypeMoralePool[unitType] ?? this.unitStats[unitType]?.baseMorale ?? 100) * weight;
                 totalInitialCountWeight += weight;
            }
        }
        const averageMorale = totalInitialCountWeight > 0 ? totalWeightedMorale / totalInitialCountWeight : 0;
        
        if (side === 'player') this.playerMorale = averageMorale;
        else this.enemyMorale = averageMorale;
        this.updateMoraleBar(side, averageMorale);
    }

    getMoraleColor(morale) {
        if (morale > 70) return '#60a5fa'; 
        if (morale > 30) return '#fbbf24'; 
        return '#f87171'; 
    }

    finalizeBattleDisplay(serverResponse) { // Accept serverResponse as a parameter
        if (this.animationDisplayTimerInterval) clearInterval(this.animationDisplayTimerInterval); this.animationDisplayTimerInterval = null; // Stop animation timer
        // const serverResponse = this.serverResponse; // No longer use this.serverResponse directly
        if (!serverResponse || !serverResponse.success) {
            this.handleBattleError(serverResponse?.error || "Battle conclusion error.");
            this.processingServerOutcome = false; // Ensure this is reset
            return;
        }
        this.processingServerOutcome = false; // Mark processing as done

        const outcome = serverResponse.result;
        let outcomeTitle = outcome.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        if (outcome === 'attacker_victory') outcomeTitle = 'VICTORY!';
        else if (outcome === 'defender_victory') outcomeTitle = 'DEFEAT!';
        
        this.addBattleLogEntry(`Final Outcome: ${outcomeTitle}!`, outcome === 'attacker_victory' ? 'player' : (outcome === 'defender_victory' ? 'enemy' : 'draw'), 'system');
        this.showNotification(`Battle Concluded: ${outcomeTitle}`, outcome === 'attacker_victory' ? 'success' : (outcome === 'defender_victory' ? 'error' : 'info'));
        
        // Update final morale from server if available (using the passed serverResponse)
        if (typeof serverResponse.attacker_morale_final !== 'undefined') this.updateMoraleBar('player', serverResponse.attacker_morale_final);
        if (typeof serverResponse.defender_morale_final !== 'undefined') this.updateMoraleBar('enemy', serverResponse.defender_morale_final);

        // Display plundered resources
        if (serverResponse.resources_plundered && Object.keys(serverResponse.resources_plundered).length > 0 && serverResponse.result === 'attacker_victory') {
            let plunderMsg = "Resources Plundered: ";
            for (const res in serverResponse.resources_plundered) {
                if (Object.prototype.hasOwnProperty.call(serverResponse.resources_plundered, res)) {
                     plunderMsg += `${res}: ${serverResponse.resources_plundered[res]} `;
                }
            }
            this.addBattleLogEntry(plunderMsg, 'system', 'info');
        }
        
        const statusEl = document.querySelector('#battleStatus');
        if (statusEl) statusEl.textContent = `Battle Concluded: ${outcomeTitle}`;
        this.showBattleResultsOverlay(outcome); // This likely uses this.serverResponse or needs update

        // Offer to view full report
        const battleReportLink = document.getElementById('battleReportLink');
        if (battleReportLink && serverResponse?.battle_id) {
            battleReportLink.href = `battle_report.php?id=${serverResponse.battle_id}`;
            battleReportLink.style.display = 'inline-block';
            battleReportLink.textContent = 'View Full Report';
        }
        
        const closeButton = document.querySelector('#battleModal .btn-close-battle');
        if(closeButton) closeButton.textContent = "Close";


        // The original setTimeout for completeBattle might still be relevant
        // but ensure it uses the passed serverResponse if needed by completeBattle.
        // For now, the primary goal is to use the passed serverResponse in this function.
        // The showBattleResultsOverlay and completeBattle might need to be checked if they rely on this.serverResponse
        // setTimeout(() => {
        //    if (this.processingServerOutcome) this.completeBattle(serverResponse); // This logic might be redundant if processingServerOutcome is set to false above
        // }, 6000);
    }
    
    showBattleResultsOverlay(outcomeKey) {
        const existingOverlay = document.querySelector('.battle-results-overlay');
        if (existingOverlay) existingOverlay.remove();
        const resultsDiv = document.createElement('div');
        resultsDiv.className = 'battle-results-overlay';
        let title;
        let messageText;
        let showSpinner = false;
        let isError = false;

        switch (outcomeKey) {
            case 'attacker_victory': title = 'VICTORY!'; messageText = 'The server confirms your forces have triumphed!'; break;
            case 'defender_victory': title = 'DEFEAT!'; messageText = 'The server confirms your forces were defeated.'; break;
            case 'draw': title = 'DRAW!'; messageText = 'The server confirms the battle ended in a draw.'; break;
            case 'pending': title = 'Awaiting Server...'; messageText = 'Final outcome is being calculated by the server.'; showSpinner = true; break;
            case 'skipped': title = 'Battle Skipped'; messageText = 'Awaiting final results from server.'; showSpinner = true; break;
            case 'error': title = 'Battle Error'; messageText = 'An error occurred. Please check notifications.'; isError = true; break;
            default: title = 'Battle Concluded'; messageText = 'Finalizing results...'; showSpinner = true;
        }

        resultsDiv.innerHTML = `
            <div class="battle-results-content-wrapper ${isError ? 'text-danger' : ''}">
                <h2>${title}</h2>
                <p>${messageText}</p>
                <div class="final-stats-summary">
                    <div>Visualization Time: ${Math.floor(this.battleTimer / 60)}:${(this.battleTimer % 60).toString().padStart(2, '0')}</div>
                    <div>Units Committed: ${this.totalSelected}</div>
                </div>
                ${showSpinner ? `
                <div class="d-flex justify-content-center my-3">
                    <div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading...</span></div>
                </div>` : `
                <div class="results-actions mt-3">
                    <p class="text-white-50 small">Page will reload with the full battle report shortly.</p>
                    <button class="btn btn-sm btn-light" id="battleOverlayReloadNowBtn">View Full Report Now</button>
                </div>`}
            </div>`;
        resultsDiv.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: rgba(0,0,0,0.9); color: white; padding: 25px; border-radius: 10px; text-align: center; z-index: 1100; min-width: 300px; box-shadow: 0 0 15px rgba(0,0,0,0.5);';

        const modalBody = document.querySelector('#battleModal .modal-body');
        if (modalBody) {
            modalBody.appendChild(resultsDiv);
            const reloadNowBtn = resultsDiv.querySelector('#battleOverlayReloadNowBtn');
            if (reloadNowBtn) {
                reloadNowBtn.addEventListener('click', () => {
                    if (this.serverResponse) this.completeBattle(this.serverResponse);
                    else window.location.reload();
                });
            }
        }
    }

    closeBattle() {
        this.battleInProgress = false;
        this.processingServerOutcome = false;
        this.serverResponse = null;

        if (this.battleInterval) clearInterval(this.battleInterval); this.battleInterval = null;
        if (this.pacedVisualizationInterval) clearInterval(this.pacedVisualizationInterval); this.pacedVisualizationInterval = null;
        
        this.hideBattleModal();
        const existingOverlay = document.querySelector('.battle-results-overlay');
        if (existingOverlay) existingOverlay.remove();
        const modalFooter = document.querySelector('#battleModal .battle-modal-footer');
        if (modalFooter) modalFooter.style.display = '';

        this.battleData = null;
        this.currentRoundDisplay = 1; this.updateRoundDisplay();
        this.battleTimer = 0; const timerEl = document.querySelector('#battleTimer'); if(timerEl) timerEl.textContent = "00:00";

        this.updateHealthBar('player', 0, 100); 
        this.updateHealthBar('enemy', 0, 100); 
        this.updateMoraleBar('player', 100); 
        this.updateMoraleBar('enemy', 100);

        const attackerUnitsEl = document.getElementById('attackerUnits');
        if (attackerUnitsEl) attackerUnitsEl.innerHTML = '<div class="p-3 text-white-50 small">Select units to deploy.</div>';
        const defenderUnitsEl = document.getElementById('defenderUnits');
        if (defenderUnitsEl) defenderUnitsEl.innerHTML = '<div class="p-3 text-white-50 small">Enemy forces unknown.</div>';
        const battleLogEl = document.getElementById('battleLog');
        if (battleLogEl) battleLogEl.innerHTML = '<div class="log-entry system"><i class="fas fa-info-circle me-2"></i><span>Battle interface ready.</span></div>';
        const progressBarEl = document.querySelector('#battleProgress');
        if (progressBarEl) progressBarEl.style.width = '0%';
        const statusEl = document.querySelector('#battleStatus');
        if (statusEl) statusEl.textContent = 'Awaiting battle initiation...';
    }

    showNotification(message, type = 'info') {
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; max-width: 350px;';
            document.body.appendChild(container);
        }
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`; 
        notification.innerHTML = `<div class="notification-content"><i class="fas ${this.getNotificationIcon(type)} me-2"></i><span>${message}</span></div>`;
        container.appendChild(notification);
        setTimeout(() => { notification.classList.add('show'); }, 100);
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => { notification.remove(); }, 300);
        }, type === 'error' ? 5000 : 3000);
    }

    getNotificationIcon(type) {
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        return icons[type] || 'fa-info-circle';
    }

    async submitBattleAjax(formData) {
        this.startBattle(); 
        try {
            const ajaxUrl = `${typeof baseUrl !== 'undefined' ? baseUrl : ''}backend/scripts/process_battle.php`;
            const response = await fetch(ajaxUrl, { method: 'POST', body: formData });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (this.battleInProgress || this.processingServerOutcome) { // Check if battle is still relevant
                this.serverResponse = result;
                // updateWaitingAnimation will see serverResponse and trigger displayServerOutcome
                // Or if updateWaitingAnimation interval already cleared, call directly
                if(!this.battleInterval && !this.processingServerOutcome) {
                    this.displayServerOutcome(this.serverResponse);
                }
            }
        } catch (error) {
            console.error('Battle submission error:', error);
            if (this.battleInProgress || this.processingServerOutcome) this.handleBattleError(`AJAX Error: ${error.message}`);
        }
    }

    completeBattle(serverResponse) {
        this.processingServerOutcome = false; 
        const statusEl = document.querySelector('#battleStatus');
        if (statusEl) statusEl.textContent = serverResponse?.success ? `Report: ${serverResponse.result}. Reloading...` : 'Error processing. Reloading...';
        this.showNotification('Loading full battle report page...', 'info');
        setTimeout(() => { window.location.reload(); }, 500);
    }

    handleBattleError(errorMessage) {
        this.battleInProgress = false;
        this.processingServerOutcome = false;
        if (this.battleInterval) clearInterval(this.battleInterval); this.battleInterval = null;
        if (this.pacedVisualizationInterval) clearInterval(this.pacedVisualizationInterval); this.pacedVisualizationInterval = null;
        
        this.showNotification(errorMessage, 'error');
        this.showBattleResultsOverlay('error'); 
        
        setTimeout(() => { if (!this.battleInProgress && !this.processingServerOutcome) this.closeBattle(); }, 7000); 
    }

    showBattleModal() {
        const modal = document.getElementById('battleModal');
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'block'; 
            document.body.classList.add('modal-open');
            
            let backdrop = document.querySelector('.modal-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
            } else { 
                backdrop.classList.add('show');
                backdrop.style.display = 'block'; // Ensure visible
            }
        }
    }

    hideBattleModal() {
        const modal = document.getElementById('battleModal');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) backdrop.remove(); 
        }
    }
    
    // Battle Control Methods (Pause, Resume, Speed for Paced Visualization)
    pauseVisualization() {
        if ((this.battleInProgress || this.pacedVisualizationInterval) && !this.battlePaused) {
            this.battlePaused = true;
            if (this.pacedVisualizationInterval) clearInterval(this.pacedVisualizationInterval);
            else if (this.battleInterval) clearInterval(this.battleInterval); // Pause waiting animation too
            this.showNotification('Visualization Paused', 'info');
            const pauseBtn = document.getElementById('pauseBattle');
            const resumeBtn = document.getElementById('resumeBattle');
            if(pauseBtn) pauseBtn.style.display = 'none';
            if(resumeBtn) resumeBtn.style.display = 'inline-block';
        }
    }
    resumeVisualization() {
        if (this.battlePaused) { // Check battlePaused state primarily
            this.battlePaused = false;
            if (this.visualEventQueue.length > 0 && this.serverResponse) { // Resuming paced visualization
                 if (this.pacedVisualizationInterval) clearInterval(this.pacedVisualizationInterval);
                this.pacedVisualizationInterval = setInterval(() => this.processNextVisualEvent(), 1000 / this.battleSpeed);
            } else if (!this.serverResponse) { // Resuming "waiting for server" animation
                if (this.battleInterval) clearInterval(this.battleInterval);
                this.battleInterval = setInterval(() => this.updateWaitingAnimation(), 1000 / this.battleSpeed);
            }
            this.showNotification('Visualization Resumed', 'info');
            const pauseBtn = document.getElementById('pauseBattle');
            const resumeBtn = document.getElementById('resumeBattle');
            if(pauseBtn) pauseBtn.style.display = 'inline-block';
            if(resumeBtn) resumeBtn.style.display = 'none';
        }
    }
    adjustVisualizationSpeed(factor) { 
        this.battleSpeed /= factor;
        if (this.battleSpeed < 0.25) this.battleSpeed = 0.25; 
        if (this.battleSpeed > 4) this.battleSpeed = 4;     
        
        const currentIntervalSpeed = 1000 / this.battleSpeed;

        if (!this.battlePaused) {
            if (this.pacedVisualizationInterval) {
                clearInterval(this.pacedVisualizationInterval);
                this.pacedVisualizationInterval = setInterval(() => this.processNextVisualEvent(), currentIntervalSpeed);
            } else if (this.battleInterval) { // Adjust waiting animation speed too
                clearInterval(this.battleInterval);
                this.battleInterval = setInterval(() => this.updateWaitingAnimation(), currentIntervalSpeed);
            }
        }
         this.showNotification(`Visualization Speed: ${ (1 / this.battleSpeed * 100).toFixed(0) }%`, 'info');
         // Update speed control display if it exists
         const speedControl = document.getElementById('battleSpeed');
         if(speedControl) speedControl.value = this.battleSpeed;
    }
    skipVisualization() {
        if (this.battleInProgress || this.pacedVisualizationInterval || !this.serverResponse) { // If battle is active or we are waiting
            if (this.pacedVisualizationInterval) clearInterval(this.pacedVisualizationInterval); this.pacedVisualizationInterval = null;
            if (this.battleInterval) clearInterval(this.battleInterval); this.battleInterval = null;
            
            this.visualEventQueue = []; 
            if (this.serverResponse) {
                this.finalizeBattleDisplay(); 
            } else {
                this.endBattle('skipped'); // Will show pending/skipped overlay
            }
            this.showNotification('Visualization Skipped', 'warning');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.battleSystem = new EnhancedBattleSystem();
});
