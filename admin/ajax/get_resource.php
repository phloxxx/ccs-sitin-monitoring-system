<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get resource ID
$resource_id = isset($_GET['id']) ? $_GET['id'] : 0;

if (!$resource_id) {
    echo json_encode(['success' => false, 'message' => 'Resource ID is required']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT * FROM LAB_RESOURCES WHERE RESOURCE_ID = ?");
    $stmt->bind_param("i", $resource_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'resource' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Resource not found']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
