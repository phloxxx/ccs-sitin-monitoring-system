<?php
session_start();
require_once('../../config/db.php');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Get lab ID from request
$lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : 0;

if ($lab_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid laboratory ID'
    ]);
    exit;
}

try {
    // Get PCs that are available for the selected lab and date/time
    $stmt = $conn->prepare("
        SELECT PC_ID, PC_NUMBER, STATUS 
        FROM PC 
        WHERE LAB_ID = ? AND STATUS = 'AVAILABLE'
        ORDER BY PC_NUMBER
    ");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pcs = [];
    while($pc = $result->fetch_assoc()) {
        $pcs[] = $pc;
    }
    $stmt->close();
    
    // Get lab name
    $lab_name = "";
    $stmt = $conn->prepare("SELECT LAB_NAME FROM LABORATORY WHERE LAB_ID = ?");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $stmt->bind_result($lab_name);
    $stmt->fetch();
    $stmt->close();
    
    // Return PC data and lab information
    echo json_encode([
        'success' => true,
        'pcs' => $pcs,
        'lab_name' => $lab_name,
        'count' => count($pcs)
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching PC data: ' . $e->getMessage()
    ]);
}
?>
