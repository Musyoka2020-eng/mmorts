<?php
/**
 * Get Messages API - Retrieve messages for user's inbox
 * Supports filtering, pagination, and search
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

// Get parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20; // Max 50 per page
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$order = isset($_GET['order']) && $_GET['order'] === 'ASC' ? 'ASC' : 'DESC';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Valid filters
$valid_filters = ['all', 'unread', 'system', 'personal', 'starred'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'all';
}

try {
    $options = [
        'page' => $page,
        'limit' => $limit,
        'filter' => $filter,
        'order' => $order,
        'search' => $search
    ];
    
    $result = $mailManager->getInboxMessages($user_id, $options);
    
    if ($result['success']) {
        // Also get unread count for header notification
        $unread_count = $mailManager->getUnreadCount($user_id);
        $result['unread_count'] = $unread_count;
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("get_messages.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>