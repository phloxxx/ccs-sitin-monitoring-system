<?php
session_start();
require_once('../../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$idno = $_POST['idno'] ?? '';
$lab_id = $_POST['lab_id'] ?? '';
$purpose = $_POST['purpose'] ?? '';
$session_count = $_POST['session_count'] ?? 1;
$admin_id = $_SESSION['admin_id'];

// Validate parameters
if (empty($idno) || empty($lab_id) || empty($purpose)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Check if student exists and has sessions left
    $stmt = $conn->prepare("SELECT SESSION FROM USERS WHERE IDNO = ?");
    $stmt->bind_param("s", $idno);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Student not found");
    }
    
    $student = $result->fetch_assoc();
    $remaining_sessions = $student['SESSION'];
    
    if ($remaining_sessions < $session_count) {
        throw new Exception("Student does not have enough remaining sessions");
    }
    
    // Check if student already has an active session
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM SITIN WHERE IDNO = ? AND STATUS = 'ACTIVE'");
    $stmt->bind_param("s", $idno);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        throw new Exception("Student already has an active session");
    }
    
    // Insert new sit-in session
    $stmt = $conn->prepare("INSERT INTO SITIN (IDNO, LAB_ID, ADMIN_ID, PURPOSE, SESSION_START, SESSION_DURATION, STATUS) 
                          VALUES (?, ?, ?, ?, NOW(), ?, 'ACTIVE')");
    $stmt->bind_param("siisi", $idno, $lab_id, $admin_id, $purpose, $session_count);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create session: " . $stmt->error);
    }
    
    // IMPORTANT: No longer deducting sessions when creating the sit-in
    // The sessions will only be deducted when the admin clicks "End Session"
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sit-in session created successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
