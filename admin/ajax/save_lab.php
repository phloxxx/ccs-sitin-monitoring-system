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
if (!isset($_POST['lab_name']) || trim($_POST['lab_name']) === '') {
    echo json_encode(['success' => false, 'message' => 'Laboratory name is required']);
    exit();
}

if (!isset($_POST['capacity']) || !is_numeric($_POST['capacity']) || intval($_POST['capacity']) < 1) {
    echo json_encode(['success' => false, 'message' => 'Valid capacity is required']);
    exit();
}

try {
    $lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;
    $lab_name = trim($_POST['lab_name']);
    $capacity = intval($_POST['capacity']);
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    
    // Check for duplicate name
    $stmt = $conn->prepare("SELECT LAB_ID FROM LABORATORY WHERE LAB_NAME = ? AND LAB_ID != ?");
    $stmt->bind_param("si", $lab_name, $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'A laboratory with this name already exists']);
        exit();
    }
    
    // If lab_id is 0, insert new lab, otherwise update existing
    if ($lab_id === 0) {
        $stmt = $conn->prepare("INSERT INTO LABORATORY (LAB_NAME, CAPACITY, LOCATION) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $lab_name, $capacity, $location);
        $stmt->execute();
        $lab_id = $conn->insert_id;
        $message = 'Laboratory created successfully';
    } else {
        $stmt = $conn->prepare("UPDATE LABORATORY SET LAB_NAME = ?, CAPACITY = ?, LOCATION = ? WHERE LAB_ID = ?");
        $stmt->bind_param("sisi", $lab_name, $capacity, $location, $lab_id);
        $stmt->execute();
        $message = 'Laboratory updated successfully';
    }
    
    // Log the action
    $admin_id = $_SESSION['admin_id'];
    $action = $lab_id === 0 ? "Created new laboratory: $lab_name" : "Updated laboratory: $lab_name";
    $stmt = $conn->prepare("INSERT INTO ADMIN_LOGS (admin_id, action, timestamp) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $admin_id, $action);
    $stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'lab_id' => $lab_id
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}