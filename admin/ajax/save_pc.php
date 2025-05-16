<?php
session_start();
require_once('../../config/db.php');

// For debugging - log the received data
error_log("save_pc.php received: " . print_r($_POST, true));

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate inputs
if (!isset($_POST['lab_id']) || !isset($_POST['pc_number']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$lab_id = intval($_POST['lab_id']);
$pc_id = isset($_POST['pc_id']) ? intval($_POST['pc_id']) : 0;
$pc_number = intval($_POST['pc_number']);
$status = $_POST['status']; // Allow any status including "IN_USE"

// Validate PC number
if ($pc_number <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PC number']);
    exit();
}

// For debugging
error_log("Processing PC update: ID=$pc_id, Number=$pc_number, Status=$status");

try {
    if ($pc_id > 0) {
        // Update existing PC
        $stmt = $conn->prepare("UPDATE PC SET PC_NUMBER = ?, STATUS = ? WHERE PC_ID = ? AND LAB_ID = ?");
        $stmt->bind_param("isii", $pc_number, $status, $pc_id, $lab_id);
        
        $result = $stmt->execute();
        error_log("Update result: " . ($result ? "Success" : "Failed") . " - " . $stmt->error);
        
        if ($result && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'PC updated successfully']);
        } else if ($result) {
            // Query executed but no rows affected (possibly same values)
            echo json_encode(['success' => true, 'message' => 'No changes made to PC']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
    } else {
        // Add new PC
        $stmt = $conn->prepare("INSERT INTO PC (LAB_ID, PC_NUMBER, STATUS) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $lab_id, $pc_number, $status);
        
        $result = $stmt->execute();
        error_log("Insert result: " . ($result ? "Success" : "Failed") . " - " . $stmt->error);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'PC added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>