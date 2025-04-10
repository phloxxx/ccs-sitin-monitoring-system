<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$lab_id = isset($_GET['lab_id']) ? $_GET['lab_id'] : '';
$purpose = isset($_GET['purpose']) ? $_GET['purpose'] : '';

// Validate dates
if (empty($start_date) || empty($end_date)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
    exit();
}

try {
    // Build query with parameters
    $query = "SELECT s.SITIN_ID, s.IDNO, s.LAB_ID, s.SESSION_START, s.SESSION_END, s.PURPOSE, s.STATUS, 
                     u.LASTNAME, u.FIRSTNAME, u.MIDNAME, l.LAB_NAME 
              FROM SITIN s
              JOIN USERS u ON s.IDNO = u.IDNO
              JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
              WHERE DATE(s.SESSION_START) BETWEEN ? AND ?";

    $params = [$start_date, $end_date];
    $types = "ss";

    if (!empty($lab_id)) {
        $query .= " AND s.LAB_ID = ?";
        $params[] = $lab_id;
        $types .= "i";
    }

    if (!empty($purpose)) {
        $query .= " AND s.PURPOSE = ?";
        $params[] = $purpose;
        $types .= "s";
    }

    $query .= " ORDER BY s.SESSION_START DESC";

    // Execute query
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch data
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'sessions' => $sessions]);
} catch (Exception $e) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close statement and connection
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
exit();
?>
