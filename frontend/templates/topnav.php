<div class="header">
    <div class="bg-dark text-white main-nav">
        <div class="container-fluid">
            <div class="d-flex align-content-center justify-content-between">
                <div class="col-lg-8 text-center">
                    <div class="d-flex justify-content-center align-items-center mb-4">
                        <div class="logo me-3">
                            <img src="frontend/images/<?= $logo; ?>" alt="Logo" class="img-fluid" style="width: 100px;">
                        </div>
                        <div class="title">
                            <h1 class="mb-0"><?= $title ?></h1>
                            <h2 class="mb-0"><?= $description ?></h2>
                        </div>
                    </div>
                </div>

                <div class="col-4 text-right mt-2">
                    <?php if ($user_logged_in) { ?>
                        <div class="dropdown">
                            <a class="btn game-button dropdown-toggle" href="#" role="button" id="userDropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?= $username ?>
                            </a>

                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdownMenuLink">
                                <a class="dropdown-item" href="#">Profile</a>
                                <a class="dropdown-item" href="#">Settings</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="index.php?page=logout">Logout</a>
                            </div>
                        </div>
                    <?php } else { ?>
                        <a href="index.php?page=login" class="btn game-button">Login</a>
                    <?php } ?>
                </div>
            </div>

            <nav class="game-nav d-flex justify-content-center">
                <div class="col-12 text-center">
                    <nav class="nav justify-content-center">
                        <a class="nav-link <?= ($_GET['page'] ?? '') === 'home' || !isset($_GET['page']) ? 'active' : ''; ?>" href="index.php?page=home">Home</a>
                        <?php if ($user_logged_in) { ?>
                            <a class="nav-link <?= ($_GET['page'] ?? '') === 'world_map' ? 'active' : ''; ?>" href="index.php?page=world_map">World Map</a>
                            <a class="nav-link <?= ($_GET['page'] ?? '') === 'ai_opponents' ? 'active' : ''; ?>" href="index.php?page=ai_opponents">AI Opponents</a>
                            <a class="nav-link <?= ($_GET['page'] ?? '') === 'battle_history' ? 'active' : ''; ?>" href="index.php?page=battle_history">Battle History</a>
                        <?php } ?>
                        <a class="nav-link <?= ($_GET['page'] ?? '') === 'contact' ? 'active' : ''; ?>" href="index.php?page=contact">Contact</a>
                        <a class="nav-link <?= ($_GET['page'] ?? '') === 'community' ? 'active' : ''; ?>" href="index.php?page=community">Community</a>
                        <a class="nav-link <?= ($_GET['page'] ?? '') === 'support' ? 'active' : ''; ?>" href="index.php?page=support">Support</a>
                        <a class="nav-link <?= ($_GET['page'] ?? '') === 'about' ? 'active' : ''; ?>" href="index.php?page=about">About</a>
                    </nav>
                </div>
            </nav>

            <?php if ($user_logged_in) {
                include_once __DIR__ . '/resources_bar.php';
            } ?>
        </div>
    </div>
</div>