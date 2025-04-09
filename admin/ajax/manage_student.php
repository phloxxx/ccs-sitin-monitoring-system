<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Function to sanitize input data
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// Initialize response
$response = ['success' => false, 'message' => ''];

try {
    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $mode = isset($_POST['mode']) ? sanitize($_POST['mode']) : '';
        
        if ($mode === 'create') {
            // Required fields for creating a new student
            $requiredFields = ['idno', 'username', 'firstname', 'lastname', 'course', 'year', 'password', 'confirm_password', 'sessions'];
            $missingFields = [];
            
            // Check for missing fields
            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                $response['message'] = 'Required fields are missing: ' . implode(', ', $missingFields);
                throw new Exception('Missing required fields');
            }
            
            // Validate and sanitize inputs
            $idno = sanitize($_POST['idno']);
            $username = sanitize($_POST['username']);
            $firstname = sanitize($_POST['firstname']);
            $lastname = sanitize($_POST['lastname']);
            $midname = isset($_POST['midname']) ? sanitize($_POST['midname']) : '';
            $course = sanitize($_POST['course']);
            $year = sanitize($_POST['year']);
            $password = sanitize($_POST['password']); // Store password as-is without hashing
            $confirm_password = $_POST['confirm_password'];
            $sessions = (int)sanitize($_POST['sessions']);
            
            // Check if passwords match
            if ($password !== $confirm_password) {
                $response['message'] = 'Passwords do not match';
                throw new Exception('Password mismatch');
            }
            
            // Check if student ID already exists
            $checkIdno = $conn->prepare("SELECT IDNO FROM USERS WHERE IDNO = ?");
            $checkIdno->bind_param("s", $idno);
            $checkIdno->execute();
            $idnoResult = $checkIdno->get_result();
            
            if ($idnoResult->num_rows > 0) {
                $response['message'] = 'Student ID already exists';
                throw new Exception('Student ID already exists');
            }
            
            // Check if username already exists
            $checkUsername = $conn->prepare("SELECT USERNAME FROM USERS WHERE USERNAME = ?");
            $checkUsername->bind_param("s", $username);
            $checkUsername->execute();
            $usernameResult = $checkUsername->get_result();
            
            if ($usernameResult->num_rows > 0) {
                $response['message'] = 'Username already exists';
                throw new Exception('Username already exists');
            }
            
            // Insert new student into the database - with plain text password
            $stmt = $conn->prepare("INSERT INTO USERS (IDNO, LASTNAME, FIRSTNAME, MIDNAME, COURSE, YEAR, USERNAME, PASSWORD, SESSION, CREATED_AT) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->bind_param("ssssssssi", $idno, $lastname, $firstname, $midname, $course, $year, $username, $password, $sessions);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Student added successfully';
            } else {
                $response['message'] = 'Error adding student: ' . $conn->error;
                throw new Exception('Database error');
            }
            
        } elseif ($mode === 'edit') {
            // Required fields for editing a student
            $requiredFields = ['idno', 'username', 'firstname', 'lastname', 'course', 'year', 'sessions'];
            $missingFields = [];
            
            // Check for missing fields
            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                $response['message'] = 'Required fields are missing: ' . implode(', ', $missingFields);
                throw new Exception('Missing required fields');
            }
            
            // Validate and sanitize inputs
            $idno = sanitize($_POST['idno']);
            $username = sanitize($_POST['username']);
            $firstname = sanitize($_POST['firstname']);
            $lastname = sanitize($_POST['lastname']);
            $midname = isset($_POST['midname']) ? sanitize($_POST['midname']) : '';
            $course = sanitize($_POST['course']);
            $year = sanitize($_POST['year']);
            $sessions = (int)sanitize($_POST['sessions']);
            
            // Check if username already exists (excluding current student)
            $checkUsername = $conn->prepare("SELECT USERNAME FROM USERS WHERE USERNAME = ? AND IDNO != ?");
            $checkUsername->bind_param("ss", $username, $idno);
            $checkUsername->execute();
            $usernameResult = $checkUsername->get_result();
            
            if ($usernameResult->num_rows > 0) {
                $response['message'] = 'Username already exists';
                throw new Exception('Username already exists');
            }
            
            // Check if password is being updated
            if (isset($_POST['password']) && !empty($_POST['password'])) {
                // Validate password confirmation
                if (!isset($_POST['confirm_password']) || $_POST['password'] !== $_POST['confirm_password']) {
                    $response['message'] = 'Passwords do not match';
                    throw new Exception('Password mismatch');
                }
                
                $password = sanitize($_POST['password']); // Store password as-is without hashing
                
                // Update student in the database including password
                $stmt = $conn->prepare("UPDATE USERS SET LASTNAME = ?, FIRSTNAME = ?, MIDNAME = ?, COURSE = ?, YEAR = ?, USERNAME = ?, PASSWORD = ?, SESSION = ? WHERE IDNO = ?");
                $stmt->bind_param("sssssssss", $lastname, $firstname, $midname, $course, $year, $username, $password, $sessions, $idno);
            } else {
                // Update student in the database without changing password
                $stmt = $conn->prepare("UPDATE USERS SET LASTNAME = ?, FIRSTNAME = ?, MIDNAME = ?, COURSE = ?, YEAR = ?, USERNAME = ?, SESSION = ? WHERE IDNO = ?");
                $stmt->bind_param("ssssssss", $lastname, $firstname, $midname, $course, $year, $username, $sessions, $idno);
            }
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Student updated successfully';
            } else {
                $response['message'] = 'Error updating student: ' . $conn->error;
                throw new Exception('Database error');
            }
        } else {
            $response['message'] = 'Invalid operation mode';
        }
    } else {
        $response['message'] = 'Invalid request method';
    }
} catch (Exception $e) {
    // Exception message is already set in $response
    error_log('Error in manage_student.php: ' . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
