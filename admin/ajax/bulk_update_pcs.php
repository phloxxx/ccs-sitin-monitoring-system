<?php
session_start();
require_once('../../config/db.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get action type and PCs
$action = isset($_POST['action']) ? $_POST['action'] : '';
$pcsJson = isset($_POST['pcs']) ? $_POST['pcs'] : '[]';
$pcs = json_decode($pcsJson, true);

// Validate
if (empty($pcs) || !is_array($pcs)) {
    echo json_encode(['success' => false, 'message' => 'No PCs selected']);
    exit();
}

if (!in_array($action, ['status', 'delete'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    $success = true;
    $message = '';
      if ($action === 'status') {
        // Update status action
        $status = isset($_POST['status']) ? trim(strtoupper($_POST['status'])) : '';
        
        // Validate status values
        if (!in_array($status, ['AVAILABLE', 'RESERVED', 'MAINTENANCE'])) {
            throw new Exception('Invalid status value');
        }
        
        if (empty($status)) {
            throw new Exception('No status specified');
        }
        
        // Prepare statement to update status
        $stmt = $conn->prepare("UPDATE PC SET STATUS = ? WHERE PC_ID = ?");
        $stmt->bind_param("si", $status, $pcId);
        
        // Update each PC
        $updatedCount = 0;
        foreach ($pcs as $pcId) {
            $pcId = intval($pcId); // Ensure PC_ID is an integer
            if ($pcId > 0) {
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $updatedCount++;
                }
            }
        }
        
        $stmt->close();
        $message = "$updatedCount PC(s) updated successfully";
        
    } else if ($action === 'delete') {
        // Delete action
        $stmt = $conn->prepare("DELETE FROM PC WHERE PC_ID = ?");
        $stmt->bind_param("i", $pcId);
        
        // Delete each PC
        $deletedCount = 0;
        foreach ($pcs as $pcId) {
            $pcId = intval($pcId); // Ensure PC_ID is an integer
            if ($pcId > 0) {
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $deletedCount++;
                }
            }
        }
        
        $stmt->close();
        $message = "$deletedCount PC(s) deleted successfully";
    }
    
    // Commit transaction if we got here
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    // Roll back on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>