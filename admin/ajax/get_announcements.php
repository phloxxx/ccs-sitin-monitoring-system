<?php
session_start();
require_once('../../db.php');

// Set the content type to JSON
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get recent announcements (last 5) with admin username
$announcements = [];
try {
    // Query that joins ANNOUNCEMENT with ADMIN to get the username
    $stmt = $conn->prepare("SELECT a.*, admin.username FROM ANNOUNCEMENT a 
                           JOIN ADMIN admin ON a.ADMIN_ID = admin.ADMIN_ID 
                           ORDER BY a.CREATED_AT DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Sanitize data for JSON output
        $announcements[] = [
            'ANNOUNCE_ID' => $row['ANNOUNCE_ID'],
            'TITLE' => htmlspecialchars($row['TITLE']),
            'CONTENT' => htmlspecialchars($row['CONTENT']),
            'CREATED_AT' => $row['CREATED_AT'],
            'ADMIN_ID' => $row['ADMIN_ID'],
            'username' => htmlspecialchars($row['username'])
        ];
    }
    
    $stmt->close();
    echo json_encode(['success' => true, 'announcements' => $announcements]);
} catch (Exception $e) {
    // Handle any errors
    echo json_encode(['success' => false, 'message' => 'Error fetching announcements: ' . $e->getMessage()]);
}
?>
