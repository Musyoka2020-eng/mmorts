<?php
/**
 * User Search API - Search for users to send messages to
 */

require_once __DIR__ . '/../../system/includes.php';

header('Content-Type: application/json');

$g = globals();

// Check authentication
if (!$g->isUserLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$conn = $g->getDatabase();
$current_user_id = $g->getCurrentUser('id');

// Get search query
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 20) : 10;

if (empty($search) || strlen($search) < 2) {
    echo json_encode(['success' => false, 'error' => 'Search query must be at least 2 characters']);
    exit;
}

try {
    // Search for users by username (excluding current user)
    $query = "SELECT id, username 
              FROM players 
              WHERE username LIKE ? 
              AND id != ? 
              ORDER BY username 
              LIMIT ?";
    
    $search_term = "%$search%";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $search_term, $current_user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'username' => $row['username']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);
    
} catch (Exception $e) {
    error_log("search_users.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>