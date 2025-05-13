<?php
session_start();
require_once('../../config/db.php');

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Set response header to JSON
header('Content-Type: application/json');

try {
    // Check if file was uploaded
    if (!isset($_FILES['schedule_image']) || $_FILES['schedule_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }

    $file = $_FILES['schedule_image'];
    $fileName = $file['name'];
    $fileTmpPath = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    // Validate file size (max 2MB)
    $maxSize = 2 * 1024 * 1024; // 2MB in bytes
    if ($fileSize > $maxSize) {
        throw new Exception('File size exceeds the 2MB limit');
    }
    
    // Get file extension and validate file type
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png'];
    
    if (!in_array($fileExt, $allowedExtensions)) {
        throw new Exception('Invalid file type. Only JPG, JPEG, and PNG files are allowed');
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = '../../uploads/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create uploads directory');
        }
    }
    
    // Prepare file name
    $newFileName = 'lab_schedule.' . $fileExt;
    $uploadPath = $uploadDir . $newFileName;
    
    // Remove old schedule files if they exist
    foreach ($allowedExtensions as $ext) {
        $oldFile = $uploadDir . 'lab_schedule.' . $ext;
        if (file_exists($oldFile) && $oldFile != $uploadPath) {
            unlink($oldFile);
        }
    }
    
    // Move uploaded file to target location
    if (!move_uploaded_file($fileTmpPath, $uploadPath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Update schedule title in database if provided
    if (!empty($_POST['schedule_title'])) {
        $scheduleTitle = $_POST['schedule_title'];
        
        // Check if settings table exists and has schedule_title field
        // If not, you can create it or use another table for storing settings
        // For this example, we'll assume a SETTINGS table exists
        try {
            $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'SETTINGS'");
            $stmt->execute();
            $tableExists = $stmt->fetch();
            
            if ($tableExists) {
                // Update or insert the schedule title setting
                $stmt = $conn->prepare("INSERT INTO SETTINGS (setting_key, setting_value, updated_at) 
                                        VALUES ('schedule_title', ?, NOW()) 
                                        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
                $stmt->bind_param("ss", $scheduleTitle, $scheduleTitle);
                $stmt->execute();
            }
        } catch (Exception $e) {
            // If there's an error with the database, we'll just continue
            // The file upload was successful, so we don't want to fail the operation
        }
    }
    
    // Log the upload action
    $adminId = $_SESSION['admin_id'];
    $username = $_SESSION['username'];
    $action = "Lab schedule image uploaded by {$username} (ID: {$adminId})";
    
    try {
        $stmt = $conn->prepare("INSERT INTO ADMIN_LOGS (admin_id, action, timestamp) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $adminId, $action);
        $stmt->execute();
    } catch (Exception $e) {
        // If logging fails, we'll just continue
        // The file upload was successful, so we don't want to fail the operation
    }
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Schedule uploaded successfully',
        'file' => [
            'name' => $newFileName,
            'path' => substr($uploadPath, 3), // Remove the "../../" from the path
            'updated' => date('F d, Y')
        ]
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>