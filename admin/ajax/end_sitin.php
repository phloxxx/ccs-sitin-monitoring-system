<?php
session_start();
require_once('../../config/db.php');

// Ensure the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Get the sit-in ID
$sitin_id = isset($_POST['sitin_id']) ? (int)$_POST['sitin_id'] : 0;

if ($sitin_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid sit-in ID']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Get the sit-in record
    $stmt = $conn->prepare("SELECT * FROM SITIN WHERE SITIN_ID = ? AND STATUS = 'ACTIVE'");
    $stmt->bind_param("i", $sitin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Sit-in session not found or already completed");
    }
    
    $sitin = $result->fetch_assoc();
    
    // Calculate the actual session duration
    $start_time = new DateTime($sitin['SESSION_START']);
    $end_time = new DateTime();
    $duration = $start_time->diff($end_time);
    $duration_hours = $duration->h + ($duration->days * 24);
    
    // Update the sit-in record
    $stmt = $conn->prepare("UPDATE SITIN SET 
                           SESSION_END = NOW(), 
                           STATUS = 'COMPLETED', 
                           UPDATED_AT = NOW() 
                           WHERE SITIN_ID = ?");
    $stmt->bind_param("i", $sitin_id);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sit-in session completed successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
