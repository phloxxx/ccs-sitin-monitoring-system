<?php
session_start();
require_once('../../config/db.php');

// For debugging
error_log("bulk_update_pcs.php received: " . print_r($_POST, true));

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate inputs
if (!isset($_POST['action']) || !isset($_POST['pcs'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$action = $_POST['action'];
$pcs = json_decode($_POST['pcs'], true);

if (!is_array($pcs) || count($pcs) === 0) {
    echo json_encode(['success' => false, 'message' => 'No PCs selected']);
    exit();
}

try {
    if ($action === 'status') {
        if (!isset($_POST['status'])) {
            echo json_encode(['success' => false, 'message' => 'Status field is required']);
            exit();
        }
        
        $status = $_POST['status'];
        error_log("Updating status to: $status for " . count($pcs) . " PCs");
        
        // Update PC status
        $stmt = $conn->prepare("UPDATE PC SET STATUS = ? WHERE PC_ID = ?");
        $stmt->bind_param("si", $status, $pc_id);
        
        $updated = 0;
        foreach ($pcs as $pc_id) {
            $pc_id = intval($pc_id);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $updated++;
            }
        }
        
        error_log("Updated $updated PCs");
        echo json_encode(['success' => true, 'message' => "{$updated} PCs updated."]);
        $stmt->close();
    } else if ($action === 'delete') {
        // Delete PCs
        $stmt = $conn->prepare("DELETE FROM PC WHERE PC_ID = ?");
        $stmt->bind_param("i", $pc_id);
        
        $deleted = 0;
        foreach ($pcs as $pc_id) {
            $pc_id = intval($pc_id);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $deleted++;
            }
        }
        
        echo json_encode(['success' => true, 'message' => "{$deleted} PCs deleted."]);
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>