<?php
/**
 * Mail Manager - Core messaging system for MechaEmpire
 * Handles all mail/messaging operations including sending, receiving, and management
 * 
 * Features:
 * - Player-to-player messaging
 * - System notifications 
 * - Message threading/replies
 * - Resource attachments
 * - Bulk operations
 * - Auto-deletion policies
 * - Template-based system messages
 */

class MailManager {
    private $db;
    private $globals;
    
    public function __construct() {
        $this->globals = globals();
        $this->db = $this->globals->getDatabase();
    }
    
    /**
     * Send a message to one or more recipients
     * 
     * @param array $params Message parameters
     * @return array Result with success status and message ID
     */
    public function sendMessage($params) {
        try {
            // Validate required parameters
            $required = ['sender_id', 'recipients', 'subject', 'content'];
            foreach ($required as $field) {
                if (!isset($params[$field]) || empty($params[$field])) {
                    return ['success' => false, 'error' => "Missing required field: $field"];
                }
            }
            
            // Default parameters
            $params = array_merge([
                'sender_type' => 'player',
                'message_type' => 'personal',
                'priority' => 'normal',
                'reply_to_id' => null,
                'attachments' => [],
                'expires_at' => null
            ], $params);
            
            $this->db->begin_transaction();
            
            // Insert the message
            $query = "INSERT INTO messages (
                sender_id, sender_type, subject, content, message_type, 
                priority, reply_to_id, has_attachments, expires_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $has_attachments = !empty($params['attachments']) ? 1 : 0;
            
            $stmt->bind_param("isssssiis", 
                $params['sender_id'],
                $params['sender_type'],
                $params['subject'],
                $params['content'],
                $params['message_type'],
                $params['priority'],
                $params['reply_to_id'],
                $has_attachments,
                $params['expires_at']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create message: " . $stmt->error);
            }
            
            $message_id = $this->db->insert_id;
            
            // Add recipients
            $recipient_success = $this->addMessageRecipients($message_id, $params['recipients']);
            if (!$recipient_success['success']) {
                throw new Exception($recipient_success['error']);
            }
            
            // Add attachments if any
            if (!empty($params['attachments'])) {
                $attachment_success = $this->addMessageAttachments($message_id, $params['attachments']);
                if (!$attachment_success['success']) {
                    throw new Exception($attachment_success['error']);
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true, 
                'message_id' => $message_id,
                'recipients_count' => $recipient_success['count']
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("MailManager::sendMessage error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Add recipients to a message
     */
    private function addMessageRecipients($message_id, $recipients) {
        try {
            $query = "INSERT INTO message_recipients (message_id, recipient_id, recipient_type) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $count = 0;
            
            foreach ($recipients as $recipient) {
                $recipient_id = $recipient['id'];
                $recipient_type = $recipient['type'] ?? 'player';
                
                $stmt->bind_param("iis", $message_id, $recipient_id, $recipient_type);
                if ($stmt->execute()) {
                    $count++;
                }
            }
            
            return ['success' => true, 'count' => $count];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Add attachments to a message
     */
    private function addMessageAttachments($message_id, $attachments) {
        try {
            $query = "INSERT INTO message_attachments (
                message_id, attachment_type, attachment_name, attachment_data, quantity
            ) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            
            foreach ($attachments as $attachment) {
                $attachment_data = json_encode($attachment['data']);
                $quantity = $attachment['quantity'] ?? 1;
                
                $stmt->bind_param("isssi", 
                    $message_id,
                    $attachment['type'],
                    $attachment['name'],
                    $attachment_data,
                    $quantity
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to add attachment: " . $stmt->error);
                }
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get messages for a user's inbox
     * 
     * @param int $user_id User ID
     * @param array $options Query options (page, limit, filter, etc.)
     * @return array Messages and pagination info
     */
    public function getInboxMessages($user_id, $options = []) {
        try {
            // Default options
            $options = array_merge([
                'page' => 1,
                'limit' => 20,
                'filter' => 'all', // all, unread, system, personal, starred
                'order' => 'DESC',
                'search' => ''
            ], $options);
            
            $offset = ($options['page'] - 1) * $options['limit'];
            
            // Build the WHERE clause
            $where_conditions = ["mr.recipient_id = ? AND mr.deleted_at IS NULL"];
            $params = [$user_id];
            $param_types = "i";
            
            if ($options['filter'] === 'unread') {
                $where_conditions[] = "mr.read_at IS NULL";
            } elseif ($options['filter'] === 'system') {
                $where_conditions[] = "m.sender_type IN ('system', 'ai', 'admin')";
            } elseif ($options['filter'] === 'personal') {
                $where_conditions[] = "m.sender_type = 'player'";
            } elseif ($options['filter'] === 'starred') {
                $where_conditions[] = "mr.starred = 1";
            }
            
            // Add search condition
            if (!empty($options['search'])) {
                $where_conditions[] = "(m.subject LIKE ? OR m.content LIKE ?)";
                $search_term = "%" . $options['search'] . "%";
                $params[] = $search_term;
                $params[] = $search_term;
                $param_types .= "ss";
            }
            
            $where_clause = implode(" AND ", $where_conditions);
            
            // Get total count
            $count_query = "SELECT COUNT(*) as total 
                           FROM message_recipients mr 
                           JOIN messages m ON mr.message_id = m.id 
                           WHERE $where_clause";
            
            $count_stmt = $this->db->prepare($count_query);
            $count_stmt->bind_param($param_types, ...$params);
            $count_stmt->execute();
            $total = $count_stmt->get_result()->fetch_assoc()['total'];
            
            // Get messages
            $query = "SELECT 
                        m.id,
                        m.sender_id,
                        m.sender_type,
                        m.subject,
                        m.content,
                        m.message_type,
                        m.priority,
                        m.reply_to_id,
                        m.has_attachments,
                        m.created_at,
                        mr.read_at,
                        mr.starred,
                        mr.archived_at,
                        CASE 
                            WHEN m.sender_type = 'player' THEN u.username
                            WHEN m.sender_type = 'system' THEN 'System'
                            WHEN m.sender_type = 'ai' THEN CONCAT('AI - ', ai.name)
                            WHEN m.sender_type = 'admin' THEN 'Administrator'
                            ELSE 'Unknown'
                        END as sender_name
                      FROM message_recipients mr
                      JOIN messages m ON mr.message_id = m.id
                      LEFT JOIN players u ON m.sender_id = u.id AND m.sender_type = 'player'
                      LEFT JOIN ai_players ai ON m.sender_id = ai.id AND m.sender_type = 'ai'
                      WHERE $where_clause
                      ORDER BY m.created_at {$options['order']}
                      LIMIT ? OFFSET ?";
            
            $params[] = $options['limit'];
            $params[] = $offset;
            $param_types .= "ii";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param($param_types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $messages = [];
            while ($row = $result->fetch_assoc()) {
                $messages[] = [
                    'id' => $row['id'],
                    'sender_id' => $row['sender_id'],
                    'sender_name' => $row['sender_name'],
                    'sender_type' => $row['sender_type'],
                    'subject' => $row['subject'],
                    'content' => $row['content'],
                    'message_type' => $row['message_type'],
                    'priority' => $row['priority'],
                    'reply_to_id' => $row['reply_to_id'],
                    'has_attachments' => (bool)$row['has_attachments'],
                    'created_at' => $row['created_at'],
                    'read_at' => $row['read_at'],
                    'is_read' => !empty($row['read_at']),
                    'is_starred' => (bool)$row['starred'],
                    'is_archived' => !empty($row['archived_at']),
                    'time_ago' => $this->getTimeAgo($row['created_at'])
                ];
            }
            
            return [
                'success' => true,
                'messages' => $messages,
                'pagination' => [
                    'total' => $total,
                    'page' => $options['page'],
                    'limit' => $options['limit'],
                    'total_pages' => ceil($total / $options['limit'])
                ]
            ];
            
        } catch (Exception $e) {
            error_log("MailManager::getInboxMessages error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get a specific message with full details including attachments
     */
    public function getMessage($message_id, $user_id) {
        try {
            // Get message details
            $query = "SELECT 
                        m.*,
                        mr.read_at,
                        mr.starred,
                        mr.archived_at,
                        CASE 
                            WHEN m.sender_type = 'player' THEN u.username
                            WHEN m.sender_type = 'system' THEN 'System'
                            WHEN m.sender_type = 'ai' THEN CONCAT('AI - ', ai.name)
                            WHEN m.sender_type = 'admin' THEN 'Administrator'
                            ELSE 'Unknown'
                        END as sender_name
                      FROM messages m
                      JOIN message_recipients mr ON m.id = mr.message_id
                      LEFT JOIN players u ON m.sender_id = u.id AND m.sender_type = 'player'
                      LEFT JOIN ai_players ai ON m.sender_id = ai.id AND m.sender_type = 'ai'
                      WHERE m.id = ? AND mr.recipient_id = ? AND mr.deleted_at IS NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $message_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                return ['success' => false, 'error' => 'Message not found or access denied'];
            }
            
            $message = $result->fetch_assoc();
            
            // Get attachments if any
            $attachments = [];
            if ($message['has_attachments']) {
                $attachments = $this->getMessageAttachments($message_id);
            }
            
            // Format message data
            $formatted_message = [
                'id' => $message['id'],
                'sender_id' => $message['sender_id'],
                'sender_name' => $message['sender_name'],
                'sender_type' => $message['sender_type'],
                'subject' => $message['subject'],
                'content' => $message['content'],
                'message_type' => $message['message_type'],
                'priority' => $message['priority'],
                'reply_to_id' => $message['reply_to_id'],
                'has_attachments' => (bool)$message['has_attachments'],
                'attachments' => $attachments,
                'created_at' => $message['created_at'],
                'read_at' => $message['read_at'],
                'is_read' => !empty($message['read_at']),
                'is_starred' => (bool)$message['starred'],
                'is_archived' => !empty($message['archived_at']),
                'time_ago' => $this->getTimeAgo($message['created_at'])
            ];
            
            return ['success' => true, 'message' => $formatted_message];
            
        } catch (Exception $e) {
            error_log("MailManager::getMessage error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get message attachments
     */
    private function getMessageAttachments($message_id) {
        try {
            $query = "SELECT * FROM message_attachments WHERE message_id = ? ORDER BY id";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $message_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $attachments = [];
            while ($row = $result->fetch_assoc()) {
                $attachments[] = [
                    'id' => $row['id'],
                    'type' => $row['attachment_type'],
                    'name' => $row['attachment_name'],
                    'data' => json_decode($row['attachment_data'], true),
                    'quantity' => $row['quantity'],
                    'claimed_at' => $row['claimed_at'],
                    'is_claimed' => !empty($row['claimed_at'])
                ];
            }
            
            return $attachments;
            
        } catch (Exception $e) {
            error_log("MailManager::getMessageAttachments error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark a message as read
     */
    public function markAsRead($message_id, $user_id) {
        try {
            $query = "UPDATE message_recipients 
                     SET read_at = CURRENT_TIMESTAMP 
                     WHERE message_id = ? AND recipient_id = ? AND read_at IS NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $message_id, $user_id);
            $success = $stmt->execute();
            
            return [
                'success' => $success,
                'affected_rows' => $stmt->affected_rows
            ];
            
        } catch (Exception $e) {
            error_log("MailManager::markAsRead error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Toggle starred status of a message
     */
    public function toggleStar($message_id, $user_id) {
        try {
            $query = "UPDATE message_recipients 
                     SET starred = NOT starred 
                     WHERE message_id = ? AND recipient_id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $message_id, $user_id);
            $success = $stmt->execute();
            
            // Get new starred status
            $query2 = "SELECT starred FROM message_recipients WHERE message_id = ? AND recipient_id = ?";
            $stmt2 = $this->db->prepare($query2);
            $stmt2->bind_param("ii", $message_id, $user_id);
            $stmt2->execute();
            $result = $stmt2->get_result();
            $starred = $result->fetch_assoc()['starred'];
            
            return [
                'success' => $success,
                'starred' => (bool)$starred
            ];
            
        } catch (Exception $e) {
            error_log("MailManager::toggleStar error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Delete a message (soft delete)
     */
    public function deleteMessage($message_id, $user_id) {
        try {
            $query = "UPDATE message_recipients 
                     SET deleted_at = CURRENT_TIMESTAMP 
                     WHERE message_id = ? AND recipient_id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $message_id, $user_id);
            $success = $stmt->execute();
            
            return [
                'success' => $success,
                'affected_rows' => $stmt->affected_rows
            ];
            
        } catch (Exception $e) {
            error_log("MailManager::deleteMessage error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get unread message count for a user
     */
    public function getUnreadCount($user_id) {
        try {
            $query = "SELECT COUNT(*) as count 
                     FROM message_recipients mr
                     JOIN messages m ON mr.message_id = m.id
                     WHERE mr.recipient_id = ? 
                     AND mr.read_at IS NULL 
                     AND mr.deleted_at IS NULL
                     AND (m.expires_at IS NULL OR m.expires_at > NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->fetch_assoc()['count'];
            
        } catch (Exception $e) {
            error_log("MailManager::getUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Send system message using template
     */
    public function sendSystemMessage($recipient_id, $template_name, $variables = []) {
        try {
            // Get template
            $query = "SELECT * FROM message_templates WHERE template_name = ? AND is_active = 1";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("s", $template_name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Template not found: $template_name");
            }
            
            $template = $result->fetch_assoc();
            
            // Replace variables in subject and content
            $subject = $this->replaceTemplateVariables($template['subject_template'], $variables);
            $content = $this->replaceTemplateVariables($template['content_template'], $variables);
            
            // Send message
            return $this->sendMessage([
                'sender_id' => 0, // System sender
                'sender_type' => 'system',
                'recipients' => [['id' => $recipient_id, 'type' => 'player']],
                'subject' => $subject,
                'content' => $content,
                'message_type' => $template['category']
            ]);
            
        } catch (Exception $e) {
            error_log("MailManager::sendSystemMessage error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Replace template variables with actual values
     */
    private function replaceTemplateVariables($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }
    
    /**
     * Helper function to format time ago
     */
    private function getTimeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'just now';
        if ($time < 3600) return floor($time/60) . 'm ago';
        if ($time < 86400) return floor($time/3600) . 'h ago';
        if ($time < 2592000) return floor($time/86400) . 'd ago';
        if ($time < 31104000) return floor($time/2592000) . 'mo ago';
        return floor($time/31104000) . 'y ago';
    }
    
    /**
     * Clean up expired messages
     */
    public function cleanupExpiredMessages() {
        try {
            $query = "UPDATE message_recipients mr
                     JOIN messages m ON mr.message_id = m.id
                     SET mr.deleted_at = CURRENT_TIMESTAMP
                     WHERE m.expires_at IS NOT NULL 
                     AND m.expires_at < NOW() 
                     AND mr.deleted_at IS NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return ['success' => true, 'cleaned_count' => $stmt->affected_rows];
            
        } catch (Exception $e) {
            error_log("MailManager::cleanupExpiredMessages error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>