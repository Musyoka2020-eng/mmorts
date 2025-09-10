<?php
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/game_config.php';
require_once __DIR__ . '/globals.php';
require_once __DIR__ . '/deprecation.php';
require_once __DIR__ . '/../backend/account/registration.php';
require_once __DIR__ . '/../backend/account/logging.php';

// Check for deprecated global usage in development mode
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    check_deprecated_globals();
}