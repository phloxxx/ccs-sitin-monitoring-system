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
    // Get form data
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
    
    // First, check if LAB_RESOURCES table exists
    $tableCheckResult = $conn->query("SHOW TABLES LIKE 'LAB_RESOURCES'");
    
    // If table doesn't exist, create it
    if ($tableCheckResult->num_rows === 0) {
        $createTableSQL = "CREATE TABLE LAB_RESOURCES (
            RESOURCE_ID INT AUTO_INCREMENT PRIMARY KEY,
            RESOURCE_NAME VARCHAR(255) NOT NULL,
            RESOURCE_TYPE VARCHAR(50) DEFAULT 'other',
            DESCRIPTION TEXT,
            RESOURCE_LINK TEXT,
            FILE_PATH TEXT,
            LAB_ID VARCHAR(50) DEFAULT 'all',
            CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if (!$conn->query($createTableSQL)) {
            throw new Exception('Failed to create LAB_RESOURCES table: ' . $conn->error);
        }
    }
    
    // Insert resource into database (without file path initially)
    $stmt = $conn->prepare("INSERT INTO LAB_RESOURCES (RESOURCE_NAME, RESOURCE_TYPE, DESCRIPTION, RESOURCE_LINK, LAB_ID) 
                           VALUES (?, ?, ?, ?, ?)");
    
    $stmt->bind_param("sssss", $resourceName, $resourceType, $resourceDescription, $resourceLink, $resourceLab);
    $stmt->execute();
    
    if ($stmt->affected_rows <= 0) {
        throw new Exception('Failed to add resource: ' . $stmt->error);
    }
    
    $resourceId = $stmt->insert_id;
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
        
        error_log("Uploading file to: " . $targetFilePath);
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            // Use a relative path that will work better across environments
            $relativeFilePath = $targetFilePath;
            error_log("File uploaded successfully. Saving path: " . $relativeFilePath);
            
            $stmt = $conn->prepare("UPDATE LAB_RESOURCES SET FILE_PATH = ? WHERE RESOURCE_ID = ?");
            $stmt->bind_param("si", $relativeFilePath, $resourceId);
            $stmt->execute();
            
            if ($stmt->affected_rows < 0) {
                throw new Exception('Failed to update file path in database');
            }
            
            $stmt->close();
        } else {
            throw new Exception('Failed to upload file: ' . error_get_last()['message']);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $response = [
        'success' => true,
        'message' => 'Resource added successfully!',
        'resourceId' => $resourceId
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
    error_log("Error adding resource: " . $e->getMessage());
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
