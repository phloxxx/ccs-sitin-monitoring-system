<?php
session_start();
require_once('../../config/db.php');

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Set response header to JSON
header('Content-Type: application/json');

// Check if lab_id is provided
if (!isset($_GET['lab_id']) || !is_numeric($_GET['lab_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid laboratory ID']);
    exit();
}

$lab_id = intval($_GET['lab_id']);

try {
    // Get PCs for the lab
    $stmt = $conn->prepare("SELECT * FROM PC WHERE LAB_ID = ? ORDER BY PC_NUMBER");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pcs = [];
    while ($row = $result->fetch_assoc()) {
        $pcs[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'pcs' => $pcs
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}