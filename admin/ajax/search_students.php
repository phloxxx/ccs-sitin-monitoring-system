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

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a search term']);
    exit;
}

// Prepare the search query with improved name search
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
        WHERE u.IDNO LIKE ? 
        OR u.USERNAME LIKE ?
        OR CONCAT(u.FIRSTNAME, ' ', COALESCE(u.MIDNAME, ''), ' ', u.LASTNAME) LIKE ?
        OR CONCAT(u.LASTNAME, ', ', u.FIRSTNAME, ' ', COALESCE(u.MIDNAME, '')) LIKE ?
        OR CONCAT(u.FIRSTNAME, ' ', u.LASTNAME) LIKE ?
        OR CONCAT(u.LASTNAME, ', ', u.FIRSTNAME) LIKE ?
        OR u.FIRSTNAME LIKE ?
        OR u.LASTNAME LIKE ?
        ORDER BY 
            CASE 
                WHEN u.IDNO = ? THEN 1
                WHEN u.USERNAME = ? THEN 2
                WHEN CONCAT(u.LASTNAME, ', ', u.FIRSTNAME) = ? THEN 3
                WHEN CONCAT(u.FIRSTNAME, ' ', u.LASTNAME) = ? THEN 4
                ELSE 5
            END,
            u.LASTNAME, u.FIRSTNAME
        LIMIT 30";

try {
    $stmt = $conn->prepare($sql);
    $searchPattern = "%{$query}%";
    $exactPattern = $query;
    $stmt->bind_param("ssssssssssss", 
        $searchPattern, // IDNO
        $searchPattern, // USERNAME
        $searchPattern, // Full name (FIRSTNAME MIDNAME LASTNAME)
        $searchPattern, // Full name (LASTNAME, FIRSTNAME MIDNAME)
        $searchPattern, // Full name without middle name (FIRSTNAME LASTNAME)
        $searchPattern, // Full name without middle name (LASTNAME, FIRSTNAME)
        $searchPattern, // FIRSTNAME
        $searchPattern, // LASTNAME
        $exactPattern, // Exact IDNO match
        $exactPattern, // Exact USERNAME match
        $exactPattern, // Exact full name match (LASTNAME, FIRSTNAME)
        $exactPattern  // Exact full name match (FIRSTNAME LASTNAME)
    );
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
