<?php
// NEW Simplified Gather Page - Pure rendering, server handles all logic
include_once __DIR__ . '/../templates/header.php';
include_once __DIR__ . '/../templates/topnav.php';

echo '<link rel="stylesheet" href="frontend/design/css/gather.css">';
echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';

// Use new globals system for authentication
$g = globals();
$conn = $g->getDatabase();

// Check if user is logged in
if (!$g->isUserLoggedIn()) {
    header('Location: ' . url('login', ['msg' => 'You must be logged in to access this page.']));
    exit;
}

// Check if coordinates are provided
if (!isset($_GET['target_x']) || !isset($_GET['target_y'])) {
    $error = "No target location specified.";
}
?>

<div class="gathering-main">
    <div class="gathering-container">
        <?php if (isset($error)): ?>
            <div style="text-align: center; padding: 4rem;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #ef4444;"></i>
                <p style="color: white; margin: 1rem 0;"><?= $error ?></p>
                <a href="index.php?page=world_map" class="btn-enhanced btn-primary-enhanced">
                    <i class="fas fa-map"></i>
                    Return to World Map
                </a>
            </div>
        <?php else: ?>
            <!-- Content will be loaded by JavaScript -->
            <div style="text-align: center; padding: 4rem;">
                <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: #60a5fa;"></i>
                <p style="color: white; margin-top: 1rem;">Loading gathering interface...</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="frontend/design/js/gathering-interface.js"></script>

<?php
include_once __DIR__ . '/../templates/footer.php';
include_once __DIR__ . '/../templates/scripts.php';
?>
