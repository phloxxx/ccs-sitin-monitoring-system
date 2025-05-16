<?php
session_start();
require_once('../../config/db.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get item type and ID
$type = isset($_POST['type']) ? $_POST['type'] : '';
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

// Validate
if (empty($type) || $id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Handle different types
try {
    if ($type === 'lab') {
        // Delete laboratory and all its PCs
        $conn->begin_transaction();
        
        // First delete all PCs in the lab
        $stmt = $conn->prepare("DELETE FROM PC WHERE LAB_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        // Then delete the lab
        $stmt = $conn->prepare("DELETE FROM LABORATORY WHERE LAB_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Laboratory deleted successfully']);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Laboratory not found']);
        }
        $stmt->close();
        
    } else if ($type === 'pc') {
        // Delete a single PC
        $stmt = $conn->prepare("DELETE FROM PC WHERE PC_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'PC deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'PC not found']);
        }
        $stmt->close();
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown item type']);
    }
    
} catch (Exception $e) {
    // If in transaction, roll back
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>