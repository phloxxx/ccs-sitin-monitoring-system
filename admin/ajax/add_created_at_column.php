<?php
require_once('../../config/db.php');

header('Content-Type: application/json');

try {
    // Check if column exists
    $columnCheck = $conn->query("SHOW COLUMNS FROM USERS LIKE 'CREATED_AT'");
    
    if (!$columnCheck || $columnCheck->num_rows === 0) {
        // Add CREATED_AT column with current timestamp as default
        $result = $conn->query("ALTER TABLE USERS ADD COLUMN CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'CREATED_AT column added successfully.']);
        } else {
            throw new Exception("Failed to add CREATED_AT column: " . $conn->error);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'CREATED_AT column already exists.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
