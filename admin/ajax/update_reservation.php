<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get action and reservation ID
$action = isset($_POST['action']) ? $_POST['action'] : '';
$reservation_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

// Validate inputs
if (empty($action) || $reservation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get reservation details to send notification
    $stmt = $conn->prepare("SELECT r.IDNO, r.LAB_ID, r.PC_NUMBER, l.LAB_NAME 
                            FROM RESERVATION r 
                            JOIN LABORATORY l ON r.LAB_ID = l.LAB_ID 
                            WHERE r.RESERVATION_ID = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation = $result->fetch_assoc();
    $stmt->close();
    
    if (!$reservation) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        exit();
    }
    
    // Get user_id for the student
    $stmt = $conn->prepare("SELECT USER_ID FROM USERS WHERE IDNO = ?");
    $stmt->bind_param("s", $reservation['IDNO']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }
    
    $user_id = $user['USER_ID'];
    $pc_number = $reservation['PC_NUMBER'];
    $lab_name = $reservation['LAB_NAME'];
    
    // Check if notifications table exists, create it if it doesn't
    $result = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($result->num_rows == 0) {
        // Create notifications table
        $createTableSQL = "CREATE TABLE `notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `title` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_read` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $conn->query($createTableSQL);
    }
    
    // Process based on action
    switch ($action) {
        case 'approve':
            // Update reservation status
            $stmt = $conn->prepare("UPDATE RESERVATION SET STATUS = 'APPROVED' WHERE RESERVATION_ID = ?");
            $stmt->bind_param("i", $reservation_id);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($success) {
                // Update PC status to RESERVED
                $stmt = $conn->prepare("UPDATE PC SET STATUS = 'RESERVED' WHERE LAB_ID = ? AND PC_NUMBER = ?");
                $stmt->bind_param("ii", $reservation['LAB_ID'], $pc_number);
                $stmt->execute();
                $stmt->close();
                
                // Create notification for the student
                $title = "Reservation Approved";
                $message = "Your reservation for PC-{$pc_number} in {$lab_name} has been approved.";
                
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
                $stmt->bind_param("iss", $user_id, $title, $message);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Reservation approved successfully']);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to approve reservation']);
            }
            break;
            
        case 'reject':
            // Update reservation status
            $stmt = $conn->prepare("UPDATE RESERVATION SET STATUS = 'REJECTED' WHERE RESERVATION_ID = ?");
            $stmt->bind_param("i", $reservation_id);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($success) {
                // Create notification for the student
                $title = "Reservation Rejected";
                $message = "Your reservation for PC-{$pc_number} in {$lab_name} has been rejected.";
                
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
                $stmt->bind_param("iss", $user_id, $title, $message);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Reservation rejected successfully']);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to reject reservation']);
            }
            break;
            
        case 'cancel':
            // Update reservation status
            $stmt = $conn->prepare("UPDATE RESERVATION SET STATUS = 'CANCELLED' WHERE RESERVATION_ID = ?");
            $stmt->bind_param("i", $reservation_id);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($success) {
                // Update PC status back to AVAILABLE
                $stmt = $conn->prepare("UPDATE PC SET STATUS = 'AVAILABLE' WHERE LAB_ID = ? AND PC_NUMBER = ?");
                $stmt->bind_param("ii", $reservation['LAB_ID'], $pc_number);
                $stmt->execute();
                $stmt->close();
                
                // Create notification for the student
                $title = "Reservation Cancelled";
                $message = "Your reservation for PC-{$pc_number} in {$lab_name} has been cancelled by admin.";
                
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
                $stmt->bind_param("iss", $user_id, $title, $message);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Reservation cancelled successfully']);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to cancel reservation']);
            }
            break;
            
        default:
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>
