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
    // Get laboratory details
    $stmt = $conn->prepare("SELECT * FROM LABORATORY WHERE LAB_ID = ?");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Laboratory not found']);
        exit();
    }
    
    $lab = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'lab' => $lab
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}