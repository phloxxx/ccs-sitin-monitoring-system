<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if SCHEDULE_UPLOADS table exists, create if not
try {
    // Check if table exists
    $tableExistsQuery = "SHOW TABLES LIKE 'SCHEDULE_UPLOADS'";
    $result = $conn->query($tableExistsQuery);
    
    if ($result->num_rows == 0) {
        // Table doesn't exist, create it
        $createTableQuery = "CREATE TABLE SCHEDULE_UPLOADS (
            UPLOAD_ID INT AUTO_INCREMENT PRIMARY KEY,
            LAB_ID VARCHAR(10) NOT NULL,
            TITLE VARCHAR(255) NOT NULL,
            FILENAME VARCHAR(255) NOT NULL,
            UPLOADED_BY INT NOT NULL,
            UPLOAD_DATE DATETIME NOT NULL
        )";
        
        if (!$conn->query($createTableQuery)) {
            throw new Exception("Failed to create SCHEDULE_UPLOADS table: " . $conn->error);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database setup error: ' . $e->getMessage()]);
    exit();
}

// Debug information
$debug = [];

// Check if file was uploaded
if (!isset($_FILES['schedule_image']) || $_FILES['schedule_image']['error'] != UPLOAD_ERR_OK) {
    $error = isset($_FILES['schedule_image']) ? $_FILES['schedule_image']['error'] : 'No file uploaded';
    echo json_encode([
        'success' => false, 
        'message' => 'No file uploaded or error in upload. Error code: ' . $error
    ]);
    exit();
}

// Get lab ID from form
$lab_id = isset($_POST['schedule_lab']) ? $_POST['schedule_lab'] : 'all';
$title = isset($_POST['schedule_title']) ? $_POST['schedule_title'] : 'Lab Schedule';

$debug['lab_id'] = $lab_id;
$debug['title'] = $title;

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
$fileType = $_FILES['schedule_image']['type'];
$debug['fileType'] = $fileType;

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG and PNG files are allowed']);
    exit();
}

// Validate file size (2MB max)
$maxSize = 2 * 1024 * 1024; // 2MB in bytes
if ($_FILES['schedule_image']['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds the 2MB limit']);
    exit();
}

// Directory for lab schedules
$uploadsDirectory = "../../uploads/lab_schedules/";
$debug['uploadsDirectory'] = $uploadsDirectory;

// Check if the directory exists, if not create it
if (!file_exists($uploadsDirectory)) {
    $dirCreated = mkdir($uploadsDirectory, 0777, true);
    $debug['dirCreated'] = $dirCreated ? 'yes' : 'no';
    if (!$dirCreated) {
        echo json_encode(['success' => false, 'message' => 'Failed to create uploads directory']);
        exit();
    }
}

// Check if directory is writable
$isWritable = is_writable($uploadsDirectory);
$debug['isWritable'] = $isWritable ? 'yes' : 'no';
if (!$isWritable) {
    echo json_encode(['success' => false, 'message' => 'Uploads directory is not writable']);
    exit();
}

// Set the destination file name based on the lab ID and file type
$extension = ($fileType === 'image/png') ? 'png' : 'jpg';
$destinationFile = $uploadsDirectory . $lab_id . "." . $extension;
$debug['destinationFile'] = $destinationFile;

// Delete any existing schedule files for this lab (both jpg and png)
if (file_exists($uploadsDirectory . $lab_id . ".jpg")) {
    unlink($uploadsDirectory . $lab_id . ".jpg");
    $debug['deletedJpg'] = 'yes';
}
if (file_exists($uploadsDirectory . $lab_id . ".png")) {
    unlink($uploadsDirectory . $lab_id . ".png");
    $debug['deletedPng'] = 'yes';
}

// Move the uploaded file to the destination
$moveSuccess = move_uploaded_file($_FILES['schedule_image']['tmp_name'], $destinationFile);
$debug['moveSuccess'] = $moveSuccess ? 'yes' : 'no';

// Set proper permissions on the file
if ($moveSuccess) {
    chmod($destinationFile, 0644);
    $debug['chmod'] = 'applied';
    
    // Check if file exists after upload
    $fileExists = file_exists($destinationFile);
    $debug['fileExists'] = $fileExists ? 'yes' : 'no';
    $debug['fileSize'] = $fileExists ? filesize($destinationFile) : 0;
    
    // Log the upload in database
    try {
        $stmt = $conn->prepare("INSERT INTO SCHEDULE_UPLOADS (LAB_ID, TITLE, FILENAME, UPLOADED_BY, UPLOAD_DATE) 
                              VALUES (?, ?, ?, ?, NOW())");
        $filename = $lab_id . "." . $extension;
        $admin_id = $_SESSION['admin_id'];
        $stmt->bind_param("sssi", $lab_id, $title, $filename, $admin_id);
        $stmt->execute();
        $debug['dbInsert'] = 'success';
        $stmt->close();
    } catch (Exception $e) {
        $debug['dbError'] = $e->getMessage();
        // Continue even if logging fails
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Schedule uploaded successfully', 
        'debug' => $debug,
        'path' => '../uploads/lab_schedules/' . $filename,
        'refreshUrl' => '../lab_resources.php?lab=' . $lab_id . '&t=' . time()
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to move uploaded file', 
        'debug' => $debug
    ]);
}
?>