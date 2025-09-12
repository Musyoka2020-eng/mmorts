<?php
/**
 * Get Single Message API - Retrieve a specific message with full details
 */

require_once __DIR__ . '/../../system/includes.php';
require_once __DIR__ . '/../mail/mail_manager.php';

header('Content-Type: application/json');

$g = globals();

// Check authentication
if (!$g->isUserLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $g->getCurrentUser('id');
$mailManager = new MailManager();

// Get message ID
$message_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($message_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
    exit;
}

try {
    // Get the message
    $result = $mailManager->getMessage($message_id, $user_id);
    
    if ($result['success']) {
        // Automatically mark as read when viewing
        $mailManager->markAsRead($message_id, $user_id);
        $result['message']['is_read'] = true;
        $result['message']['read_at'] = date('Y-m-d H:i:s');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("get_message.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>