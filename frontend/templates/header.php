<?php
/**
 * Header Template - Updated to use Centralized Globals
 * Migrated from old global variable system to new Globals manager
 */

// Get the globals instance
$g = globals();

// Get user information through the globals manager
$user_logged_in = $g->isUserLoggedIn();
if ($user_logged_in) {
    $id = $g->getCurrentUser('id');
    $username = $g->getCurrentUser('uname');
    $name = $g->getCurrentUser('name');
    $email = $g->getCurrentUser('email');
} else {
    $username = "MMORTS";
}

// Get site configuration
$title = $g->getSiteConfig('title');
$description = $g->getSiteConfig('description');
$logo = $g->getSiteConfig('logo');


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- <meta http-equiv="X-UA-Compatible" content="IE=edge"> -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $g->getPageTitle(); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Teko:wght@300;400;500;600;700&family=Orbitron:wght@400;700;900&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="frontend/design/css/bootstrap.min.css">
    <link rel="stylesheet" href="frontend/design/css/custom.css">
    <link rel="stylesheet" href="frontend/design/css/game-ui.css">
    <link rel="stylesheet" href="frontend/design/css/home-game.css">
    <link rel="stylesheet" href="frontend/design/css/commander-alerts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <!-- <link rel="stylesheet" href="frontend/design/css/home-game-override.css"> -->
</head>

<body>
    <section class="layout">