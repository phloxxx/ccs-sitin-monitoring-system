<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get form data
$lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;
$count = isset($_POST['count']) ? intval($_POST['count']) : 0;
$start_number = isset($_POST['start_number']) ? intval($_POST['start_number']) : 1;
$status = isset($_POST['status']) ? $_POST['status'] : 'AVAILABLE';

// Validate lab_id
if ($lab_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid laboratory ID']);
    exit();
}

// Validate count
if ($count <= 0) {
    echo json_encode(['success' => false, 'message' => 'Number of PCs must be greater than 0']);
    exit();
}

// Check if adding these PCs would exceed lab capacity
try {
    // Get lab capacity
    $stmt = $conn->prepare("SELECT CAPACITY FROM LABORATORY WHERE LAB_ID = ?");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lab = $result->fetch_assoc();
    $stmt->close();
    
    if (!$lab) {
        echo json_encode(['success' => false, 'message' => 'Laboratory not found']);
        exit();
    }
    
    $capacity = $lab['CAPACITY'];
    
    // Count existing PCs
    $stmt = $conn->prepare("SELECT COUNT(*) as pc_count FROM PC WHERE LAB_ID = ?");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count_data = $result->fetch_assoc();
    $existing_count = $count_data['pc_count'];
    $stmt->close();
    
    // Check if adding would exceed capacity
    if ($existing_count + $count > $capacity) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot add {$count} PCs. Would exceed laboratory capacity of {$capacity} PCs. Currently have {$existing_count} PCs."
        ]);
        exit();
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Check for duplicate PC numbers
    $duplicates = [];
    for ($i = 0; $i < $count; $i++) {
        $pc_number = $start_number + $i;
        
        $stmt = $conn->prepare("SELECT PC_ID FROM PC WHERE LAB_ID = ? AND PC_NUMBER = ?");
        $stmt->bind_param("ii", $lab_id, $pc_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $duplicates[] = $pc_number;
        }
        
        $stmt->close();
    }
    
    if (!empty($duplicates)) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'PC numbers ' . implode(', ', $duplicates) . ' already exist in this laboratory']);
        exit();
    }
    
    // Add PCs
    $stmt = $conn->prepare("INSERT INTO PC (LAB_ID, PC_NUMBER, STATUS) VALUES (?, ?, ?)");
    
    for ($i = 0; $i < $count; $i++) {
        $pc_number = $start_number + $i;
        $stmt->bind_param("iis", $lab_id, $pc_number, $status);
        $stmt->execute();
    }
    
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => "{$count} PCs added successfully"]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>