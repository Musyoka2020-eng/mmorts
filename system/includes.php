<?php
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/game_config.php';
require_once __DIR__ . '/globals.php';
require_once __DIR__ . '/deprecation.php';
require_once __DIR__ . '/../backend/account/registration.php';
require_once __DIR__ . '/../backend/account/logging.php';

// Deprecation checking is now manual - call check_deprecated_globals() only when needed
// This prevents false positives during system initialization
// The globals() system is now fully self-contained and doesn't rely on global variables