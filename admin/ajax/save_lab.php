<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

// Check if required fields are present
if (!isset($_POST['lab_name']) || !isset($_POST['capacity'])) {
    echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
    exit();
}

$lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;
$lab_name = trim($_POST['lab_name']);
$capacity = intval($_POST['capacity']);

// Validate inputs
if (empty($lab_name)) {
    echo json_encode(['success' => false, 'message' => 'Laboratory name is required']);
    exit();
}

if ($capacity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Capacity must be greater than 0']);
    exit();
}

try {
    // Check if this is an update (existing lab) or insert (new lab)
    if ($lab_id > 0) {
        // Update existing laboratory
        $stmt = $conn->prepare("UPDATE LABORATORY SET LAB_NAME = ?, CAPACITY = ? WHERE LAB_ID = ?");
        $stmt->bind_param("sii", $lab_name, $capacity, $lab_id);
    } else {
        // Insert new laboratory
        $stmt = $conn->prepare("INSERT INTO LABORATORY (LAB_NAME, CAPACITY) VALUES (?, ?)");
        $stmt->bind_param("si", $lab_name, $capacity);
    }
    
    $result = $stmt->execute();
    
    if ($result) {
        // If it was an insert, get the new ID
        if ($lab_id == 0) {
            $lab_id = $conn->insert_id;
        }
        
        echo json_encode([
            'success' => true, 
            'lab_id' => $lab_id,
            'message' => 'Laboratory saved successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Error saving laboratory: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>