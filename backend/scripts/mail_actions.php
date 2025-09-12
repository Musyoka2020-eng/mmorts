<?php
/**
 * Mail Actions API - Handle various mail actions (mark read, star, delete, etc.)
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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
    exit;
}

$user_id = $g->getCurrentUser('id');
$mailManager = new MailManager();

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $action = $input['action'] ?? '';
    $message_id = (int)($input['message_id'] ?? 0);
    $message_ids = $input['message_ids'] ?? [$message_id]; // Support batch operations
    
    if ($message_id <= 0 && empty($message_ids)) {
        echo json_encode(['success' => false, 'error' => 'Message ID required']);
        exit;
    }
    
    $results = [];
    
    switch ($action) {
        case 'mark_read':
            foreach ($message_ids as $msg_id) {
                $result = $mailManager->markAsRead($msg_id, $user_id);
                $results[] = $result;
            }
            break;
            
        case 'toggle_star':
            if ($message_id > 0) {
                $result = $mailManager->toggleStar($message_id, $user_id);
                echo json_encode($result);
                exit;
            }
            break;
            
        case 'delete':
            foreach ($message_ids as $msg_id) {
                $result = $mailManager->deleteMessage($msg_id, $user_id);
                $results[] = $result;
            }
            break;
            
        case 'get_unread_count':
            $count = $mailManager->getUnreadCount($user_id);
            echo json_encode(['success' => true, 'unread_count' => $count]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
    
    // For batch operations, return aggregated results
    $success_count = 0;
    $total_count = count($results);
    
    foreach ($results as $result) {
        if ($result['success']) {
            $success_count++;
        }
    }
    
    echo json_encode([
        'success' => $success_count > 0,
        'total_processed' => $total_count,
        'success_count' => $success_count,
        'failed_count' => $total_count - $success_count
    ]);
    
} catch (Exception $e) {
    error_log("mail_actions.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>