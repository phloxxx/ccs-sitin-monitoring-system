<?php
session_start();
require_once('db.php');

// Check if the notifications table exists
$result = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($result->num_rows == 0) {
    // Create notifications table
    $sql = "CREATE TABLE `notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($sql) === TRUE) {
        echo "Notifications table created successfully";
    } else {
        echo "Error creating notifications table: " . $conn->error;
    }
} else {
    echo "Notifications table already exists";
}

$conn->close();
?>
