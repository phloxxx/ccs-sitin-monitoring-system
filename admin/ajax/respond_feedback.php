<?php
session_start();
require_once('../../config/db.php');

// Initialize response array
$response = array(
    'success' => false,
    'message' => ''
);

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    $response['message'] = 'Not authorized';
    echo json_encode($response);
    exit();
}

// Check if required data is present
if (!isset($_POST['feedback_id']) || !isset($_POST['status']) || !isset($_POST['response'])) {
    $response['message'] = 'Missing required data';
    echo json_encode($response);
    exit();
}

// Sanitize and validate inputs
$feedback_id = intval($_POST['feedback_id']);
$admin_response = trim($_POST['response']);
$status = trim($_POST['status']);

// Validate status
$valid_statuses = array('PENDING', 'REVIEWED', 'RESOLVED');
if (!in_array($status, $valid_statuses)) {
    $response['message'] = 'Invalid status value';
    echo json_encode($response);
    exit();
}

// Update the feedback entry
$stmt = $conn->prepare("UPDATE FEEDBACK SET ADMIN_RESPONSE = ?, STATUS = ?, RESPONSE_DATE = NOW() WHERE FEEDBACK_ID = ?");
$stmt->bind_param("ssi", $admin_response, $status, $feedback_id);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Response submitted successfully';
} else {
    $response['message'] = 'Error submitting response: ' . $conn->error;
}
$stmt->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
