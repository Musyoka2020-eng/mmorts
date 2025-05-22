<?php
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';
?>
<section class="content py-3">
    <div class="container">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3>About MMORTS - Single Player Edition</h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <img src="frontend/images/logo.png" alt="Game Logo" class="img-fluid" style="max-width: 150px;">
                        </div>
                        
                        <h4>Game Overview</h4>
                        <p>
                            MMORTS - Single Player Edition is a strategic city-building and conquest game where you can build your empire and battle against AI opponents with different personalities and difficulties.
                        </p>
                        
                        <h4 class="mt-4">Game Features</h4>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card h-100 bg-light">
                                    <div class="card-body">
                                        <h5><i class="fas fa-city"></i> City Building</h5>
                                        <p>Build and manage your own city, gather resources, and develop infrastructure.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 bg-light">
                                    <div class="card-body">
                                        <h5><i class="fas fa-users"></i> Military Training</h5>
                                        <p>Train various types of military units to defend your city and attack opponents.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card h-100 bg-light">
                                    <div class="card-body">
                                        <h5><i class="fas fa-robot"></i> AI Opponents</h5>
                                        <p>Face off against AI opponents with different personalities (aggressive, defensive, balanced).</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 bg-light">
                                    <div class="card-body">
                                        <h5><i class="fas fa-map"></i> World Exploration</h5>
                                        <p>Explore a procedurally generated world map with various resources and terrains.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <h4 class="mt-4">Getting Started</h4>
                        <ol>
                            <li><strong>Create an Account:</strong> Register and login to get started.</li>
                            <li><strong>Initialize the Game World:</strong> Set up the game world with AI opponents.</li>
                            <li><strong>Build Your City:</strong> Visit the world map to establish your first city.</li>
                            <li><strong>Gather Resources:</strong> Collect resources to build and train units.</li>
                            <li><strong>Train Units:</strong> Develop a military force to defend and expand.</li>
                            <li><strong>Process AI Turns:</strong> Allow AI opponents to make their moves and evolve.</li>
                        </ol>
                        
                        <h4 class="mt-4">AI Personalities</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Characteristic</th>
                                        <th>Attack Probability</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge bg-danger">Aggressive</span></td>
                                        <td>Focuses on military strength and frequent attacks</td>
                                        <td>High (30-90%)</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-secondary">Balanced</span></td>
                                        <td>Equal focus on economy and military</td>
                                        <td>Medium (15-45%)</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-primary">Defensive</span></td>
                                        <td>Focuses on economy and defense, rarely attacks</td>
                                        <td>Low (5-15%)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <h4 class="mt-4">Development Roadmap</h4>
                        <div class="accordion" id="roadmapAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingOne">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                        Phase 1: Core Mechanics (Current)
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#roadmapAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Basic city building</li>
                                            <li>Resource gathering</li>
                                            <li>Military training and combat</li>
                                            <li>AI opponents with different personalities</li>
                                            <li>World map exploration</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingTwo">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                        Phase 2: Advanced Features (Coming Soon)
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#roadmapAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Technology research system</li>
                                            <li>Enhanced building types and upgrades</li>
                                            <li>Advanced AI strategies</li>
                                            <li>New military unit types</li>
                                            <li>Alliances with AI factions</li>
                                            <li>Campaign missions</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingThree">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                        Phase 3: Complete Experience (Future)
                                    </button>
                                </h2>
                                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#roadmapAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Weather and seasons affecting gameplay</li>
                                            <li>Diplomacy and trading with AI</li>
                                            <li>Random events and disasters</li>
                                            <li>Achievement system</li>
                                            <li>Multiple city management</li>
                                            <li>Custom AI personality creation</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>