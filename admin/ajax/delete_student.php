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
            
            // Start a transaction to ensure data integrity
            $conn->begin_transaction();
            
            try {
                // Check if student exists
                $checkStudent = $conn->prepare("SELECT IDNO FROM USERS WHERE IDNO = ?");
                $checkStudent->bind_param("s", $student_id);
                $checkStudent->execute();
                $result = $checkStudent->get_result();
                
                if ($result->num_rows === 0) {
                    throw new Exception("Student not found");
                }
                
                // First, try to delete any related SITIN records
                // This step may fail if there's no foreign key constraint, but we'll continue anyway
                try {
                    $deleteSitIn = $conn->prepare("DELETE FROM SITIN WHERE IDNO = ?");
                    $deleteSitIn->bind_param("s", $student_id);
                    $deleteSitIn->execute();
                } catch (Exception $e) {
                    // Log the error but continue with student deletion
                    error_log('Error deleting SITIN records: ' . $e->getMessage());
                }
                
                // Now delete the student
                $deleteStudent = $conn->prepare("DELETE FROM USERS WHERE IDNO = ?");
                $deleteStudent->bind_param("s", $student_id);
                
                if ($deleteStudent->execute()) {
                    // Commit the transaction
                    $conn->commit();
                    
                    $response['success'] = true;
                    $response['message'] = 'Student deleted successfully';
                } else {
                    throw new Exception("Error deleting student: " . $conn->error);
                }
                
            } catch (Exception $e) {
                // Rollback the transaction
                $conn->rollback();
                throw $e;
            }
            
        } else {
            $response['message'] = 'Student ID is required';
        }
    } else {
        $response['message'] = 'Invalid request method';
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Error in delete_student.php: ' . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
