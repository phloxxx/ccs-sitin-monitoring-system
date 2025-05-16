<?php
require_once('../config/db.php');

// Create notifications table
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES USERS(USER_ID)
)";

if ($conn->query($sql) === TRUE) {
    echo "Notifications table created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
