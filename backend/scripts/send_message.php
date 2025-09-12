<?php
/**
 * Send Message API - Send a message to other players
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
    
    // Validate required fields
    if (empty($input['recipients'])) {
        echo json_encode(['success' => false, 'error' => 'Recipients required']);
        exit;
    }
    
    if (empty($input['subject'])) {
        echo json_encode(['success' => false, 'error' => 'Subject required']);
        exit;
    }
    
    if (empty($input['content'])) {
        echo json_encode(['success' => false, 'error' => 'Message content required']);
        exit;
    }
    
    // Process recipients - can be usernames or user IDs
    $recipients = [];
    $conn = $g->getDatabase();
    
    foreach ($input['recipients'] as $recipient) {
        if (is_numeric($recipient)) {
            // It's a user ID
            $recipients[] = ['id' => (int)$recipient, 'type' => 'player'];
        } else {
            // It's a username, look up the ID
            $query = "SELECT id FROM players WHERE username = ? LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $recipient);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $recipients[] = ['id' => $user['id'], 'type' => 'player'];
            } else {
                echo json_encode(['success' => false, 'error' => "User not found: $recipient"]);
                exit;
            }
        }
    }
    
    if (empty($recipients)) {
        echo json_encode(['success' => false, 'error' => 'No valid recipients found']);
        exit;
    }
    
    // Prepare message data
    $message_params = [
        'sender_id' => $user_id,
        'sender_type' => 'player',
        'recipients' => $recipients,
        'subject' => trim($input['subject']),
        'content' => trim($input['content']),
        'message_type' => 'personal',
        'priority' => $input['priority'] ?? 'normal'
    ];
    
    // Handle reply_to if this is a reply
    if (!empty($input['reply_to_id'])) {
        $message_params['reply_to_id'] = (int)$input['reply_to_id'];
    }
    
    // Handle attachments if any
    if (!empty($input['attachments'])) {
        $message_params['attachments'] = $input['attachments'];
    }
    
    // Send the message
    $result = $mailManager->sendMessage($message_params);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("send_message.php error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>