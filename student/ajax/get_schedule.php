<?php
session_start();
require_once('../../config/db.php');

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Get lab ID from request
$lab_id = isset($_GET['lab_id']) ? $_GET['lab_id'] : 'all';

// Check if schedule image exists for the selected lab
$schedulePath = "../../uploads/lab_schedules/{$lab_id}.jpg";
$schedulePathPng = "../../uploads/lab_schedules/{$lab_id}.png";
$scheduleExists = file_exists($schedulePath) || file_exists($schedulePathPng);
$actualPath = file_exists($schedulePath) ? "../uploads/lab_schedules/{$lab_id}.jpg" : (file_exists($schedulePathPng) ? "../uploads/lab_schedules/{$lab_id}.png" : "");

// Get lab name if specific lab
$lab_name = "All Laboratories";
if ($lab_id !== 'all') {
    try {
        $stmt = $conn->prepare("SELECT LAB_NAME FROM LABORATORY WHERE LAB_ID = ?");
        $stmt->bind_param("i", $lab_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $lab_name = $row['LAB_NAME'];
        }
        $stmt->close();
    } catch (Exception $e) {
        // Error getting lab name
    }
}

if ($scheduleExists) {
    $lastUpdated = date("F d, Y", filemtime(file_exists($schedulePath) ? $schedulePath : $schedulePathPng));
    
    // Return HTML for the schedule display
    $html = '
        <div class="mb-3 flex items-center justify-between">
            <h4 class="font-medium text-gray-800">Laboratory Schedule: ' . htmlspecialchars($lab_name) . '</h4>
            <div class="text-sm text-gray-500">Last updated: ' . $lastUpdated . '</div>
        </div>
        <div class="border rounded-lg overflow-hidden shadow-sm">
            <img src="' . $actualPath . '?v=' . time() . '" alt="Laboratory Schedule" class="max-w-full h-auto">
        </div>
        <div class="mt-4 flex justify-end">
            <a href="' . $actualPath . '" download="' . $lab_name . '_schedule.' . (file_exists($schedulePath) ? 'jpg' : 'png') . '" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors inline-flex items-center shadow-sm">
                <i class="fas fa-download mr-2"></i> Download Schedule
            </a>
        </div>
    ';
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No schedule is available for ' . htmlspecialchars($lab_name) . '.'
    ]);
}
?>
