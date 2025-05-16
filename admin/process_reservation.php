<?php
session_start();
require_once('../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['reservation_id']) && isset($_POST['action'])) {
        $reservation_id = $_POST['reservation_id'];
        $action = $_POST['action'];
        $admin_id = $_SESSION['admin_id'];
        
        // Get reservation details first for the notification
        $stmt = $conn->prepare("SELECT r.*, u.USER_ID, l.LAB_NAME, c.PC_NUMBER 
                               FROM reservation r 
                               JOIN USERS u ON r.user_id = u.USER_ID 
                               JOIN LABS l ON r.lab_id = l.LAB_ID
                               JOIN COMPUTERS c ON r.computer_id = c.PC_ID
                               WHERE r.id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $reservation = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$reservation) {
            $_SESSION['error'] = "Reservation not found";
            header('Location: reservations.php');
            exit();
        }
        
        // Process the action
        if ($action == 'approve') {
            $new_status = 'Approved';
            $message = "Your reservation for " . $reservation['LAB_NAME'] . ", PC-" . $reservation['PC_NUMBER'] . 
                      " on " . date('F j, Y', strtotime($reservation['reservation_date'])) . 
                      " at " . date('g:i A', strtotime($reservation['start_time'])) . 
                      " has been approved.";
            $title = "Reservation Approved";
        } elseif ($action == 'reject') {
            $new_status = 'Rejected';
            $message = "Your reservation for " . $reservation['LAB_NAME'] . ", PC-" . $reservation['PC_NUMBER'] . 
                      " on " . date('F j, Y', strtotime($reservation['reservation_date'])) . 
                      " at " . date('g:i A', strtotime($reservation['start_time'])) . 
                      " has been rejected.";
            $title = "Reservation Rejected";
        } else {
            $_SESSION['error'] = "Invalid action";
            header('Location: reservations.php');
            exit();
        }
        
        // Update reservation status
        $stmt = $conn->prepare("UPDATE reservation SET status = ?, admin_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $new_status, $admin_id, $reservation_id);
        
        if ($stmt->execute()) {
            // Check if notifications table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
            if ($check_table->num_rows > 0) {
                // Create notification for the user
                $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                $stmt2->bind_param("iss", $reservation['USER_ID'], $title, $message);
                $stmt2->execute();
                $stmt2->close();
            } else {
                // Try to create the notifications table
                $create_table = "CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    title VARCHAR(100) NOT NULL,
                    message TEXT NOT NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES USERS(USER_ID)
                )";
                
                if ($conn->query($create_table) === TRUE) {
                    // Table created, now insert the notification
                    $stmt2 = $conn->prepare("INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                    $stmt2->bind_param("iss", $reservation['USER_ID'], $title, $message);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
            
            $_SESSION['success'] = "Reservation has been " . strtolower($new_status);
        } else {
            $_SESSION['error'] = "Error updating reservation: " . $conn->error;
        }
        $stmt->close();
        
        header('Location: reservations.php');
        exit();
    }
}

header('Location: reservations.php');
exit();
