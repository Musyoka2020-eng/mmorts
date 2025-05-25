<?php
// Include header and top navigation
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

// Add battle report CSS
echo '<link rel="stylesheet" href="frontend/design/css/battle-report.css">';
// Add Font Awesome if not already included
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">';
// Add battle report JavaScript
echo '<script src="frontend/design/js/battle-report-renderer.js"></script>';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php?page=login&msg=' . urlencode('You must be logged in to access this page.'));
    exit;
}

// Check if battle ID is provided
$battleId = isset($_GET['id']) ? intval($_GET['id']) : null;
if (!$battleId) {
    $error = "No battle ID provided.";
}
?>

<div class="main battle-report-page">
    <section class="content py-3">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">                    <div class="card">
                        <div class="card-header">
                            <h3>Battle Report</h3>
                            <div id="battle-date" class="text-muted mb-0">
                                <!-- Date will be populated by JavaScript -->
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                                <a href="index.php?page=home" class="btn btn-primary">Return to Home</a>
                            <?php else: ?>
                                <!-- Container for AJAX content -->
                                <div id="battle-report-content">
                                    <!-- Content will be loaded via JavaScript -->
                                </div>

                                <div class="battle-report-action-buttons mt-4">
                                    <div class="d-flex justify-content-between">
                                        <a href="index.php?page=battle" class="btn btn-primary">
                                            Back to Battle
                                        </a>
                                        <a href="index.php?page=world_map" class="btn btn-secondary">
                                            Return to Map
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php if (!isset($error)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the battle report renderer
    const renderer = new BattleReportRenderer();
    renderer.init(<?= $battleId ?>);
});
</script>
<?php endif; ?>

<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>
