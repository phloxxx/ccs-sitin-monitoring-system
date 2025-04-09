<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Initialize response
$response = ['success' => false, 'message' => ''];

try {
    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Reset sessions for all students to 30
        $stmt = $conn->prepare("UPDATE USERS SET SESSION = 30");
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'All sessions reset successfully';
        } else {
            $response['message'] = 'Error resetting sessions: ' . $conn->error;
            throw new Exception('Database error');
        }
    } else {
        $response['message'] = 'Invalid request method';
    }
} catch (Exception $e) {
    // Exception message is already set in $response
    error_log('Error in reset_sessions.php: ' . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
