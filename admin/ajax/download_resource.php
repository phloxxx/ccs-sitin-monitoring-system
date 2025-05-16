<?php
session_start();
require_once('../../config/db.php');

// Check if user is logged in (either admin or student)
$isLoggedIn = isset($_SESSION['admin_id']) || isset($_SESSION['student_id']);
if (!$isLoggedIn) {
    header("HTTP/1.1 403 Forbidden");
    echo "Access denied. You must be logged in to download resources.";
    exit();
}

// Check if resource ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("HTTP/1.1 400 Bad Request");
    echo "Resource ID is required.";
    exit();
}

$resourceId = intval($_GET['id']);

try {
    // Get resource information from database
    $stmt = $conn->prepare("SELECT * FROM LAB_RESOURCES WHERE RESOURCE_ID = ?");
    $stmt->bind_param("i", $resourceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("HTTP/1.1 404 Not Found");
        echo "Resource not found.";
        exit();
    }
    
    $resource = $result->fetch_assoc();
    $stmt->close();
    
    // Check if file path exists
    if (empty($resource['FILE_PATH'])) {
        header("HTTP/1.1 404 Not Found");
        echo "No file attached to this resource.";
        exit();
    }
    
    // Debug information
    error_log("Resource ID: " . $resourceId);
    error_log("File path from DB: " . $resource['FILE_PATH']);
    
    // Try different path variations to find the actual file
    $possiblePaths = [
        str_replace('../', '', $resource['FILE_PATH']), // Remove '../' prefix
        '../../' . str_replace('../', '', $resource['FILE_PATH']), // Go up two directories
        $resource['FILE_PATH'], // Use as is
        '../' . $resource['FILE_PATH'], // Go up one directory
        '../../uploads/resources/' . basename($resource['FILE_PATH']), // Direct to uploads folder
    ];
    
    $filePath = null;
    foreach ($possiblePaths as $path) {
        error_log("Trying path: " . $path);
        if (file_exists($path)) {
            $filePath = $path;
            error_log("Found file at: " . $filePath);
            break;
        }
    }
    
    if (!$filePath) {
        header("HTTP/1.1 404 Not Found");
        echo "File not found on server. Please contact administrator. Attempted paths: " . implode(', ', $possiblePaths);
        exit();
    }
    
    // Get file information
    $fileName = basename($filePath);
    $fileSize = filesize($filePath);
    
    // Determine MIME type
    if (function_exists('mime_content_type')) {
        $fileType = mime_content_type($filePath);
    } else {
        // Fallback to extension-based MIME type
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'pdf': $fileType = 'application/pdf'; break;
            case 'doc': $fileType = 'application/msword'; break;
            case 'docx': $fileType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'; break;
            case 'xls': $fileType = 'application/vnd.ms-excel'; break;
            case 'xlsx': $fileType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'; break;
            case 'jpg': case 'jpeg': $fileType = 'image/jpeg'; break;
            case 'png': $fileType = 'image/png'; break;
            case 'gif': $fileType = 'image/gif'; break;
            case 'zip': $fileType = 'application/zip'; break;
            case 'rar': $fileType = 'application/x-rar-compressed'; break;
            default: $fileType = 'application/octet-stream';
        }
    }
    
    // Set headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $fileType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $fileSize);
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Output file content
    readfile($filePath);
    exit();
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    echo "Error: " . $e->getMessage();
    exit();
}
?>
