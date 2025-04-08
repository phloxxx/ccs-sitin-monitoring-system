<?php
session_start();
require_once('../../db.php');

// Set the content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get the last announcement ID that the client has
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

// Get announcements newer than the last ID the client has
$announcements = [];
try {
    // Query that joins ANNOUNCEMENT with ADMIN to get the username
    $stmt = $conn->prepare("SELECT a.*, admin.username FROM ANNOUNCEMENT a 
                           JOIN ADMIN admin ON a.ADMIN_ID = admin.ADMIN_ID 
                           WHERE a.ANNOUNCE_ID > ?
                           ORDER BY a.CREATED_AT DESC LIMIT 10");
    $stmt->bind_param("i", $last_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Sanitize data for JSON output
        $announcements[] = [
            'ANNOUNCE_ID' => $row['ANNOUNCE_ID'],
            'TITLE' => htmlspecialchars($row['TITLE']),
            'CONTENT' => htmlspecialchars($row['CONTENT']),
            'CREATED_AT' => $row['CREATED_AT'],
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
