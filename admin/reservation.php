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

// Fetch laboratories for dropdown
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

// Fetch pending reservation requests count
$pendingCount = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM RESERVATION WHERE STATUS = 'PENDING'");
    $stmt->execute();
    $result = $stmt->get_result();
    $pendingCount = $result->fetch_assoc()['count'];
    $stmt->close();
} catch (Exception $e) {
    // If error, default count is 0
}

// Get current date for calendar
$currentDate = date('Y-m-d');

$pageTitle = "Reservation Management";
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
                    <a href="sitin.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3"></i>
                        <span>Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center px-4 py-3 text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
                    <h1 class="ml-4 text-xl font-semibold text-secondary">Reservation Management</h1>
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
                    <a href="sitin.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-desktop mr-3"></i>
                        Sit-in
                    </a>
                    <a href="reservation.php" class="block px-4 py-2 text-white rounded-lg bg-primary bg-opacity-30 hover:bg-primary hover:bg-opacity-20">
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
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Reservation Management</h2>
            <p class="text-gray-600 mb-6">Manage computer reservations, requests, and view activity logs</p>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Computer Control Panel -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white p-3 flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-desktop mr-2"></i> Computer Status
                        </h3>
                        <a href="manage_pcs.php" class="bg-white text-blue-600 px-2 py-1 rounded-md text-xs flex items-center hover:bg-gray-100 transition-colors shadow-sm">
                            <i class="fas fa-cog mr-1"></i> Configure
                        </a>
                    </div>
                    
                    <div class="p-3">
                        <div class="mb-3">
                            <label for="lab-select" class="block text-xs font-medium text-gray-700 mb-1">Select Laboratory</label>
                            <div class="relative">
                                <select id="lab-select" class="w-full px-3 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary appearance-none pl-10 text-sm">
                                    <?php foreach ($laboratories as $lab): ?>
                                        <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?> (<?php echo $lab['CAPACITY']; ?> PCs)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-building text-gray-400"></i>
                                </div>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3 flex justify-end">
                            <button id="update-lab" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-md flex items-center transition-colors shadow-sm text-xs">
                                <i class="fas fa-sync-alt mr-2"></i> Update View
                            </button>
                        </div>
                        
                        <div>
                            <div class="mb-2 flex items-center bg-blue-50 p-2 rounded-md border-l-4 border-blue-500">
                                <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>
                                <span class="font-medium text-gray-700 text-sm" id="selected-lab-name">Laboratory 524</span>
                                <span class="ml-auto text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-md">
                                    <i class="fas fa-info-circle mr-1"></i> Click PC for details
                                </span>
                            </div>
                            
                            <div class="h-56 overflow-y-auto pr-1 scrollbar-thin">
                                <div class="grid grid-cols-4 sm:grid-cols-5 gap-2" id="pc-grid">
                                    <!-- PC Grid will be populated dynamically -->
                                    <?php 
                                    // Sample grid for demonstration
                                    for ($i = 1; $i <= 30; $i++): 
                                        $pcId = sprintf("PC-%02d", $i);
                                        
                                        // Randomly assign statuses for demonstration
                                        $statuses = ['available', 'reserved', 'maintenance'];
                                        $status = $statuses[array_rand($statuses)];
                                        
                                        $statusClass = '';
                                        $statusText = '';
                                        $icon = 'fa-desktop';
                                        
                                        switch($status) {
                                            case 'available':
                                                $statusClass = 'bg-green-100 border-green-200 text-green-600';
                                                $statusText = 'Available';
                                                break;
                                            case 'reserved':
                                                $statusClass = 'bg-blue-100 border-blue-200 text-blue-600';
                                                $statusText = 'Reserved';
                                                $icon = 'fa-user-clock';
                                                break;
                                            case 'maintenance':
                                                $statusClass = 'bg-yellow-100 border-yellow-200 text-yellow-600';
                                                $statusText = 'Maintenance';
                                                $icon = 'fa-tools';
                                                break;
                                        }
                                    ?>
                                    <div class="pc-item <?php echo $statusClass; ?> border rounded-md p-1.5 text-center cursor-pointer hover:shadow-md transition-all transform hover:-translate-y-1">
                                        <div class="mb-0.5">
                                            <i class="fas <?php echo $icon; ?> text-sm"></i>
                                        </div>
                                        <div class="text-xs font-medium truncate"><?php echo $pcId; ?></div>
                                        <div class="text-xxs truncate"><?php echo $statusText; ?></div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Reservation Requests Panel -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-green-600 to-green-500 text-white p-3 flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-clock mr-2"></i> Pending Requests
                        </h3>
                        <div class="bg-white text-green-600 px-2 py-1 rounded-md text-xs flex items-center shadow-sm">
                            <i class="fas fa-bell mr-1"></i> Pending: <?php echo $pendingCount; ?>
                        </div>
                    </div>
                    
                    <div class="p-3 h-64 overflow-y-auto">
                        <?php if ($pendingCount > 0): ?>
                            <!-- Reservation requests will be displayed here -->
                            <div class="w-full space-y-3">
                                <!-- Sample requests for demonstration -->
                                <div class="border border-gray-200 rounded-md p-2 hover:bg-gray-50 transition-colors shadow-sm">
                                    <div class="flex justify-between items-center mb-1.5 pb-1.5 border-b border-gray-100">
                                        <div>
                                            <div class="font-medium text-gray-800 text-sm">John Doe</div>
                                            <div class="text-xs text-gray-500">Student ID: 21100123</div>
                                        </div>
                                        <div class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-md">Today, 10:30 AM</div>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5 mb-1.5">
                                        <div class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-md flex items-center">
                                            <i class="fas fa-desktop mr-1"></i> PC-05
                                        </div>
                                        <div class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-md flex items-center">
                                            <i class="fas fa-clock mr-1"></i> 2 hours
                                        </div>
                                        <div class="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded-md flex items-center">
                                            <i class="fas fa-building mr-1"></i> Lab 524
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-600 mb-1.5 bg-gray-50 p-1.5 rounded-md border-l-2 border-gray-300 line-clamp-2 overflow-hidden">
                                        <i class="fas fa-quote-left text-gray-400 mr-1 text-xs"></i>
                                        Purpose: Java Programming for OOP class
                                        <i class="fas fa-quote-right text-gray-400 ml-1 text-xs"></i>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button class="bg-green-500 hover:bg-green-600 text-white px-2 py-0.5 rounded text-xs flex items-center transition-colors shadow-sm">
                                            <i class="fas fa-check mr-1"></i> Approve
                                        </button>
                                        <button class="bg-red-500 hover:bg-red-600 text-white px-2 py-0.5 rounded text-xs flex items-center transition-colors shadow-sm">
                                            <i class="fas fa-times mr-1"></i> Reject
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="border border-gray-200 rounded-md p-2 hover:bg-gray-50 transition-colors shadow-sm">
                                    <div class="flex justify-between items-center mb-1.5 pb-1.5 border-b border-gray-100">
                                        <div>
                                            <div class="font-medium text-gray-800 text-sm">Jane Smith</div>
                                            <div class="text-xs text-gray-500">Student ID: 21100456</div>
                                        </div>
                                        <div class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-md">Today, 11:15 AM</div>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5 mb-1.5">
                                        <div class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-md flex items-center">
                                            <i class="fas fa-desktop mr-1"></i> PC-12
                                        </div>
                                        <div class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-md flex items-center">
                                            <i class="fas fa-clock mr-1"></i> 3 hours
                                        </div>
                                        <div class="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded-md flex items-center">
                                            <i class="fas fa-building mr-1"></i> Lab 524
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-600 mb-1.5 bg-gray-50 p-1.5 rounded-md border-l-2 border-gray-300 line-clamp-2 overflow-hidden">
                                        <i class="fas fa-quote-left text-gray-400 mr-1 text-xs"></i>
                                        Purpose: C# Programming for final project
                                        <i class="fas fa-quote-right text-gray-400 ml-1 text-xs"></i>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button class="bg-green-500 hover:bg-green-600 text-white px-2 py-0.5 rounded text-xs flex items-center transition-colors shadow-sm">
                                            <i class="fas fa-check mr-1"></i> Approve
                                        </button>
                                        <button class="bg-red-500 hover:bg-red-600 text-white px-2 py-0.5 rounded text-xs flex items-center transition-colors shadow-sm">
                                            <i class="fas fa-times mr-1"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <div class="bg-blue-100 inline-block p-4 rounded-full mb-3">
                                    <i class="fas fa-clipboard-check text-3xl text-blue-500"></i>
                                </div>
                                <h4 class="text-base font-medium text-secondary mb-1">All Caught Up!</h4>
                                <p class="text-xs text-gray-500 max-w-sm mx-auto">No pending reservation requests at the moment. New requests will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Activity Logs Panel -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden flex flex-col">
                    <div class="bg-gradient-to-r from-purple-600 to-purple-500 text-white p-3 flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-history mr-2"></i> Reservation History
                        </h3>
                        <button class="bg-white text-purple-600 px-2 py-1 rounded-md text-xs flex items-center hover:bg-gray-100 transition-colors shadow-sm">
                            <i class="fas fa-filter mr-1"></i> Filter
                        </button>
                    </div>
                    
                    <div class="p-3 overflow-y-auto flex-grow">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-xs font-medium text-gray-700 flex items-center">
                                <i class="fas fa-calendar-alt mr-1 text-purple-500"></i> Recent Activity
                            </h4>
                            <div class="text-xs text-white bg-purple-500 px-2 py-0.5 rounded-md">Today</div>
                        </div>
                        
                        <div class="space-y-3 relative">
                            <!-- Timeline Line -->
                            <div class="absolute left-3 top-2 bottom-0 w-0.5 bg-gray-200"></div>
                            
                            <!-- Timeline Items -->
                            <div class="flex items-start relative">
                                <div class="bg-green-500 rounded-full h-6 w-6 flex items-center justify-center text-white z-10 shadow-sm">
                                    <i class="fas fa-check text-xs"></i>
                                </div>
                                <div class="ml-4 bg-white rounded-md border border-gray-200 p-2 flex-1 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-center mb-0.5">
                                        <span class="font-medium text-green-700 text-xs">Reservation Approved</span>
                                        <span class="text-xxs text-gray-500">10:45 AM</span>
                                    </div>
                                    <p class="text-xxs text-gray-600 line-clamp-2">Approved Mark Johnson's reservation for PC-08 in Lab 524</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start relative">
                                <div class="bg-red-500 rounded-full h-6 w-6 flex items-center justify-center text-white z-10 shadow-sm">
                                    <i class="fas fa-times text-xs"></i>
                                </div>
                                <div class="ml-4 bg-white rounded-md border border-gray-200 p-2 flex-1 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-center mb-0.5">
                                        <span class="font-medium text-red-700 text-xs">Reservation Rejected</span>
                                        <span class="text-xxs text-gray-500">10:30 AM</span>
                                    </div>
                                    <p class="text-xxs text-gray-600 line-clamp-2">Rejected Sarah Lee's reservation for PC-03 in Lab 522 (Scheduling conflict)</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start relative">
                                <div class="bg-blue-500 rounded-full h-6 w-6 flex items-center justify-center text-white z-10 shadow-sm">
                                    <i class="fas fa-plus text-xs"></i>
                                </div>
                                <div class="ml-4 bg-white rounded-md border border-gray-200 p-2 flex-1 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-center mb-0.5">
                                        <span class="font-medium text-blue-700 text-xs">New Reservation</span>
                                        <span class="text-xxs text-gray-500">9:15 AM</span>
                                    </div>
                                    <p class="text-xxs text-gray-600 line-clamp-2">John Doe requested PC-05 in Lab 524 for 2 hours</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start relative">
                                <div class="bg-yellow-500 rounded-full h-6 w-6 flex items-center justify-center text-white z-10 shadow-sm">
                                    <i class="fas fa-tools text-xs"></i>
                                </div>
                                <div class="ml-4 bg-white rounded-md border border-gray-200 p-2 flex-1 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-center mb-0.5">
                                        <span class="font-medium text-yellow-700 text-xs">Maintenance Scheduled</span>
                                        <span class="text-xxs text-gray-500">8:30 AM</span>
                                    </div>
                                    <p class="text-xxs text-gray-600 line-clamp-2">PC-15 in Lab 522 scheduled for maintenance (Software update)</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="reservation_logs.php" class="text-white bg-purple-500 hover:bg-purple-600 transition-colors px-3 py-1 rounded text-xs font-medium inline-flex items-center shadow-sm">
                                <i class="fas fa-list mr-1.5"></i> View Complete History
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekly Schedule Overview -->
            <div class="mt-6 bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white p-3 flex justify-between items-center">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-calendar-week mr-2"></i> Weekly Lab Schedule Overview
                    </h3>
                    <div class="flex space-x-2">
                        <button class="bg-white text-blue-600 px-2 py-0.5 rounded-md text-xs flex items-center shadow-sm hover:bg-gray-100 transition-colors">
                            <i class="fas fa-print mr-1"></i> Print
                        </button>
                        <button class="bg-white text-blue-600 px-2 py-0.5 rounded-md text-xs flex items-center shadow-sm hover:bg-gray-100 transition-colors">
                            <i class="fas fa-download mr-1"></i> Export
                        </button>
                    </div>
                </div>
                
                <div class="p-3 overflow-x-auto">
                    <div class="flex mb-2 items-center">
                        <button id="prevWeek" class="text-gray-600 hover:text-blue-600 transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <h4 class="text-base font-medium text-center flex-1" id="weekDisplay"></h4>
                        <button id="nextWeek" class="text-gray-600 hover:text-blue-600 transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    
                    <div class="min-w-max">
                        <!-- Calendar Header - Days of the Week -->
                        <div class="grid grid-cols-8 gap-1.5">
                            <div class="p-1"></div> <!-- Empty cell for time labels -->
                            <div id="calendarHeader" class="contents">
                                <!-- Days will be filled by JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Calendar Body - Time Slots -->
                        <div id="calendarBody">
                            <!-- Time slots will be filled by JavaScript -->
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

<!-- PC Details Modal -->
<div id="pc-details-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-blue-700" id="modal-pc-id">PC-01 Details</h3>
            <button id="close-modal" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mb-6" id="modal-content">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-blue-100 flex items-center justify-center rounded-full mr-4">
                    <i class="fas fa-desktop text-blue-600 text-xl"></i>
                </div>
                <div>
                    <div class="font-medium text-gray-700" id="modal-status">Available</div>
                    <div class="text-sm text-gray-500" id="modal-lab">Laboratory 524</div>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-gray-500">PC Name</div>
                        <div class="font-medium" id="modal-name">PC-01</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Status</div>
                        <div class="font-medium text-green-600" id="modal-status-detail">Available</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Last Used</div>
                        <div class="font-medium" id="modal-last-used">Today, 9:30 AM</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Reservations Today</div>
                        <div class="font-medium" id="modal-reservations">2</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors shadow-sm">
                View History
            </button>
            <button class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors shadow-sm">
                Manage PC
            </button>
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
    
    document.getElementById('confirmation-dialog').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
    
    // PC Grid Item Click Event
    const pcItems = document.querySelectorAll('.pc-item');
    const pcDetailsModal = document.getElementById('pc-details-modal');
    const closeModal = document.getElementById('close-modal');
    
    pcItems.forEach(item => {
        item.addEventListener('click', () => {
            // Update modal content with PC details
            const pcId = item.querySelector('.text-sm.font-medium').textContent;
            document.getElementById('modal-pc-id').textContent = pcId + ' Details';
            document.getElementById('modal-name').textContent = pcId;
            
            // Show the modal
            pcDetailsModal.classList.remove('hidden');
        });
    });
    
    closeModal.addEventListener('click', () => {
        pcDetailsModal.classList.add('hidden');
    });
    
    pcDetailsModal.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
    
    // Update lab button
    document.getElementById('update-lab').addEventListener('click', function() {
        const labSelect = document.getElementById('lab-select');
        const selectedLabName = labSelect.options[labSelect.selectedIndex].text;
        document.getElementById('selected-lab-name').textContent = selectedLabName;
        
        // In a real application, you would fetch PC data for the selected lab
        alert('Updated to ' + selectedLabName);
    });
    
    // Calendar functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Define variables for calendar
        let currentDate = new Date();
        let weekStartDate = getWeekStartDate(currentDate);
        
        // Initialize calendar
        updateCalendarHeader();
        updateCalendarBody();
        
        // Event listeners for week navigation
        document.getElementById('prevWeek').addEventListener('click', function() {
            weekStartDate.setDate(weekStartDate.getDate() - 7);
            updateCalendarHeader();
            updateCalendarBody();
        });
        
        document.getElementById('nextWeek').addEventListener('click', function() {
            weekStartDate.setDate(weekStartDate.getDate() + 7);
            updateCalendarHeader();
            updateCalendarBody();
        });
        
        // Function to get the start date of the week (Monday)
        function getWeekStartDate(date) {
            const dayOfWeek = date.getDay(); // 0 = Sunday, 1 = Monday, etc.
            const diff = date.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1); // Adjust to get Monday
            const monday = new Date(date);
            monday.setDate(diff);
            return monday;
        }
        
        // Function to format date as Month DD
        function formatDateHeader(date) {
            const options = { month: 'short', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }
        
        // Function to update the calendar header with days of the week
        function updateCalendarHeader() {
            const calendarHeader = document.getElementById('calendarHeader');
            const weekDisplay = document.getElementById('weekDisplay');
            calendarHeader.innerHTML = '';
            
            // Update week display (e.g., "October 23 - 29, 2023")
            const weekEndDate = new Date(weekStartDate);
            weekEndDate.setDate(weekStartDate.getDate() + 6);
            
            const startMonth = weekStartDate.toLocaleDateString('en-US', { month: 'long' });
            const endMonth = weekEndDate.toLocaleDateString('en-US', { month: 'long' });
            const startDay = weekStartDate.getDate();
            const endDay = weekEndDate.getDate();
            const year = weekStartDate.getFullYear();
            
            if (startMonth === endMonth) {
                weekDisplay.textContent = `${startMonth} ${startDay} - ${endDay}, ${year}`;
            } else {
                weekDisplay.textContent = `${startMonth} ${startDay} - ${endMonth} ${endDay}, ${year}`;
            }
            
            // Create day headers
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            
            days.forEach((day, index) => {
                const date = new Date(weekStartDate);
                date.setDate(date.getDate() + index);
                
                const dayElement = document.createElement('div');
                dayElement.className = 'bg-blue-50 p-1.5 text-center rounded-md';
                dayElement.innerHTML = `
                    <div class="font-medium text-blue-700 text-xs">${day}</div>
                    <div class="text-xxs text-gray-500">${formatDateHeader(date)}</div>
                `;
                calendarHeader.appendChild(dayElement);
            });
        }
        
        // Function to update the calendar body with time slots
        function updateCalendarBody() {
            const calendarBody = document.getElementById('calendarBody');
            calendarBody.innerHTML = '<div class="flex justify-center my-8"><i class="fas fa-spinner fa-spin text-2xl text-blue-500"></i></div>';
            
            // Format dates for the API call
            const endDate = new Date(weekStartDate);
            endDate.setDate(endDate.getDate() + 6);
            
            const startDateStr = formatDateForAPI(weekStartDate);
            const endDateStr = formatDateForAPI(endDate);
            
            // Fetch reservations from the server for this date range
            fetch(`ajax/get_reservations.php?start_date=${startDateStr}&end_date=${endDateStr}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderCalendarWithReservations(data.reservations);
                    } else {
                        calendarBody.innerHTML = `<div class="text-center py-8 text-red-500">
                                                 <i class="fas fa-exclamation-circle mr-2"></i>
                                                 ${data.message || 'Failed to load reservations'}</div>`;
                    }
                })
                .catch(error => {
                    console.error("Error fetching reservations:", error);
                    calendarBody.innerHTML = `<div class="text-center py-8 text-red-500">
                                             <i class="fas fa-exclamation-circle mr-2"></i>
                                             Network error while loading reservations</div>`;
                });
        }
        
        // Format date as YYYY-MM-DD for API calls
        function formatDateForAPI(date) {
            return date.toISOString().split('T')[0];
        }
        
        // Get time from datetime string (HH:MM format)
        function getTimeFromDateTime(dateTimeStr) {
            const date = new Date(dateTimeStr);
            return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        }
        
        // Get day index (0-6) from date string
        function getDayIndex(dateStr) {
            const date = new Date(dateStr);
            // Convert from 0 (Sunday) - 6 (Saturday) to 0 (Monday) - 6 (Sunday)
            let day = date.getDay() - 1;
            if (day < 0) day = 6;
            return day;
        }
        
        // Find time slot index from time string
        function getTimeSlotIndex(timeStr) {
            const timeSlots = ['8:00 AM', '9:00 AM', '10:00 AM', '11:00 AM', '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM'];
            return timeSlots.findIndex(slot => {
                // Simple string comparison should work for these standard formats
                return timeStr.includes(slot);
            });
        }
        
        // Render calendar with actual reservation data
        function renderCalendarWithReservations(reservations) {
            const calendarBody = document.getElementById('calendarBody');
            calendarBody.innerHTML = '';
            
            const timeSlots = ['8:00 AM', '9:00 AM', '10:00 AM', '11:00 AM', '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM'];
            
            // Create a data structure to hold reservations by time slot and day
            const reservationsBySlot = Array(timeSlots.length).fill().map(() => Array(7).fill([]));
            
            // Organize reservations into our 2D array
            reservations.forEach(reservation => {
                const startTime = getTimeFromDateTime(reservation.START_TIME);
                const dayIndex = getDayIndex(reservation.DATE);
                const timeIndex = getTimeSlotIndex(startTime);
                
                if (timeIndex !== -1 && dayIndex >= 0 && dayIndex <= 6) {
                    reservationsBySlot[timeIndex][dayIndex].push(reservation);
                }
            });
            
            // Build the grid rows for each time slot
            timeSlots.forEach((time, timeIndex) => {
                const row = document.createElement('div');
                row.className = `grid grid-cols-8 gap-1.5 ${timeIndex % 2 === 0 ? 'bg-gray-50' : ''}`;
                
                // Time column
                const timeColumn = document.createElement('div');
                timeColumn.className = 'p-1 text-right text-xxs text-gray-500';
                timeColumn.textContent = time;
                row.appendChild(timeColumn);
                
                // Day columns
                for (let dayIndex = 0; dayIndex < 7; dayIndex++) {
                    const cellDate = new Date(weekStartDate);
                    cellDate.setDate(cellDate.getDate() + dayIndex);
                    
                    const cell = document.createElement('div');
                    cell.className = 'border border-gray-200 p-1 min-h-[40px] relative';
                    cell.dataset.date = cellDate.toISOString().split('T')[0];
                    cell.dataset.time = time;
                    
                    // Check if there are any reservations for this time slot and day
                    const cellReservations = reservationsBySlot[timeIndex][dayIndex];
                    
                    if (cellReservations && cellReservations.length > 0) {
                        // Display each reservation in this cell
                        cellReservations.forEach(res => {
                            // Determine the color based on status or user preferences or type
                            let colorClass;
                            switch (res.STATUS) {
                                case 'APPROVED':
                                    colorClass = 'bg-green-100 text-green-800 border-green-200';
                                    break;
                                case 'PENDING':
                                    colorClass = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                    break;
                                case 'REJECTED':
                                    colorClass = 'bg-red-50 text-red-700 border-red-200';
                                    break;
                                default:
                                    colorClass = 'bg-blue-50 text-blue-700 border-blue-200';
                            }
                            
                            const reservation = document.createElement('div');
                            reservation.className = `${colorClass} p-0.5 text-xxs rounded-sm border shadow-sm hover:shadow-md transition-shadow mb-0.5 truncate`;
                            reservation.innerHTML = `
                                <div class="font-medium truncate">${res.STUDENT_NAME}</div>
                                <div class="flex justify-between text-xxs">
                                    <span class="truncate">${res.LAB_NAME}</span>
                                    <span class="truncate">${res.PC_NAME || 'N/A'}</span>
                                </div>
                            `;

                            // Add a data attribute with full reservation details for potential modal/tooltip
                            reservation.dataset.reservationId = res.RESERVATION_ID;
                            
                            // Optionally add click event to show more details
                            reservation.addEventListener('click', () => {
                                // Could show a modal with all reservation details
                                console.log('Reservation details:', res);
                                // Or implement a showReservationDetails(res) function
                            });
                            
                            cell.appendChild(reservation);
                        });
                    }
                    
                    row.appendChild(cell);
                }
                
                calendarBody.appendChild(row);
            });
        }
    });
</script>

<style>
    /* Add extra small text size */
    .text-xxs {
        font-size: 0.65rem;
    }
    
    /* Custom scrollbar styling */
    .scrollbar-thin::-webkit-scrollbar {
        width: 4px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 4px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }
    
    /* Make all panels in grid row the same height */
    .grid {
        grid-auto-rows: 1fr;
    }
    
    /* Ensure reservation history panel matches height of other panels */
    .grid > div {
        display: flex;
        flex-direction: column;
    }
</style>

<?php include('includes/footer.php'); ?>
