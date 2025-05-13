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

// Check if required data is present
if (!isset($_POST['lab_id']) || !is_numeric($_POST['lab_id'])) {
    echo json_encode(['success' => false, 'message' => 'Laboratory ID is required']);
    exit();
}

if (!isset($_POST['pc_number']) || !is_numeric($_POST['pc_number']) || intval($_POST['pc_number']) < 1) {
    echo json_encode(['success' => false, 'message' => 'Valid PC number is required']);
    exit();
}

if (!isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'PC status is required']);
    exit();
}

// Valid statuses
$validStatuses = ['AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE'];
if (!in_array($_POST['status'], $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

try {
    $pc_id = isset($_POST['pc_id']) ? intval($_POST['pc_id']) : 0;
    $lab_id = intval($_POST['lab_id']);
    $pc_number = intval($_POST['pc_number']);
    $status = $_POST['status'];
    
    // Check if lab exists
    $stmt = $conn->prepare("SELECT LAB_ID FROM LABORATORY WHERE LAB_ID = ?");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Laboratory not found']);
        exit();
    }
    
    // Check for duplicate PC number in the same lab
    $stmt = $conn->prepare("SELECT PC_ID FROM PC WHERE PC_NUMBER = ? AND LAB_ID = ? AND PC_ID != ?");
    $stmt->bind_param("iii", $pc_number, $lab_id, $pc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'A PC with this number already exists in this laboratory']);
        exit();
    }
    
    // If pc_id is 0, insert new PC, otherwise update existing
    if ($pc_id === 0) {
        $stmt = $conn->prepare("INSERT INTO PC (LAB_ID, PC_NUMBER, STATUS) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $lab_id, $pc_number, $status);
        $stmt->execute();
        $pc_id = $conn->insert_id;
        $message = 'PC created successfully';
    } else {
        $stmt = $conn->prepare("UPDATE PC SET LAB_ID = ?, PC_NUMBER = ?, STATUS = ? WHERE PC_ID = ?");
        $stmt->bind_param("iisi", $lab_id, $pc_number, $status, $pc_id);
        $stmt->execute();
        $message = 'PC updated successfully';
    }
    
    // Log the action
    $admin_id = $_SESSION['admin_id'];
    $action = $pc_id === 0 ? "Created new PC: $pc_number in Lab ID: $lab_id" : "Updated PC: $pc_number (Status: $status)";
    $stmt = $conn->prepare("INSERT INTO ADMIN_LOGS (admin_id, action, timestamp) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $admin_id, $action);
    $stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'pc_id' => $pc_id
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}