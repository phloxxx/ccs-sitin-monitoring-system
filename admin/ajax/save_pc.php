<?php
session_start();
require_once('../../config/db.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get form data
$pc_id = isset($_POST['pc_id']) ? intval($_POST['pc_id']) : 0;
$lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;
$pc_number = isset($_POST['pc_number']) ? intval($_POST['pc_number']) : 0;
$status = isset($_POST['status']) ? trim(strtoupper($_POST['status'])) : 'AVAILABLE';

// Validate status values
if (!in_array($status, ['AVAILABLE', 'RESERVED', 'MAINTENANCE'])) {
    $status = 'AVAILABLE'; // Default to AVAILABLE if invalid
}

// Validate
if ($lab_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid laboratory']);
    exit();
}

if ($pc_number <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PC number']);
    exit();
}

// If adding a new PC, check if the lab has capacity
if ($pc_id <= 0) {
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
    
    // Check if adding this PC would exceed capacity
    if ($currentCount >= $capacity) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot add more PCs. The laboratory capacity is {$capacity} PCs and currently has {$currentCount} PCs."
        ]);
        exit();
    }
}

// Check for duplicate PC numbers in the same lab (except for the current PC being edited)
$sql = "SELECT COUNT(*) as count FROM PC WHERE LAB_ID = ? AND PC_NUMBER = ? AND PC_ID != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $lab_id, $pc_number, $pc_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'A PC with this number already exists in this laboratory']);
    exit();
}

if ($pc_id > 0) {
    // Update existing PC
    $sql = "UPDATE PC SET LAB_ID = ?, PC_NUMBER = ?, STATUS = ? WHERE PC_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisi", $lab_id, $pc_number, $status, $pc_id);
} else {
    // Insert new PC
    $sql = "INSERT INTO PC (LAB_ID, PC_NUMBER, STATUS) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $lab_id, $pc_number, $status);
}

$success = $stmt->execute();

if ($success) {
    if ($pc_id <= 0) {
        $pc_id = $conn->insert_id;
    }
    echo json_encode(['success' => true, 'pc_id' => $pc_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>