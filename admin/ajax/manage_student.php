<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$mode = isset($_POST['mode']) ? trim($_POST['mode']) : '';
$idno = isset($_POST['idno']) ? trim($_POST['idno']) : '';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
$lastname = isset($_POST['lastname']) ? trim($_POST['lastname']) : '';
$midname = isset($_POST['midname']) ? trim($_POST['midname']) : '';
$course = isset($_POST['course']) ? trim($_POST['course']) : '';
$year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
$sessions = isset($_POST['sessions']) ? (int)$_POST['sessions'] : 10;

// Validate required fields
if (empty($idno) || empty($username) || empty($firstname) || empty($lastname) || empty($course) || $year <= 0) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled out']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    if ($mode === 'create') {
        // Check if password is provided for new student
        if (empty($password)) {
            throw new Exception('Password is required for new students');
        }
        
        // Check if ID number already exists
        $stmt = $conn->prepare("SELECT IDNO FROM USERS WHERE IDNO = ?");
        $stmt->bind_param("s", $idno);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Student ID already exists');
        }
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT USERNAME FROM USERS WHERE USERNAME = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Username already exists');
        }
        
        // Insert new student
        $stmt = $conn->prepare("INSERT INTO USERS (IDNO, USERNAME, PASSWORD, FIRSTNAME, LASTNAME, MIDNAME, COURSE, YEAR, SESSION) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssii", $idno, $username, $password, $firstname, $lastname, $midname, $course, $year, $sessions);
        $stmt->execute();
        
        $message = 'Student added successfully';
    } else {
        // Update existing student
        $sql = "UPDATE USERS SET 
                USERNAME = ?, 
                FIRSTNAME = ?, 
                LASTNAME = ?, 
                MIDNAME = ?, 
                COURSE = ?, 
                YEAR = ?, 
                SESSION = ?";
        
        // Add password to update if provided
        $params = [$username, $firstname, $lastname, $midname, $course, $year, $sessions];
        $types = "sssssii";
        
        if (!empty($password)) {
            $sql .= ", PASSWORD = ?";
            $params[] = $password;
            $types .= "s";
        }
        
        $sql .= " WHERE IDNO = ?";
        $params[] = $idno;
        $types .= "s";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('No changes were made');
        }
        
        $message = 'Student updated successfully';
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
