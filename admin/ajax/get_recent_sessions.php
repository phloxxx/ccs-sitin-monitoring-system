<?php
session_start();
require_once('../../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get recent sit-in sessions
    $query = "SELECT s.*, u.FIRSTNAME, u.LASTNAME, u.MIDNAME, l.LAB_NAME 
              FROM SITIN s
              JOIN USERS u ON s.IDNO = u.IDNO
              JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
              ORDER BY s.SESSION_START DESC
              LIMIT 50";
    
    $result = $conn->query($query);
    
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
