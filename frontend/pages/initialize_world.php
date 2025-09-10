<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Use new globals system
$g = globals();
$conn = $g->getDatabase();

// Check if user is logged in and is admin
if (!$g->isUserLoggedIn()) {
    header('Location: ' . url('login', ['msg' => 'You must be logged in to access this page.']));
    exit;
}

// Get initialization status
$query = "SHOW COLUMNS FROM configuration LIKE 'game_initialized'";
$result = $conn->query($query);
$initColumnExists = $result->num_rows > 0;

if ($initColumnExists) {
    $query = "SELECT game_initialized FROM configuration WHERE id = 1";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $isInitialized = $row['game_initialized'] == 1;
} else {
    $isInitialized = false;
}

// Process initialization request
if (isset($_POST['initialize']) && !$isInitialized) {
    // Include the initialization script
    include_once __DIR__ . '/../../backend/setup/initialize_game_world.php';
    
    // Mark initialization as complete
    $query = "UPDATE configuration SET game_initialized = 1 WHERE id = 1";
    $conn->query($query);
    
    $message = "Game world initialized successfully!";
    $isInitialized = true;
}
?>

<div class="main">
    <section class="content py-3">
        <div class="container">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="card">
                        <div class="card-header">
                            <h3>Game World Initialization</h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($message)): ?>
                                <div class="alert alert-success"><?= $message ?></div>
                            <?php endif; ?>
                            
                            <?php if ($isInitialized): ?>
                                <div class="alert alert-info">
                                    <h4>Game World is Already Initialized</h4>
                                    <p>The game world has already been set up with AI opponents and a map.</p>
                                </div>
                                
                                <p>You can:</p>
                                <ul>
                                    <li>Explore the world map</li>
                                    <li>Attack AI opponents</li>
                                    <li>Gather resources</li>
                                    <li>Train new units</li>
                                </ul>
                                
                                <div class="mt-4">
                                    <a href="index.php?page=world_map" class="btn btn-primary">Explore World Map</a>
                                    <a href="index.php?page=home" class="btn btn-secondary">Return to Home</a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <h4>Game World Not Initialized</h4>
                                    <p>The game world has not been set up yet. This process will:</p>
                                    <ul>
                                        <li>Create a world map</li>
                                        <li>Generate AI opponents</li>
                                        <li>Place AI cities on the map</li>
                                        <li>Prepare initial resources and armies</li>
                                    </ul>
                                </div>
                                
                                <div class="alert alert-danger">
                                    <p><strong>Warning:</strong> This will reset any existing world data. Only proceed if you're starting a new game.</p>
                                </div>
                                
                                <form method="post">
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="initialize" class="btn btn-primary">Initialize Game World</button>
                                        <a href="index.php?page=home" class="btn btn-secondary">Cancel</a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>
