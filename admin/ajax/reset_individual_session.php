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
        // Check if student_id is provided
        if (isset($_POST['student_id']) && !empty($_POST['student_id'])) {
            $student_id = trim($_POST['student_id']);
            
            // Sanitize input
            $student_id = mysqli_real_escape_string($conn, $student_id);
            
            // Reset the student's sessions to 30
            $stmt = $conn->prepare("UPDATE USERS SET SESSION = 30 WHERE IDNO = ?");
            $stmt->bind_param("s", $student_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Sessions reset successfully';
            } else {
                $response['message'] = 'Error resetting sessions: ' . $conn->error;
                throw new Exception('Database error');
            }
        } else {
            $response['message'] = 'Student ID is required';
        }
    } else {
        $response['message'] = 'Invalid request method';
    }
} catch (Exception $e) {
    // Exception message is already set in $response
    error_log('Error in reset_individual_session.php: ' . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
