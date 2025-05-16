<?php
session_start();
require_once('../../config/db.php');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT IDNO FROM USERS WHERE USER_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

$idno = $user['IDNO'];

// Validate inputs
if (!isset($_POST['reservation_id']) || empty($_POST['reservation_id'])) {
    echo json_encode(['success' => false, 'message' => 'Reservation ID is required']);
    exit();
}

$reservation_id = intval($_POST['reservation_id']);

// Check if reservation exists and belongs to this user
$stmt = $conn->prepare("SELECT * FROM RESERVATION WHERE RESERVATION_ID = ? AND IDNO = ?");
$stmt->bind_param("is", $reservation_id, $idno);
$stmt->execute();
$result = $stmt->get_result();
$reservation = $result->fetch_assoc();
$stmt->close();

if (!$reservation) {
    echo json_encode(['success' => false, 'message' => 'Reservation not found or does not belong to you']);
    exit();
}

// Check if reservation can be cancelled (only PENDING or APPROVED can be cancelled)
if ($reservation['STATUS'] !== 'PENDING' && $reservation['STATUS'] !== 'APPROVED') {
    echo json_encode(['success' => false, 'message' => 'This reservation cannot be cancelled']);
    exit();
}

// Update reservation status to CANCELLED
try {
    $stmt = $conn->prepare("UPDATE RESERVATION SET STATUS = 'CANCELLED', UPDATED_AT = NOW() WHERE RESERVATION_ID = ?");
    $stmt->bind_param("i", $reservation_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel reservation: ' . $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
