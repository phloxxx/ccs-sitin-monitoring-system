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

$sitin_id = $_POST['sitin_id'] ?? null;

if (!$sitin_id) {
    echo json_encode(['success' => false, 'message' => 'Missing sit-in ID']);
    exit;
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Get the sit-in session and student details
    $stmt = $conn->prepare("SELECT s.*, u.SESSION as remaining_sessions 
                            FROM SITIN s
                            JOIN USERS u ON s.IDNO = u.IDNO 
                            WHERE s.SITIN_ID = ? AND s.STATUS = 'ACTIVE'");
    $stmt->bind_param("i", $sitin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Active sit-in session not found");
    }
    
    $sitin = $result->fetch_assoc();
    $idno = $sitin['IDNO'];
    $remainingSessions = $sitin['remaining_sessions'];
    $sessionCount = $sitin['SESSION_DURATION'];
    
    // Check if student has enough sessions
    if ($remainingSessions < $sessionCount) {
        // In case the student doesn't have enough sessions, use what they have
        $sessionCount = $remainingSessions;
    }
    
    // Update the sit-in session to COMPLETED
    $stmt = $conn->prepare("UPDATE SITIN SET STATUS = 'COMPLETED', SESSION_END = NOW() WHERE SITIN_ID = ?");
    $stmt->bind_param("i", $sitin_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update sit-in session: " . $stmt->error);
    }
    
    // NOW deduct the session from the user
    $newSessionCount = $remainingSessions - $sessionCount;
    $stmt = $conn->prepare("UPDATE USERS SET SESSION = ? WHERE IDNO = ?");
    $stmt->bind_param("is", $newSessionCount, $idno);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update user's session count: " . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Sit-in session ended successfully',
        'session_deducted' => $sessionCount,
        'remaining_sessions' => $newSessionCount
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
