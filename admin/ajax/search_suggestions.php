<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get search term from request
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$response = ['success' => false, 'suggestions' => []];

if (!empty($searchTerm)) { // Changed from checking length to just checking if not empty
    try {
        // Search for students with names containing the search term
        $searchPattern = '%' . $searchTerm . '%';
        
        $stmt = $conn->prepare(
            "SELECT IDNO as USER_ID, FIRSTNAME, LASTNAME, MIDNAME, 
                   CONCAT(FIRSTNAME, ' ', LASTNAME) as FULLNAME, 
                   COURSE, YEAR, IFNULL(sp.POINTS, 0) as POINTS
             FROM USERS u
             LEFT JOIN STUDENT_POINTS sp ON u.IDNO = sp.USER_ID
             WHERE CONCAT(FIRSTNAME, ' ', LASTNAME) LIKE ? 
                OR IDNO LIKE ? 
                OR USERNAME LIKE ?
                OR FIRSTNAME LIKE ? 
                OR LASTNAME LIKE ?
                OR CONCAT(LASTNAME, ', ', FIRSTNAME) LIKE ?
             ORDER BY 
                CASE 
                    WHEN IDNO = ? THEN 1
                    WHEN USERNAME = ? THEN 2
                    WHEN CONCAT(LASTNAME, ', ', FIRSTNAME) = ? THEN 3
                    WHEN FIRSTNAME = ? OR LASTNAME = ? THEN 4
                    ELSE 5 
                END,
                LASTNAME, FIRSTNAME
             LIMIT 20"
        );
        
        $exactPattern = $searchTerm;
        $stmt->bind_param("sssssssssss", 
            $searchPattern, // Full name 
            $searchPattern, // IDNO
            $searchPattern, // USERNAME
            $searchPattern, // FIRSTNAME
            $searchPattern, // LASTNAME
            $searchPattern, // Last, First format
            $exactPattern, // Exact IDNO match
            $exactPattern, // Exact USERNAME match 
            $exactPattern, // Exact full name match
            $exactPattern, // Exact FIRSTNAME match
            $exactPattern  // Exact LASTNAME match
        );
        $stmt->execute();
        $result = $stmt->get_result();
        
        $suggestions = [];
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = [
                'id' => $row['USER_ID'],
                'name' => $row['FULLNAME'],
                'course' => $row['COURSE'],
                'year' => $row['YEAR'],
                'points' => $row['POINTS'],
                'display' => $row['FULLNAME'] . ' (' . $row['USER_ID'] . ')' . ($row['POINTS'] > 0 ? ' - ' . $row['POINTS'] . ' pts' : '')
            ];
        }
        
        $response = ['success' => true, 'suggestions' => $suggestions];
        $stmt->close();
        
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
}

// Return the response as JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
