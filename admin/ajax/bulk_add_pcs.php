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

if (!isset($_POST['count']) || !is_numeric($_POST['count']) || intval($_POST['count']) < 1) {
    echo json_encode(['success' => false, 'message' => 'Valid PC count is required']);
    exit();
}

if (!isset($_POST['prefix']) || trim($_POST['prefix']) === '') {
    echo json_encode(['success' => false, 'message' => 'PC name prefix is required']);
    exit();
}

if (!isset($_POST['start_number']) || !is_numeric($_POST['start_number']) || intval($_POST['start_number']) < 1) {
    echo json_encode(['success' => false, 'message' => 'Valid starting number is required']);
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
    $lab_id = intval($_POST['lab_id']);
    $count = intval($_POST['count']);
    $prefix = trim($_POST['prefix']);
    $start_number = intval($_POST['start_number']);
    $status = $_POST['status'];
    
    // Check if lab exists
    $stmt = $conn->prepare("SELECT LAB_ID, CAPACITY FROM LABORATORY WHERE LAB_ID = ?");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Laboratory not found']);
        exit();
    }
    
    $lab = $result->fetch_assoc();
    
    // Check current PC count in lab
    $stmt = $conn->prepare("SELECT COUNT(*) as pc_count FROM PC WHERE LAB_ID = ?");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_count = $result->fetch_assoc()['pc_count'];
    
    // Check if adding these PCs would exceed lab capacity
    if (($current_count + $count) > $lab['CAPACITY']) {
        echo json_encode([
            'success' => false, 
            'message' => "Adding $count PCs would exceed the laboratory capacity of {$lab['CAPACITY']}. " .
                        "The lab currently has $current_count PCs."
        ]);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    $added_pcs = 0;
    $max_pc_number = $start_number + $count - 1;
    
    // Check for duplicate PC names or numbers
    $existing_names = [];
    $existing_numbers = [];
    
    for ($i = $start_number; $i <= $max_pc_number; $i++) {
        $pc_name = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
        
        // Check if name exists
        $stmt = $conn->prepare("SELECT PC_ID FROM PC WHERE PC_NAME = ? AND LAB_ID = ?");
        $stmt->bind_param("si", $pc_name, $lab_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $existing_names[] = $pc_name;
        }
        
        // Check if number exists
        $stmt = $conn->prepare("SELECT PC_ID FROM PC WHERE PC_NUMBER = ? AND LAB_ID = ?");
        $stmt->bind_param("ii", $i, $lab_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $existing_numbers[] = $i;
        }
    }
    
    // If duplicates found, abort
    if (!empty($existing_names) || !empty($existing_numbers)) {
        $conn->rollback();
        
        $error_message = '';
        if (!empty($existing_names)) {
            $error_message .= 'PC names already exist: ' . implode(', ', $existing_names) . '. ';
        }
        if (!empty($existing_numbers)) {
            $error_message .= 'PC numbers already exist: ' . implode(', ', $existing_numbers) . '.';
        }
        
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit();
    }
    
    // Insert PCs
    $stmt = $conn->prepare("INSERT INTO PC (LAB_ID, PC_NAME, PC_NUMBER, STATUS) VALUES (?, ?, ?, ?)");
    
    for ($i = $start_number; $i <= $max_pc_number; $i++) {
        $pc_name = $prefix . str_pad($i, 2, '0', STR_PAD_LEFT);
        $pc_number = $i;
        
        $stmt->bind_param("isis", $lab_id, $pc_name, $pc_number, $status);
        
        if ($stmt->execute()) {
            $added_pcs++;
        }
    }
    
    if ($added_pcs > 0) {
        // Log the action
        $admin_id = $_SESSION['admin_id'];
        $action = "Bulk added $added_pcs PCs to Lab ID: $lab_id with status: $status";
        $log_stmt = $conn->prepare("INSERT INTO ADMIN_LOGS (admin_id, action, timestamp) VALUES (?, ?, NOW())");
        $log_stmt->bind_param("is", $admin_id, $action);
        $log_stmt->execute();
        
        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => "$added_pcs PCs added successfully",
            'count' => $added_pcs
        ]);
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to add PCs']);
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}