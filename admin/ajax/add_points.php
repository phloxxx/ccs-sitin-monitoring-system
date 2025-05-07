<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$admin_id = $_SESSION['admin_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and validate input
    $student_input = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
    $points = isset($_POST['points']) ? (int)$_POST['points'] : 0;
    
    if (empty($student_input) || $points <= 0) {
        $response['message'] = 'Please provide a valid student name/ID and points.';
    } else {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // Check if input is a student ID or a name
            // First try exact match with student ID
            $stmt = $conn->prepare("SELECT IDNO as USER_ID, CONCAT(FIRSTNAME, ' ', LASTNAME) as fullname FROM USERS WHERE IDNO = ?");
            $stmt->bind_param("s", $student_input);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Found by ID - exact match
                $student_data = $result->fetch_assoc();
                $student_id = $student_data['USER_ID'];
                $student_name = $student_data['fullname'];
            } else {
                // Try to find by name (partial match)
                $stmt = $conn->prepare("SELECT IDNO as USER_ID, CONCAT(FIRSTNAME, ' ', LASTNAME) as fullname 
                                       FROM USERS 
                                       WHERE CONCAT(FIRSTNAME, ' ', LASTNAME) LIKE ? OR FIRSTNAME LIKE ? OR LASTNAME LIKE ? 
                                       ORDER BY (CASE 
                                           WHEN CONCAT(FIRSTNAME, ' ', LASTNAME) LIKE ? THEN 1
                                           WHEN FIRSTNAME LIKE ? OR LASTNAME LIKE ? THEN 2
                                           ELSE 3
                                       END)
                                       LIMIT 1");
                $search_pattern = "%" . $student_input . "%";
                $exact_pattern = $student_input;
                $stmt->bind_param("ssssss", $search_pattern, $search_pattern, $search_pattern, $exact_pattern, $exact_pattern, $exact_pattern);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Found by name
                    $student_data = $result->fetch_assoc();
                    $student_id = $student_data['USER_ID'];
                    $student_name = $student_data['fullname'];
                } else {
                    // No student found with ID or name
                    $response['message'] = 'No student found with the provided name or ID. Please verify your input and try again.';
                    $conn->rollback();
                    $stmt->close();
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit();
                }
            }
            $stmt->close();
            
            // Check if student has a record in the POINTS table
            $stmt = $conn->prepare("SELECT POINTS FROM STUDENT_POINTS WHERE USER_ID = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $current_points = $result->fetch_assoc()['POINTS'];
                $new_points = $current_points + $points;
                
                $stmt = $conn->prepare("UPDATE STUDENT_POINTS SET POINTS = ?, UPDATED_AT = NOW(), ADMIN_ID = ? WHERE USER_ID = ?");
                $stmt->bind_param("iis", $new_points, $admin_id, $student_id);
                $stmt->execute();
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO STUDENT_POINTS (USER_ID, POINTS, ADMIN_ID, CREATED_AT, UPDATED_AT) VALUES (?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("sii", $student_id, $points, $admin_id);
                $stmt->execute();
                $new_points = $points;
            }
            $stmt->close();
            
            // Create a history entry
            $stmt = $conn->prepare("INSERT INTO POINTS_HISTORY (USER_ID, POINTS_ADDED, ADMIN_ID, CREATED_AT) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sii", $student_id, $points, $admin_id);
            $stmt->execute();
            $stmt->close();
            
            $reward_earned = false;
            
            // Check if student has reached 3 points to grant an additional session
            if ($new_points >= 3) {
                // Reduce points by 3
                $reduced_points = $new_points - 3;
                $stmt = $conn->prepare("UPDATE STUDENT_POINTS SET POINTS = ?, UPDATED_AT = NOW() WHERE USER_ID = ?");
                $stmt->bind_param("is", $reduced_points, $student_id);
                $stmt->execute();
                $stmt->close();
                
                // Add a record to the REWARDS table
                $stmt = $conn->prepare("INSERT INTO STUDENT_REWARDS (USER_ID, REWARD_TYPE, ADMIN_ID, CREATED_AT) VALUES (?, 'EXTRA_SESSION', ?, NOW())");
                $stmt->bind_param("si", $student_id, $admin_id);
                $stmt->execute();
                $stmt->close();
                
                $reward_earned = true;
                $new_points = $reduced_points;
            }
            
            $conn->commit();
            
            $response['success'] = true;
            $response['student_id'] = $student_id;
            $response['student_name'] = $student_name;
            $response['new_points'] = $new_points;
            $response['reward_earned'] = $reward_earned;
            
            if ($reward_earned) {
                $response['message'] = "Points added successfully! $student_name has earned an extra session as a reward!";
            } else {
                $response['message'] = "Points added successfully to $student_name!";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $response['message'] = 'An error occurred: ' . $e->getMessage();
        }
    }
}

// Return the response as JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
