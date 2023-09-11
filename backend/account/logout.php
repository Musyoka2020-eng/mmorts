<?php

// if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_unset();
    ob_end_flush();
    // $_SESSION['logged_in'] == false;
    header('Location: index.php?page=home');
// }
