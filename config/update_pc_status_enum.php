<?php
// Connect to database
require_once('db.php');

// Alter the PC table to update the STATUS enum values
$sql = "ALTER TABLE PC MODIFY COLUMN STATUS enum('AVAILABLE','RESERVED','IN_USE','MAINTENANCE') NOT NULL DEFAULT 'AVAILABLE'";

if ($conn->query($sql) === TRUE) {
    echo "PC table STATUS column updated successfully!";
} else {
    echo "Error updating PC table: " . $conn->error;
}

$conn->close();
?>
