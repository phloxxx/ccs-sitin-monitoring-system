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
if (!isset($_POST['type']) || trim($_POST['type']) === '') {
    echo json_encode(['success' => false, 'message' => 'Item type is required']);
    exit();
}

if (!isset($_POST['id']) || !is_numeric($_POST['id']) || intval($_POST['id']) < 1) {
    echo json_encode(['success' => false, 'message' => 'Valid item ID is required']);
    exit();
}

try {
    $type = trim($_POST['type']);
    $id = intval($_POST['id']);
    $admin_id = $_SESSION['admin_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    if ($type === 'lab') {
        // Get lab name for logging
        $stmt = $conn->prepare("SELECT LAB_NAME FROM LABORATORY WHERE LAB_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Laboratory not found']);
            exit();
        }
        
        $lab_name = $result->fetch_assoc()['LAB_NAME'];
        
        // Delete all PCs in this lab first
        $stmt = $conn->prepare("DELETE FROM PC WHERE LAB_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $pc_count = $stmt->affected_rows;
        
        // Now delete the lab
        $stmt = $conn->prepare("DELETE FROM LABORATORY WHERE LAB_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Log the action
            $action = "Deleted laboratory: $lab_name (ID: $id) and $pc_count associated PCs";
            $log_stmt = $conn->prepare("INSERT INTO ADMIN_LOGS (admin_id, action, timestamp) VALUES (?, ?, NOW())");
            $log_stmt->bind_param("is", $admin_id, $action);
            $log_stmt->execute();
            
            $conn->commit();
            echo json_encode([
                'success' => true, 
                'message' => "Laboratory \"$lab_name\" and all its PCs have been deleted"
            ]);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete laboratory']);
        }
        
    } elseif ($type === 'pc') {
        // Get PC details for logging
        $stmt = $conn->prepare("SELECT PC_NAME, LAB_ID FROM PC WHERE PC_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'PC not found']);
            exit();
        }
        
        $pc_data = $result->fetch_assoc();
        $pc_name = $pc_data['PC_NAME'];
        $lab_id = $pc_data['LAB_ID'];
        
        // Delete the PC
        $stmt = $conn->prepare("DELETE FROM PC WHERE PC_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Log the action
            $action = "Deleted PC: $pc_name (ID: $id) from Lab ID: $lab_id";
            $log_stmt = $conn->prepare("INSERT INTO ADMIN_LOGS (admin_id, action, timestamp) VALUES (?, ?, NOW())");
            $log_stmt->bind_param("is", $admin_id, $action);
            $log_stmt->execute();
            
            $conn->commit();
            echo json_encode([
                'success' => true, 
                'message' => "PC \"$pc_name\" has been deleted"
            ]);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete PC']);
        }
        
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Invalid item type']);
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}