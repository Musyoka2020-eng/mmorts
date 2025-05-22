// Battle History animations and enhancements
document.addEventListener('DOMContentLoaded', () => {
    // Add animation effects to battle rows
    const battleRows = document.querySelectorAll('.battle-row');
    
    battleRows.forEach((row, index) => {
        // Add a slight delay to each row for a cascade effect
        setTimeout(() => {
            row.classList.add('battle-row-visible');
        }, 100 * index);
    });
    
    // Battle report expansion
    const viewReportButtons = document.querySelectorAll('.view-report-btn');
    
    for (const button of viewReportButtons) {
        button.addEventListener('click', function() {
            const battleId = this.getAttribute('data-battle-id');
            const reportContainer = document.getElementById(`report-${battleId}`);
            
            // Toggle the report visibility
            if (reportContainer.classList.contains('d-none')) {
                // Hide any other open reports
                const containers = document.querySelectorAll('.battle-report-container');
                for (const container of containers) {
                    container.classList.add('d-none');
                }
                
                // Show this report with animation
                reportContainer.classList.remove('d-none');
                setTimeout(() => {
                    reportContainer.classList.add('battle-report-visible');
                }, 10);
            } else {
                // Hide this report with animation
                reportContainer.classList.remove('battle-report-visible');
                setTimeout(() => {
                    reportContainer.classList.add('d-none');
                }, 300);
            }
        });
    }
    
    // Battle graph visualization
    const battleGraphs = document.querySelectorAll('.battle-graph');
    
    for (const graph of battleGraphs) {
        const attackerValue = Number.parseInt(graph.getAttribute('data-attacker-strength'));
        const defenderValue = Number.parseInt(graph.getAttribute('data-defender-strength'));
        const total = attackerValue + defenderValue;
        
        const attackerPercent = (attackerValue / total) * 100;
        const defenderPercent = (defenderValue / total) * 100;
        
        const attackerBar = graph.querySelector('.attacker-bar');
        const defenderBar = graph.querySelector('.defender-bar');
        
        // Set initial width to 0 for animation
        attackerBar.style.width = '0%';
        defenderBar.style.width = '0%';
        
        // Animate the bars
        setTimeout(() => {
            attackerBar.style.width = `${attackerPercent}%`;
            defenderBar.style.width = `${defenderPercent}%`;
        }, 300);
    }
    
    // Add hovering effects for battle units display
    const unitIcons = document.querySelectorAll('.unit-icon');
    
    for (const icon of unitIcons) {
        icon.addEventListener('mouseenter', function() {
            const unitTooltip = this.querySelector('.unit-tooltip');
            if (unitTooltip) {
                unitTooltip.classList.add('visible');
            }
        });
        
        icon.addEventListener('mouseleave', function() {
            const unitTooltip = this.querySelector('.unit-tooltip');
            if (unitTooltip) {
                unitTooltip.classList.remove('visible');
            }
        });
    }
});

// Function to format battle report for display
function formatBattleReport(reportText) {
    if (!reportText) return 'No detailed report available.';
    
    // Format the report text with highlights and formatting
    const formattedReport = reportText
        .replace(/Round (\d+)/g, '<strong class="text-warning">Round $1</strong>')
        .replace(/(Attack|Defense): (\d+)/g, '<span class="text-info">$1: <strong>$2</strong></span>')
        .replace(/(lost|destroyed|defeated)/gi, '<span class="text-danger">$1</span>')
        .replace(/(victory|won|captured)/gi, '<span class="text-success">$1</span>')
        .replace(/\n/g, '<br>');
        
    return formattedReport;
}
