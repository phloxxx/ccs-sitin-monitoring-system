<?php
session_start();
require_once('../../config/db.php');

// Set the content type to JSON
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get announcements with admin username
$announcements = [];
try {
    // Check if limit parameter is provided
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
    
    // Query that joins ANNOUNCEMENT with ADMIN to get the username
    $query = "SELECT a.*, admin.username FROM ANNOUNCEMENT a 
              JOIN ADMIN admin ON a.ADMIN_ID = admin.ADMIN_ID 
              ORDER BY a.CREATED_AT DESC";
              
    // Add limit clause if limit parameter is provided
    if ($limit) {
        $query .= " LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $limit);
    } else {
        $stmt = $conn->prepare($query);
    }
    
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
