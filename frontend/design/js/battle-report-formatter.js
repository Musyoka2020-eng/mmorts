/**
 * Battle Report Formatter
 * Enhances the display of battle reports with better formatting and visual elements
 */
document.addEventListener('DOMContentLoaded', () => {
    // Apply the battle-report-page class to the main container if not already present
    const mainContainer = document.querySelector('.main');
    if (mainContainer && !mainContainer.classList.contains('battle-report-page')) {
        mainContainer.classList.add('battle-report-page');
    }

    // Find the battle report element - could be either pre.battle-report or div.battle-report-container
    const battleReportElement = document.querySelector('.battle-report') ||
        document.querySelector('pre.battle-report');

    console.log('Page initialized');
    console.log('Battle report element found:', !!battleReportElement);

    if (battleReportElement) {
        console.log('Formatting battle report');
        // Get the original content
        const originalContent = battleReportElement.innerHTML;

        // Format the battle report content
        battleReportElement.innerHTML = formatBattleReport(originalContent);
    }

    // Style the battle outcome banner if it exists
    styleOutcomeBanner();
});

/**
 * Style the battle outcome banner to match the design in the screenshot
 */
function styleOutcomeBanner() {
    // Target the outcome banner which might be in different formats
    const outcomeElement = document.querySelector('.battle-outcome-banner') ||
        document.querySelector('.battle-result');

    if (outcomeElement) {
        console.log('Styling outcome banner');
        // Apply additional styles if needed
    }
}

/**
 * Format battle report with enhanced styling
 * @param {string} originalText - The original battle report text
 * @return {string} - Formatted HTML content
 */
function formatBattleReport(originalText) {
    if (!originalText) return 'No battle report available.';

    console.log('Formatting text length:', originalText.length);

    // Check if we're working with a pre element or the text has been HTML encoded
    const isPreElement = document.querySelector('pre.battle-report') !== null;
    let formattedText = originalText;

    if (isPreElement) {
        // For pre elements, we need to be careful with HTML encoding
        // Convert the text to HTML safely
        const tempDiv = document.createElement('div');
        tempDiv.textContent = originalText;

        // Apply simple formatting for pre elements
        formattedText = tempDiv.innerHTML
            .replace(/Battle Report/g, '===== BATTLE REPORT =====')
            .replace(/Attacker:/g, '>> ATTACKER:')
            .replace(/Defender:/g, '>> DEFENDER:')
            .replace(/Victory!/g, '✓ VICTORY!')
            .replace(/Defeat!/g, '✗ DEFEAT!')
            .replace(/Draw!/g, '◯ DRAW!')
            .replace(/Attacker Losses:/g, '⚔️ ATTACKER LOSSES:')
            .replace(/Defender Losses:/g, '⚔️ DEFENDER LOSSES:')
            .replace(/Resources Plundered:/g, '💰 RESOURCES PLUNDERED:');

        return formattedText;
    }

    // For normal HTML elements, continue with the original approach
    // Replace HTML entities with their actual characters if needed
    formattedText = formattedText.replace(/&lt;/g, '<').replace(/&gt;/g, '>');

    // Add section formatting
    formattedText = formattedText.replace(/<h2>(.*?)<\/h2>/g, '<div class="report-title">$1</div>');
    formattedText = formattedText.replace(/<h3>(.*?)<\/h3>/g, '<div class="report-section-title">$1</div>');
    formattedText = formattedText.replace(/<h4>(.*?)<\/h4>/g, '<div class="report-subsection-title">$1</div>');

    // Format result styles
    formattedText = formattedText.replace(
        /<div class="result victory">(.*?)<\/div>/g,
        '<div class="result-box victory"><i class="fas fa-trophy"></i> $1</div>'
    );
    formattedText = formattedText.replace(
        /<div class="result defeat">(.*?)<\/div>/g,
        '<div class="result-box defeat"><i class="fas fa-skull-crossbones"></i> $1</div>'
    );
    formattedText = formattedText.replace(
        /<div class="result draw">(.*?)<\/div>/g,
        '<div class="result-box draw"><i class="fas fa-balance-scale"></i> $1</div>'
    );

    // Enhance unit counts
    formattedText = formattedText.replace(
        /: <span class="loss-count">(\d+)<\/span>/g,
        ': <span class="loss-count">-$1</span>'
    );
    formattedText = formattedText.replace(
        /: <span class="resource-amount">(\d+)<\/span>/g,
        ': <span class="resource-amount">+$1</span>'
    );

    // Add icons to units but avoid replacing text that's already been processed
    formattedText = formattedText.replace(/\b(fighters)\b/gi, '<i class="fas fa-user-shield"></i> Fighters');
    formattedText = formattedText.replace(/\b(shooters)\b/gi, '<i class="fas fa-crosshairs"></i> Shooters');
    formattedText = formattedText.replace(/\b(vehicles)\b/gi, '<i class="fas fa-truck-monster"></i> Vehicles');
    formattedText = formattedText.replace(/\b(skmisher)\b/gi, '<i class="fas fa-running"></i> Skirmishers');
    formattedText = formattedText.replace(/\b(rides)\b/gi, '<i class="fas fa-horse"></i> Riders');
    formattedText = formattedText.replace(/\b(canons)\b/gi, '<i class="fas fa-bomb"></i> Cannons');
    formattedText = formattedText.replace(/\b(jets)\b/gi, '<i class="fas fa-fighter-jet"></i> Jets');
    formattedText = formattedText.replace(/\b(archers)\b/gi, '<i class="fas fa-archway"></i> Archers');
    formattedText = formattedText.replace(/\b(marauders)\b/gi, '<i class="fas fa-user-ninja"></i> Marauders');

    // Add icons to resources
    formattedText = formattedText.replace(/\b(wood)\b/gi, '<i class="fas fa-tree"></i> Wood');
    formattedText = formattedText.replace(/\b(oil)\b/gi, '<i class="fas fa-oil-can"></i> Oil');
    formattedText = formattedText.replace(/\b(iron)\b/gi, '<i class="fas fa-hammer"></i> Iron');
    formattedText = formattedText.replace(/\b(food)\b/gi, '<i class="fas fa-drumstick-bite"></i> Food');
    formattedText = formattedText.replace(/\b(stone)\b/gi, '<i class="fas fa-cubes"></i> Stone');

    // Format lists and other elements
    formattedText = formattedText.replace(/<ul>/g, '<ul class="report-list">');
    formattedText = formattedText.replace(/<div class="battle-participants">/g, '<div class="report-participants">');
    formattedText = formattedText.replace(/<div class="battle-timestamp">(.*?)<\/div>/g, '<div class="report-timestamp"><i class="far fa-clock"></i> $1</div>');

    return formattedText;
}
