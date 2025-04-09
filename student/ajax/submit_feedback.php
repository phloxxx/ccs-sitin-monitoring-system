<?php
session_start();
require_once('../../config/db.php');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_log("Feedback submission started");

// Initialize response array
$response = array(
    'success' => false,
    'message' => ''
);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in';
    error_log("Feedback error: User not logged in");
    echo json_encode($response);
    exit();
}

// Check if required data is present
if (!isset($_POST['sitin_id']) || !isset($_POST['rating']) || !isset($_POST['comments'])) {
    $response['message'] = 'Missing required data';
    error_log("Feedback error: Missing required data. POST data: " . print_r($_POST, true));
    echo json_encode($response);
    exit();
}

// Sanitize and validate inputs
$user_id = $_SESSION['user_id'];
$sitin_id = intval($_POST['sitin_id']);
$rating = intval($_POST['rating']);
$comments = trim($_POST['comments']);

// Validate rating (1-5)
if ($rating < 1 || $rating > 5) {
    $response['message'] = 'Invalid rating. Please select 1-5 stars.';
    echo json_encode($response);
    exit();
}

// First, check the actual column name in the SITIN table
try {
    $columns_query = "SHOW COLUMNS FROM SITIN";
    $columns_result = $conn->query($columns_query);
    $column_exists = false;
    $column_name = "SITIN_ID"; // Default column name
    
    if ($columns_result) {
        while ($col = $columns_result->fetch_assoc()) {
            error_log("Found column: " . $col['Field']);
            if (strtoupper($col['Field']) === 'SITIN_ID') {
                $column_exists = true;
                $column_name = $col['Field'];
                break;
            }
            // Check for alternative column names that might be the primary key
            if (strtoupper($col['Field']) === 'SIT_IN_ID' || 
                strtoupper($col['Field']) === 'ID' || 
                strtoupper($col['Field']) === 'SIT_ID') {
                $column_exists = true;
                $column_name = $col['Field'];
                error_log("Using alternative column name: " . $column_name);
                break;
            }
        }
    }
    
    if (!$column_exists) {
        error_log("SITIN_ID column not found in SITIN table. Available columns: " . 
                  implode(", ", array_column($columns_result->fetch_all(MYSQLI_ASSOC), 'Field')));
        throw new Exception("SITIN_ID column not found in database");
    }

    // Verify the sitin_id belongs to this user with the correct column name
    $verify_stmt = $conn->prepare("SELECT s.$column_name 
                                 FROM SITIN s 
                                 JOIN USERS u ON s.IDNO = u.IDNO 
                                 WHERE s.$column_name = ? AND u.USER_ID = ?");
    $verify_stmt->bind_param("ii", $sitin_id, $user_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        $response['message'] = 'Invalid sit-in session or not authorized';
        error_log("Feedback error: Invalid sit-in session ($sitin_id) for user ID $user_id");
        echo json_encode($response);
        exit();
    }
    $verify_stmt->close();
} catch (Exception $e) {
    $response['message'] = 'Error verifying session: ' . $e->getMessage();
    error_log("Feedback error in verification: " . $e->getMessage());
    echo json_encode($response);
    exit();
}

try {
    // Check if the FEEDBACK table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'FEEDBACK'");
    if ($table_check->num_rows === 0) {
        // Create FEEDBACK table if it doesn't exist
        $create_table = "CREATE TABLE FEEDBACK (
            FEEDBACK_ID INT AUTO_INCREMENT PRIMARY KEY,
            SITIN_ID INT NOT NULL,
            RATING INT NOT NULL,
            COMMENTS TEXT,
            SUBMISSION_DATE DATETIME DEFAULT CURRENT_TIMESTAMP,
            STATUS VARCHAR(20) DEFAULT 'PENDING',
            ADMIN_RESPONSE TEXT NULL,
            RESPONSE_DATE DATETIME NULL
        )";
        $conn->query($create_table);
        error_log("Created FEEDBACK table");
    }

    // Check if SITIN_ID and COMMENTS columns exist in FEEDBACK table
    $feedback_columns_query = "SHOW COLUMNS FROM FEEDBACK";
    $feedback_columns_result = $conn->query($feedback_columns_query);
    $columns_in_feedback = [];
    
    if ($feedback_columns_result) {
        while ($col = $feedback_columns_result->fetch_assoc()) {
            $columns_in_feedback[] = strtoupper($col['Field']);
        }
    }
    
    // Log all columns for debugging
    error_log("Columns in FEEDBACK table: " . implode(", ", $columns_in_feedback));
    
    // Check for and add missing columns
    if (!in_array('SITIN_ID', $columns_in_feedback)) {
        $conn->query("ALTER TABLE FEEDBACK ADD COLUMN SITIN_ID INT NOT NULL AFTER FEEDBACK_ID");
        error_log("Added SITIN_ID column to FEEDBACK table");
    }
    
    if (!in_array('COMMENTS', $columns_in_feedback)) {
        $conn->query("ALTER TABLE FEEDBACK ADD COLUMN COMMENTS TEXT AFTER RATING");
        error_log("Added COMMENTS column to FEEDBACK table");
    }
    
    // Ensure other necessary columns exist
    if (!in_array('RATING', $columns_in_feedback)) {
        $conn->query("ALTER TABLE FEEDBACK ADD COLUMN RATING INT NOT NULL AFTER SITIN_ID");
        error_log("Added RATING column to FEEDBACK table");
    }
    
    if (!in_array('SUBMISSION_DATE', $columns_in_feedback)) {
        $conn->query("ALTER TABLE FEEDBACK ADD COLUMN SUBMISSION_DATE DATETIME DEFAULT CURRENT_TIMESTAMP");
        error_log("Added SUBMISSION_DATE column to FEEDBACK table");
    }
    
    if (!in_array('STATUS', $columns_in_feedback)) {
        $conn->query("ALTER TABLE FEEDBACK ADD COLUMN STATUS VARCHAR(20) DEFAULT 'PENDING'");
        error_log("Added STATUS column to FEEDBACK table");
    }

    // Check if this user has already submitted feedback for this sit-in session
    $check_stmt = $conn->prepare("SELECT FEEDBACK_ID FROM FEEDBACK WHERE SITIN_ID = ?");
    $check_stmt->bind_param("i", $sitin_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // Feedback already exists, update it instead
        $feedback_row = $result->fetch_assoc();
        $feedback_id = $feedback_row['FEEDBACK_ID'];
        
        $update_stmt = $conn->prepare("UPDATE FEEDBACK SET RATING = ?, COMMENTS = ?, SUBMISSION_DATE = NOW() WHERE FEEDBACK_ID = ?");
        $update_stmt->bind_param("isi", $rating, $comments, $feedback_id);
        
        if ($update_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Feedback updated successfully';
        } else {
            $response['message'] = 'Error updating feedback: ' . $conn->error;
            error_log("Error updating feedback: " . $conn->error);
        }
        $update_stmt->close();
    } else {
        // Insert new feedback
        $insert_stmt = $conn->prepare("INSERT INTO FEEDBACK (SITIN_ID, RATING, COMMENTS, SUBMISSION_DATE, STATUS) 
                                     VALUES (?, ?, ?, NOW(), 'PENDING')");
        $insert_stmt->bind_param("iis", $sitin_id, $rating, $comments);
        
        if ($insert_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Feedback submitted successfully';
        } else {
            $response['message'] = 'Error submitting feedback: ' . $conn->error;
            error_log("Error submitting feedback: " . $conn->error);
        }
        $insert_stmt->close();
    }
    $check_stmt->close();

} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Feedback database error: " . $e->getMessage() . ", Trace: " . $e->getTraceAsString());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
