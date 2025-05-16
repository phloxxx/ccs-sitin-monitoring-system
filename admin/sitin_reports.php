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
            <!-- Added Logos -->                
             <div class="flex flex-col items-center pt-5 pb-2">
                    <div class="relative w-16 h-16 mb-1">
                        <!-- UC Logo -->
                        <div class="absolute inset-0 rounded-full bg-white shadow-md overflow-hidden flex items-center justify-center">
                            <img src="../student/images/uc_logo.png" alt="University of Cebu Logo" class="h-13 w-13 object-contain">
                        </div>
                        <!-- CCS Logo (smaller and positioned at the bottom right) -->
                        <div class="absolute bottom-0 right-0 w-9 h-9 rounded-ful-l bg-white shadow-md border-2 border-white overflow-hidden flex items-center justify-center">
                            <img src="../student/images/ccs_logo.png" alt="CCS Logo" class="h-7 w-7 object-contain">
                        </div>
                    </div>
                    <h1 class="text-white font-bold text-sm">CCS Sit-In</h1>
                    <p class="text-gray-300 text-xs">Monitoring System</p>
                </div>                
                <div class="flex flex-col flex-grow px-4 py-3 overflow-hidden">
                    <nav class="flex-1 space-y-1">
                    <a href="dashboard.php" class="flex items-center px-3 py-3.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-home mr-3 text-lg"></i>
                        <span class="font-medium">Home</span>
                    </a>
                    <a href="search.php" class="flex items-center px-3 py-3.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-search mr-3 text-lg"></i>
                        <span class="font-medium">Search</span>
                    </a>
                    <a href="students.php" class="flex items-center px-3 py-3.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-user-graduate mr-3 text-lg"></i>
                        <span class="font-medium">Students</span>
                    </a>
                    <a href="sitin.php" class="flex items-center px-3 py-3.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3 text-lg"></i>
                        <span class="font-medium">Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center justify-between px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                            <span class="font-medium">Reservation</span>
                        </div>
                        <?php if ($pendingCount > 0): ?>
                        <span class="bg-red-500 text-white text-xs rounded-full px-2 py-1 font-semibold"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="lab_resources.php" class="flex items-center px-3 py-3.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-book-open mr-3 text-lg"></i>
                        <span class="font-medium">Lab Resources</span>
                    </a>
                    <a href="feedbacks.php" class="flex items-center px-3 py-3.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-comments mr-3 text-lg"></i>
                        <span class="font-medium">Feedbacks</span>
                    </a>
                    <a href="leaderboard.php" class="flex items-center px-3 py-3.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-trophy mr-3 text-lg"></i>
                        <span class="font-medium">Leaderboard</span>
                    </a>
                </nav>                  
                <div class="mt-3 border-t border-white-700 pt-2">
                    <a href="#" onclick="confirmLogout(event)" class="flex items-center px-3 py-3.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-sign-out-alt mr-3 text-lg"></i>
                        <span class="font-medium">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 overflow-hidden">        
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm sticky top-0 z-10">
            <div class="flex items-center justify-between h-16 px-6">
                <!-- Mobile Menu Button -->
                <div class="flex items-center">
                    <button id="mobile-menu-button" class="text-gray-500 md:hidden focus:outline-none p-1 hover:bg-gray-100 rounded-md transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="ml-4 text-xl font-semibold text-secondary flex items-center">
                        Sit-In Reports
                    </h1>
                </div>
                
                <!-- Admin Profile -->
                <div class="flex items-center">
                    <span class="mr-3 text-sm font-medium text-gray-700 hidden sm:inline-block"><?php echo htmlspecialchars($username); ?></span>
                    <div class="relative group">
                        <button class="flex items-center justify-center w-9 h-9 rounded-full bg-primary text-white hover:bg-primary-dark transition-colors border-2 border-white shadow-sm">
                            <i class="fas fa-user"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                            <div class="px-4 py-2 text-sm text-gray-700 border-b border-gray-100">
                                Signed in as <span class="font-semibold"><?php echo htmlspecialchars($username); ?></span>
                            </div>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile Settings</a>
                            <a href="#" onclick="confirmLogout(event)" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Logout</a>
                        </div>
                    </div>
                </div>
            </div>            
              <!-- Mobile Navigation -->
            <div id="mobile-menu" class="hidden md:hidden px-4 py-3 bg-secondary">
                <nav class="space-y-1 overflow-y-auto max-h-[calc(100vh-80px)]">
                    <a href="dashboard.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-home mr-3 text-lg"></i>
                        <span class="font-medium">Home</span>
                    </a>
                    <a href="search.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-search mr-3 text-lg"></i>
                        <span class="font-medium">Search</span>
                    </a>
                    <a href="students.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-user-graduate mr-3 text-lg"></i>
                        <span class="font-medium">Students</span>
                    </a>
                    <a href="sitin.php" class="flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3 text-lg"></i>
                        <span class="font-medium">Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center justify-between px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                            <span class="font-medium">Reservation</span>
                        </div>
                        <?php if ($pendingCount > 0): ?>
                        <span class="bg-red-500 text-white text-xs rounded-full px-2 py-1 font-semibold"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="lab_resources.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-book-open mr-3 text-lg"></i>
                        <span class="font-medium">Lab Resources</span>
                    </a>
                    <a href="feedbacks.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-comments mr-3 text-lg"></i>
                        <span class="font-medium">Feedbacks</span>
                    </a>
                <a href="leaderboard.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-trophy mr-3 text-lg"></i>
                        <span class="font-medium">Leaderboard</span>
                    </a>
                    
                    <div class="border-t border-gray-700 mt-2 pt-2">
                        <a href="#" onclick="confirmLogout(event)" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                            <i class="fas fa-sign-out-alt mr-3 text-lg"></i>
                            <span class="font-medium">Logout</span>
                        </a>
                    </div>
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
                        action: function(e, dt, node, config) {
                            // Show loading spinner
                            const loadingDiv = document.createElement('div');
                            loadingDiv.id = 'pdf-loading';
                            loadingDiv.className = 'fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 flex items-center justify-center z-50';
                            loadingDiv.innerHTML = `
                                <div class="bg-white p-6 rounded-lg shadow-xl flex flex-col items-center">
                                    <i class="fas fa-spinner fa-spin text-4xl text-blue-500 mb-4"></i>
                                    <p class="text-lg font-semibold">Generating PDF...</p>
                                    <p class="text-sm text-gray-500 mt-2">Please wait, this may take a few moments.</p>
                                </div>`;
                            document.body.appendChild(loadingDiv);

                            // Load logos as base64
                            const ucLogo = new Image();
                            const ccsLogo = new Image();
                            
                            ucLogo.src = '../student/images/uc_logo.png';
                            ccsLogo.src = '../student/images/ccs_logo.png';

                            Promise.all([
                                new Promise(resolve => {
                                    ucLogo.onload = () => {
                                        const canvas = document.createElement('canvas');
                                        canvas.width = ucLogo.width;
                                        canvas.height = ucLogo.height;
                                        const ctx = canvas.getContext('2d');
                                        ctx.drawImage(ucLogo, 0, 0);
                                        resolve(canvas.toDataURL('image/png'));
                                    };
                                }),
                                new Promise(resolve => {
                                    ccsLogo.onload = () => {
                                        const canvas = document.createElement('canvas');
                                        canvas.width = ccsLogo.width;
                                        canvas.height = ccsLogo.height;
                                        const ctx = canvas.getContext('2d');
                                        ctx.drawImage(ccsLogo, 0, 0);
                                        resolve(canvas.toDataURL('image/png'));
                                    };
                                })
                            ]).then(([ucLogoBase64, ccsLogoBase64]) => {
                                // Create and download PDF
                                Promise.all([ucLogoBase64, ccsLogoBase64]).then(([ucLogo, ccsLogo]) => {
                                    // Create PDF instance
                                    let doc = new jsPDF('landscape', 'pt', 'a4');
                                    
                                    // Add logos and header
                                    doc.addImage(ucLogo, 'PNG', 40, 20, 50, 50);
                                    doc.addImage(ccsLogo, 'PNG', doc.internal.pageSize.width - 90, 20, 50, 50);
                                    
                                    // Add title and headers
                                    doc.setFontSize(16);
                                    doc.text('UNIVERSITY OF CEBU', doc.internal.pageSize.width / 2, 40, { align: 'center' });
                                    doc.setFontSize(12);
                                    doc.text('College of Computer Studies', doc.internal.pageSize.width / 2, 60, { align: 'center' });
                                    doc.setFontSize(14);
                                    doc.text('CCS SITIN MONITORING SYSTEM', doc.internal.pageSize.width / 2, 80, { align: 'center' });

                                    // Convert table data
                                    let data = dt.rows().data().toArray();
                                    let columns = dt.columns().header().map(h => $(h).text()).toArray();
                                    
                                    // Add table with error handling
                                    try {
                                        doc.autoTable({
                                            head: [columns],
                                            body: data,
                                            startY: 100,
                                            styles: { 
                                                fontSize: 8,
                                                textColor: [0, 0, 0] // Black text for all cells
                                            },                                            headStyles: { 
                                                fillColor: [53, 100, 128], // Dark blue background for headers
                                                textColor: [255, 255, 255], // White text for headers
                                                fontStyle: 'bold',
                                                fontSize: 8,                 // Consistent font size
                                                halign: 'left',
                                                minCellHeight: 12,          // Ensure minimum height for header cells
                                                valign: 'middle',           // Vertical alignment
                                                lineColor: [255, 255, 255], // White border for better contrast
                                                lineWidth: 0.1              // Thin border
                                            },
                                            alternateRowStyles: { 
                                                fillColor: [245, 245, 245] // Light gray for alternate rows
                                            },
                                            theme: 'grid',
                                            columnStyles: {
                                                0: {cellWidth: 25}, // ID
                                                1: {cellWidth: 40}, // Name
                                                2: {cellWidth: 30}, // Laboratory
                                                3: {cellWidth: 30}, // Purpose
                                                4: {cellWidth: 35}, // Start Time
                                                5: {cellWidth: 35}, // End Time
                                                6: {cellWidth: 20}  // Status
                                            },
                                            didDrawPage: function(data) {
                                                // Add logos on each page
                                                try {
                                                    if (ucLogoLoaded && ucLogo.complete) {
                                                        const ucCanvas = document.createElement('canvas');
                                                        const ucCtx = ucCanvas.getContext('2d');
                                                        ucCanvas.width = ucLogo.width;
                                                        ucCanvas.height = ucLogo.height;
                                                        ucCtx.drawImage(ucLogo, 0, 0);
                                                        const ucLogoDataUrl = ucCanvas.toDataURL('image/png');
                                                        // Position UC logo to the left of the title text
                                                        doc.addImage(ucLogoDataUrl, 'PNG', (doc.internal.pageSize.width / 2) - 75, 28, 13, 13);
                                                    }
                                                    
                                                    if (ccsLogoLoaded && ccsLogo.complete) {
                                                        const ccsCanvas = document.createElement('canvas');
                                                        const ccsCtx = ccsCanvas.getContext('2d');
                                                        ccsCanvas.width = ccsLogo.width;
                                                        ccsCanvas.height = ccsLogo.height;
                                                        ccsCtx.drawImage(ccsLogo, 0, 0);
                                                        const ccsLogoDataUrl = ccsCanvas.toDataURL('image/png');
                                                        // Position CCS logo to the right of the title text
                                                        doc.addImage(ccsLogoDataUrl, 'PNG', (doc.internal.pageSize.width / 2) + 62, 28, 13, 13);
                                                    }
                                                } catch (logoError) {
                                                    console.error("Error adding logos to PDF in header:", logoError);
                                                }
                                                
                                                // Add headers on each page
                                                doc.setFillColor(25, 64, 175); // Dark blue header background
                                                doc.rect(0, 0, doc.internal.pageSize.width, 22, 'F');
                                                
                                                doc.setFontSize(16);
                                                doc.setTextColor(255, 255, 255);
                                                doc.setFont(undefined, 'bold');
                                                doc.text('UNIVERSITY OF CEBU', doc.internal.pageSize.width / 2, 12, { align: 'center' });
                                                
                                                doc.setFontSize(12);
                                                doc.setTextColor(0, 0, 0);
                                                doc.text('College of Computer Studies', doc.internal.pageSize.width / 2, 28, { align: 'center' });
                                                
                                                doc.setFontSize(14);
                                                doc.setFont(undefined, 'bold');
                                                doc.text('CCS SITIN MONITORING SYSTEM', doc.internal.pageSize.width / 2, 36, { align: 'center' });

                                                // Add page numbers
                                                let pageCount = doc.internal.getNumberOfPages();
                                                doc.setFontSize(8);
                                                doc.text('Page ' + data.pageNumber + ' of ' + pageCount, doc.internal.pageSize.width - 20, doc.internal.pageSize.height - 10, { align: 'right' });
                                                
                                                // Add footer
                                                doc.setFontSize(8);
                                                doc.setTextColor(100, 100, 100);
                                                doc.text('Generated on: ' + new Date().toLocaleString(), 15, doc.internal.pageSize.height - 10);
                                            },
                                            margin: { top: 100 } // Ensure enough space for header
                                        });
                                        
                                        let pdfSaved = false;
                                        
                                        // Function to clean up after successful save
                                        const cleanupAfterSave = () => {
                                            if (!pdfSaved) {
                                                pdfSaved = true;
                                                setTimeout(() => {
                                                    const loadingElement = document.getElementById('pdf-loading');
                                                    if (loadingElement) {
                                                        loadingElement.remove();
                                                    }
                                                }, 3000); // Increased delay to 3 seconds
                                            }
                                        };

                                        // Try multiple save methods in sequence
                                        try {
                                            // Method 1: Standard save
                                            doc.save('SitIn_Report_' + startDate + '_to_' + endDate + '.pdf');
                                            cleanupAfterSave();
                                        } catch (saveError) {
                                            console.warn('Standard save failed, trying alternative method:', saveError);
                                            try {
                                                // Method 2: Blob save
                                                const blob = doc.output('blob');
                                                const url = window.URL.createObjectURL(blob);
                                                const link = document.createElement('a');
                                                link.href = url;
                                                link.download = 'SitIn_Report_' + startDate + '_to_' + endDate + '.pdf';
                                                document.body.appendChild(link);
                                                link.click();
                                                document.body.removeChild(link);
                                                window.URL.revokeObjectURL(url);
                                                cleanupAfterSave();
                                            } catch (blobError) {
                                                console.warn('Blob save failed, trying final method:', blobError);
                                                // Method 3: Direct data URI
                                                const pdfData = doc.output('datauristring');
                                                const newWindow = window.open(pdfData);
                                                if (newWindow) {
                                                    cleanupAfterSave();
                                                } else {
                                                    throw new Error('Popup blocked');
                                                }
                                            }
                                        }

                                    } catch (tableError) {
                                        console.error('Error in PDF generation:', tableError);
                                        setTimeout(() => {
                                            const loadingElement = document.getElementById('pdf-loading');
                                            if (loadingElement) {
                                                loadingElement.remove();
                                            }
                                            // Only show error if PDF wasn't successfully saved
                                            if (!pdfSaved) {
                                                alert('Error generating PDF. Please try again.');
                                            }
                                        }, 1000);
                                    }
                                }).catch(error => {
                                    console.error('Error loading images:', error);
                                    const loadingElement = document.getElementById('pdf-loading');
                                    if (loadingElement) {
                                        loadingElement.remove();
                                    }
                                });
                            }).catch(error => {
                                console.error('Error loading images:', error);
                                const loadingElement = document.getElementById('pdf-loading');
                                if (loadingElement) {
                                    loadingElement.remove();
                                }
                            });
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
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 flex items-center justify-center z-50';
            loadingDiv.innerHTML = '<div class="bg-white p-6 rounded-lg shadow-xl flex flex-col items-center">' +
                                   '<i class="fas fa-spinner fa-spin text-4xl text-blue-500 mb-4"></i>' +
                                   '<p class="text-lg font-semibold">Generating PDF...</p>' +
                                   '<p class="text-sm text-gray-500 mt-2">Please wait, this may take a few moments.</p>' +
                                   '</div>';
            document.body.appendChild(loadingDiv);
            
            // Load the logos first, then proceed with PDF generation
            const ucLogo = new Image();
            const ccsLogo = new Image();
            
            let ucLogoLoaded = false;
            let ccsLogoLoaded = false;
            
            ucLogo.onload = function() {
                ucLogoLoaded = true;
                checkBothLogosLoaded();
            };
            
            ccsLogo.onload = function() {
                ccsLogoLoaded = true;
                checkBothLogosLoaded();
            };
            
            // Set error handlers for image loading
            ucLogo.onerror = function() {
                console.error("Failed to load UC logo");
                ucLogoLoaded = true; // Consider it "loaded" to proceed
                checkBothLogosLoaded();
            };
            
            ccsLogo.onerror = function() {
                console.error("Failed to load CCS logo");
                ccsLogoLoaded = true; // Consider it "loaded" to proceed
                checkBothLogosLoaded();
            };
            
            // Set the image sources - use absolute URLs to ensure they load
            ucLogo.src = '../student/images/uc_logo.png';
            ccsLogo.src = '../student/images/ccs_logo.png';
            
            function checkBothLogosLoaded() {
                if (!ucLogoLoaded || !ccsLogoLoaded) return;
                
                // Both logos loaded (or failed to load), proceed with PDF generation
                generatePDFWithLogos();
            }
            
            function generatePDFWithLogos() {
                // Initialize jsPDF
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
                
                // Add a header with school logo background color bar
                doc.setFillColor(25, 64, 175); // Dark blue header background
                doc.rect(0, 0, doc.internal.pageSize.width, 22, 'F');
                
                // Add logos if they were successfully loaded
                try {
                    if (ucLogoLoaded && ucLogo.complete) {
                        const ucCanvas = document.createElement('canvas');
                        const ucCtx = ucCanvas.getContext('2d');
                        ucCanvas.width = ucLogo.width;
                        ucCanvas.height = ucLogo.height;
                        ucCtx.drawImage(ucLogo, 0, 0);
                        const ucLogoDataUrl = ucCanvas.toDataURL('image/png');
                        // Position UC logo to the left of the title text
                        doc.addImage(ucLogoDataUrl, 'PNG', (doc.internal.pageSize.width / 2) - 75, 28, 13, 13);
                    }
                    
                    if (ccsLogoLoaded && ccsLogo.complete) {
                        const ccsCanvas = document.createElement('canvas');
                        const ccsCtx = ccsCanvas.getContext('2d');
                        ccsCanvas.width = ccsLogo.width;
                        ccsCanvas.height = ccsLogo.height;
                        ccsCtx.drawImage(ccsLogo, 0, 0);
                        const ccsLogoDataUrl = ccsCanvas.toDataURL('image/png');
                        // Position CCS logo to the right of the title text
                        doc.addImage(ccsLogoDataUrl, 'PNG', (doc.internal.pageSize.width / 2) + 62, 28, 13, 13);
                    }
                } catch (logoError) {
                    console.error("Error adding logos to PDF:", logoError);
                    // Continue without logos if there's an error
                }
                
                // Add title with improved styling
                doc.setTextColor(255, 255, 255); // White text for header
                doc.setFontSize(20);
                doc.setFont(undefined, 'bold');
                doc.text('UNIVERSITY OF CEBU', doc.internal.pageSize.width / 2, 12, { align: 'center' });
                
                // Add subtitle with improved styling
                doc.setTextColor(0, 0, 0); // Reset text color to black
                doc.setFontSize(14);
                doc.text('College of Computer Studies', doc.internal.pageSize.width / 2, 28, { align: 'center' });
                
                // Add system name with logos on both sides
                doc.setFontSize(16);
                doc.setFont(undefined, 'bold');
                doc.text('CCS SITIN MONITORING SYSTEM', doc.internal.pageSize.width / 2, 36, { align: 'center' });
                
                // Add decorative underline
                doc.setDrawColor(25, 64, 175); // Dark blue line
                doc.setLineWidth(0.5);
                const textWidth = doc.getTextWidth('CCS SITIN MONITORING SYSTEM');
                doc.line(
                    (doc.internal.pageSize.width / 2) - (textWidth / 2), 38,
                    (doc.internal.pageSize.width / 2) + (textWidth / 2), 38
                );
                
                // Add report title with improved styling
                doc.setFontSize(16);
                doc.setTextColor(25, 64, 175); // Blue text for report title
                doc.text('Sit-In Report', doc.internal.pageSize.width / 2, 46, { align: 'center' });
                
                // Add report metadata with better spacing and styling
                doc.setFontSize(10);
                doc.setTextColor(0, 0, 0); // Reset to black
                doc.setFont(undefined, 'normal');
                let labFilter = $('#report-lab option:selected').text();
                let purposeFilter = $('#report-purpose').val() || 'All Purposes';
                
                doc.text('Period: ' + startDate + ' to ' + endDate, doc.internal.pageSize.width / 2, 53, { align: 'center' });
                doc.text('Lab: ' + labFilter + ' | Purpose: ' + purposeFilter, doc.internal.pageSize.width / 2, 58, { align: 'center' });
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
                        styles: { fontSize: 8 },                    headStyles: {
                            fillColor: [53, 100, 128],     // Blue background for all headers
                            textColor: [255, 255, 255],    // White text for all headers
                            fontStyle: 'bold',
                            fontSize: 8                    // Consistent font size
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
                            doc.text('Page ' + doc.internal.getCurrentPageInfo().pageNumber + ' of ' + doc.internal.getNumberOfPages(), doc.internal.pageSize.width - 15, doc.internal.pageSize.height - 10, {align: 'right'});
                        }
                    });
                } else {
                    // Fallback: Draw a basic table manually                console.log("AutoTable plugin not available, using basic table");
                    const startY = 82;
                    const rowHeight = 10;
                    // Adjusted column widths for better content display
                    // [Student ID, Student Name, Laboratory, Purpose, Start Time, End Time, Status]
                    const colWidths = [30, 55, 35, 35, 40, 40, 25];
                    let xPos = 10;
                      // Draw headers with consistent styling
                    // Set up header styling (important to set styles completely before each use)
                    doc.setFillColor(53, 100, 128); // Blue background
                    
                    // Draw header backgrounds first (separate from text to ensure proper layering)
                    let headerX = xPos;
                    for (let i = 0; i < headers.length; i++) {
                        // Draw the filled rectangle (background)
                        doc.rect(headerX, startY, colWidths[i], rowHeight, 'F');
                        headerX += colWidths[i];
                    }
                    
                    // Now draw all header text on top of backgrounds
                    doc.setTextColor(255, 255, 255); // White text color
                    doc.setFontSize(8);
                    doc.setFont(undefined, 'bold');
                    
                    let currentX = xPos;
                    for (let i = 0; i < headers.length; i++) {
                        // Draw the header text after all rectangles are drawn
                        doc.text(headers[i], currentX + 2, startY + 6);
                        currentX += colWidths[i];
                    }
                    
                    // Draw rows
                    // Reset text styling to default for row data
                    doc.setTextColor(0, 0, 0);
                    doc.setFont(undefined, 'normal');
                    doc.setFontSize(8); // Ensure consistent font size for data
                    let currentY = startY + rowHeight;
                    const pageHeight = doc.internal.pageSize.height;
                    const marginBottom = 20;
                    
                    // Draw all rows, adding new pages as needed                // Add footer function
                    function addFooter() {
                        doc.setFontSize(8);
                        doc.setTextColor(100, 100, 100);
                        doc.setFont(undefined, 'normal');
                        doc.text('Generated by: <?php echo htmlspecialchars($username); ?> | ' + new Date().toLocaleString(), 15, doc.internal.pageSize.height - 10);
                        doc.text('Page ' + doc.internal.getCurrentPageInfo().pageNumber + ' of ' + doc.internal.getNumberOfPages(), doc.internal.pageSize.width - 15, doc.internal.pageSize.height - 10, {align: 'right'});
                    }
                    
                    // Add footer to first page
                    addFooter();
                    
                    for (let i = 0; i < rows.length; i++) {
                        // Check if we need to create a new page
                        if (currentY + rowHeight > pageHeight - marginBottom) {
                            doc.addPage();
                            // Reset the Y position to the top of the new page with some margin
                            currentY = 20;                      // Redraw the headers on new page with consistent styling
                            // Set fill color for header backgrounds
                            doc.setFillColor(53, 100, 128);
                            
                            // First pass: draw all header backgrounds
                            let headerBackgroundX = xPos;
                            for (let j = 0; j < headers.length; j++) {
                                // Draw the filled rectangle (background)
                                doc.rect(headerBackgroundX, currentY, colWidths[j], rowHeight, 'F');
                                headerBackgroundX += colWidths[j];
                            }
                            
                            // Second pass: draw all header text on top of backgrounds
                            doc.setTextColor(255, 255, 255); // Ensure white text color
                            doc.setFontSize(8);
                            doc.setFont(undefined, 'bold');
                            
                            let headerX = xPos;
                            for (let j = 0; j < headers.length; j++) {
                                // Draw the text after all rectangles are drawn
                                doc.text(headers[j], headerX + 2, currentY + 6);
                                headerX += colWidths[j];
                            }
                            
                            // Move to the next row position after drawing headers
                            currentY += rowHeight;
                              // Reset text color for data rows
                            doc.setTextColor(0, 0, 0);
                            doc.setFont(undefined, 'normal');
                            
                            // Add footer to the new page
                            addFooter();
                        }
                        
                        currentX = xPos;
                        // Alternate row colors
                        if (i % 2 === 1) {
                            doc.setFillColor(245, 247, 250);
                            for (let j = 0; j < headers.length; j++) {
                                doc.rect(currentX, currentY, colWidths[j], rowHeight, 'F');
                                currentX += colWidths[j];
                            }
                        }
                          // Ensure consistent styling for each row's text
                        doc.setTextColor(0, 0, 0);
                        doc.setFont(undefined, 'normal');
                        doc.setFontSize(8);
                        
                        currentX = xPos;
                        for (let j = 0; j < rows[i].length; j++) {
                            // Allow more text to be displayed by increasing substring limit
                            doc.text(String(rows[i][j]).substring(0, 30), currentX + 2, currentY + 6);
                            currentX += colWidths[j];
                        }
                        currentY += rowHeight;
                    }                // Add summary information at the end of the table
                    currentY += 10; 
                    doc.setFont(undefined, 'bold');
                    doc.text('Total Records: ' + rows.length, xPos, currentY);
                    doc.setFont(undefined, 'normal');
                    
                    // Add footer
                    doc.setFontSize(8);
                    doc.setTextColor(100, 100, 100);
                    doc.setFont(undefined, 'normal');
                    doc.text('Generated by: <?php echo htmlspecialchars($username); ?> | ' + new Date().toLocaleString(), 15, doc.internal.pageSize.height - 10);
                    doc.text('Page ' + doc.internal.getCurrentPageInfo().pageNumber + ' of ' + doc.internal.getNumberOfPages(), doc.internal.pageSize.width - 15, doc.internal.pageSize.height - 10, {align: 'right'});
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
                
            }
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