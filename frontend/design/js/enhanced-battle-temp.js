/**
 * Enhanced Battle System JavaScript
 * Includes real-time battle animations and improved UI
 */

class EnhancedBattleSystem {
    constructor() {
        this.selectedUnits = {};
        this.totalSelected = 0;
        this.totalPower = 0;
        this.currentStrategy = 'balanced';
        this.battleSpeed = 1;
        this.battleInProgress = false;
        this.battleData = null;
        this.battleTimer = 0;
        this.battleInterval = null;
        this.currentRound = 1;
        this.totalRounds = 5;
        
        this.unitStats = {
            'fighters': { icon: 'fa-user-shield', attack: 15, defense: 12, name: 'Fighters', color: '#3b82f6' },
            'shooters': { icon: 'fa-crosshairs', attack: 20, defense: 8, name: 'Shooters', color: '#ef4444' },
            'vehicles': { icon: 'fa-truck-military', attack: 25, defense: 20, name: 'Vehicles', color: '#8b5cf6' },
            'riders': { icon: 'fa-horse', attack: 18, defense: 10, name: 'Riders', color: '#f59e0b' },
            'canons': { icon: 'fa-cannon', attack: 35, defense: 5, name: 'Cannons', color: '#dc2626' },
            'skirmishers': { icon: 'fa-running', attack: 12, defense: 15, name: 'Skirmishers', color: '#059669' },
            'jets': { icon: 'fa-fighter-jet', attack: 40, defense: 8, name: 'Jets', color: '#0891b2' },
            'archers': { icon: 'fa-bow-arrow', attack: 16, defense: 9, name: 'Archers', color: '#7c3aed' },
            'marauders': { icon: 'fa-mask', attack: 22, defense: 14, name: 'Marauders', color: '#be123c' }
        };
        
        this.strategies = {
            balanced: {
                name: 'Balanced',
                recommendations: ['fighters', 'shooters', 'vehicles'],
                description: 'Equal focus on offense and defense'
            },
            aggressive: {
                name: 'Aggressive',
                recommendations: ['shooters', 'canons', 'jets'],
                description: 'Maximum attack power, high risk'
            },
            defensive: {
                name: 'Defensive',
                recommendations: ['fighters', 'vehicles', 'skirmishers'],
                description: 'Minimize losses, steady advance'
            }
        };
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.updateStrategyRecommendations();
        this.updateTotalInfo();
        this.createParticleEffect();
    }

    bindEvents() {
        // Unit selection buttons
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

        // Strategy selection
        for (const radio of document.querySelectorAll('input[name="strategy"]')) {
            radio.addEventListener('change', (e) => this.handleStrategyChange(e));
        }

        // Unit input changes
        for (const input of document.querySelectorAll('.unit-input')) {
            input.addEventListener('input', (e) => this.handleUnitChange(e));
        }

        // Auto-select button
        const autoSelectBtn = document.getElementById('autoSelectBtn');
        if (autoSelectBtn) {
            autoSelectBtn.addEventListener('click', () => this.autoSelectUnits());
        }

        // Battle modal controls
        const battleModal = document.getElementById('battleModal');
        if (battleModal) {
            const speedControl = battleModal.querySelector('#battleSpeed');
            if (speedControl) {
                speedControl.addEventListener('change', (e) => {
                    this.battleSpeed = Number.parseFloat(e.target.value);
                });
            }

            const pauseBtn = battleModal.querySelector('#pauseBattle');
            if (pauseBtn) {
                pauseBtn.addEventListener('click', () => this.pauseBattle());
            }

            const resumeBtn = battleModal.querySelector('#resumeBattle');
            if (resumeBtn) {
                resumeBtn.addEventListener('click', () => this.resumeBattle());
            }

            const closeBattleBtn = battleModal.querySelector('#closeBattle');
            if (closeBattleBtn) {
                closeBattleBtn.addEventListener('click', () => this.closeBattle());
            }
        }

        // Form submission
        const battleForm = document.getElementById('battleForm');
        if (battleForm) {
            battleForm.addEventListener('submit', (e) => this.handleBattleSubmit(e));
        }
    }

    increaseUnit(e) {
        const btn = e.target.closest('.btn-increase');
        const unitType = btn.getAttribute('data-unit');
        const input = document.querySelector(`input[name="${unitType}"]`);
        const max = Number.parseInt(btn.getAttribute('data-max'));
        const current = Number.parseInt(input.value) || 0;
        
        if (current < max) {
            input.value = current + 1;
            input.dispatchEvent(new Event('input'));
        }
    }

    decreaseUnit(e) {
        const btn = e.target.closest('.btn-decrease');
        const unitType = btn.getAttribute('data-unit');
        const input = document.querySelector(`input[name="${unitType}"]`);
        const current = Number.parseInt(input.value) || 0;
        
        if (current > 0) {
            input.value = current - 1;
            input.dispatchEvent(new Event('input'));
        }
    }

    selectAllUnits(e) {
        const btn = e.target.closest('.btn-select-all');
        const unitType = btn.getAttribute('data-unit');
        const input = document.querySelector(`input[name="${unitType}"]`);
        const max = Number.parseInt(btn.getAttribute('data-max'));
        
        input.value = max;
        input.dispatchEvent(new Event('input'));
    }

    selectNoneUnits(e) {
        for (const input of document.querySelectorAll('.unit-input')) {
            input.value = 0;
            input.dispatchEvent(new Event('input'));
        }
    }

    handleUnitChange(e) {
        const input = e.target;
        const unitType = input.name;
        const value = Number.parseInt(input.value) || 0;
        const max = Number.parseInt(input.getAttribute('max'));
        
        // Validate input
        if (value > max) {
            input.value = max;
            return;
        }
        
        if (value < 0) {
            input.value = 0;
            return;
        }
        
        // Update selected units
        this.selectedUnits[unitType] = value;
        this.updateTotalInfo();
        this.updateUnitDisplay(unitType, value);
    }

    handleStrategyChange(e) {
        this.currentStrategy = e.target.value;
        this.updateStrategyRecommendations();
    }

    autoSelectUnits() {
        const strategy = this.strategies[this.currentStrategy];
        const recommendations = strategy.recommendations;
        
        // Clear current selections
        this.selectNoneUnits();
        
        // Auto-select recommended units with balanced distribution
        recommendations.forEach((unitType, index) => {
            const input = document.querySelector(`input[name="${unitType}"]`);
            if (input) {
                const max = Number.parseInt(input.getAttribute('max'));
                const percentage = index === 0 ? 0.4 : index === 1 ? 0.35 : 0.25;
                const suggested = Math.floor(max * percentage);
                
                input.value = Math.max(1, suggested);
                input.dispatchEvent(new Event('input'));
            }
        });
        
        this.showNotification(`Auto-selected units based on ${strategy.name} strategy!`, 'success');
    }

    updateTotalInfo() {
        this.totalSelected = 0;
        this.totalPower = 0;
        
        for (const [unitType, count] of Object.entries(this.selectedUnits)) {
            this.totalSelected += count;
            if (this.unitStats[unitType]) {
                this.totalPower += count * (this.unitStats[unitType].attack + this.unitStats[unitType].defense);
            }
        }
        
        // Update UI
        const totalSelectedElement = document.getElementById('totalSelected');
        const totalPowerElement = document.getElementById('totalPower');
        
        if (totalSelectedElement) {
            totalSelectedElement.textContent = this.totalSelected;
        }
        
        if (totalPowerElement) {
            totalPowerElement.textContent = this.totalPower;
        }
        
        // Update submit button state
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = this.totalSelected === 0;
        }
    }

    updateUnitDisplay(unitType, count) {
        const unitCard = document.querySelector(`[data-unit="${unitType}"]`);
        if (unitCard) {
            const countElement = unitCard.querySelector('.unit-count');
            if (countElement) {
                countElement.textContent = count;
                
                // Add visual feedback
                if (count > 0) {
                    unitCard.classList.add('selected');
                } else {
                    unitCard.classList.remove('selected');
                }
            }
        }
    }

    updateStrategyRecommendations() {
        const strategy = this.strategies[this.currentStrategy];
        const recommendationsContainer = document.getElementById('strategyRecommendations');
        
        if (recommendationsContainer && strategy) {
            recommendationsContainer.innerHTML = `
                <div class="strategy-info">
                    <h4><i class="fas fa-lightbulb"></i> ${strategy.name} Strategy</h4>
                    <p>${strategy.description}</p>
                    <div class="recommended-units">
                        <strong>Recommended Units:</strong>
                        ${strategy.recommendations.map(unitType => {
                            const unit = this.unitStats[unitType];
                            return `<span class="unit-badge" style="color: ${unit?.color || '#666'}">
                                <i class="fas ${unit?.icon || 'fa-question'}"></i> ${unit?.name || unitType}
                            </span>`;
                        }).join('')}
                    </div>
                </div>
            `;
        }
    }

    async handleBattleSubmit(e) {
        e.preventDefault();
        
        if (this.totalSelected === 0) {
            this.showNotification('Please select at least one unit!', 'error');
            return;
        }
        
        if (this.battleInProgress) {
            this.showNotification('Battle is already in progress!', 'warning');
            return;
        }
        
        // Use the existing form submission approach
        const form = e.target;
        const formData = new FormData(form);
        
        // Add attack action to form data
        formData.append('attack', '1');
        
        // Submit the form normally to allow PHP to handle it
        form.submit();
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `;
        
        // Add to page
        document.body.appendChild(notification);
        
        // Trigger animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        // Remove after delay
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        return icons[type] || 'fa-info-circle';
    }
}

// Initialize the battle system when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.battleSystem = new EnhancedBattleSystem();
    
    // Responsive design handler
    const handleResize = () => {
        const sidebar = document.querySelector('.battle-sidebar');
        if (sidebar) {
            if (window.innerWidth <= 768) {
                sidebar.style.position = 'relative';
                sidebar.style.right = 'auto';
                sidebar.style.top = 'auto';
                sidebar.style.transform = 'none';
                sidebar.style.width = '100%';
                sidebar.style.marginTop = '20px';
            } else {
                sidebar.style.position = 'fixed';
                sidebar.style.right = '20px';
                sidebar.style.top = '50%';
                sidebar.style.transform = 'translateY(-50%)';
                sidebar.style.width = '300px';
                sidebar.style.marginTop = '0';
            }
        }
    };
    
    window.addEventListener('resize', handleResize);
    handleResize(); // Initial call
});
