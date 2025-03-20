<?php
// This script will set up the database and tables

// Include the database connection file
require_once('db.php');

// Create uploads directory for profile pictures
$uploads_dir = __DIR__ . '/user/uploads/';
if (!is_dir($uploads_dir)) {
    if (mkdir($uploads_dir, 0777, true)) {
        echo "<p>Created uploads directory for profile pictures.</p>";
    } else {
        echo "<p style='color:red'>Warning: Failed to create uploads directory. Please create it manually at: $uploads_dir</p>";
    }
} else {
    echo "<p>Uploads directory already exists.</p>";
}

// Ensure proper permissions
if (is_dir($uploads_dir)) {
    chmod($uploads_dir, 0777);
    echo "<p>Set permissions for uploads directory.</p>";
}

echo "<h1>Database Initialization</h1>";
echo "<p>If you see this message without errors, your database setup is working correctly.</p>";
echo "<p>You can now go to the <a href='user/login.php'>login page</a> and start using the system.</p>";
?>
