<?php
require_once('../../config/db.php');

header('Content-Type: application/json');

try {
    // Check if column exists
    $columnCheck = $conn->query("SHOW COLUMNS FROM STUDENT_POINTS LIKE 'TOTAL_POINTS'");
    
    if (!$columnCheck || $columnCheck->num_rows === 0) {
        // Add TOTAL_POINTS column with default value of POINTS
        $result = $conn->query("ALTER TABLE STUDENT_POINTS ADD COLUMN TOTAL_POINTS INT DEFAULT 0");
        
        // Copy current POINTS values to TOTAL_POINTS for existing records
        $updateResult = $conn->query("UPDATE STUDENT_POINTS SET TOTAL_POINTS = POINTS");
        
        if ($result && $updateResult) {
            echo json_encode(['success' => true, 'message' => 'TOTAL_POINTS column added successfully and initialized with current point values.']);
        } else {
            throw new Exception("Failed to add TOTAL_POINTS column: " . $conn->error);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'TOTAL_POINTS column already exists.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>