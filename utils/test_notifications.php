<?php
require_once('../config/db.php');

echo "<h2>Notifications Table Diagnostic</h2>";

// 1. Check if the notifications table exists
$tableExists = false;
$checkTable = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($checkTable->num_rows > 0) {
    echo "<p style='color:green'>✓ The notifications table exists</p>";
    $tableExists = true;
} else {
    echo "<p style='color:red'>✗ The notifications table does not exist</p>";
    
    // Try to create the notifications table
    echo "<p>Attempting to create notifications table...</p>";
    
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES USERS(USER_ID)
        )
    ";
    
    if ($conn->query($createTableQuery)) {
        echo "<p style='color:green'>✓ Successfully created the notifications table</p>";
        $tableExists = true;
    } else {
        echo "<p style='color:red'>✗ Failed to create notifications table: " . $conn->error . "</p>";
    }
}

// 2. If table exists, check for notifications
if ($tableExists) {
    $result = $conn->query("SELECT COUNT(*) as total FROM notifications");
    $row = $result->fetch_assoc();
    $totalNotifications = $row['total'];
    
    echo "<p>Total notifications in the system: $totalNotifications</p>";
    
    if ($totalNotifications > 0) {
        // Show the latest 5 notifications
        echo "<h3>Latest 5 notifications:</h3>";
        $result = $conn->query("
            SELECT n.*, u.USERNAME 
            FROM notifications n
            JOIN USERS u ON n.user_id = u.USER_ID
            ORDER BY n.created_at DESC
            LIMIT 5
        ");
        
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>ID</th><th>User</th><th>Title</th><th>Message</th><th>Read</th><th>Date</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['USERNAME'] . " (ID: " . $row['user_id'] . ")</td>";
            echo "<td>" . $row['title'] . "</td>";
            echo "<td>" . $row['message'] . "</td>";
            echo "<td>" . ($row['is_read'] ? "Yes" : "No") . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    // 3. For testing - add a test notification to a user
    echo "<h3>Add Test Notification</h3>";
    echo "<form method='post'>";
    
    // Get list of users for dropdown
    $users = $conn->query("SELECT USER_ID, USERNAME FROM USERS ORDER BY USERNAME");
    
    echo "<p><label>User: <select name='user_id'>";
    while ($user = $users->fetch_assoc()) {
        echo "<option value='" . $user['USER_ID'] . "'>" . $user['USERNAME'] . " (ID: " . $user['USER_ID'] . ")</option>";
    }
    echo "</select></label></p>";
    
    echo "<p><label>Title: <input type='text' name='title' value='Test Notification'></label></p>";
    echo "<p><label>Message: <textarea name='message'>This is a test notification from the diagnostic tool.</textarea></label></p>";
    echo "<p><input type='submit' name='add_notification' value='Add Test Notification'></p>";
    echo "</form>";
    
    // Process form submission
    if (isset($_POST['add_notification'])) {
        $user_id = intval($_POST['user_id']);
        $title = $_POST['title'];
        $message = $_POST['message'];
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt->bind_param("iss", $user_id, $title, $message);
        
        if ($stmt->execute()) {
            echo "<p style='color:green'>✓ Test notification added successfully!</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to add test notification: " . $stmt->error . "</p>";
        }
        
        $stmt->close();
    }
}

// 4. Table structure
if ($tableExists) {
    echo "<h3>Table Structure</h3>";
    $result = $conn->query("DESCRIBE notifications");
    
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            echo "<td>" . ($value === NULL ? "NULL" : $value) . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
}

$conn->close();
?>

<p><a href="../student/notification.php">Go to Student Notifications Page</a></p>
<p><a href="../admin/reservation.php">Go to Admin Reservation Page</a></p>
