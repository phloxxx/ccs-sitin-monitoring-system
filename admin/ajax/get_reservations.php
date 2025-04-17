<?php
session_start();
require_once('../../config/db.php');

header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get date range parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Validate date parameters
if (!$start_date || !$end_date) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing date range parameters'
    ]);
    exit;
}

try {
    // Query to get all reservations within the specified date range
    $query = "SELECT 
                r.RESERVATION_ID,
                r.DATE,
                r.START_TIME,
                r.END_TIME,
                r.PURPOSE,
                r.STATUS,
                r.PC_ID,
                CONCAT(u.FIRSTNAME, ' ', SUBSTRING(u.MIDNAME, 1, 1), '. ', u.LASTNAME) AS STUDENT_NAME,
                u.IDNO,
                l.LAB_NAME,
                CONCAT('PC-', LPAD(p.PC_NUMBER, 2, '0')) AS PC_NAME
              FROM 
                RESERVATION r
              JOIN 
                USERS u ON r.IDNO = u.IDNO
              JOIN 
                LABORATORY l ON r.LAB_ID = l.LAB_ID
              LEFT JOIN
                PC p ON r.PC_ID = p.PC_ID
              WHERE 
                r.DATE BETWEEN ? AND ?
              ORDER BY 
                r.DATE, r.START_TIME";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reservations = [];
    
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'reservations' => $reservations
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching reservations: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch reservations'
    ]);
}
?>
