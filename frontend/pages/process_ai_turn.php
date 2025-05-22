<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php?page=login&msg=' . urlencode('You must be logged in to access this page.'));
    exit;
}

// Check if game is initialized
$query = "SELECT game_initialized FROM configuration WHERE id = 1";
$result = $conn->query($query);
$isInitialized = false;

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $isInitialized = $row['game_initialized'] == 1;
}

// Create AI turn log table if it doesn't exist
$query = "CREATE TABLE IF NOT EXISTS ai_turn_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    execution_time FLOAT NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($query);

// Process AI turn request
if (isset($_POST['process_ai_turn']) && $isInitialized) {
    // Include and run the AI turns script
    include_once __DIR__ . '/../../backend/cronjobs/ai_turns.php';
    
    $message = "AI turns processed successfully!";
}

// Get recent AI turn logs
$query = "SELECT * FROM ai_turn_log ORDER BY processed_at DESC LIMIT 10";
$result = $conn->query($query);
$turnLogs = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $turnLogs[] = $row;
    }
}
?>

<div class="main">
    <section class="content py-3">
        <div class="container">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="card">
                        <div class="card-header">
                            <h3>AI Turn Processing</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!$isInitialized): ?>
                                <div class="alert alert-warning">
                                    <h4>Game World Not Initialized</h4>
                                    <p>The game world must be initialized before processing AI turns.</p>
                                    <a href="index.php?page=initialize_world" class="btn btn-primary">Initialize Game World</a>
                                </div>
                            <?php else: ?>
                                <?php if (isset($message)): ?>
                                    <div class="alert alert-success"><?= $message ?></div>
                                <?php endif; ?>
                                
                                <p>Processing AI turns will cause all AI opponents to:</p>
                                <ul>
                                    <li>Gather and produce resources</li>
                                    <li>Upgrade buildings and technology</li>
                                    <li>Train new military units</li>
                                    <li>Potentially attack other AI bases or the player</li>
                                </ul>
                                
                                <p class="text-warning">
                                    <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                    In a finished game, this would happen automatically on a schedule. For now, you can manually trigger AI turns.
                                </p>
                                
                                <form method="post" class="mt-4">
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="process_ai_turn" class="btn btn-primary">Process AI Turn</button>
                                        <a href="index.php?page=home" class="btn btn-secondary">Return to Home</a>
                                    </div>
                                </form>
                                
                                <?php if (!empty($turnLogs)): ?>
                                    <div class="mt-4">
                                        <h4>Recent AI Turn Logs</h4>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Execution Time (s)</th>
                                                        <th>Processed At</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($turnLogs as $log): ?>
                                                        <tr>
                                                            <td><?= $log['id'] ?></td>
                                                            <td><?= round($log['execution_time'], 4) ?></td>
                                                            <td><?= $log['processed_at'] ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>
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
