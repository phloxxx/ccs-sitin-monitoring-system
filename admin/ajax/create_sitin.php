<?php
session_start();
require_once('../../config/db.php');

// Ensure the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Get POST data
$idno = isset($_POST['idno']) ? trim($_POST['idno']) : '';
$lab_id = isset($_POST['lab_id']) ? (int)$_POST['lab_id'] : 0;
$purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
$session_count = isset($_POST['session_count']) ? (int)$_POST['session_count'] : 1; // Default to 1

// Validate inputs
if (empty($idno) || $lab_id <= 0 || empty($purpose)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Check if student already has an active session
    $stmt = $conn->prepare("SELECT SITIN_ID, LAB_NAME FROM SITIN s 
                           JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID 
                           WHERE s.IDNO = ? AND s.STATUS = 'ACTIVE'");
    $stmt->bind_param("s", $idno);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $active_session = $result->fetch_assoc();
        throw new Exception("Student already has an active session in " . $active_session['LAB_NAME'] . 
                          ". Please end the current session before starting a new one.");
    }
    
    // Check if student exists and has enough sessions
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
        throw new Exception("Student doesn't have enough remaining sessions");
    }
    
    // Create the sit-in record
    $now = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime("+1 hour")); // 1 hour session
    
    $stmt = $conn->prepare("INSERT INTO SITIN (IDNO, LAB_ID, ADMIN_ID, PURPOSE, SESSION_START, SESSION_END, SESSION_DURATION, STATUS) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE')");
    $stmt->bind_param("siisssi", $idno, $lab_id, $admin_id, $purpose, $now, $end_time, $session_count);
    $stmt->execute();
    
    // Update student's remaining sessions
    $new_sessions = $remaining_sessions - $session_count;
    $stmt = $conn->prepare("UPDATE USERS SET SESSION = ? WHERE IDNO = ?");
    $stmt->bind_param("is", $new_sessions, $idno);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sit-in session created successfully',
        'remaining_sessions' => $new_sessions
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
