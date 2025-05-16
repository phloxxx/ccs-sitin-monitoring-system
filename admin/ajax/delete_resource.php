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
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Resource ID is required');
    }
    
    $resourceId = intval($_GET['id']);
    
    // Begin transaction
    $conn->begin_transaction();
    
    // First, get the file path (if any)
    $stmt = $conn->prepare("SELECT FILE_PATH FROM LAB_RESOURCES WHERE RESOURCE_ID = ?");
    $stmt->bind_param("i", $resourceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Resource not found');
    }
    
    $filePath = $result->fetch_assoc()['FILE_PATH'];
    $stmt->close();
    
    // Delete resource from database
    $stmt = $conn->prepare("DELETE FROM LAB_RESOURCES WHERE RESOURCE_ID = ?");
    $stmt->bind_param("i", $resourceId);
    $stmt->execute();
    
    if ($stmt->affected_rows <= 0) {
        throw new Exception('Failed to delete resource from database');
    }
    
    $stmt->close();
    
    // Delete file if exists
    if (!empty($filePath)) {
        $serverFilePath = str_replace('../', '', $filePath);
        if (file_exists($serverFilePath)) {
            unlink($serverFilePath);
        } else {
            // Try alternative path
            $altPath = '../../' . str_replace('../', '', $filePath);
            if (file_exists($altPath)) {
                unlink($altPath);
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $response = [
        'success' => true,
        'message' => 'Resource deleted successfully!'
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
