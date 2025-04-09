<?php
session_start();
require_once('../../config/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get report parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$labId = isset($_GET['lab_id']) ? $_GET['lab_id'] : '';
$purpose = isset($_GET['purpose']) ? $_GET['purpose'] : '';

try {
    // Validate dates
    if (!$startDate || !$endDate) {
        throw new Exception('Start and end dates are required.');
    }
    
    // Build query with potential filters
    $query = "SELECT s.*, u.FIRSTNAME, u.LASTNAME, u.MIDNAME, l.LAB_NAME 
              FROM SITIN s
              JOIN USERS u ON s.IDNO = u.IDNO
              JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
              WHERE DATE(s.SESSION_START) BETWEEN ? AND ?";
    
    $params = array($startDate, $endDate);
    $types = "ss";
    
    if ($labId) {
        $query .= " AND s.LAB_ID = ?";
        $params[] = $labId;
        $types .= "i";
    }
    
    if ($purpose) {
        $query .= " AND s.PURPOSE = ?"; // Changed from LIKE to exact match
        $params[] = $purpose;
        $types .= "s";
    }
    
    $query .= " ORDER BY s.SESSION_START DESC";
    
    $stmt = $conn->prepare($query);
    
    // Properly bind parameters using call_user_func_array for dynamic binding
    if (!empty($params)) {
        $bindParams = array();
        $bindParams[] = &$types;
        
        for ($i = 0; $i < count($params); $i++) {
            $bindParams[] = &$params[$i];
        }
        
        call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessions = array();
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
