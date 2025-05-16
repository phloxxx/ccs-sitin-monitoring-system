<?php
session_start();
require_once('../../config/db.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo "Unauthorized access";
    exit;
}

// Get resource ID
$resource_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($resource_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo "Invalid resource ID";
    exit;
}

try {
    // Get resource file information
    $stmt = $conn->prepare("SELECT RESOURCE_NAME, FILE_PATH FROM LAB_RESOURCES WHERE RESOURCE_ID = ?");
    $stmt->bind_param("i", $resource_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $file_path = $row['FILE_PATH'];
        $resource_name = $row['RESOURCE_NAME'];
        
        if (empty($file_path)) {
            header('HTTP/1.1 404 Not Found');
            echo "No file attached to this resource";
            exit;
        }
        
        // Create full file path (adjust path as needed)
        $full_path = "../../" . $file_path;
        
        if (!file_exists($full_path)) {
            header('HTTP/1.1 404 Not Found');
            echo "File not found on server";
            exit;
        }
        
        // Get file extension and set appropriate content type
        $file_extension = pathinfo($full_path, PATHINFO_EXTENSION);
        $content_type = getContentType($file_extension);
        
        // Create safe filename
        $filename = sanitizeFilename($resource_name) . '.' . $file_extension;
        
        // Set headers for download
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($full_path));
        
        // Clean output buffer
        ob_clean();
        flush();
        
        // Read file and output
        readfile($full_path);
        exit;
    } else {
        header('HTTP/1.1 404 Not Found');
        echo "Resource not found";
    }
    
    $stmt->close();
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error: " . $e->getMessage();
}

// Helper function to get content type based on file extension
function getContentType($extension) {
    $content_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg'
    ];
    
    $ext = strtolower($extension);
    return isset($content_types[$ext]) ? $content_types[$ext] : 'application/octet-stream';
}

// Helper function to create safe filenames
function sanitizeFilename($name) {
    // Replace spaces with underscores and remove special characters
    $name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $name);
    return $name;
}
?>
