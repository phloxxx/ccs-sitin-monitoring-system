<?php
session_start();
require_once('../../config/db.php');

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Get parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$lab_id = $_GET['lab_id'] ?? '';

// Add time to dates for proper comparison
$start_date_with_time = $start_date . ' 00:00:00';
$end_date_with_time = $end_date . ' 23:59:59';

try {
    // Get session data
    $query = "SELECT s.*, u.FIRSTNAME, u.LASTNAME, u.MIDNAME, u.COURSE, u.YEAR, l.LAB_NAME 
              FROM SITIN s
              JOIN USERS u ON s.IDNO = u.IDNO
              JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
              WHERE s.SESSION_START BETWEEN ? AND ?";
    
    $params = [$start_date_with_time, $end_date_with_time];
    $types = "ss";
    
    // Add lab filter if provided
    if (!empty($lab_id)) {
        $query .= " AND s.LAB_ID = ?";
        $params[] = $lab_id;
        $types .= "i";
    }
    
    $query .= " ORDER BY s.SESSION_START DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sitin_report_' . $start_date . '_to_' . $end_date . '.csv');
    
    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Output the column headings
    fputcsv($output, [
        'Sit-In ID', 
        'Student ID', 
        'Student Name', 
        'Course & Year',
        'Laboratory',
        'Purpose',
        'Start Date & Time',
        'End Date & Time',
        'Duration (Hours)',
        'Status'
    ]);
    
    // Output each row of the data
    while ($row = $result->fetch_assoc()) {
        $fullname = $row['LASTNAME'] . ', ' . $row['FIRSTNAME'] . ' ' . $row['MIDNAME'];
        $courseYear = $row['COURSE'] . ' - ' . $row['YEAR'];
        
        fputcsv($output, [
            $row['SITIN_ID'],
            $row['IDNO'],
            $fullname,
            $courseYear,
            $row['LAB_NAME'],
            $row['PURPOSE'],
            $row['SESSION_START'],
            $row['SESSION_END'] ?? 'N/A',
            $row['SESSION_DURATION'],
            $row['STATUS']
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    // Output error as CSV for clarity
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sitin_report_error.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Error']);
    fputcsv($output, [$e->getMessage()]);
    fclose($output);
    exit;
}
?>
