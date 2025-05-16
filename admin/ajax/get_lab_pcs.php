<?php
session_start();
require_once('../../config/db.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate input
if (!isset($_GET['lab_id'])) {
    echo json_encode(['success' => false, 'message' => 'Laboratory ID is required']);
    exit();
}

$lab_id = intval($_GET['lab_id']);

try {
    $stmt = $conn->prepare("SELECT * FROM PC WHERE LAB_ID = ? ORDER BY PC_NUMBER");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pcs = [];
    while ($row = $result->fetch_assoc()) {
        // Make sure STATUS is properly included
        if (!isset($row['STATUS'])) {
            $row['STATUS'] = 'AVAILABLE';
        }
        $pcs[] = $row;
    }
    
    echo json_encode(['success' => true, 'pcs' => $pcs]);
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>