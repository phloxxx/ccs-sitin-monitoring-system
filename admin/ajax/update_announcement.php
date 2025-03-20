<?php
session_start();
require_once('../../db.php');

// Set the content type to JSON
header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate input
if (empty($_POST['id']) || empty($_POST['title']) || empty($_POST['content'])) {
    echo json_encode(['success' => false, 'message' => 'ID, title, and content are required']);
    exit();
}

// Sanitize input
$id = intval($_POST['id']);
$title = htmlspecialchars(trim($_POST['title']));
$content = htmlspecialchars(trim($_POST['content']));
$admin_id = $_SESSION['admin_id'];

// Check if the announcement exists and belongs to the current admin
try {
    $stmt = $conn->prepare("SELECT ADMIN_ID FROM ANNOUNCEMENT WHERE ANNOUNCE_ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        $stmt->close();
        exit();
    }
    
    $row = $result->fetch_assoc();
    if ($row['ADMIN_ID'] != $admin_id) {
        echo json_encode(['success' => false, 'message' => 'You can only edit your own announcements']);
        $stmt->close();
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking announcement: ' . $e->getMessage()]);
    exit();
}

// Update the announcement
try {
    $stmt = $conn->prepare("UPDATE ANNOUNCEMENT SET TITLE = ?, CONTENT = ? WHERE ANNOUNCE_ID = ? AND ADMIN_ID = ?");
    $stmt->bind_param("ssis", $title, $content, $id, $admin_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Announcement updated successfully']);
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update announcement: ' . $e->getMessage()]);
}
?>
