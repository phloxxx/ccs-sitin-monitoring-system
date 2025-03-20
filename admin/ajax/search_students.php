<?php
session_start();
require_once('../../config/db.php');

// Ensure the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Get the search query
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Please enter at least 2 characters']);
    exit;
}

// Prepare the search query
$sql = "SELECT u.IDNO, u.LASTNAME, u.FIRSTNAME, u.MIDNAME, u.COURSE, u.YEAR, u.SESSION, 
        CASE WHEN s.SITIN_ID IS NOT NULL THEN 'ACTIVE' ELSE NULL END AS ACTIVE_SESSION,
        CASE WHEN s.SITIN_ID IS NOT NULL THEN l.LAB_NAME ELSE NULL END AS ACTIVE_LAB
        FROM USERS u
        LEFT JOIN (
            SELECT IDNO, SITIN_ID, LAB_ID
            FROM SITIN
            WHERE STATUS = 'ACTIVE'
        ) s ON u.IDNO = s.IDNO
        LEFT JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
        WHERE u.IDNO LIKE ? OR u.LASTNAME LIKE ? OR u.FIRSTNAME LIKE ? OR u.USERNAME LIKE ?
        ORDER BY u.LASTNAME, u.FIRSTNAME";

try {
    $stmt = $conn->prepare($sql);
    $searchParam = "%$query%";
    $stmt->bind_param("ssss", $searchParam, $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    echo json_encode(['success' => true, 'students' => $students]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
