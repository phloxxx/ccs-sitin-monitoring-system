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
if (!isset($_POST['action']) || trim($_POST['action']) === '') {
    echo json_encode(['success' => false, 'message' => 'Action is required']);
    exit();
}

if (!isset($_POST['pcs']) || trim($_POST['pcs']) === '') {
    echo json_encode(['success' => false, 'message' => 'No PCs selected']);
    exit();
}

try {
    $action = trim($_POST['action']);
    $pcs = json_decode($_POST['pcs'], true);
    
    if (!is_array($pcs) || empty($pcs)) {
        echo json_encode(['success' => false, 'message' => 'No PCs selected']);
        exit();
    }
    
    // Sanitize PC IDs
    $pc_ids = array_map('intval', $pcs);
    
    // Start transaction
    $conn->begin_transaction();
    
    if ($action === 'status') {
        // Update status action
        
        if (!isset($_POST['status']) || trim($_POST['status']) === '') {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Status is required for this action']);
            exit();
        }
        
        $status = trim($_POST['status']);
        
        // Valid statuses
        $validStatuses = ['AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE'];
        if (!in_array($status, $validStatuses)) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Invalid status value']);
            exit();
        }
        
        // Prepare comma-separated list of PC IDs for IN clause
        $pc_ids_str = implode(',', $pc_ids);
        
        // Update status
        $stmt = $conn->prepare("UPDATE PC SET STATUS = ? WHERE PC_ID IN ($pc_ids_str)");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        
        $affected_rows = $stmt->affected_rows;
        
        // Log the action
        $admin_id = $_SESSION['admin_id'];
        $action_log = "Bulk updated status to '$status' for " . count($pc_ids) . " PCs (IDs: $pc_ids_str)";
        $log_stmt = $conn->prepare("INSERT INTO ADMIN_LOGS (admin_id, action, timestamp) VALUES (?, ?, NOW())");
        $log_stmt->bind_param("is", $admin_id, $action_log);
        $log_stmt->execute();
        
        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Status updated for $affected_rows PCs",
            'affected_rows' => $affected_rows
        ]);
        
    } elseif ($action === 'delete') {
        // Delete action
        
        // Prepare comma-separated list of PC IDs for IN clause
        $pc_ids_str = implode(',', $pc_ids);
        
        // Get PC numbers for logging
        $pc_names = [];
        $stmt = $conn->prepare("SELECT PC_NUMBER FROM PC WHERE PC_ID IN ($pc_ids_str)");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $pc_names[] = "PC #" . $row['PC_NUMBER'];
        }
        
        // Delete PCs
        $stmt = $conn->prepare("DELETE FROM PC WHERE PC_ID IN ($pc_ids_str)");
        $stmt->execute();
        
        $affected_rows = $stmt->affected_rows;
        
        // Log the action
        $admin_id = $_SESSION['admin_id'];
        $pc_names_str = implode(", ", $pc_names);
        $action_log = "Bulk deleted " . count($pc_ids) . " PCs (Names: $pc_names_str)";
        $log_stmt = $conn->prepare("INSERT INTO ADMIN_LOGS (admin_id, action, timestamp) VALUES (?, ?, NOW())");
        $log_stmt->bind_param("is", $admin_id, $action_log);
        $log_stmt->execute();
        
        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Deleted $affected_rows PCs",
            'affected_rows' => $affected_rows
        ]);
        
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}