<?php
session_start();
require_once('../../config/db.php');

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Create simple HTML fallback in case TCPDF isn't available
function outputHtmlReport($sessions, $start_date, $end_date, $lab_name, $error_message = null) {
    header('Content-Type: text/html');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Sit-In Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            h1, h2 { color: #333; }
            .error { color: red; }
            .back-link { margin-top: 20px; }
        </style>
    </head>
    <body>
        <h1>Sit-In Sessions Report</h1>
        <p>Date Range: '.$start_date.' to '.$end_date.'</p>
        <p>Laboratory: '.$lab_name.'</p>';
        
    if ($error_message) {
        echo '<div class="error">
            <h2>Error Generating PDF</h2>
            <p>'.$error_message.'</p>
            <p>Showing HTML report instead.</p>
        </div>';
    }
    
    if (empty($sessions)) {
        echo '<p>No records found for the selected criteria.</p>';
    } else {
        echo '<table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Course & Year</th>
                    <th>Laboratory</th>
                    <th>Purpose</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Duration</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
            
        foreach ($sessions as $session) {
            $fullname = $session['LASTNAME'] . ', ' . $session['FIRSTNAME'] . ' ' . $session['MIDNAME'];
            $course_year = $session['COURSE'] . ' - ' . $session['YEAR'];
            $end_time = $session['SESSION_END'] ?? 'N/A';
            
            echo '<tr>
                <td>'.$session['SITIN_ID'].'</td>
                <td>'.$session['IDNO'].'</td>
                <td>'.$fullname.'</td>
                <td>'.$course_year.'</td>
                <td>'.$session['LAB_NAME'].'</td>
                <td>'.$session['PURPOSE'].'</td>
                <td>'.$session['SESSION_START'].'</td>
                <td>'.$end_time.'</td>
                <td>'.$session['SESSION_DURATION'].' hrs</td>
                <td>'.$session['STATUS'].'</td>
            </tr>';
        }
        
        echo '</tbody></table>';
    }
    
    echo '<p class="back-link"><a href="../sitin_reports.php">Go back to reports page</a></p>
    </body>
    </html>';
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
    
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    
    // Get lab name if specific lab is selected
    $lab_name = "All Laboratories";
    if (!empty($lab_id)) {
        $stmt = $conn->prepare("SELECT LAB_NAME FROM LABORATORY WHERE LAB_ID = ?");
        $stmt->bind_param("i", $lab_id);
        $stmt->execute();
        $lab_result = $stmt->get_result();
        if ($lab_row = $lab_result->fetch_assoc()) {
            $lab_name = $lab_row['LAB_NAME'];
        }
    }
    
    // Try to include TCPDF
    $tcpdf_loaded = false;
    
    // List of possible TCPDF locations
    $tcpdf_paths = [
        '../../vendor/tcpdf/tcpdf.php',
        '../../tcpdf/tcpdf.php',
        '../../../tcpdf/tcpdf.php',
        '../../lib/tcpdf/tcpdf.php',
        '../lib/tcpdf/tcpdf.php'
    ];
    
    // Try each path
    foreach ($tcpdf_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            if (class_exists('TCPDF') || class_exists('\TCPDF')) {
                $tcpdf_loaded = true;
                break;
            }
        }
    }
    
    // If TCPDF couldn't be loaded, use HTML fallback
    if (!$tcpdf_loaded) {
        outputHtmlReport($sessions, $start_date, $end_date, $lab_name, 
            "TCPDF library not found. Please install TCPDF library to generate PDF reports.");
    }
    
    // Create PDF document
    $pdf_class = class_exists('TCPDF') ? 'TCPDF' : '\TCPDF';
    $pdf = new $pdf_class('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('CCS Monitoring System');
    $pdf->SetAuthor('Admin Panel');
    $pdf->SetTitle('Sit-In Report');
    $pdf->SetSubject('Sit-In Sessions Report');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'CCS Sit-In Monitoring System', "Report: $start_date to $end_date ($lab_name)");
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array('helvetica', '', 10));
    $pdf->setFooterFont(Array('helvetica', '', 8));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont('courier');
    
    // Set margins
    $pdf->SetMargins(10, 20, 10);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Report title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Sit-In Sessions Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, "Date Range: $start_date to $end_date", 0, 1, 'C');
    $pdf->Cell(0, 5, "Laboratory: $lab_name", 0, 1, 'C');
    $pdf->Ln(5);
    
    // Check if there are any records
    if (count($sessions) === 0) {
        $pdf->SetFont('helvetica', 'I', 12);
        $pdf->Cell(0, 10, 'No records found for the selected criteria', 0, 1, 'C');
    } else {
        // Summary statistics
        $totalSessions = count($sessions);
        $activeSessions = 0;
        $completedSessions = 0;
        
        foreach ($sessions as $session) {
            if ($session['STATUS'] === 'ACTIVE') {
                $activeSessions++;
            } else {
                $completedSessions++;
            }
        }
        
        // Add summary statistics
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Summary Statistics:', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 6, 'Total Sessions: ' . $totalSessions, 0, 0);
        $pdf->Cell(60, 6, 'Active Sessions: ' . $activeSessions, 0, 0);
        $pdf->Cell(60, 6, 'Completed Sessions: ' . $completedSessions, 0, 1);
        $pdf->Ln(5);
        
        // Create the table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(20, 7, 'ID', 1, 0, 'C', 1);
        $pdf->Cell(25, 7, 'Student ID', 1, 0, 'C', 1);
        $pdf->Cell(50, 7, 'Student Name', 1, 0, 'C', 1);
        $pdf->Cell(25, 7, 'Course & Year', 1, 0, 'C', 1);
        $pdf->Cell(25, 7, 'Laboratory', 1, 0, 'C', 1);
        $pdf->Cell(25, 7, 'Purpose', 1, 0, 'C', 1);
        $pdf->Cell(38, 7, 'Start Time', 1, 0, 'C', 1);
        $pdf->Cell(38, 7, 'End Time', 1, 0, 'C', 1);
        $pdf->Cell(20, 7, 'Duration', 1, 0, 'C', 1);
        $pdf->Cell(20, 7, 'Status', 1, 1, 'C', 1);
        
        // Create the table data rows
        $pdf->SetFont('helvetica', '', 8);
        foreach ($sessions as $session) {
            $fullname = $session['LASTNAME'] . ', ' . $session['FIRSTNAME'] . ' ' . $session['MIDNAME'];
            $course_year = $session['COURSE'] . ' - ' . $session['YEAR'];
            $end_time = $session['SESSION_END'] ?? 'N/A';
            
            $pdf->Cell(20, 6, $session['SITIN_ID'], 1, 0, 'C');
            $pdf->Cell(25, 6, $session['IDNO'], 1, 0, 'C');
            $pdf->Cell(50, 6, $fullname, 1, 0, 'L');
            $pdf->Cell(25, 6, $course_year, 1, 0, 'C');
            $pdf->Cell(25, 6, $session['LAB_NAME'], 1, 0, 'C');
            $pdf->Cell(25, 6, $session['PURPOSE'], 1, 0, 'C');
            $pdf->Cell(38, 6, $session['SESSION_START'], 1, 0, 'C');
            $pdf->Cell(38, 6, $end_time, 1, 0, 'C');
            $pdf->Cell(20, 6, $session['SESSION_DURATION'] . ' hrs', 1, 0, 'C');
            $pdf->Cell(20, 6, $session['STATUS'], 1, 1, 'C');
        }
    }
    
    // Output the PDF as download
    $pdf->Output('sitin_report_' . $start_date . '_to_' . $end_date . '.pdf', 'D');
    
} catch (Exception $e) {
    // If any error occurs, show HTML report with error message
    outputHtmlReport($sessions ?? [], $start_date, $end_date, $lab_name ?? 'All Laboratories', $e->getMessage());
}
?>
