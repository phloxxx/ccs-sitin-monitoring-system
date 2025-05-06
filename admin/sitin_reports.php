<?php 
session_start();
require_once('../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$username = $_SESSION['username'];

// Get all laboratories for the filter dropdown
$laboratories = [];
try {
    $stmt = $conn->prepare("SELECT * FROM LABORATORY ORDER BY LAB_NAME");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $laboratories[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // If error, use default values
    $laboratories = [];
}

// Get usage statistics
$stats = [];
try {
    // Total completed sessions
    $stmt = $conn->query("SELECT COUNT(*) as total FROM SITIN WHERE STATUS = 'COMPLETED'");
    $stats['total_sessions'] = $stmt->fetch_assoc()['total'];

    // Total active sessions
    $stmt = $conn->query("SELECT COUNT(*) as active FROM SITIN WHERE STATUS = 'ACTIVE'");
    $stats['active_sessions'] = $stmt->fetch_assoc()['active'];

    // Total unique students
    $stmt = $conn->query("SELECT COUNT(DISTINCT IDNO) as students FROM SITIN");
    $stats['unique_students'] = $stmt->fetch_assoc()['students'];

    // Today's sessions
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as today FROM SITIN WHERE DATE(SESSION_START) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['today_sessions'] = $result->fetch_assoc()['today'];

    // Average session duration in minutes (for completed sessions)
    $stmt = $conn->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, SESSION_START, SESSION_END)) as avg_duration 
                         FROM SITIN WHERE STATUS = 'COMPLETED' AND SESSION_END IS NOT NULL");
    $avgDuration = $stmt->fetch_assoc()['avg_duration'];
    $stats['avg_duration'] = $avgDuration ? round($avgDuration) : 0;

    // Peak usage time (hour with most session starts)
    $stmt = $conn->query("SELECT HOUR(SESSION_START) as hour, COUNT(*) as count 
                         FROM SITIN 
                         GROUP BY HOUR(SESSION_START) 
                         ORDER BY count DESC 
                         LIMIT 1");
    $peakHourData = $stmt->fetch_assoc();
    if ($peakHourData) {
        $hour = $peakHourData['hour'];
        $displayHour = $hour % 12 == 0 ? 12 : $hour % 12;
        $ampm = $hour < 12 ? 'AM' : 'PM';
        $stats['peak_hour'] = "$displayHour:00 $ampm";
    } else {
        $stats['peak_hour'] = "N/A";
    }
    
    // Most used laboratory
    $stmt = $conn->query("SELECT l.LAB_NAME, COUNT(*) as count 
                         FROM SITIN s
                         JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
                         GROUP BY s.LAB_ID, l.LAB_NAME 
                         ORDER BY count DESC 
                         LIMIT 1");
    $mostUsedLab = $stmt->fetch_assoc();
    $stats['most_used_lab'] = $mostUsedLab ? $mostUsedLab['LAB_NAME'] : "N/A";

    // Weekly data for time-series chart (last 7 days)
    $dayLabels = [];
    $dailyCounts = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayLabels[] = date('D', strtotime("-$i days"));
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM SITIN WHERE DATE(SESSION_START) = ?");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $dailyCounts[] = $result->fetch_assoc()['count'];
    }
    
    $stats['day_labels'] = $dayLabels;
    $stats['daily_counts'] = $dailyCounts;

    // Month to month comparison (current month vs previous month)
    $currentMonth = date('Y-m');
    $previousMonth = date('Y-m', strtotime('first day of last month'));
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM SITIN WHERE DATE_FORMAT(SESSION_START, '%Y-%m') = ?");
    $stmt->bind_param("s", $currentMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentMonthCount = $result->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM SITIN WHERE DATE_FORMAT(SESSION_START, '%Y-%m') = ?");
    $stmt->bind_param("s", $previousMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    $prevMonthCount = $result->fetch_assoc()['count'];
    
    $stats['current_month'] = $currentMonthCount;
    $stats['prev_month'] = $prevMonthCount;
    $stats['month_change'] = $prevMonthCount > 0 ? round(($currentMonthCount - $prevMonthCount) / $prevMonthCount * 100) : 0;
    
} catch (Exception $e) {
    // If error, use default values
    $stats = [
        'total_sessions' => 0,
        'active_sessions' => 0, 
        'unique_students' => 0,
        'today_sessions' => 0,
        'avg_duration' => 0,
        'peak_hour' => 'N/A',
        'most_used_lab' => 'N/A',
        'day_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        'daily_counts' => [0, 0, 0, 0, 0, 0, 0],
        'current_month' => 0,
        'prev_month' => 0,
        'month_change' => 0
    ];
}

// Get recent activity for the dashboard
$recent_sitin = [];
try {
    $query = "SELECT s.*, u.FIRSTNAME, u.LASTNAME, u.MIDNAME, l.LAB_NAME 
              FROM SITIN s
              JOIN USERS u ON s.IDNO = u.IDNO
              JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
              ORDER BY s.SESSION_START DESC
              LIMIT 10";
    
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $recent_sitin[] = $row;
    }
} catch (Exception $e) {
    // Handle error
    $error_message = $e->getMessage();
}

// Get recent sit-in sessions for the data table
$recent_sessions = [];
try {
    $query = "SELECT s.*, u.FIRSTNAME, u.LASTNAME, u.MIDNAME, l.LAB_NAME 
              FROM SITIN s
              JOIN USERS u ON s.IDNO = u.IDNO
              JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
              ORDER BY s.SESSION_START DESC
              LIMIT 50";
    
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $recent_sessions[] = $row;
    }
} catch (Exception $e) {
    // Handle error
    $error_message = $e->getMessage();
}

$pageTitle = "Sit-In Reports";
$bodyClass = "bg-light font-poppins";
include('includes/header.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'CCS SITIN MONITORING SYSTEM'; ?></title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom Styles -->
    <?php if(isset($customStyles)): ?>
        <?php foreach($customStyles as $style): ?>
            <link rel="stylesheet" href="<?php echo $style; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <!-- Additional PDF libraries for fallback solution -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#94B0DF', // Soft blue
                        secondary: '#356480', // Medium blue-gray
                        dark: '#2c3e50', // Dark blue-gray (tertiary)
                        light: '#FCFDFF', // Very light blue-white
                        success: '#22c55e', // Softer green
                        danger: '#ef4444', // Softer red
                    },
                    fontFamily: {
                        poppins: ['"Poppins"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #94B0DF;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #356480;
        }
        
        /* DataTables buttons styling - match reference code */
        .buttons-csv, .buttons-excel {
            background-color: #10B981 !important;
            color: white !important;
            border: none !important;
            border-radius: 0.25rem !important;
            padding: 0.375rem 0.75rem !important;
            font-size: 0.9rem !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        }
        
        .buttons-csv:hover, .buttons-excel:hover {
            background-color: #059669 !important;
        }
        
        .buttons-pdf {
            background-color: #EF4444 !important;
            color: white !important;
            border: none !important;
            border-radius: 0.25rem !important;
            padding: 0.375rem 0.75rem !important;
            font-size: 0.9rem !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        }
        
        .buttons-pdf:hover {
            background-color: #DC2626 !important;
        }
        
        .buttons-print {
            background-color: #3B82F6 !important;
            color: white !important;
            border: none !important;
            border-radius: 0.25rem !important;
            padding: 0.375rem 0.75rem !important;
            font-size: 0.9rem !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        }
        
        .buttons-print:hover {
            background-color: #2563EB !important;
        }
        
        /* DataTables general styling */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #D1D5DB;
            border-radius: 0.25rem;
            padding: 0.375rem 0.75rem;
        }
        
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #D1D5DB;
            border-radius: 0.25rem;
            padding: 0.375rem 0.5rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 0.25rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #3B82F6 !important;
            border-color: #3B82F6 !important;
            color: white !important;
        }
        
        /* Report table styling */
        #reportTable {
            border-collapse: collapse;
            width: 100%;
        }
        
        #reportTable th {
            background-color: #F3F4F6;
            color: #374151;
            font-weight: 600;
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #D1D5DB;
        }
        
        #reportTable td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #E5E7EB;
            color: #4B5563;
        }
        
        #reportTable tr:nth-child(even) {
            background-color: #F9FAFB;
        }
    </style>
</head>
<body class="<?php echo $bodyClass ?? 'font-poppins bg-light'; ?>">

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <div class="hidden md:flex md:flex-shrink-0">
        <div class="flex flex-col w-64 bg-secondary">
            <div class="flex items-center justify-center h-16 px-4 bg-dark text-white">
                <span class="text-xl font-semibold">CCS Admin Panel</span>
            </div>
            <div class="flex flex-col flex-grow px-4 py-4 overflow-y-auto">
                <nav class="flex-1 space-y-2">
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-home mr-3"></i>
                        <span>Home</span>
                    </a>
                    <a href="search.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-search mr-3"></i>
                        <span>Search</span>
                    </a>
                    <a href="students.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-user-graduate mr-3"></i>
                        <span>Students</span>
                    </a>
                    <a href="sitin.php" class="flex items-center px-4 py-3 text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3"></i>
                        <span>Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        <span>Reservation</span>
                    </a>
                    <hr class="my-4 border-gray-400 border-opacity-20">
                    <a href="announcements.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-bullhorn mr-3"></i>
                        <span>Announcements</span>
                    </a>
                    <a href="feedbacks.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-comments mr-3"></i>
                        <span>Feedbacks</span>
                    </a>
                </nav>
                
                <div class="mt-auto">
                    <a href="#" onclick="confirmLogout(event)" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 overflow-hidden">
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm">
            <div class="flex items-center justify-between h-16 px-6">
                <!-- Mobile Menu Button -->
                <div class="flex items-center">
                    <button id="mobile-menu-button" class="text-gray-500 md:hidden focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="ml-4 text-xl font-semibold text-secondary">Sit-In Reports</h1>
                </div>
                
                <!-- Admin Profile -->
                <div class="flex items-center">
                    <span class="mr-4 text-sm font-medium text-gray-700"><?php echo htmlspecialchars($username); ?></span>
                    <button class="flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white">
                        <i class="fas fa-user"></i>
                    </button>
                </div>
            </div>
            
            <!-- Mobile Navigation -->
            <div id="mobile-menu" class="hidden md:hidden px-4 py-2 bg-secondary">
                <nav class="space-y-2">
                    <a href="dashboard.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-home mr-3"></i>
                        Home
                    </a>
                    <a href="search.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-search mr-3"></i>
                        Search
                    </a>
                    <a href="students.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-user-graduate mr-3"></i>
                        Students
                    </a>
                    <a href="sitin.php" class="block px-4 py-2 text-white rounded-lg bg-primary bg-opacity-30 hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-desktop mr-3"></i>
                        Sit-in
                    </a>
                    <a href="reservation.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        Reservation
                    </a>
                    <hr class="my-2 border-gray-400 border-opacity-20">
                    <a href="announcements.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-bullhorn mr-3"></i>
                        Announcements
                    </a>
                    <a href="feedbacks.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-comments mr-3"></i>
                        Feedbacks
                    </a>
                    <a href="#" onclick="confirmLogout(event)" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        Logout
                    </a>
                </nav>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                            <i class="fas fa-clipboard-list text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600 text-sm">Total Sessions</h2>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($stats['total_sessions']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-500">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600 text-sm">Unique Students</h2>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($stats['unique_students']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-amber-100 text-amber-500">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600 text-sm">Active Sessions</h2>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($stats['active_sessions']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                            <i class="fas fa-calendar-day text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600 text-sm">Today's Sessions</h2>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($stats['today_sessions']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Generator Panel -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-secondary to-primary p-4">
                    <h2 class="text-lg font-semibold text-white flex items-center">
                        <i class="fas fa-chart-line mr-2"></i> Generate Sit-In Report
                    </h2>
                </div>
                
                <div class="p-5">
                    <form id="report-form">
                        <div class="flex flex-wrap items-end gap-4 mb-4">
                            <div class="w-48">
                                <label for="start-date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="far fa-calendar-alt text-gray-400"></i>
                                    </div>
                                    <input type="date" id="start-date" name="start_date" 
                                           class="w-full pl-9 pr-2 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                                           value="<?php echo date('Y-m-01'); ?>">
                                </div>
                            </div>
                            
                            <div class="w-48">
                                <label for="end-date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="far fa-calendar-alt text-gray-400"></i>
                                    </div>
                                    <input type="date" id="end-date" name="end_date" 
                                           class="w-full pl-9 pr-2 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                                           value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="w-52">
                                <label for="report-lab" class="block text-sm font-medium text-gray-700 mb-1">Laboratory</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-desktop text-gray-400"></i>
                                    </div>
                                    <select id="report-lab" name="lab_id"
                                           class="w-full pl-9 pr-8 py-2 text-sm border border-gray-300 rounded-md appearance-none focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                                        <option value="">All Laboratories</option>
                                        <?php foreach ($laboratories as $lab): ?>
                                            <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="w-52">
                                <label for="report-purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-tasks text-gray-400"></i>
                                    </div>
                                    <select id="report-purpose" name="purpose"
                                           class="w-full pl-9 pr-8 py-2 text-sm border border-gray-300 rounded-md appearance-none focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                                        <option value="">All Purposes</option>
                                        <?php
                                        // Get unique purposes from database for dropdown
                                        try {
                                            $purposeQuery = "SELECT DISTINCT PURPOSE FROM SITIN WHERE PURPOSE != '' ORDER BY PURPOSE";
                                            $purposeResult = $conn->query($purposeQuery);
                                            while ($purpose = $purposeResult->fetch_assoc()) {
                                                echo '<option value="' . htmlspecialchars($purpose['PURPOSE']) . '">' . 
                                                     htmlspecialchars($purpose['PURPOSE']) . '</option>';
                                            }
                                        } catch (Exception $e) {
                                            // Silently fail - dropdown will just have the "All Purposes" option
                                        }
                                        ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="self-end mb-0.5">
                                <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors flex items-center shadow-sm">
                                    <i class="fas fa-search mr-2"></i> Generate
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Report Data Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-secondary">Detailed Report Data</h2>
                    <div class="flex space-x-3">
                        <a href="sitin.php" class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-opacity-90 transition-colors flex items-center">
                            <i class="fas fa-desktop mr-2"></i> Active Sessions
                        </a>
                    </div>
                </div>
                
                <div id="report-results" class="p-6">
                    <div class="flex items-center justify-center h-32 text-gray-500">
                        <p>Select date range and generate a report to view data</p>
                    </div>
                </div>
            </div>

            <!-- Recent Sit-In Sessions -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mt-6">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-secondary">Recent Sit-In Sessions</h2>
                    <button id="refresh-sessions" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors flex items-center">
                        <i class="fas fa-sync-alt mr-2"></i> Refresh Data
                    </button>
                </div>
                
                <div id="recent-sessions" class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time-In</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time-Out</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($recent_sessions)): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">No recent sit-in sessions found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_sessions as $session): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('Y-m-d', strtotime($session['SESSION_START'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($session['LASTNAME'] . ', ' . $session['FIRSTNAME'] . ' ' . $session['MIDNAME']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($session['IDNO']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($session['PURPOSE']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($session['LAB_NAME']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('h:i A', strtotime($session['SESSION_START'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php if ($session['SESSION_END']): ?>
                                                    <?php echo date('h:i A', strtotime($session['SESSION_END'])); ?>
                                                <?php else: ?>
                                                    <span class="italic">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php $statusClass = $session['STATUS'] === 'ACTIVE' ? 
                                                    "bg-green-100 text-green-800" : "bg-blue-100 text-blue-800"; ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                    <?php echo $session['STATUS']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-between">
                        <div class="text-sm text-gray-500">
                            Showing up to 50 recent sessions
                        </div>
                        <div>
                            <a href="sitin_records.php" class="inline-flex items-center px-4 py-2 text-sm text-blue-600 hover:text-blue-800">
                                View all records <i class="fas fa-angle-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Confirmation Dialog for Logout -->
<div id="confirmation-dialog" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-secondary mb-4">Confirm Logout</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to log out from the admin panel?</p>
        <div class="flex justify-end space-x-4">
            <button id="cancel-logout" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <a href="logout.php" class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-dark transition-colors">
                Logout
            </a>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>

<script>
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }
    
    // Confirmation dialog for logout
    function confirmLogout(event) {
        event.preventDefault();
        document.getElementById('confirmation-dialog').classList.remove('hidden');
    }
    
    document.getElementById('cancel-logout').addEventListener('click', () => {
        document.getElementById('confirmation-dialog').classList.add('hidden');
    });
    
    // Close dialog when clicking outside
    document.getElementById('confirmation-dialog').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });

    // Formatting functions
    function formatDateTime(dateTimeStr) {
        const date = new Date(dateTimeStr);
        return date.toLocaleString('en-US', {
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    function formatDate(dateTimeStr) {
        const date = new Date(dateTimeStr);
        return date.toISOString().split('T')[0];
    }
    
    function formatTime(dateTimeStr) {
        const date = new Date(dateTimeStr);
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }
    
    // Store report data globally
    let reportData = [];

    // Handle report form submission
    document.getElementById('report-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        const labId = document.getElementById('report-lab').value;
        const purpose = document.getElementById('report-purpose').value;
        
        // Show loading indicator
        document.getElementById('report-results').innerHTML = '<div class="flex justify-center"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>';
        
        // Fetch report data
        fetch(`ajax/get_sitin_report.php?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&lab_id=${encodeURIComponent(labId)}&purpose=${encodeURIComponent(purpose)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Store data globally for export
                    reportData = data.sessions;
                    
                    if (reportData.length === 0) {
                        document.getElementById('report-results').innerHTML = '<div class="bg-blue-50 p-4 rounded-md"><p class="text-blue-700">No sit-in sessions found for the selected date range.</p></div>';
                } else {
                        createReportTable(reportData, startDate, endDate);
                    }
                } else {
                    document.getElementById('report-results').innerHTML = '<div class="bg-red-50 p-4 rounded-md"><p class="text-red-700">' + (data.message || 'An error occurred while generating the report.') + '</p></div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('report-results').innerHTML = '<div class="bg-red-50 p-4 rounded-md"><p class="text-red-700">Network error. Please try again.</p></div>';
            });
    });

    // Function to create report table with DataTables
    function createReportTable(sessions, startDate, endDate) {
        // Create table HTML
        let tableHtml = '<table id="reportTable" class="min-w-full divide-y divide-gray-200">';
        tableHtml += '<thead class="bg-gray-50">';
        tableHtml += '<tr>';
        tableHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>';
        tableHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>';
        tableHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>';
        tableHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>';
        tableHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>';
        tableHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>';
        tableHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>';
        tableHtml += '</tr>';
        tableHtml += '</thead>';
        tableHtml += '<tbody class="bg-white divide-y divide-gray-200">';
        
        // Add rows
        sessions.forEach(session => {
            const statusClass = session.STATUS === 'ACTIVE' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
            
            tableHtml += '<tr>';
            tableHtml += '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">' + session.IDNO + '</td>';
            tableHtml += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + session.LASTNAME + ', ' + session.FIRSTNAME + ' ' + (session.MIDNAME || '') + '</td>';
            tableHtml += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + session.LAB_NAME + '</td>';
            tableHtml += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + session.PURPOSE + '</td>';
            tableHtml += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + formatDateTime(session.SESSION_START) + '</td>';
            tableHtml += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + (session.SESSION_END ? formatDateTime(session.SESSION_END) : 'N/A') + '</td>';
            tableHtml += '<td class="px-6 py-4 whitespace-nowrap">';
            tableHtml += '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' + statusClass + '">' + session.STATUS + '</span>';
            tableHtml += '</td>';
            tableHtml += '</tr>';
        });
        
        tableHtml += '</tbody>';
        tableHtml += '</table>';
        
        // Add table to DOM
        document.getElementById('report-results').innerHTML = tableHtml;
        
        // Define tiny logo base64 strings - very small to ensure they load fast
        const ucLogoBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAACXBIWXMAAAsTAAALEwEAmpwYAAAAIGNIUk0AAHolAACAgwAA+f8AAIDpAAB1MAAA6mAAADqYAAAXb5JfxUYAAAEkSURBVHja7JW9TsMwFIU/J24TUdEOZWLtRie68wZ9KvpaVZHYGJAQS7vBwo9oB6SVcDsQh4EwJCJggLvoWNbx+Xy/a0tCCmOMkUUJQZXKE1BV1Q4wB+ZZli2buohkQJ7n9Vrbtk+rhLuui4F34Molq+Bsc80nsAdcAkNgZgYubadr9+eWbN9lVZRlmatFYMvpXSJy7+JHmyil9h1L4GQTlQs3tjqP/wF/L4iiqJRSPqs1cLAJo6qqnDHLlFKztm0/1hC8GWPGQCQi+6uK3ntWStXe+3vgMU3TyU+BtW37CbwAB7YX37G5r+u6IIqicRiGkYhM0jQdbSMwxpzabCeO43C5XL7+FGitc+AEOO/7HhFxq95sbWJgBrwCDyJSrAGkLkdG+phP9DX+twDfAwBCIQrTj5IC7QAAAABJRU5ErkJggg==';
        const ccsLogoBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAACXBIWXMAAAsTAAALEwEAmpwYAAAAIGNIUk0AAHolAACAgwAA+f8AAIDpAAB1MAAA6mAAADqYAAAXb5JfxUYAAACvSURBVHja7JVBDsIgEEXfFDcu9TDeoRdoD9EjcAsXrFxosWqikRYXJoRQoWjC9CeTDJmB+eRnfhAAlFKeRGQEXIAXkDoqIsc2ORhjLsCt/QZPVd06HLO1x9twURSFruvugbV4UVPsvwGM9uEWuPfdoK7rOzAYY84islbVpCsKgiBQ1QlIrLWitZ4Bzxamy3gZQJ7nJnrHxpgJMG0aqIOCw6prVdUOWL4A7ODnAC/gsvHZdwB0NrYPrlX0sAAAAABJRU5ErkJggg==';
        
        // Initialize DataTables with a simple PDF export approach
        $(document).ready(function() {
            $('#reportTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv mr-1"></i> CSV',
                        title: 'Sit-In Report ' + startDate + ' to ' + endDate,
                        className: 'btn btn-success text-white'
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel mr-1"></i> Excel',
                        title: 'Sit-In Report ' + startDate + ' to ' + endDate,
                        className: 'btn btn-success text-white'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf mr-1"></i> PDF',
                        orientation: 'landscape',
                        pageSize: 'A4',
                        title: 'Sit-In Report ' + startDate + ' to ' + endDate,
                        className: 'btn btn-danger text-white',
                        customize: function(doc) {
                            // Set page margins [left, top, right, bottom]
                            doc.pageMargins = [40, 60, 40, 40];
                            
                            // Set default font size
                            doc.defaultStyle.fontSize = 10;
                            
                            // Add logos at the top
                            doc.content.unshift({
                                margin: [0, 0, 0, 12],
                                alignment: 'center',
                                columns: [
                                    {
                                        image: ucLogoBase64,
                                        width: 30,
                                        alignment: 'right',
                                        margin: [0, 0, 10, 0]
                                    },
                                    {
                                        width: '*',
                                        text: [
                                            {text: 'UNIVERSITY OF CEBU\n', fontSize: 16, bold: true},
                                            {text: 'College of Computer Studies\n', fontSize: 12, bold: true},
                                            {text: 'CCS SITIN MONITORING SYSTEM', fontSize: 11, italics: true}
                                        ],
                                        alignment: 'center'
                                    },
                                    {
                                        image: ccsLogoBase64,
                                        width: 30,
                                        alignment: 'left',
                                        margin: [10, 0, 0, 0]
                                    }
                                ]
                            });
                            
                            // Add report metadata
                            let labFilter = document.getElementById('report-lab').options[document.getElementById('report-lab').selectedIndex].text;
                            let purposeFilter = document.getElementById('report-purpose').value || 'All Purposes';
                            
                            doc.content.splice(1, 0, {
                                margin: [0, 10, 0, 10],
                                alignment: 'center',
                                columns: [
                                    {
                                        alignment: 'center',
                                        fontSize: 10,
                                        text: [
                                            {text: 'Sit-In Report\n', fontSize: 14, bold: true},
                                            {text: 'Period: ' + startDate + ' to ' + endDate + '\n', fontSize: 10},
                                            {text: 'Lab: ' + labFilter + ' | Purpose: ' + purposeFilter, fontSize: 10},
                                            {text: '\nTotal Records: ' + reportData.length, fontSize: 10, bold: true}
                                        ]
                                    }
                                ]
                            });
                            
                            // Add summary statistics
                            let activeCount = reportData.filter(session => session.STATUS === 'ACTIVE').length;
                            let completedCount = reportData.filter(session => session.STATUS === 'COMPLETED').length;
                            
                            let uniqueStudents = new Set();
                            let uniqueLabs = {};
                            let purposes = {};
                            
                            reportData.forEach(session => {
                                uniqueStudents.add(session.IDNO);
                                
                                if (!uniqueLabs[session.LAB_NAME]) {
                                    uniqueLabs[session.LAB_NAME] = 0;
                                }
                                uniqueLabs[session.LAB_NAME]++;
                                
                                if (!purposes[session.PURPOSE]) {
                                    purposes[session.PURPOSE] = 0;
                                }
                                purposes[session.PURPOSE]++;
                            });
                            
                            // Get top lab and purpose
                            let topLab = Object.keys(uniqueLabs).reduce((a, b) => uniqueLabs[a] > uniqueLabs[b] ? a : b, '');
                            let topPurpose = Object.keys(purposes).reduce((a, b) => purposes[a] > purposes[b] ? a : b, '');
                            
                            doc.content.splice(2, 0, {
                                margin: [0, 0, 0, 10],
                                columnGap: 20,
                                columns: [
                                    {
                                        width: 'auto',
                                        table: {
                                            headerRows: 1,
                                            widths: ['*', '*'],
                                            body: [
                                                [{text: 'Summary Statistics', style: 'tableHeader', colSpan: 2, alignment: 'center'}, {}],
                                                ['Unique Students', uniqueStudents.size],
                                                ['Active Sessions', activeCount],
                                                ['Completed Sessions', completedCount],
                                                ['Most Used Lab', topLab],
                                                ['Most Common Purpose', topPurpose]
                                            ]
                                        },
                                        fontSize: 8,
                                        alignment: 'center'
                                    }
                                ],
                                alignment: 'center'
                            });
                            
                            // Modify table styling
                            doc.content[3].table.widths = ['15%', '20%', '15%', '15%', '15%', '15%', '5%'];
                            
                            // Fix: Apply styling to all header cells
                            if (doc.content[3].table.body && doc.content[3].table.body.length > 0) {
                                // Style all cells in the header row
                                for (let i = 0; i < doc.content[3].table.body[0].length; i++) {
                                    if (!doc.content[3].table.body[0][i]) continue;
                                    
                                    doc.content[3].table.body[0][i].fillColor = '#356480';
                                    doc.content[3].table.body[0][i].color = '#ffffff';
                                    doc.content[3].table.body[0][i].fontSize = 9;
                                    doc.content[3].table.body[0][i].bold = true;
                                }
                                
                                // Apply consistent row styles to data rows
                                for (let rowIndex = 1; rowIndex < doc.content[3].table.body.length; rowIndex++) {
                                    // Apply alternating row background colors
                                    const shouldColor = rowIndex % 2 === 1;
                                    const rowBgColor = shouldColor ? '#f8f9fa' : '#ffffff';
                                    
                                    // Apply the background color to each cell in the row
                                    for (let cellIndex = 0; cellIndex < doc.content[3].table.body[rowIndex].length; cellIndex++) {
                                        // Check if cell exists and is an object (could be a string or object)
                                        if (!doc.content[3].table.body[rowIndex][cellIndex]) continue;
                                        
                                        // If cell is a string, convert it to object with text property
                                        if (typeof doc.content[3].table.body[rowIndex][cellIndex] === 'string') {
                                            doc.content[3].table.body[rowIndex][cellIndex] = {
                                                text: doc.content[3].table.body[rowIndex][cellIndex],
                                                fillColor: rowBgColor
                                            };
                                        } else {
                                            // Otherwise, just set the fillColor property
                                            doc.content[3].table.body[rowIndex][cellIndex].fillColor = rowBgColor;
                                        }
                                    }
                                }
                            }
                            
                            // Add footer with page numbers
                            var now = new Date().toLocaleString();
                            doc.footer = function(currentPage, pageCount) {
                                return {
                                    columns: [
                                        { 
                                            text: 'Generated by: <?php echo htmlspecialchars($username); ?> | ' + now, 
                                            alignment: 'left', 
                                            fontSize: 8, 
                                            margin: [40, 0] 
                                        },
                                        { 
                                            text: 'Page ' + currentPage.toString() + ' of ' + pageCount, 
                                            alignment: 'right', 
                                            fontSize: 8, 
                                            margin: [0, 0, 40, 0] 
                                        }
                                    ],
                                    margin: [40, 0]
                                };
                            };
                            
                            // Add watermark
                            doc.watermark = {
                                text: 'UNIVERSITY OF CEBU',
                                color: '#eeeeee',
                                opacity: 0.1,
                                bold: true,
                                fontSize: 60
                            };
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print mr-1"></i> Print',
                        className: 'btn btn-info text-white'
                    }
                ],
                "pageLength": 25,
                "language": {
                    "search": "<i class='fas fa-search'></i> _INPUT_",
                    "searchPlaceholder": "Search records...",
                    "lengthMenu": "Show _MENU_ records",
                    "info": "Showing _START_ to _END_ of _TOTAL_ records",
                    "zeroRecords": "No matching records found",
                    "paginate": {
                        "first": "<i class='fas fa-angle-double-left'></i>",
                        "last": "<i class='fas fa-angle-double-right'></i>",
                        "next": "<i class='fas fa-angle-right'></i>",
                        "previous": "<i class='fas fa-angle-left'></i>"
                    }
                }
            });
            
            // Add custom classes to button container
            $('.dt-buttons').addClass('flex gap-2 mb-4');
        });
    }

    // Refresh recent sessions table
    document.getElementById('refresh-sessions').addEventListener('click', function() {
        const refreshButton = this;
        const originalHtml = refreshButton.innerHTML;
        refreshButton.disabled = true;
        refreshButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Refreshing...';
        
        fetch('ajax/get_recent_sessions.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tableBody = document.querySelector('#recent-sessions table tbody');
                    if (data.sessions.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">No recent sit-in sessions found</td></tr>';
                    } else {
                        let html = '';
                        
                        data.sessions.forEach(session => {
                            const statusClass = session.STATUS === 'ACTIVE' ? 
                                "bg-green-100 text-green-800" : "bg-blue-100 text-blue-800";
                            
                            const timeOut = session.SESSION_END ? 
                                formatTime(session.SESSION_END) : 
                                '<span class="italic">Active</span>';
                            
                            html += '<tr>';
                            html += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + formatDate(session.SESSION_START) + '</td>';
                            html += '<td class="px-6 py-4 whitespace-nowrap">';
                            html += '<div class="text-sm font-medium text-gray-900">' + session.LASTNAME + ', ' + session.FIRSTNAME + ' ' + (session.MIDNAME || '') + '</div>';
                            html += '<div class="text-sm text-gray-500">' + session.IDNO + '</div>';
                            html += '</td>';
                            html += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + session.PURPOSE + '</td>';
                            html += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + session.LAB_NAME + '</td>';
                            html += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + formatTime(session.SESSION_START) + '</td>';
                            html += '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + timeOut + '</td>';
                            html += '<td class="px-6 py-4 whitespace-nowrap">';
                            html += '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' + statusClass + '">' + session.STATUS + '</span>';
                            html += '</td>';
                            html += '</tr>';
                        });
                        
                        tableBody.innerHTML = html;
                    }
                } else {
                    alert('Error refreshing data: ' + (data.message || 'Unknown error'));
                }
                
                refreshButton.disabled = false;
                refreshButton.innerHTML = originalHtml;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
                refreshButton.disabled = false;
                refreshButton.innerHTML = originalHtml;
            });
    });

    // Add direct PDF download function that bypasses DataTables buttons
    function generateDirectPDF(startDate, endDate) {
        try {
            // Show loading message
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 flex items-center justify-center z-50';
            loadingDiv.innerHTML = '<div class="bg-white p-6 rounded-lg shadow-xl flex flex-col items-center">' +
                                   '<i class="fas fa-spinner fa-spin text-4xl text-blue-500 mb-4"></i>' +
                                   '<p class="text-lg font-semibold">Generating PDF...</p>' +
                                   '<p class="text-sm text-gray-500 mt-2">Please wait, this may take a few moments.</p>' +
                                   '</div>';
            document.body.appendChild(loadingDiv);
            
            // Initialize jsPDF without waiting for images
            window.jsPDF = window.jspdf.jsPDF;
            const doc = new jsPDF({
                orientation: 'landscape',
                unit: 'mm',
                format: 'a4'
            });
            
            // Wait to ensure jspdf-autotable is fully loaded and attached to jsPDF
            if (typeof doc.autoTable !== 'function') {
                // Add the plugin manually if it's not automatically attached
                if (window.jspdf && window.jspdf.jsPDF && typeof window.jspdf.jsPDF.API.autoTable === 'function') {
                    window.jspdf.jsPDF.API.autoTable = window.jspdf.jsPDF.API.autoTable;
                }
            }
            
            // Check if autoTable is available, if not use a simpler approach
            const hasAutoTable = typeof doc.autoTable === 'function';
            
            // Add title immediately - don't wait for images
            doc.setFontSize(16);
            doc.setFont(undefined, 'bold');
            doc.text('UNIVERSITY OF CEBU', doc.internal.pageSize.width / 2, 15, { align: 'center' });
            doc.setFontSize(12);
            doc.text('College of Computer Studies', doc.internal.pageSize.width / 2, 22, { align: 'center' });
            doc.setFontSize(11);
            doc.setFont(undefined, 'italic');
            doc.text('CCS SITIN MONITORING SYSTEM', doc.internal.pageSize.width / 2, 27, { align: 'center' });
            
            // Add report details
            doc.setFont(undefined, 'bold');
            doc.setFontSize(14);
            doc.text('Sit-In Report', doc.internal.pageSize.width / 2, 35, { align: 'center' });
            
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            let labFilter = $('#report-lab option:selected').text();
            let purposeFilter = $('#report-purpose').val() || 'All Purposes';
            
            doc.text('Period: ' + startDate + ' to ' + endDate, doc.internal.pageSize.width / 2, 40, { align: 'center' });
            doc.text('Lab: ' + labFilter + ' | Purpose: ' + purposeFilter, doc.internal.pageSize.width / 2, 45, { align: 'center' });
            doc.text('Total Records: ' + reportData.length, doc.internal.pageSize.width / 2, 50, { align: 'center' });
            
            // Add summary statistics
            let activeCount = reportData.filter(session => session.STATUS === 'ACTIVE').length;
            let completedCount = reportData.filter(session => session.STATUS === 'COMPLETED').length;
            
            let uniqueStudents = new Set();
            reportData.forEach(session => uniqueStudents.add(session.IDNO));
            
            // Draw statistics box
            doc.setDrawColor(100, 100, 100);
            doc.setFillColor(240, 240, 240);
            doc.roundedRect(90, 53, 120, 25, 2, 2, 'FD');
            
            doc.setFontSize(9);
            doc.setFont(undefined, 'bold');
            doc.text('Summary Statistics', doc.internal.pageSize.width / 2, 58, { align: 'center' });
            
            doc.setFont(undefined, 'normal');
            doc.text('Unique Students: ' + uniqueStudents.size, 100, 64);
            doc.text('Active Sessions: ' + activeCount, 100, 69);
            doc.text('Completed Sessions: ' + completedCount, 100, 74);
            
            // Get table data
            const headers = [];
            const rows = [];
            
            // Extract headers
            $('#reportTable thead th').each(function() {
                headers.push($(this).text());
            });
            
            // Extract data
            $('#reportTable tbody tr').each(function() {
                const row = [];
                $(this).find('td').each(function() {
                    row.push($(this).text());
                });
                rows.push(row);
            });
            
            // Generate table in PDF
            if (hasAutoTable) {
                // Use autoTable if available
                doc.autoTable({
                    head: [headers],
                    body: rows,
                    startY: 82,
                    theme: 'grid',
                    styles: { fontSize: 8 },
                    headStyles: {
                        fillColor: [53, 100, 128],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold'
                    },
                    alternateRowStyles: {
                        fillColor: [245, 247, 250]
                    },
                    didDrawPage: function(data) {
                        // Add footer
                        doc.setFontSize(8);
                        doc.setTextColor(100, 100, 100);
                        doc.setFont(undefined, 'normal');
                        doc.text('Generated by: <?php echo htmlspecialchars($username); ?> | ' + new Date().toLocaleString(), 15, doc.internal.pageSize.height - 10);
                        doc.text('Page ' + doc.internal.getCurrentPageInfo().pageNumber + ' of ' + doc.internal.getNumberOfPages(), 280, doc.internal.pageSize.height - 10);
                    }
                });
            } else {
                // Fallback: Draw a basic table manually
                console.log("AutoTable plugin not available, using basic table");
                const startY = 82;
                const rowHeight = 10;
                const colWidths = [25, 40, 30, 30, 35, 35, 20];
                let xPos = 15;
                
                // Draw headers
                doc.setFillColor(53, 100, 128);
                doc.setTextColor(255, 255, 255);
                doc.setFontSize(8);
                doc.setFont(undefined, 'bold');
                
                let currentX = xPos;
                for (let i = 0; i < headers.length; i++) {
                    doc.rect(currentX, startY, colWidths[i], rowHeight, 'F');
                    doc.text(headers[i], currentX + 2, startY + 6);
                    currentX += colWidths[i];
                }
                
                // Draw rows
                doc.setTextColor(0, 0, 0);
                doc.setFont(undefined, 'normal');
                
                let currentY = startY + rowHeight;
                for (let i = 0; i < Math.min(rows.length, 20); i++) { // Limit to 20 rows to prevent overflow
                    currentX = xPos;
                    // Alternate row colors
                    if (i % 2 === 1) {
                        doc.setFillColor(245, 247, 250);
                        for (let j = 0; j < headers.length; j++) {
                            doc.rect(currentX, currentY, colWidths[j], rowHeight, 'F');
                            currentX += colWidths[j];
                        }
                    }
                    
                    currentX = xPos;
                    for (let j = 0; j < rows[i].length; j++) {
                        doc.text(String(rows[i][j]).substring(0, 18), currentX + 2, currentY + 6); // Limit text length
                        currentX += colWidths[j];
                    }
                    currentY += rowHeight;
                }
                
                // Add note if rows were truncated
                if (rows.length > 20) {
                    doc.text('Note: Only showing first 20 records due to PDF size limitations', xPos, currentY + 10);
                }
                
                // Add footer
                doc.setFontSize(8);
                doc.setTextColor(100, 100, 100);
                doc.setFont(undefined, 'normal');
                doc.text('Generated by: <?php echo htmlspecialchars($username); ?> | ' + new Date().toLocaleString(), 15, doc.internal.pageSize.height - 10);
                doc.text('Page 1 of 1', 280, doc.internal.pageSize.height - 10);
            }
            
            // Directly trigger download using multiple methods for browser compatibility
            setTimeout(function() {
                try {
                    // Method 1: Standard save
                    doc.save('Sit-In_Report_' + startDate + '_to_' + endDate + '.pdf');
                } catch (e) {
                    console.error('Standard save failed:', e);
                    try {
                        // Method 2: Using FileSaver.js
                        const blob = doc.output('blob');
                        window.saveAs(blob, 'Sit-In_Report_' + startDate + '_to_' + endDate + '.pdf');
                    } catch (e2) {
                        console.error('FileSaver failed:', e2);
                        try {
                            // Method 3: Direct download link
                            const pdfData = doc.output('datauristring');
                            const link = document.createElement('a');
                            link.href = pdfData;
                            link.download = 'Sit-In_Report_' + startDate + '_to_' + endDate + '.pdf';
                            link.click();
                        } catch (e3) {
                            console.error('Link method failed:', e3);
                            // Method 4: Open in new window as last resort
                            const pdfDataUri = doc.output('datauristring');
                            window.open(pdfDataUri);
                        }
                    }
                }
                
                // Remove loading message
                document.body.removeChild(loadingDiv);
            }, 1000); // Small delay to ensure PDF generation is complete
            
        } catch (error) {
            console.error('Error generating PDF:', error);
            alert('Error generating PDF: ' + error.message);
            // Remove loading message if there's an error
            const loadingDiv = document.querySelector('.fixed.top-0.left-0.w-full.h-full');
            if (loadingDiv) document.body.removeChild(loadingDiv);
        }
    }
    
    // Override both the original PDF button and the alternative button
    $(document).on('click', '.buttons-pdf, #direct-pdf-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        
        // Use our direct PDF generation function
        generateDirectPDF(startDate, endDate);
        
        return false;
    });
</script>
<?php include('includes/footer.php'); ?>
</body>
</html>