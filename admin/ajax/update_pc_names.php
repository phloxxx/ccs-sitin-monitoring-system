<?php
// This script updates all existing PC records to set PC_NAME = PC_NUMBER where PC_NAME is NULL
// Use absolute path for command-line execution
$currentDir = dirname(__FILE__);
$configPath = realpath($currentDir . '/../../config/db.php');
require_once($configPath);

// Check if we have any PCs with NULL PC_NAME
$check_sql = "SELECT COUNT(*) as count FROM PC WHERE PC_NAME IS NULL";
$result = $conn->query($check_sql);
$row = $result->fetch_assoc();

if ($row['count'] > 0) {
    // We have PCs with NULL PC_NAME, update them
    $update_sql = "UPDATE PC SET PC_NAME = PC_NUMBER WHERE PC_NAME IS NULL";
    
    if ($conn->query($update_sql) === TRUE) {
        echo "Success: Updated " . $conn->affected_rows . " PC records with missing names.<br>";
        echo "<a href='../manage_pcs.php'>Return to PC Management</a>";
    } else {
        echo "Error updating PC records: " . $conn->error;
    }
} else {
    echo "All PC records already have names set.<br>";
    echo "<a href='../manage_pcs.php'>Return to PC Management</a>";
}

$conn->close();
?>