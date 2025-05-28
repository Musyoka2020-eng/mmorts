/**
 * Enhanced Training Unit Tooltip System
 * Provides detailed unit statistics and combat efficiency analysis
 * for unit cards in the training page
 */

// Execute when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('Training unit tooltips loading...');
    console.log('Game config available:', !!window.gameConfig);
    if (window.gameConfig) {
        console.log('Unit stats available:', !!window.gameConfig.unitStats);
        console.log('Unit costs available:', !!window.gameConfig.unitCosts);
    }
    // Add a small delay to ensure training-enhanced.js has run first
    setTimeout(() => {
        initTrainingUnitTooltips();
    }, 200);
});

// Global configuration for unit analysis
const UNIT_ANALYSIS_CONFIG = {
    // Combat effectiveness thresholds
    EXCELLENT_THRESHOLD: 90,
    GOOD_THRESHOLD: 70,
    AVERAGE_THRESHOLD: 50,
    
    // Cost efficiency ratings
    VERY_EFFICIENT: 0.8,
    EFFICIENT: 0.6,
    MODERATE: 0.4,
    
    // Role effectiveness multipliers
    ROLE_MULTIPLIERS: {
        'tank': { defense: 1.5, attack: 0.8 },
        'damage': { attack: 1.5, defense: 0.8 },
        'balanced': { attack: 1.0, defense: 1.0 },
        'mobility': { speed: 1.5, capacity: 1.2 }
    }
};

/**
 * Initialize training unit tooltips
 */
function initTrainingUnitTooltips() {
    console.log('Initializing Training Unit Tooltips');
    
    // Create tooltip element if it doesn't already exist
    let tooltip = document.getElementById('training-unit-tooltip');
    
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'training-unit-tooltip';
        tooltip.className = 'training-tooltip';
        document.body.appendChild(tooltip);
    }
    
    // Get all unit cards with tooltip class
    const unitCards = document.querySelectorAll('.training-unit-tooltip');
    console.log(`Found ${unitCards.length} training unit cards for tooltips`);
    
    // Create handler functions
    const handleMouseEnter = function() {
        showTrainingTooltip(this, tooltip);
    };
    
    const handleMouseLeave = () => {
        hideTrainingTooltip(tooltip);
    };
    
    // Attach tooltip handlers to each unit card
    for (const card of unitCards) {
        // Skip cards that already have tooltip handlers
        if (card.hasAttribute('data-training-tooltip-initialized')) {
            continue;
        }
        
        // Mark this card as tooltip-initialized
        card.setAttribute('data-training-tooltip-initialized', 'true');
        
        // Add mouseenter/mouseleave listeners
        card.addEventListener('mouseenter', handleMouseEnter);
        card.addEventListener('mouseleave', handleMouseLeave);
    }
    
    // Also update on scroll
    window.addEventListener('scroll', () => {
        if (tooltip.classList.contains('active')) {
            const hoveredCard = document.querySelector('.training-unit-tooltip:hover');
            if (hoveredCard) {
                showTrainingTooltip(hoveredCard, tooltip);
            } else {
                hideTrainingTooltip(tooltip);
            }
        }
    }, { passive: true });
    
    // Re-initialize on window resize for correct positioning
    window.addEventListener('resize', () => {
        const hoveredCard = document.querySelector('.training-unit-tooltip:hover');
        if (hoveredCard && tooltip.classList.contains('active')) {
            showTrainingTooltip(hoveredCard, tooltip);
        }
    }, { passive: true });
    
    console.log('Training unit tooltips initialized successfully');
}

/**
 * Show tooltip for a specific unit card
 */
function showTrainingTooltip(unitCard, tooltip) {
    const unitType = unitCard.getAttribute('data-unit');
    if (!unitType) return;
    
    // Get unit data from the global game config or parse from the card
    const unitData = getUnitDataFromCard(unitCard, unitType);
    if (!unitData) return;
    
    // Generate tooltip content
    const tooltipContent = generateTrainingTooltipContent(unitData);
    
    // Set tooltip content
    tooltip.innerHTML = tooltipContent;
    
    // Make tooltip visible to calculate size
    tooltip.style.display = 'block';
    tooltip.classList.add('active');
    
    // Position the tooltip
    positionTrainingTooltip(unitCard, tooltip);
}

/**
 * Extract unit data from the card and available sources
 */
function getUnitDataFromCard(unitCard, unitType) {
    // Try to get data from global game config if available
    if (window.gameConfig?.unitStats?.[unitType]) {
        const stats = window.gameConfig.unitStats[unitType];
        const costs = window.gameConfig.unitCosts?.[unitType] || {};
        const display = window.gameConfig.unitDisplay?.[unitType] || {};
        
        return {
            type: unitType,
            name: display.name || unitType.charAt(0).toUpperCase() + unitType.slice(1),
            icon: display.icon || '⚔️',
            stats: stats,
            costs: costs,
            category: display.category || 'unknown'
        };
    }
    
    // Fallback: parse data from the card itself
    const nameElement = unitCard.querySelector('.unit-name');
    const iconElement = unitCard.querySelector('.unit-icon');
    const statElements = unitCard.querySelectorAll('.stat-item');
    const costElements = unitCard.querySelectorAll('.cost-item');
    
    const stats = {};
    const costs = {};
      // Parse stats from stat elements
    for (const statElement of statElements) {
        const label = statElement.querySelector('.stat-label')?.textContent.toLowerCase();
        const value = Number.parseInt(statElement.querySelector('.stat-value')?.textContent) || 0;
        if (label) {
            stats[label] = value;
        }
    }
    
    // Parse costs from cost elements
    for (const costElement of costElements) {
        const costText = costElement.textContent;
        const costMatch = costText.match(/(\d+)/);
        const resourceClass = costElement.querySelector('.cost-icon')?.className;
        
        if (costMatch && resourceClass) {
            const resourceType = resourceClass.replace('cost-icon training-resource-', '');
            costs[resourceType] = Number.parseInt(costMatch[1]);
        }
    }
    
    return {
        type: unitType,
        name: nameElement?.textContent || unitType.charAt(0).toUpperCase() + unitType.slice(1),
        icon: iconElement?.textContent || '⚔️',
        stats: stats,
        costs: costs,
        category: 'unknown'
    };
}

/**
 * Generate comprehensive tooltip content with combat analysis
 */
function generateTrainingTooltipContent(unitData) {
    const { name, icon, stats, costs, category, type } = unitData;
    
    // Calculate combat effectiveness
    const combatAnalysis = calculateCombatEffectiveness(stats, costs, type);
    
    // Generate HTML content
    return `
        <div class="training-tooltip-header">
            <div class="tooltip-unit-icon">${icon}</div>
            <div class="tooltip-unit-info">
                <h3 class="tooltip-unit-name">${name}</h3>
                <div class="tooltip-unit-category">${getCategoryDisplayName(category)}</div>
            </div>
        </div>
        
        <div class="training-tooltip-content">
            ${generateStatsSection(stats)}
            ${generateCostSection(costs)}
            ${generateEfficiencySection(combatAnalysis)}
            ${generateTacticalSection(combatAnalysis, type)}
        </div>
    `;
}

/**
 * Generate the stats section
 */
function generateStatsSection(stats) {
    if (!stats || Object.keys(stats).length === 0) {
        return '<div class="tooltip-section">No stats available</div>';
    }
    
    return `
        <div class="tooltip-section">
            <h4><i class="fas fa-chart-bar"></i> Combat Statistics</h4>
            <div class="tooltip-stats-grid">
                ${generateStatItem('Attack', stats.attack, 'fa-skull', '#e74c3c')}
                ${generateStatItem('Defense', stats.defense, 'fa-shield-alt', '#3498db')}
                ${generateStatItem('Speed', stats.speed, 'fa-tachometer-alt', '#f39c12')}
                ${generateStatItem('Capacity', stats.capacity, 'fa-weight-hanging', '#9b59b6')}
            </div>
        </div>
    `;
}

/**
 * Generate individual stat item
 */
function generateStatItem(label, value, icon, color) {
    if (value === undefined || value === null) return '';
    
    // Calculate stat rating
    const rating = getStatRating(label.toLowerCase(), value);
    const ratingClass = rating.toLowerCase().replace(' ', '-');
    
    return `
        <div class="tooltip-stat-item">
            <div class="stat-icon" style="color: ${color}">
                <i class="fas ${icon}"></i>
            </div>
            <div class="stat-details">
                <div class="stat-label">${label}</div>
                <div class="stat-value">${value}</div>
                <div class="stat-rating ${ratingClass}">${rating}</div>
            </div>
        </div>
    `;
}

/**
 * Generate the cost section
 */
function generateCostSection(costs) {
    if (!costs || Object.keys(costs).length === 0) {
        return '<div class="tooltip-section">No cost information available</div>';
    }
    
    const totalCost = Object.values(costs).reduce((sum, cost) => sum + cost, 0);
    
    return `
        <div class="tooltip-section">
            <h4><i class="fas fa-coins"></i> Training Cost</h4>
            <div class="tooltip-costs-grid">
                ${Object.entries(costs).map(([resource, cost]) => 
                    generateCostItem(resource, cost)
                ).join('')}
            </div>
            <div class="total-cost">Total Resources: <span class="cost-value">${totalCost}</span></div>
        </div>
    `;
}

/**
 * Generate individual cost item
 */
function generateCostItem(resource, cost) {
    const resourceIcons = {
        wood: '🪵',
        iron: '⚙️',
        food: '🍞',
        oil: '🛢️',
        stone: '🗿'
    };
    
    return `
        <div class="tooltip-cost-item">
            <span class="cost-icon">${resourceIcons[resource] || '📦'}</span>
            <span class="cost-label">${resource.charAt(0).toUpperCase() + resource.slice(1)}</span>
            <span class="cost-value">${cost}</span>
        </div>
    `;
}

/**
 * Generate the efficiency analysis section
 */
function generateEfficiencySection(analysis) {
    return `
        <div class="tooltip-section">
            <h4><i class="fas fa-analytics"></i> Combat Efficiency</h4>
            <div class="efficiency-grid">
                <div class="efficiency-item">
                    <span class="efficiency-label">Cost Effectiveness:</span>
                    <span class="efficiency-value ${analysis.costEfficiency.class}">${analysis.costEfficiency.rating}</span>
                </div>
                <div class="efficiency-item">
                    <span class="efficiency-label">Combat Power:</span>
                    <span class="efficiency-value ${analysis.combatPower.class}">${analysis.combatPower.rating}</span>
                </div>
                <div class="efficiency-item">
                    <span class="efficiency-label">Overall Rating:</span>
                    <span class="efficiency-value ${analysis.overall.class}">${analysis.overall.rating}</span>
                </div>
            </div>
        </div>
    `;
}

/**
 * Generate the tactical analysis section
 */
function generateTacticalSection(analysis, unitType) {
    const roleInfo = getUnitRoleInfo(unitType, analysis);
    
    return `
        <div class="tooltip-section">
            <h4><i class="fas fa-chess"></i> Tactical Analysis</h4>
            <div class="tactical-info">
                <div class="role-info">
                    <strong>Primary Role:</strong> ${roleInfo.primary}
                </div>
                <div class="strengths">
                    <strong>Strengths:</strong>
                    <ul>
                        ${roleInfo.strengths.map(strength => `<li>${strength}</li>`).join('')}
                    </ul>
                </div>
                <div class="recommendations">
                    <strong>Best Used For:</strong>
                    <ul>
                        ${roleInfo.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                    </ul>
                </div>
            </div>
        </div>
    `;
}

/**
 * Calculate combat effectiveness and efficiency ratings
 */
function calculateCombatEffectiveness(stats, costs, unitType) {
    const attack = stats.attack || 0;
    const defense = stats.defense || 0;
    const speed = stats.speed || 0;
    const capacity = stats.capacity || 0;
    
    // Calculate total combat power
    const combatPower = (attack * 1.2) + (defense * 1.0) + (speed * 0.8) + (capacity * 0.5);
    
    // Calculate total cost
    const totalCost = Object.values(costs).reduce((sum, cost) => sum + cost, 0) || 1;
    
    // Cost effectiveness (power per resource)
    const costEffectiveness = combatPower / totalCost;
    
    // Determine ratings
    const costEfficiencyRating = getCostEfficiencyRating(costEffectiveness);
    const combatPowerRating = getCombatPowerRating(combatPower);
    const overallRating = getOverallRating(costEffectiveness, combatPower);
    
    return {
        costEfficiency: costEfficiencyRating,
        combatPower: combatPowerRating,
        overall: overallRating,
        rawValues: {
            combatPower,
            costEffectiveness,
            totalCost
        }
    };
}

/**
 * Get stat rating based on value
 */
function getStatRating(statType, value) {
    const thresholds = {
        attack: { excellent: 100, good: 70, average: 40 },
        defense: { excellent: 80, good: 60, average: 40 },
        speed: { excellent: 150, good: 100, average: 60 },
        capacity: { excellent: 20, good: 15, average: 10 }
    };
    
    const threshold = thresholds[statType] || thresholds.attack;
    
    if (value >= threshold.excellent) return 'Excellent';
    if (value >= threshold.good) return 'Good';
    if (value >= threshold.average) return 'Average';
    return 'Poor';
}

/**
 * Get cost efficiency rating
 */
function getCostEfficiencyRating(effectiveness) {
    if (effectiveness >= UNIT_ANALYSIS_CONFIG.VERY_EFFICIENT) {
        return { rating: 'Excellent', class: 'excellent' };
    } 
    if (effectiveness >= UNIT_ANALYSIS_CONFIG.EFFICIENT) {
        return { rating: 'Good', class: 'good' };
    } 
    if (effectiveness >= UNIT_ANALYSIS_CONFIG.MODERATE) {
        return { rating: 'Average', class: 'average' };
    } 
    return { rating: 'Poor', class: 'poor' };
}

/**
 * Get combat power rating
 */
function getCombatPowerRating(power) {
    if (power >= 200) {
        return { rating: 'Elite', class: 'elite' };
    } 
    if (power >= 150) {
        return { rating: 'Strong', class: 'strong' };
    } 
    if (power >= 100) {
        return { rating: 'Average', class: 'average' };
    } 
    return { rating: 'Weak', class: 'weak' };
}

/**
 * Get overall rating
 */
function getOverallRating(costEffectiveness, combatPower) {
    const combinedScore = (costEffectiveness * 100) + (combatPower / 2);
    
    if (combinedScore >= 150) {
        return { rating: 'S-Tier', class: 's-tier' };
    } 
    if (combinedScore >= 120) {
        return { rating: 'A-Tier', class: 'a-tier' };
    } 
    if (combinedScore >= 90) {
        return { rating: 'B-Tier', class: 'b-tier' };
    } 
    if (combinedScore >= 60) {
        return { rating: 'C-Tier', class: 'c-tier' };
    } 
    return { rating: 'D-Tier', class: 'd-tier' };
}

/**
 * Get unit role and tactical information
 */
function getUnitRoleInfo(unitType, analysis) {
    const roleData = {
        fighters: {
            primary: 'Front-line Infantry',
            strengths: ['Balanced combat stats', 'Cost effective', 'Reliable in most situations'],
            recommendations: ['Early game armies', 'Defensive formations', 'Mixed unit compositions']
        },
        shooters: {
            primary: 'Ranged DPS',
            strengths: ['High attack power', 'Good range capabilities', 'Effective against light units'],
            recommendations: ['Offensive operations', 'Support for heavy units', 'Harassment tactics']
        },
        vehicles: {
            primary: 'Heavy Assault',
            strengths: ['Excellent armor', 'High durability', 'Good capacity'],
            recommendations: ['Tank roles', 'Breaking enemy lines', 'Resource transport']
        },
        riders: {
            primary: 'Fast Strike',
            strengths: ['High mobility', 'Quick deployment', 'Hit and run tactics'],
            recommendations: ['Raiding operations', 'Quick strikes', 'Flanking maneuvers']
        },
        canons: {
            primary: 'Artillery',
            strengths: ['Massive attack power', 'Area denial', 'Fortress breaking'],
            recommendations: ['Siege warfare', 'Heavy fortifications', 'Long-range support']
        },
        skirmishers: {
            primary: 'Defensive Specialist',
            strengths: ['Good defense', 'Balanced mobility', 'Versatile deployment'],
            recommendations: ['Defensive lines', 'Area control', 'Support operations']
        },
        jets: {
            primary: 'Air Superiority',
            strengths: ['Extreme speed', 'Elite attack power', 'Air dominance'],
            recommendations: ['Elite operations', 'Quick response', 'High-value targets']
        },
        archers: {
            primary: 'Precision Ranged',
            strengths: ['Accurate strikes', 'Good mobility', 'Cost effective'],
            recommendations: ['Precision operations', 'Support fire', 'Light harassment']
        },
        marauders: {
            primary: 'Elite Raiders',
            strengths: ['High attack', 'Good mobility', 'Devastating strikes'],
            recommendations: ['Elite raids', 'High-value targets', 'Shock tactics']
        }
    };
    
    return roleData[unitType] || {
        primary: 'Unknown',
        strengths: ['No data available'],
        recommendations: ['Requires analysis']
    };
}

/**
 * Get category display name
 */
function getCategoryDisplayName(category) {
    const categoryNames = {
        basic: '⚔️ Infantry Forces',
        advanced: '🚗 Heavy Units',
        special: '⚡ Elite Forces'
    };
    
    return categoryNames[category] || category.charAt(0).toUpperCase() + category.slice(1);
}

/**
 * Position the tooltip relative to the unit card
 */
function positionTrainingTooltip(unitCard, tooltip) {
    // Get dimensions
    const tooltipWidth = tooltip.offsetWidth;
    const tooltipHeight = tooltip.offsetHeight;
    
    // Get card position relative to viewport
    const rect = unitCard.getBoundingClientRect();
    
    // Default position (to the right of the card)
    let top = rect.top;
    let left = rect.right + 15;
    
    // Adjust position if tooltip would be off screen
    // If too close to the right edge, position to the left
    if (left + tooltipWidth > window.innerWidth - 10) {
        left = rect.left - tooltipWidth - 15;
    }
    
    // If too close to the bottom edge, position above
    if (top + tooltipHeight > window.innerHeight - 10) {
        top = rect.bottom - tooltipHeight;
    }
    
    // If too close to the top edge
    if (top < 10) {
        top = 10;
    }
    
    // If still too far left, center it
    if (left < 10) {
        left = Math.max(10, (window.innerWidth - tooltipWidth) / 2);
    }
    
    // Set position
    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;
}

/**
 * Hide the training tooltip
 */
function hideTrainingTooltip(tooltip) {
    tooltip.classList.remove('active');
    tooltip.style.display = 'none';
}

// Make the init function globally accessible for re-initialization
window.initTrainingUnitTooltips = initTrainingUnitTooltips;
