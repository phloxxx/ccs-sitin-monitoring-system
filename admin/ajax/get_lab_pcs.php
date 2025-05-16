<?php
session_start();
require_once('../../config/db.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get lab ID
$lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : 0;

// Validate
if ($lab_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid laboratory ID']);
    exit();
}

// Fetch PCs
try {
    $stmt = $conn->prepare("SELECT * FROM PC WHERE LAB_ID = ? ORDER BY PC_NUMBER");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pcs = [];
    while ($row = $result->fetch_assoc()) {
        $pcs[] = $row;
    }
    
    echo json_encode(['success' => true, 'pcs' => $pcs]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>