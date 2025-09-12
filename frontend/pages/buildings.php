<?php
/**
 * Buildings Page - City Building Management Interface
 */

// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Add building-specific CSS and JS
echo '<link rel="stylesheet" href="frontend/design/css/buildings.css">';
echo '<script src="frontend/design/js/buildings.js" defer></script>';

// Use the globals system for authentication and database
$g = globals();
if (!$g->isUserLoggedIn()) {
    header('Location: ' . url('login'));
    exit;
}

$playerId = $g->getCurrentUser('id');
$username = $g->getCurrentUser('uname');
?>

<div class="container-fluid mt-3">        
    <div class="row">
        <div class="col-12">
            <h1 class="text-center mb-4" style="color: #66bbff;">
                🏭 City Buildings
            </h1>
        </div>
    </div>
        
        <!-- Construction Queue Section -->
        <div class="row" id="construction-queue-section">
            <div class="col-12">
                <div class="construction-queue">
                    <h3 style="color: #ffd700;">🚧 Construction Queue</h3>
                    <div id="construction-queue-container" role="list" aria-label="Construction queue">
                        <p class="text-center" style="color: #ccc;" id="no-construction">
                            No buildings currently under construction
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Building Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="building-tabs" role="tablist" aria-label="Building categories">
                    <button class="building-tab active" data-tab="my-buildings" role="tab" aria-selected="true" aria-controls="my-buildings">🏘️ My Buildings</button>
                    <button class="building-tab" data-tab="resource" role="tab" aria-selected="false" aria-controls="resource">🏭 Resource</button>
                    <button class="building-tab" data-tab="military" role="tab" aria-selected="false" aria-controls="military">⚔️ Military</button>
                    <button class="building-tab" data-tab="defense" role="tab" aria-selected="false" aria-controls="defense">🛡️ Defense</button>
                    <button class="building-tab" data-tab="research" role="tab" aria-selected="false" aria-controls="research">🔬 Research</button>
                    <button class="building-tab" data-tab="special" role="tab" aria-selected="false" aria-controls="special">🏛️ Special</button>
                </div>
            </div>
        </div>
        
        <!-- Tab Contents -->
        <div class="row">
            <div class="col-12">
                <!-- My Buildings Tab -->
                <div id="my-buildings" class="tab-content active" role="tabpanel" aria-labelledby="my-buildings-tab">
                    <div class="buildings-container">
                        <h3 style="color: #66bbff;">Your Buildings</h3>
                        <div id="existing-buildings-grid" class="building-grid" role="grid" aria-label="Your constructed buildings">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <!-- Available Buildings Tabs -->
                <div id="resource" class="tab-content" role="tabpanel" aria-labelledby="resource-tab">
                    <div class="buildings-container">
                        <h3 style="color: #66bbff;">Resource Production Buildings</h3>
                        <div id="resource-buildings-grid" class="building-grid" role="grid" aria-label="Resource production buildings">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div id="military" class="tab-content" role="tabpanel" aria-labelledby="military-tab">
                    <div class="buildings-container">
                        <h3 style="color: #66bbff;">Military Facilities</h3>
                        <div id="military-buildings-grid" class="building-grid" role="grid" aria-label="Military facilities">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div id="defense" class="tab-content" role="tabpanel" aria-labelledby="defense-tab">
                    <div class="buildings-container">
                        <h3 style="color: #66bbff;">Defensive Structures</h3>
                        <div id="defense-buildings-grid" class="building-grid" role="grid" aria-label="Defensive structures">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div id="research" class="tab-content" role="tabpanel" aria-labelledby="research-tab">
                    <div class="buildings-container">
                        <h3 style="color: #66bbff;">Research Facilities</h3>
                        <div id="research-buildings-grid" class="building-grid" role="grid" aria-label="Research facilities">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div id="special" class="tab-content" role="tabpanel" aria-labelledby="special-tab">
                    <div class="buildings-container">
                        <h3 style="color: #66bbff;">Special Buildings</h3>
                        <div id="special-buildings-grid" class="building-grid" role="grid" aria-label="Special buildings">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                </div>
        </div>
    </div>
</div>

<!-- Building Details Modal -->
<div class="modal fade" id="buildingModal" tabindex="-1" aria-labelledby="buildingModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="buildingModalTitle">Building Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="buildingModalBody">
                <!-- Populated by JavaScript -->
            </div>
        </div>
    </div>
</div>