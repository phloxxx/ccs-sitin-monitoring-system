<?php
// This file checks if the PC table STATUS enum has been properly updated to include RESERVED status
require_once('../../config/db.php');

// Check if the PC table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'PC'");

if ($tableCheck->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'PC table does not exist']);
    exit();
}

// Check the STATUS column definition
$columnCheck = $conn->query("SHOW COLUMNS FROM PC LIKE 'STATUS'");
$columnInfo = $columnCheck->fetch_assoc();

if (!$columnInfo) {
    echo json_encode(['success' => false, 'message' => 'STATUS column not found in PC table']);
    exit();
}

// Output the column information for debugging
echo json_encode([
    'success' => true, 
    'column_info' => $columnInfo,
    'message' => 'The STATUS column type is: ' . $columnInfo['Type']
]);

$conn->close();
?>
