<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Debug information
$debug = [];

// Get the requested lab ID
$lab_id = isset($_GET['lab_id']) ? $_GET['lab_id'] : 'all';
$debug['lab_id'] = $lab_id;

// Directory for lab schedules
$uploadsDirectory = "../../uploads/lab_schedules/";
$debug['uploadsDirectory'] = $uploadsDirectory;

// Check if the directory exists, if not create it
if (!file_exists($uploadsDirectory)) {
    mkdir($uploadsDirectory, 0777, true);
    $debug['directoryCreated'] = 'yes';
}

// Check for schedule image (both JPG and PNG)
$schedulePath = $uploadsDirectory . $lab_id . ".jpg";
$schedulePathPng = $uploadsDirectory . $lab_id . ".png";
$scheduleExists = file_exists($schedulePath) || file_exists($schedulePathPng);
$debug['jpgExists'] = file_exists($schedulePath) ? 'yes' : 'no';
$debug['pngExists'] = file_exists($schedulePathPng) ? 'yes' : 'no';

$actualPath = file_exists($schedulePath) ? $schedulePath : (file_exists($schedulePathPng) ? $schedulePathPng : "");
$debug['actualPath'] = $actualPath;

// Get lab name for display
$labName = "All Laboratories";
if ($lab_id !== 'all') {
    try {
        $stmt = $conn->prepare("SELECT LAB_NAME FROM LABORATORY WHERE LAB_ID = ?");
        $stmt->bind_param("s", $lab_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $labName = $row['LAB_NAME'];
            $debug['labName'] = $labName;
        }
        $stmt->close();
    } catch (Exception $e) {
        // If error, use default value
        $labName = "Laboratory #" . $lab_id;
        $debug['labNameError'] = $e->getMessage();
    }
}

if ($scheduleExists) {
    $lastUpdated = date("F d, Y", filemtime($actualPath));
    $relativePath = str_replace("../../", "../", $actualPath);
    $debug['relativePath'] = $relativePath;
    $debug['lastUpdated'] = $lastUpdated;
    
    // Use cache-busting parameter
    $timestamp = time();
    
    $html = '
    <div class="mb-3 flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
        <h4 class="font-medium text-gray-800">Schedule for ' . htmlspecialchars($labName) . '</h4>
        <div class="text-sm text-gray-500">Last updated: ' . $lastUpdated . '</div>
    </div>
    <div class="border rounded-lg overflow-hidden shadow-sm">
        <img src="' . $relativePath . '?v=' . $timestamp . '" alt="Laboratory Schedule" class="max-w-full h-auto">
    </div>
    <div class="mt-4 flex justify-end space-x-3">
        <a href="' . $relativePath . '" download="lab_schedule_' . $lab_id . '.' . pathinfo($actualPath, PATHINFO_EXTENSION) . '" 
           class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors inline-flex items-center shadow-sm">
            <i class="fas fa-download mr-2"></i> Download Schedule
        </a>
    </div>';
    
    echo json_encode([
        'success' => true, 
        'html' => $html,
        'debug' => $debug
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'No schedule has been uploaded for ' . htmlspecialchars($labName) . ' yet.',
        'debug' => $debug
    ]);
}
?>
