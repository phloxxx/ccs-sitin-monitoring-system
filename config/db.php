<?php

$host = 'localhost';
$user = 'root';
$password = ''; // Make sure this matches your XAMPP MySQL password (often empty by default)
$dbname = 'ccssms_db';
$port = 3309;

// Create connection without selecting database first
$conn = new mysqli($host, $user, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if database exists, if not create it
$db_check = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");

if ($db_check->num_rows == 0) {
    // Database doesn't exist, create it
    $sql = "CREATE DATABASE $dbname";
    if ($conn->query($sql) === TRUE) {
        echo "<script>console.log('Database created successfully');</script>";
    } else {
        die("Error creating database: " . $conn->error);
    }
}

// Select the database
$conn->select_db($dbname);

// Check if users table exists
$table_check = $conn->query("SHOW TABLES LIKE 'users'");

if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE USERS (
        USER_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
        IDNO VARCHAR(20) UNIQUE NOT NULL,
        LASTNAME VARCHAR(50) NOT NULL,
        FIRSTNAME VARCHAR(50) NOT NULL,
        MIDNAME VARCHAR(50),
        COURSE VARCHAR(100) NOT NULL,
        YEAR VARCHAR(20) NOT NULL,
        USERNAME VARCHAR(50) UNIQUE NOT NULL,
        PASSWORD VARCHAR(255) NOT NULL,
        SESSION INT(11) DEFAULT 30,
        PROFILE_PIC VARCHAR(255) DEFAULT 'images/snoopy.jpg',
        CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<script>console.log('Users table created successfully');</script>";
        
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/user/uploads/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                echo "<script>console.log('Failed to create uploads directory');</script>";
            } else {
                echo "<script>console.log('Uploads directory created successfully');</script>";
            }
        }
    } else {
        die("Error creating users table: " . $conn->error);
    }
}

// Check if CREATED_AT column exists in USERS table, if not add it
$column_check = $conn->query("SHOW COLUMNS FROM USERS LIKE 'CREATED_AT'");
if ($column_check->num_rows == 0) {
    // CREATED_AT column doesn't exist, add it
    $sql = "ALTER TABLE USERS ADD COLUMN CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    try {
        $conn->query($sql);
    } catch (Exception $e) {
        // Silently handle error to prevent breaking the application
        error_log("Failed to add CREATED_AT column to USERS table: " . $e->getMessage());
    }
}

// Check if admin table exists
$table_check = $conn->query("SHOW TABLES LIKE 'admin'");

if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE ADMIN (
        ADMIN_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
        USERNAME VARCHAR(50) UNIQUE NOT NULL,
        PASSWORD VARCHAR(255) NOT NULL
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<script>console.log('Admin table created successfully');</script>";
    } else {
        die("Error creating admin table: " . $conn->error);
    }
}

// Check if announcement table exists
$table_check = $conn->query("SHOW TABLES LIKE 'announcement'");

if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE ANNOUNCEMENT (
        ANNOUNCE_ID INT(11) AUTO_INCREMENT PRIMARY KEY,
        TITLE VARCHAR(255) NOT NULL,
        CONTENT TEXT NOT NULL,
        CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
        ADMIN_ID INT(11) NOT NULL,
        FOREIGN KEY (ADMIN_ID) REFERENCES ADMIN(ADMIN_ID)
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "<script>console.log('Announcement table created successfully');</script>";
    } else {
        die("Error creating announcement table: " . $conn->error);
    }
}

// Default admin user
$admin_check = $conn->query("SELECT * FROM ADMIN LIMIT 1");
if ($admin_check->num_rows == 0) {
    // Insert default admin user
    $admin_username = 'admin';
    $admin_password = 'admin123'; // In a real app, use password_hash()
    
    $sql = "INSERT INTO ADMIN (USERNAME, PASSWORD) VALUES ('$admin_username', '$admin_password')";
    if ($conn->query($sql) === TRUE) {
        echo "<script>console.log('Default admin user created successfully');</script>";
    }
}

?>