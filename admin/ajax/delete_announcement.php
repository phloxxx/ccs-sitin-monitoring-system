<?php
session_start();
require_once('../../config/db.php');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

if (empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Announcement ID is required']);
    exit();
}

$admin_id = $_SESSION['admin_id'];
$id = $_POST['id'];

try {
    // First verify this announcement belongs to the admin
    $verify = $conn->prepare("SELECT ADMIN_ID FROM ANNOUNCEMENT WHERE ANNOUNCE_ID = ?");
    $verify->bind_param("i", $id);
    $verify->execute();
    $result = $verify->get_result();
    $announcement = $result->fetch_assoc();
    
    if (!$announcement || $announcement['ADMIN_ID'] != $admin_id) {
        echo json_encode(['success' => false, 'message' => 'Not authorized to delete this announcement']);
        exit();
    }
    
    $stmt = $conn->prepare("DELETE FROM ANNOUNCEMENT WHERE ANNOUNCE_ID = ? AND ADMIN_ID = ?");
    $stmt->bind_param("ii", $id, $admin_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete announcement']);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
