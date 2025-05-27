/**
 * Battle Report JavaScript Renderer
 * Handles AJAX loading and rendering of battle reports
 */

class BattleReportRenderer {
    constructor() {
        this.battleId = null;
        this.battleData = null;
    }

    /**
     * Initialize the battle report
     */
    init(battleId) {
        this.battleId = battleId;
        this.showLoading();
        this.loadBattleData();
    }

    /**
     * Show loading state
     */    showLoading() {
        const container = document.getElementById('battle-report-content');
        if (container) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">
                        <i class="fas fa-scroll"></i> Loading battle report...
                    </p>
                    <small class="text-muted">Please wait while we retrieve the battle data</small>
                </div>
            `;
        }
    }

    /**
     * Load battle data via AJAX
     */
    async loadBattleData() {
        try {
            const response = await fetch(`backend/scripts/get_battle_report.php?id=${this.battleId}`);
            const data = await response.json();
            
            if (data.success) {
                this.battleData = data;
                this.render();
            } else {
                this.showError(data.error || 'Failed to load battle report');
            }
        } catch (error) {
            console.error('Battle report load error:', error);
            this.showError('Failed to load battle report');
        }
    }

    /**
     * Show error message
     */    showError(message) {
        const container = document.getElementById('battle-report-content');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-3" style="font-size: 1.5rem;"></i>
                        <div>
                            <h5 class="mb-1">Unable to Load Battle Report</h5>
                            <p class="mb-2">${message}</p>
                            <div class="mt-3">
                                <a href="index.php?page=battle_history" class="btn btn-primary me-2">
                                    <i class="fas fa-history"></i> View Battle History
                                </a>
                                <a href="index.php?page=home" class="btn btn-secondary">
                                    <i class="fas fa-home"></i> Return to Home
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    /**
     * Render the complete battle report
     */    render() {
        const container = document.getElementById('battle-report-content');
        if (!container || !this.battleData) return;

        const { battle, losses, resources_plundered, battle_report } = this.battleData;

        // Update the battle date in the header
        this.updateBattleDate(battle.date);        container.innerHTML = `
            ${this.renderParticipants(battle)}
            ${this.renderOutcome(battle)}
            ${this.renderLosses(losses, battle)}
            ${this.renderResources(resources_plundered, battle)}
            ${this.renderDetailedReport(battle_report)}
        `;

        // Add fade-in animation
        container.style.opacity = '0';
        container.style.transition = 'opacity 0.5s ease-in-out';
        setTimeout(() => {
            container.style.opacity = '1';
        }, 100);
    }

    /**
     * Update the battle date in the header
     */
    updateBattleDate(dateString) {
        const dateElement = document.getElementById('battle-date');
        if (dateElement && dateString) {
            const date = new Date(dateString);
            const formattedDate = date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            dateElement.textContent = formattedDate;
        }
    }    /**
     * Render battle participants
     */
    renderParticipants(battle) {
        // Determine which side won and calculate power levels
        const attackerWon = battle.result === 'attacker_victory';
        const defenderWon = battle.result === 'defender_victory';
        const isDraw = battle.result === 'draw';
        
        // Calculate power indicators based on losses (lower losses = higher power)
        const attackerLosses = this.calculateTotalLosses(battle.losses?.attacker || {});
        const defenderLosses = this.calculateTotalLosses(battle.losses?.defender || {});
        
        const attackerPower = this.calculatePowerLevel(attackerLosses, defenderLosses, 'attacker');
        const defenderPower = this.calculatePowerLevel(defenderLosses, attackerLosses, 'defender');
        
        return `
            <div class="battle-participants-enhanced mb-4">
                <div class="participants-container">
                    <div class="participant-side attacker-side ${attackerWon ? 'winner' : defenderWon ? 'loser' : 'draw'}">
                        <div class="participant-header">                            <div class="participant-icon">
                                <i class="fas fa-fist-raised"></i>
                            </div>
                            <h3 class="participant-title">ATTACKER</h3>
                        </div>
                        <div class="participant-content">
                            <div class="participant-name">
                                <i class="fas fa-user-circle"></i>
                                ${battle.attacker_name}
                                ${battle.player_role === 'attacker' ? '<span class="you-badge">YOU</span>' : ''}
                            </div>
                            <div class="power-indicator">
                                <div class="power-label">Army Power</div>
                                <div class="power-bar">
                                    <div class="power-fill" style="width: ${attackerPower}%"></div>
                                </div>
                                <div class="power-text">${attackerPower}%</div>
                            </div>
                        </div>
                        ${attackerWon ? '<div class="victory-crown"><i class="fas fa-crown"></i></div>' : ''}
                    </div>
                    
                    <div class="vs-divider">
                        <div class="vs-circle">
                            <span class="vs-text">VS</span>
                            <div class="clash-effect"></div>
                        </div>
                    </div>
                    
                    <div class="participant-side defender-side ${defenderWon ? 'winner' : attackerWon ? 'loser' : 'draw'}">
                        <div class="participant-header">
                            <div class="participant-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3 class="participant-title">DEFENDER</h3>
                        </div>
                        <div class="participant-content">
                            <div class="participant-name">
                                <i class="fas fa-user-circle"></i>
                                ${battle.defender_name}
                                ${battle.player_role === 'defender' ? '<span class="you-badge">YOU</span>' : ''}
                            </div>
                            <div class="power-indicator">
                                <div class="power-label">Army Power</div>
                                <div class="power-bar">
                                    <div class="power-fill" style="width: ${defenderPower}%"></div>
                                </div>
                                <div class="power-text">${defenderPower}%</div>
                            </div>
                        </div>
                        ${defenderWon ? '<div class="victory-crown"><i class="fas fa-crown"></i></div>' : ''}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Render battle outcome
     */
    renderOutcome(battle) {
        let outcomeClass;
        let outcomeIcon;
        let outcomeText;
        let outcomeDescription;

        if (battle.result === 'attacker_victory') {
            if (battle.player_role === 'attacker') {
                outcomeClass = 'victory';
                outcomeIcon = 'trophy';
                outcomeText = 'Victory!';
                outcomeDescription = 'Your forces have prevailed on the battlefield!';
            } else {
                outcomeClass = 'defeat';
                outcomeIcon = 'skull-crossbones';
                outcomeText = 'Defeat!';
                outcomeDescription = 'Your defenses were overwhelmed by the enemy.';
            }
        } else if (battle.result === 'defender_victory') {
            if (battle.player_role === 'defender') {
                outcomeClass = 'victory';
                outcomeIcon = 'shield-alt';
                outcomeText = 'Victory!';
                outcomeDescription = 'Your defenses have held strong against the attack!';
            } else {
                outcomeClass = 'defeat';
                outcomeIcon = 'flag';
                outcomeText = 'Defeat!';
                outcomeDescription = 'Your attack was repelled by the enemy defenses.';
            }
        } else {
            outcomeClass = 'draw';
            outcomeIcon = 'balance-scale';
            outcomeText = 'Draw!';
            outcomeDescription = 'Neither side gained a decisive advantage.';
        }        return `
            <div class="battle-result mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-swords"></i> Battle Outcome
                    </div>
                    <div class="card-body">
                        <div class="battle-outcome-banner mb-4">
                            <div class="outcome-container ${outcomeClass}">
                                <div class="outcome-icon">
                                    <i class="fas fa-${outcomeIcon}"></i>
                                </div>
                                <div class="outcome-text">
                                    <h3>${outcomeText}</h3>
                                    <p>${outcomeDescription}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Render unit losses
     */
    renderLosses(losses, battle) {
        const attackerLossesHtml = this.renderUnitList(losses.attacker, 'Attacker Losses');
        const defenderLossesHtml = this.renderUnitList(losses.defender, 'Defender Losses');

        return `
            <div class="battle-losses mb-4">
                <div class="card">                    <div class="card-header">
                        <i class="fas fa-users-slash"></i> Battle Casualties
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="loss-card attacker mb-4">
                                    <h5 class="loss-header attacker-header">
                                        <i class="fas fa-skull-crossbones"></i> Attacker Losses
                                    </h5>
                                    ${attackerLossesHtml}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="loss-card defender mb-4">
                                    <h5 class="loss-header defender-header">
                                        <i class="fas fa-shield-alt"></i> Defender Losses
                                    </h5>
                                    ${defenderLossesHtml}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Render unit list
     */
    renderUnitList(units, title) {
        let hasLosses = false;
        let unitsHtml = '';

        for (const [unit, count] of Object.entries(units)) {
            if (count > 0) {
                hasLosses = true;
                unitsHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="unit-name">
                            <i class="fas fa-${this.getUnitIcon(unit)}"></i> ${this.capitalize(unit)}
                        </div>
                        <span class="badge bg-danger rounded-pill">${count}</span>
                    </li>
                `;
            }
        }

        if (!hasLosses) {
            unitsHtml = `
                <li class="list-group-item no-losses">
                    <i class="fas fa-check-circle"></i> No losses
                </li>
            `;
        }

        return `<ul class="list-group unit-list">${unitsHtml}</ul>`;
    }    /**
     * Render resources section
     */
    renderResources(resources, battle) {
        let hasResources = false;
        let resourcesHtml = '';

        // Sort resources by value (highest first) for better display
        const sortedResources = Object.entries(resources).sort((a, b) => b[1] - a[1]);

        for (const [resource, amount] of sortedResources) {
            if (amount > 0) {
                hasResources = true;
                resourcesHtml += `
                    <div class="resource-item-enhanced">
                        <div class="resource-icon-enhanced">
                            <i class="fas fa-${this.getResourceIcon(resource)}"></i>
                        </div>
                        <div class="resource-details-enhanced">
                            <div class="resource-name-enhanced">${this.capitalize(resource)}</div>
                            <div class="resource-amount-enhanced">
                                <span class="amount-value">${this.formatNumber(amount)}</span>
                                <span class="amount-suffix">units</span>
                            </div>
                        </div>
                        <div class="resource-gain-indicator">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                    </div>
                `;
            }
        }

        if (!hasResources) {
            resourcesHtml = `
                <div class="no-resources-enhanced">
                    <div class="no-resources-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="no-resources-text">
                        <h4>No Plunder</h4>
                        <p>No resources were captured in this battle</p>
                    </div>
                </div>
            `;
        }

        const playerRole = battle.player_role;
        const isAttacker = playerRole === 'attacker';
        const battleWon = (isAttacker && battle.result === 'attacker_victory') || 
                         (!isAttacker && battle.result === 'defender_victory');        let headerText;
        let headerIcon; 
        let headerClass;
        if (hasResources) {
            if (battleWon) {
                headerText = isAttacker ? 'Resources Plundered' : 'Resources Saved';
                headerIcon = isAttacker ? 'treasure-chest' : 'shield-alt';
                headerClass = 'success';
            } else {
                headerText = isAttacker ? 'Failed to Plunder' : 'Resources Lost';
                headerIcon = isAttacker ? 'times-circle' : 'exclamation-triangle';
                headerClass = 'danger';
            }
        } else {
            headerText = 'Resources Summary';
            headerIcon = 'coins';
            headerClass = 'neutral';
        }

        return `
            <div class="resources-section-enhanced mb-4">
                <div class="card">
                    <div class="card-header resources-header-enhanced ${headerClass}">
                        <h5>
                            <i class="fas fa-${headerIcon}"></i> ${headerText}
                        </h5>
                        ${hasResources ? '<div class="plunder-subtitle">Gained from this battle</div>' : ''}
                    </div>
                    <div class="card-body">
                        <div class="resources-container-enhanced">
                            ${resourcesHtml}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Render detailed battle report
     */    renderDetailedReport(battleReport) {
        if (!battleReport) {
            return `
                <div class="battle-details mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Detailed Report</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">No detailed battle report available.</p>
                        </div>
                    </div>
                </div>
            `;
        }

        // Apply formatting similar to battle-report-formatter.js
        const formattedReport = this.formatBattleReportText(battleReport);

        return `
            <div class="battle-details mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-scroll"></i> Detailed Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="battle-report-container">${formattedReport}</div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Format battle report text with enhanced styling
     */
    formatBattleReportText(originalText) {
        let formattedText = originalText;

        // Replace key terms with styled versions
        formattedText = formattedText.replace(/Battle Report/g, '<div class="report-title">⚔️ BATTLE REPORT</div>');
        formattedText = formattedText.replace(/Attacker:/g, '<div class="report-section-title text-primary">👥 ATTACKER:</div>');
        formattedText = formattedText.replace(/Defender:/g, '<div class="report-section-title text-danger">🛡️ DEFENDER:</div>');
        formattedText = formattedText.replace(/Victory!/g, '<span class="badge bg-success">✓ VICTORY!</span>');
        formattedText = formattedText.replace(/Defeat!/g, '<span class="badge bg-danger">✗ DEFEAT!</span>');
        formattedText = formattedText.replace(/Draw!/g, '<span class="badge bg-warning">◯ DRAW!</span>');
        formattedText = formattedText.replace(/Attacker Losses:/g, '<div class="report-subsection-title">⚔️ ATTACKER LOSSES:</div>');
        formattedText = formattedText.replace(/Defender Losses:/g, '<div class="report-subsection-title">⚔️ DEFENDER LOSSES:</div>');
        formattedText = formattedText.replace(/Resources Plundered:/g, '<div class="report-subsection-title">💰 RESOURCES PLUNDERED:</div>');

        // Add unit icons
        formattedText = formattedText.replace(/\b(fighters)\b/gi, '<i class="fas fa-user-shield"></i> Fighters');
        formattedText = formattedText.replace(/\b(shooters)\b/gi, '<i class="fas fa-crosshairs"></i> Shooters');
        formattedText = formattedText.replace(/\b(vehicles)\b/gi, '<i class="fas fa-truck-monster"></i> Vehicles');
        formattedText = formattedText.replace(/\b(riders)\b/gi, '<i class="fas fa-motorcycle"></i> Riders');
        formattedText = formattedText.replace(/\b(skirmishers)\b/gi, '<i class="fas fa-running"></i> Skirmishers');
        formattedText = formattedText.replace(/\b(canons)\b/gi, '<i class="fas fa-bomb"></i> Canons');
        formattedText = formattedText.replace(/\b(jets)\b/gi, '<i class="fas fa-fighter-jet"></i> Jets');
        formattedText = formattedText.replace(/\b(archers)\b/gi, '<i class="fas fa-bullseye"></i> Archers');
        formattedText = formattedText.replace(/\b(marauders)\b/gi, '<i class="fas fa-user-ninja"></i> Marauders');

        // Add resource icons
        formattedText = formattedText.replace(/\b(wood)\b/gi, '<i class="fas fa-tree"></i> Wood');
        formattedText = formattedText.replace(/\b(oil)\b/gi, '<i class="fas fa-oil-can"></i> Oil');
        formattedText = formattedText.replace(/\b(iron)\b/gi, '<i class="fas fa-hammer"></i> Iron');
        formattedText = formattedText.replace(/\b(food)\b/gi, '<i class="fas fa-drumstick-bite"></i> Food');
        formattedText = formattedText.replace(/\b(stone)\b/gi, '<i class="fas fa-cubes"></i> Stone');

        return formattedText;
    }

    /**
     * Get Font Awesome icon for units
     */
    getUnitIcon(unit) {
        const icons = {
            'fighters': 'user-shield',
            'shooters': 'crosshairs',
            'vehicles': 'truck-monster',
            'skirmishers': 'running',
            'riders': 'motorcycle',
            'canons': 'bomb',
            'jets': 'fighter-jet',
            'archers': 'bullseye',
            'marauders': 'user-ninja'
        };
        return icons[unit.toLowerCase()] || 'user-alt';
    }    /**
     * Calculate total losses for a side
     */
    calculateTotalLosses(losses) {
        if (!losses || typeof losses !== 'object') return 0;
        return Object.values(losses).reduce((total, count) => total + (Number.parseInt(count) || 0), 0);
    }

    /**
     * Calculate power level based on losses (inverse relationship)
     */
    calculatePowerLevel(ownLosses, enemyLosses, side) {
        const totalLosses = ownLosses + enemyLosses;
        if (totalLosses === 0) return 100; // No losses = full power
        
        // Higher enemy losses relative to own losses = higher power
        const powerRatio = totalLosses > 0 ? (enemyLosses / totalLosses) : 0.5;
        const basePower = Math.round(powerRatio * 100);
        
        // Ensure minimum of 25% and maximum of 100%
        return Math.min(100, Math.max(25, basePower));
    }

    /**
     * Get Font Awesome icon for resources
     */
    getResourceIcon(resource) {
        const icons = {
            'wood': 'tree',
            'oil': 'oil-can',
            'iron': 'hammer',
            'food': 'drumstick-bite',
            'stone': 'cubes'
        };
        return icons[resource.toLowerCase()] || 'box';
    }

    /**
     * Capitalize first letter
     */
    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }    /**
     * Format number with commas
     */
    formatNumber(num) {
        return Number.parseInt(num).toLocaleString();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on the battle report page and have a battle ID
    const urlParams = new URLSearchParams(window.location.search);
    const battleId = urlParams.get('id');
    const page = urlParams.get('page');
    
    if (page === 'battle_report' && battleId) {
        const renderer = new BattleReportRenderer();
        renderer.init(battleId);
    }
});
