<?php
/**
 * Mail System Database Setup
 * Creates all necessary tables for the mail/messaging system
 */

require_once __DIR__ . '/../../system/includes.php';

$g = globals();
$conn = $g->getDatabase();

echo "Creating Mail System Database Tables...\n\n";

// 1. Messages Table - Core message data
$query = "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    sender_type ENUM('player', 'system', 'ai', 'admin') DEFAULT 'player',
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    message_type ENUM('personal', 'system', 'diplomatic', 'trade', 'combat', 'construction', 'event', 'admin') DEFAULT 'personal',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    reply_to_id INT NULL,
    has_attachments BOOLEAN DEFAULT FALSE,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_id, sender_type),
    INDEX idx_reply (reply_to_id),
    INDEX idx_type (message_type),
    INDEX idx_created (created_at),
    FOREIGN KEY (reply_to_id) REFERENCES messages(id) ON DELETE SET NULL
) ENGINE=InnoDB";

if ($conn->query($query)) {
    echo "✅ Created messages table\n";
} else {
    echo "❌ Error creating messages table: " . $conn->error . "\n";
}

// 2. Message Recipients Table - Who receives each message
$query = "CREATE TABLE IF NOT EXISTS message_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    recipient_id INT NOT NULL,
    recipient_type ENUM('player', 'all_players', 'alliance', 'admin_group') DEFAULT 'player',
    read_at DATETIME NULL,
    deleted_at DATETIME NULL,
    archived_at DATETIME NULL,
    starred BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_id, recipient_type),
    INDEX idx_message (message_id),
    INDEX idx_read (read_at),
    INDEX idx_deleted (deleted_at),
    UNIQUE KEY unique_recipient_message (message_id, recipient_id, recipient_type),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($query)) {
    echo "✅ Created message_recipients table\n";
} else {
    echo "❌ Error creating message_recipients table: " . $conn->error . "\n";
}

// 3. Message Attachments Table - Resource/item attachments
$query = "CREATE TABLE IF NOT EXISTS message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    attachment_type ENUM('resource', 'item', 'unit', 'building_plan', 'map_data') NOT NULL,
    attachment_name VARCHAR(100) NOT NULL,
    attachment_data JSON NOT NULL,
    quantity INT DEFAULT 1,
    claimed_at DATETIME NULL,
    claimed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_message (message_id),
    INDEX idx_type (attachment_type),
    INDEX idx_claimed (claimed_at),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB";

if ($conn->query($query)) {
    echo "✅ Created message_attachments table\n";
} else {
    echo "❌ Error creating message_attachments table: " . $conn->error . "\n";
}

// 4. Message Templates Table - For system messages
$query = "CREATE TABLE IF NOT EXISTS message_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL UNIQUE,
    category ENUM('construction', 'combat', 'research', 'production', 'diplomacy', 'event', 'admin') NOT NULL,
    subject_template VARCHAR(255) NOT NULL,
    content_template TEXT NOT NULL,
    variables JSON NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB";

if ($conn->query($query)) {
    echo "✅ Created message_templates table\n";
} else {
    echo "❌ Error creating message_templates table: " . $conn->error . "\n";
}

// 5. Mail Settings Table - User preferences
$query = "CREATE TABLE IF NOT EXISTS mail_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    setting_name VARCHAR(50) NOT NULL,
    setting_value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_player_setting (player_id, setting_name)
) ENGINE=InnoDB";

if ($conn->query($query)) {
    echo "✅ Created mail_settings table\n";
} else {
    echo "❌ Error creating mail_settings table: " . $conn->error . "\n";
}

echo "\n📧 Inserting default message templates...\n";

// Default message templates
$templates = [
    [
        'name' => 'building_completed',
        'category' => 'construction',
        'subject' => 'Building Construction Complete - {{building_name}}',
        'content' => 'Your {{building_name}} construction has been completed in {{city_name}}!\n\nBuilding Details:\n- Level: {{level}}\n- Construction Time: {{construction_time}}\n- Benefits: {{benefits}}\n\nYour city grows stronger!',
        'variables' => json_encode(['building_name', 'city_name', 'level', 'construction_time', 'benefits'])
    ],
    [
        'name' => 'building_upgrade_complete',
        'category' => 'construction',
        'subject' => 'Building Upgrade Complete - {{building_name}}',
        'content' => 'Your {{building_name}} has been upgraded to level {{new_level}} in {{city_name}}!\n\nUpgrade Benefits:\n{{new_benefits}}\n\nYour infrastructure improves!',
        'variables' => json_encode(['building_name', 'city_name', 'new_level', 'new_benefits'])
    ],
    [
        'name' => 'battle_victory',
        'category' => 'combat',
        'subject' => 'Victory! Battle Report - {{location}}',
        'content' => 'Congratulations! Your forces have achieved victory!\n\nBattle Summary:\n- Location: {{location}}\n- Enemy: {{enemy_name}}\n- Your Losses: {{your_losses}}\n- Enemy Losses: {{enemy_losses}}\n- Resources Gained: {{resources_gained}}\n\nGlory to your empire!',
        'variables' => json_encode(['location', 'enemy_name', 'your_losses', 'enemy_losses', 'resources_gained'])
    ],
    [
        'name' => 'battle_defeat',
        'category' => 'combat',
        'subject' => 'Defeat - Battle Report - {{location}}',
        'content' => 'Your forces have been defeated in battle.\n\nBattle Summary:\n- Location: {{location}}\n- Enemy: {{enemy_name}}\n- Your Losses: {{your_losses}}\n- Enemy Losses: {{enemy_losses}}\n- Resources Lost: {{resources_lost}}\n\nRegroup and prepare for revenge!',
        'variables' => json_encode(['location', 'enemy_name', 'your_losses', 'enemy_losses', 'resources_lost'])
    ],
    [
        'name' => 'resource_warehouse_full',
        'category' => 'production',
        'subject' => 'Warehouse Full - {{city_name}}',
        'content' => 'Your warehouse in {{city_name}} is at maximum capacity!\n\nCurrent Storage:\n{{resource_details}}\n\nConsider:\n- Upgrading your warehouse\n- Using resources for construction\n- Trading with other players\n\nDon\'t let resources go to waste!',
        'variables' => json_encode(['city_name', 'resource_details'])
    ],
    [
        'name' => 'research_complete',
        'category' => 'research',
        'subject' => 'Research Complete - {{research_name}}',
        'content' => 'Your scientists have completed research on {{research_name}}!\n\nResearch Benefits:\n{{benefits}}\n\nNew Technologies Unlocked:\n{{unlocked_tech}}\n\nKnowledge is power!',
        'variables' => json_encode(['research_name', 'benefits', 'unlocked_tech'])
    ],
    [
        'name' => 'diplomatic_message',
        'category' => 'diplomacy',
        'subject' => 'Diplomatic Message from {{sender_name}}',
        'content' => 'Greetings,\n\n{{diplomatic_content}}\n\nWe await your response.\n\nRegards,\n{{sender_name}}\n{{sender_title}}',
        'variables' => json_encode(['sender_name', 'sender_title', 'diplomatic_content'])
    ],
    [
        'name' => 'server_announcement',
        'category' => 'admin',
        'subject' => 'Server Announcement - {{announcement_title}}',
        'content' => '{{announcement_content}}\n\n---\nMechaEmpire Administration Team\n{{date}}',
        'variables' => json_encode(['announcement_title', 'announcement_content', 'date'])
    ]
];

foreach ($templates as $template) {
    $query = "INSERT INTO message_templates (
        template_name, category, subject_template, content_template, variables
    ) VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        subject_template = VALUES(subject_template),
        content_template = VALUES(content_template),
        variables = VALUES(variables)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssss",
        $template['name'],
        $template['category'],
        $template['subject'],
        $template['content'],
        $template['variables']
    );
    
    if ($stmt->execute()) {
        echo "✅ Added template: " . $template['name'] . "\n";
    } else {
        echo "❌ Error adding template " . $template['name'] . ": " . $stmt->error . "\n";
    }
}

echo "\n📧 Inserting default mail settings...\n";

// Default mail settings for existing players
$defaultSettings = [
    ['name' => 'notifications_enabled', 'value' => 'true'],
    ['name' => 'email_notifications', 'value' => 'false'],
    ['name' => 'auto_delete_read_after_days', 'value' => '30'],
    ['name' => 'auto_delete_system_after_days', 'value' => '7'],
    ['name' => 'show_system_messages', 'value' => 'true'],
    ['name' => 'show_combat_reports', 'value' => 'true'],
    ['name' => 'show_construction_updates', 'value' => 'true'],
    ['name' => 'show_diplomatic_messages', 'value' => 'true'],
    ['name' => 'messages_per_page', 'value' => '20']
];

// Get all existing players
$result = $conn->query("SELECT id FROM players");
$playerCount = 0;

while ($player = $result->fetch_assoc()) {
    foreach ($defaultSettings as $setting) {
        $query = "INSERT IGNORE INTO mail_settings (player_id, setting_name, setting_value) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $player['id'], $setting['name'], $setting['value']);
        $stmt->execute();
    }
    $playerCount++;
}

echo "✅ Applied default settings to $playerCount players\n";

echo "\n🎯 Mail System Database Setup Complete!\n\n";
echo "Created tables:\n";
echo "- messages (core message data with threading)\n";
echo "- message_recipients (delivery tracking and status)\n";
echo "- message_attachments (resource/item attachments)\n";
echo "- message_templates (system message templates)\n";
echo "- mail_settings (user preferences)\n\n";
echo "Features supported:\n";
echo "✅ Player-to-player messaging\n";
echo "✅ System notifications\n";
echo "✅ Message threading/replies\n";
echo "✅ Resource attachments\n";
echo "✅ Bulk operations\n";
echo "✅ Message templates\n";
echo "✅ Auto-deletion policies\n";
echo "✅ User preferences\n\n";
echo "Next steps:\n";
echo "1. Create MailManager class\n";
echo "2. Build mail API endpoints\n";
echo "3. Create mail interface\n";
echo "4. Integrate with existing systems\n";
?>
