/**
 * Smooth map navigation with animations
 * This adds enhanced navigation with visual transitions
 */

document.addEventListener('DOMContentLoaded', () => {
    initSmoothMapNavigation();
});

/**
 * Initialize smooth map navigation
 * This sets up the loading overlay and visual elements
 * but doesn't set up click handlers (that's handled by ajax-map-loader.js)
 */
function initSmoothMapNavigation() {
    // Create loading overlay for transitions
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'map-loading-overlay';
    loadingOverlay.innerHTML = '<div class="loading-spinner"></div>';
    document.querySelector('.world-map-container').appendChild(loadingOverlay);
      console.log('Smooth navigation UI elements initialized');
}
