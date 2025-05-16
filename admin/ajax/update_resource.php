<?php
session_start();
require_once('../../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Initialize response array
$response = ['success' => false, 'message' => 'No action taken'];

try {
    // Check if resource ID is provided
    if (!isset($_POST['resource_id']) || empty($_POST['resource_id'])) {
        throw new Exception('Resource ID is required');
    }
    
    $resourceId = $_POST['resource_id'];
    $resourceName = $_POST['resource_name'] ?? '';
    $resourceType = $_POST['resource_type'] ?? 'other';
    $resourceDescription = $_POST['resource_description'] ?? '';
    $resourceLink = $_POST['resource_link'] ?? '';
    $resourceLab = $_POST['resource_lab'] ?? 'all';
    
    // Validate required fields
    if (empty($resourceName)) {
        throw new Exception('Resource name is required');
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Update resource in database
    $stmt = $conn->prepare("UPDATE LAB_RESOURCES 
                           SET RESOURCE_NAME = ?, RESOURCE_TYPE = ?, DESCRIPTION = ?, RESOURCE_LINK = ?, LAB_ID = ?, 
                               UPDATED_AT = CURRENT_TIMESTAMP
                           WHERE RESOURCE_ID = ?");
    
    $stmt->bind_param("sssssi", $resourceName, $resourceType, $resourceDescription, $resourceLink, $resourceLab, $resourceId);
    $stmt->execute();
    
    // Check if update was successful
    if ($stmt->affected_rows < 0) {
        throw new Exception('Failed to update resource: ' . $stmt->error);
    }
    
    $stmt->close();
    
    // Handle file upload if present
    if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['resource_file'];
        
        // Create uploads directory if it doesn't exist
        $uploadDir = '../../uploads/resources/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'resource_' . $resourceId . '_' . uniqid() . '.' . $fileExt;
        $targetFilePath = $uploadDir . $fileName;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            // Update file path in database
            $relativeFilePath = '../uploads/resources/' . $fileName;
            
            $stmt = $conn->prepare("UPDATE LAB_RESOURCES SET FILE_PATH = ? WHERE RESOURCE_ID = ?");
            $stmt->bind_param("si", $relativeFilePath, $resourceId);
            $stmt->execute();
            
            if ($stmt->affected_rows < 0) {
                throw new Exception('Failed to update file path in database');
            }
            
            $stmt->close();
        } else {
            throw new Exception('Failed to upload file');
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $response = [
        'success' => true,
        'message' => 'Resource updated successfully!'
    ];
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->connect_error === null) {
        $conn->rollback();
    }
    
    $response = [
        'success' => false, 
        'message' => $e->getMessage()
    ];
} finally {
    // Close connection
    if ($conn) {
        $conn->close();
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
