<?php
session_start();
require_once('../../config/db.php');

// Check if user is logged in
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
    // Get all PCs for this lab with their status
    $stmt = $conn->prepare("SELECT PC_ID, PC_NUMBER, STATUS FROM PC WHERE LAB_ID = ? ORDER BY PC_NUMBER");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pcs = [];
    while($pc = $result->fetch_assoc()) {
        $pcs[] = $pc;
    }
    $stmt->close();
    
    // Count PCs by status
    $counts = [
        'available' => 0,
        'in_use' => 0,
        'reserved' => 0,
        'maintenance' => 0
    ];
    
    foreach($pcs as $pc) {
        $status = strtolower($pc['STATUS'] ?? 'unknown');
        if (isset($counts[$status])) {
            $counts[$status]++;
        } else {
            // Default to "maintenance" category if status doesn't match
            $counts['maintenance']++;
        }
    }
    
    // Get lab name
    $lab_name = "Unknown Lab";
    $stmt = $conn->prepare("SELECT LAB_NAME FROM LABORATORY WHERE LAB_ID = ?");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $stmt->bind_result($db_lab_name);
    if ($stmt->fetch() && $db_lab_name) {
        $lab_name = $db_lab_name;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'pcs' => $pcs,
        'counts' => $counts,
        'lab_name' => $lab_name
    ]);
    
} catch(Exception $e) {
    error_log("PC Status Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching PC data: ' . $e->getMessage()
    ]);
}
?>
