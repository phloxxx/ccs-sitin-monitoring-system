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
                            
                            <div class="flex gap-2 ml-auto mt-2 md:mt-0">
                                <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors flex items-center shadow-sm">
                                    <i class="fas fa-search mr-2"></i> Generate
                                </button>
                                <button type="button" id="export-csv" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition-colors flex items-center shadow-sm">
                                    <i class="fas fa-file-csv mr-2"></i> CSV
                                </button>
                                <button type="button" id="export-pdf" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 transition-colors flex items-center shadow-sm">
                                    <i class="fas fa-file-pdf mr-2"></i> PDF
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
                    // Display report data in table
                    displayReportTable(data.sessions);
                } else {
                    document.getElementById('report-results').innerHTML = `
                        <div class="bg-red-50 p-4 rounded-md">
                            <p class="text-red-700">${data.message || 'An error occurred while generating the report.'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('report-results').innerHTML = `
                    <div class="bg-red-50 p-4 rounded-md">
                        <p class="text-red-700">Network error. Please try again.</p>
                    </div>
                `;
            });
    });

    // Function to display report data in table
    function displayReportTable(sessions) {
        if (sessions.length === 0) {
            document.getElementById('report-results').innerHTML = `
                <div class="bg-blue-50 p-4 rounded-md">
                    <p class="text-blue-700">No sit-in sessions found for the selected date range.</p>
                </div>
            `;
            return;
        }
        
        let html = `
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">`;
                
        sessions.forEach(session => {
            const statusClass = session.STATUS === 'ACTIVE' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
            
            html += `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${session.IDNO}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${session.LASTNAME}, ${session.FIRSTNAME} ${session.MIDNAME || ''}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${session.LAB_NAME}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${session.PURPOSE}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formatDateTime(session.SESSION_START)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${session.SESSION_END ? formatDateTime(session.SESSION_END) : 'N/A'}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                        ${session.STATUS}
                    </span>
                </td>
            </tr>`;
        });
                
        html += `
                </tbody>
            </table>
        </div>`;
        
        document.getElementById('report-results').innerHTML = html;
    }

    // Format date and time for display
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

    // Export to CSV
    document.getElementById('export-csv').addEventListener('click', function() {
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        const labId = document.getElementById('report-lab').value;
        const purpose = document.getElementById('report-purpose').value;
        
        window.location.href = `ajax/export_sitin_csv.php?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&lab_id=${encodeURIComponent(labId)}&purpose=${encodeURIComponent(purpose)}`;
    });

    // Export to PDF
    document.getElementById('export-pdf').addEventListener('click', function() {
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        const labId = document.getElementById('report-lab').value;
        const purpose = document.getElementById('report-purpose').value;
        
        window.location.href = `ajax/export_sitin_pdf.php?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&lab_id=${encodeURIComponent(labId)}&purpose=${encodeURIComponent(purpose)}`;
    });

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
                            
                            html += `
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${formatDate(session.SESSION_START)}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        ${session.LASTNAME}, ${session.FIRSTNAME} ${session.MIDNAME || ''}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        ${session.IDNO}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${session.PURPOSE}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${session.LAB_NAME}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${formatTime(session.SESSION_START)}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    ${timeOut}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                        ${session.STATUS}
                                    </span>
                                </td>
                            </tr>`;
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
    
    // Helper functions for date/time formatting
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
</script>

<?php include('includes/footer.php'); ?>
