<?php
session_start();
require_once('../../config/db.php');

// Set the content type to JSON
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate input
if (empty($_POST['title']) || empty($_POST['content'])) {
    echo json_encode(['success' => false, 'message' => 'Title and content are required']);
    exit();
}

// Sanitize input
$title = trim($_POST['title']);
$content = trim($_POST['content']);
$admin_id = $_SESSION['admin_id'];

// Insert into database
try {
    $stmt = $conn->prepare("INSERT INTO ANNOUNCEMENT (ADMIN_ID, TITLE, CONTENT, CREATED_AT) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $admin_id, $title, $content);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Announcement created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create announcement']);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
