<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get recent sit-in sessions
$sessions = [];
try {
    $query = "SELECT s.*, u.FIRSTNAME, u.LASTNAME, u.MIDNAME, l.LAB_NAME 
              FROM SITIN s
              JOIN USERS u ON s.IDNO = u.IDNO
              JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
              ORDER BY s.SESSION_START DESC
              LIMIT 50";
    
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'sessions' => $sessions]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// Close connection
$conn->close();
exit();
?>
