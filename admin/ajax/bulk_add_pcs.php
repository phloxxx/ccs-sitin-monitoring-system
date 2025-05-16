<?php
session_start();
require_once('../../config/db.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get form data
$lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;
$count = isset($_POST['count']) ? intval($_POST['count']) : 0;
$start_number = isset($_POST['start_number']) ? intval($_POST['start_number']) : 1;
$status = isset($_POST['status']) ? $_POST['status'] : 'AVAILABLE';

// Validate
if ($lab_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid laboratory']);
    exit();
}

if ($count <= 0 || $count > 100) {
    echo json_encode(['success' => false, 'message' => 'Invalid PC count (must be 1-100)']);
    exit();
}

// Check laboratory capacity
$stmt = $conn->prepare("SELECT CAPACITY FROM LABORATORY WHERE LAB_ID = ?");
$stmt->bind_param("i", $lab_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Laboratory not found']);
    exit();
}

$lab = $result->fetch_assoc();
$capacity = $lab['CAPACITY'];
$stmt->close();

// Get current PC count in this lab
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM PC WHERE LAB_ID = ?");
$stmt->bind_param("i", $lab_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$currentCount = $row['count'];
$stmt->close();

// Check if adding these PCs would exceed capacity
if ($currentCount + $count > $capacity) {
    echo json_encode([
        'success' => false, 
        'message' => "Cannot add {$count} PCs. This would exceed the laboratory capacity of {$capacity} PCs. Currently have {$currentCount} PCs."
    ]);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    $added_count = 0;
    $errors = [];

    for ($i = 0; $i < $count; $i++) {
        $pc_number = $start_number + $i;
        
        // Check for duplicate PC numbers in the same lab
        $sql = "SELECT COUNT(*) as count FROM PC WHERE LAB_ID = ? AND PC_NUMBER = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $lab_id, $pc_number);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $errors[] = "PC #{$pc_number} already exists in this laboratory";
            continue;
        }
        
        // Insert new PC
        $sql = "INSERT INTO PC (LAB_ID, PC_NUMBER, STATUS) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $lab_id, $pc_number, $status);
        
        if ($stmt->execute()) {
            $added_count++;
        } else {
            $errors[] = "Error adding PC #{$pc_number}: " . $conn->error;
        }
    }
    
    // If no PCs were added, roll back and return error
    if ($added_count === 0) {
        $conn->rollback();
        echo json_encode([
            'success' => false, 
            'message' => 'No PCs were added. ' . implode('. ', $errors)
        ]);
        exit();
    }
    
    // Otherwise commit transaction
    $conn->commit();
    
    $message = "{$added_count} PC(s) added successfully";
    if (count($errors) > 0) {
        $message .= '. Some errors occurred: ' . implode('. ', $errors);
    }
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>