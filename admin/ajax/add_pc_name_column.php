<?php
// This script adds the PC_NAME column to the PC table if it doesn't exist
require_once('../../config/db.php');

// Check if the PC_NAME column exists in the PC table
$check_column = $conn->query("SHOW COLUMNS FROM PC LIKE 'PC_NAME'");

if ($check_column->num_rows == 0) {
    // Column doesn't exist, add it
    $alter_sql = "ALTER TABLE PC ADD COLUMN PC_NAME varchar(50) DEFAULT NULL AFTER PC_NUMBER";
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "Success: Added PC_NAME column to PC table.<br>";
        
        // Now update all existing records to set PC_NAME = PC_NUMBER
        $update_sql = "UPDATE PC SET PC_NAME = PC_NUMBER";
        
        if ($conn->query($update_sql) === TRUE) {
            echo "Success: Updated " . $conn->affected_rows . " PC records with PC_NAME values.<br>";
            echo "<a href='../manage_pcs.php'>Return to PC Management</a>";
        } else {
            echo "Error updating PC records: " . $conn->error;
        }
    } else {
        echo "Error adding PC_NAME column: " . $conn->error;
    }
} else {
    echo "The PC_NAME column already exists in the PC table.<br>";
    echo "<a href='../manage_pcs.php'>Return to PC Management</a>";
}

$conn->close();
?>