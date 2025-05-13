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
                <!-- Added Logos -->                <div class="flex flex-col items-center pt-5 pb-2">
                    <div class="relative w-16 h-16 mb-1">
                        <!-- UC Logo -->
                        <div class="absolute inset-0 rounded-full bg-white shadow-md overflow-hidden flex items-center justify-center">
                            <img src="../student/images/uc_logo.png" alt="University of Cebu Logo" class="h-13 w-13 object-contain">
                        </div>
                        <!-- CCS Logo (smaller and positioned at the bottom right) -->
                        <div class="absolute bottom-0 right-0 w-9 h-9 rounded-full bg-white shadow-md border-2 border-white overflow-hidden flex items-center justify-center">
                            <img src="../student/images/ccs_logo.png" alt="CCS Logo" class="h-7 w-7 object-contain">
                        </div>
                    </div>
                    <h1 class="text-white font-bold text-sm">CCS Sit-In</h1>
                    <p class="text-gray-300 text-xs">Monitoring System</p>
                </div>                
                <div class="flex flex-col flex-grow px-4 py-3 overflow-hidden">
                    <nav class="flex-1 space-y-1">
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
                    <a href="sitin.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3 text-lg"></i>
                        <span class="font-medium">Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                        <span class="font-medium">Reservation</span>
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
                </nav>                  
                <div class="mt-1 border-t border-white-700 pt-2">
                    <a href="#" onclick="confirmLogout(event)" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
                        Reservation Management
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
                    <a href="sitin.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3 text-lg"></i>
                        <span class="font-medium">Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                        <span class="font-medium">Reservation</span>
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
        <main class="flex-1 overflow-y-auto p-8 bg-gray-50">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Computer Control Panel -->                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="bg-white border-b border-gray-100 px-5 py-4 flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center text-gray-800">
                            <i class="fas fa-desktop text-blue-500 mr-3"></i> Computer Status
                        </h3>
                        <a href="manage_pcs.php" class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg text-sm flex items-center hover:bg-blue-100 transition-colors">
                            <i class="fas fa-cog mr-2"></i> Configure
                        </a>
                    </div>
                    
                    <div class="p-5">                        <div class="mb-5">
                            <label for="lab-select" class="block text-sm font-medium text-gray-700 mb-2 flex items-center">
                                <i class="fas fa-building text-blue-500 mr-2"></i>
                                Select Laboratory
                            </label>
                            <div class="relative">
                                <select id="lab-select" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none pl-10 text-sm transition-shadow duration-150">
                                    <?php foreach ($laboratories as $lab): ?>
                                        <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?> (<?php echo $lab['CAPACITY']; ?> PCs)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-building text-blue-500"></i>
                                </div>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <i class="fas fa-chevron-down text-blue-500"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div>                            <div class="h-64 overflow-y-auto pr-2 custom-scrollbar">
                                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3" id="pc-grid">
                                    <!-- PC Grid will be populated dynamically with more spacing -->
                                    <?php 
                                    // Sample grid for demonstration
                                    for ($i = 1; $i <= 20; $i++): 
                                        $pcId = sprintf("PC-%02d", $i);
                                        
                                        // Randomly assign statuses for demonstration
                                        $statuses = ['available', 'reserved', 'maintenance'];
                                        $status = $statuses[array_rand($statuses)];
                                        
                                        $statusClass = '';
                                        $statusText = '';
                                        $icon = 'fa-desktop';
                                        $bgColor = '';
                                        $ringColor = '';
                                        
                                        switch($status) {
                                            case 'available':
                                                $statusClass = 'text-green-600';
                                                $statusText = 'Available';
                                                $bgColor = 'bg-green-50';
                                                $ringColor = 'ring-green-200';
                                                break;
                                            case 'reserved':
                                                $statusClass = 'text-blue-600';
                                                $statusText = 'Reserved';
                                                $icon = 'fa-user-clock';
                                                $bgColor = 'bg-blue-50';
                                                $ringColor = 'ring-blue-200';
                                                break;
                                            case 'maintenance':
                                                $statusClass = 'text-yellow-600';
                                                $statusText = 'Maintenance';
                                                $icon = 'fa-tools';
                                                $bgColor = 'bg-yellow-50';
                                                $ringColor = 'ring-yellow-200';
                                                break;
                                        }
                                    ?>
                                    <div class="pc-item <?php echo $bgColor; ?> <?php echo $statusClass; ?> ring-1 <?php echo $ringColor; ?> rounded-xl p-3 text-center cursor-pointer hover:shadow-md transition-all transform hover:-translate-y-1 hover:ring-2">
                                        <div class="w-8 h-8 mx-auto mb-1 flex items-center justify-center">
                                            <i class="fas <?php echo $icon; ?> text-lg"></i>
                                        </div>
                                        <div class="text-sm font-medium"><?php echo $pcId; ?></div>
                                        <div class="text-xs mt-0.5 font-medium"><?php echo $statusText; ?></div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Reservation Requests Panel -->                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <div class="bg-white border-b border-gray-100 px-5 py-4 flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center text-gray-800">
                            <i class="fas fa-clock text-green-500 mr-3"></i> Pending Requests
                        </h3>
                        <div class="bg-green-50 text-green-600 px-3 py-1.5 rounded-lg text-sm flex items-center">
                            <i class="fas fa-bell mr-2"></i> Pending: <?php echo $pendingCount; ?>
                        </div>
                    </div>
                    
                    <div class="p-5 h-[500px] overflow-y-auto">
                        <?php if ($pendingCount > 0): ?>
                            <!-- Reservation requests will be displayed here -->
                            <div class="w-full space-y-4">                                <!-- Sample requests for demonstration -->
                                <div class="border border-gray-100 rounded-xl p-5 hover:bg-gray-50 transition-colors shadow-sm">
                                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-blue-500 mr-3">
                                                <i class="fas fa-user-graduate"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-800">John Doe</div>
                                                <div class="text-sm text-gray-500 mt-0.5">Student ID: 21100123</div>
                                            </div>
                                        </div>
                                        <div class="text-sm bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg flex items-center">
                                            <i class="far fa-clock mr-2"></i>
                                            Today, 10:30 AM
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <div class="text-sm bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg flex items-center">
                                            <i class="fas fa-desktop mr-2"></i> PC-05
                                        </div>
                                        <div class="text-sm bg-green-50 text-green-600 px-3 py-1.5 rounded-lg flex items-center">
                                            <i class="fas fa-clock mr-2"></i> 2 hours
                                        </div>
                                        <div class="text-sm bg-yellow-50 text-yellow-600 px-3 py-1.5 rounded-lg flex items-center">
                                            <i class="fas fa-building mr-2"></i> Lab 524
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-600 mb-5 bg-gray-50 p-4 rounded-lg border-l-2 border-green-300 line-clamp-2 overflow-hidden">
                                        <i class="fas fa-quote-left text-gray-400 mr-2"></i>
                                        Purpose: Java Programming for OOP class
                                        <i class="fas fa-quote-right text-gray-400 ml-2"></i>
                                    </div>
                                    <div class="flex space-x-3">
                                        <button class="bg-green-50 hover:bg-green-100 text-green-600 px-4 py-2.5 rounded-lg flex items-center transition-colors font-medium text-sm">
                                            <i class="fas fa-check mr-2"></i> Approve
                                        </button>
                                        <button class="bg-red-50 hover:bg-red-100 text-red-600 px-4 py-2.5 rounded-lg flex items-center transition-colors font-medium text-sm">
                                            <i class="fas fa-times mr-2"></i> Reject
                                        </button>
                                    </div>
                                </div>
                                  <div class="border border-gray-100 rounded-xl p-5 hover:bg-gray-50 transition-colors shadow-sm">
                                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-purple-50 rounded-full flex items-center justify-center text-purple-500 mr-3">
                                                <i class="fas fa-user-graduate"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-gray-800">Jane Smith</div>
                                                <div class="text-sm text-gray-500 mt-0.5">Student ID: 21100456</div>
                                            </div>
                                        </div>
                                        <div class="text-sm bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg flex items-center">
                                            <i class="far fa-clock mr-2"></i>
                                            Today, 11:15 AM
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 mb-4">
                                        <div class="text-sm bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg flex items-center">
                                            <i class="fas fa-desktop mr-2"></i> PC-12
                                        </div>
                                        <div class="text-sm bg-green-50 text-green-600 px-3 py-1.5 rounded-lg flex items-center">
                                            <i class="fas fa-clock mr-2"></i> 3 hours
                                        </div>
                                        <div class="text-sm bg-yellow-50 text-yellow-600 px-3 py-1.5 rounded-lg flex items-center">
                                            <i class="fas fa-building mr-2"></i> Lab 524
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-600 mb-5 bg-gray-50 p-4 rounded-lg border-l-2 border-purple-300 line-clamp-2 overflow-hidden">
                                        <i class="fas fa-quote-left text-gray-400 mr-2"></i>
                                        Purpose: C# Programming for final project
                                        <i class="fas fa-quote-right text-gray-400 ml-2"></i>
                                    </div>
                                    <div class="flex space-x-3">
                                        <button class="bg-green-50 hover:bg-green-100 text-green-600 px-4 py-2.5 rounded-lg flex items-center transition-colors font-medium text-sm">
                                            <i class="fas fa-check mr-2"></i> Approve
                                        </button>
                                        <button class="bg-red-50 hover:bg-red-100 text-red-600 px-4 py-2.5 rounded-lg flex items-center transition-colors font-medium text-sm">
                                            <i class="fas fa-times mr-2"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-10">
                                <div class="bg-blue-100 inline-block p-6 rounded-full mb-4">
                                    <i class="fas fa-clipboard-check text-4xl text-blue-500"></i>
                                </div>
                                <h4 class="text-xl font-medium text-secondary mb-2">All Caught Up!</h4>
                                <p class="text-base text-gray-500 max-w-sm mx-auto">No pending reservation requests at the moment. New requests will appear here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Activity Logs Panel -->                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100 flex flex-col">
                    <div class="bg-white border-b border-gray-100 px-5 py-4 flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center text-gray-800">
                            <i class="fas fa-history text-purple-500 mr-3"></i> Reservation History
                        </h3>
                        <button class="bg-purple-50 text-purple-600 px-3 py-1.5 rounded-lg text-sm flex items-center hover:bg-purple-100 transition-colors">
                            <i class="fas fa-filter mr-2"></i> Filter
                        </button>
                    </div>
                    
                    <div class="p-5 overflow-y-auto flex-grow">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-medium text-gray-700 flex items-center">
                                <i class="fas fa-calendar-alt mr-2 text-purple-500"></i> Recent Activity
                            </h4>
                            <div class="text-sm text-white bg-purple-500 px-3 py-1 rounded-md">Today</div>
                        </div>
                        
                        <div class="space-y-5 relative">
                            <!-- Timeline Line -->
                            <div class="absolute left-4 top-4 bottom-0 w-0.5 bg-gray-200"></div>
                            
                            <!-- Timeline Items -->
                            <div class="flex items-start relative">
                                <div class="bg-green-500 rounded-full h-8 w-8 flex items-center justify-center text-white z-10 shadow-sm">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="ml-5 bg-white rounded-md border border-gray-200 p-3 flex-1 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="font-medium text-green-700">Reservation Approved</span>
                                        <span class="text-sm text-gray-500">10:45 AM</span>
                                    </div>
                                    <p class="text-sm text-gray-600 line-clamp-2">Approved Mark Johnson's reservation for PC-08 in Lab 524</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start relative">
                                <div class="bg-red-500 rounded-full h-8 w-8 flex items-center justify-center text-white z-10 shadow-sm">
                                    <i class="fas fa-times"></i>
                                </div>
                                <div class="ml-5 bg-white rounded-md border border-gray-200 p-3 flex-1 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="font-medium text-red-700">Reservation Rejected</span>
                                        <span class="text-sm text-gray-500">10:30 AM</span>
                                    </div>
                                    <p class="text-sm text-gray-600 line-clamp-2">Rejected Sarah Lee's reservation for PC-03 in Lab 522 (Scheduling conflict)</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start relative">
                                <div class="bg-blue-500 rounded-full h-8 w-8 flex items-center justify-center text-white z-10 shadow-sm">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <div class="ml-5 bg-white rounded-md border border-gray-200 p-3 flex-1 shadow-sm hover:shadow-md transition-shadow">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="font-medium text-blue-700">New Reservation</span>
                                        <span class="text-sm text-gray-500">9:15 AM</span>
                                    </div>
                                    <p class="text-sm text-gray-600 line-clamp-2">John Doe requested PC-05 in Lab 524 for 2 hours</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 text-center">
                            <a href="reservation_logs.php" class="text-white bg-purple-500 hover:bg-purple-600 transition-colors px-5 py-2 rounded text-sm font-medium inline-flex items-center shadow-sm">
                                <i class="fas fa-list mr-2"></i> View Complete History
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
        <h3 class="text-lg font-medium text-gray-800 mb-4">Confirm Logout</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to log out from the admin panel?</p>
        <div class="flex justify-end space-x-4">
            <button id="cancel-logout" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Cancel
            </button>
            <a href="logout.php" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors">
                Logout
            </a>
        </div>
    </div>
</div>

<!-- PC Details Modal -->
<div id="pc-details-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold text-gray-800 flex items-center" id="modal-pc-id">
                <i class="fas fa-desktop text-blue-500 mr-3"></i>
                PC-01 Details
            </h3>
            <button id="close-modal" class="text-gray-400 hover:text-gray-600 p-1.5 hover:bg-gray-100 rounded-full transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mb-6" id="modal-content">
            <div class="flex items-center mb-5">
                <div class="w-16 h-16 bg-blue-50 flex items-center justify-center rounded-full mr-5 ring-1 ring-blue-100">
                    <i class="fas fa-desktop text-blue-500 text-2xl"></i>
                </div>
                <div>
                    <div class="text-lg font-medium text-gray-800" id="modal-status">Available</div>
                    <div class="text-sm text-gray-500 mt-1 flex items-center" id="modal-lab">
                        <i class="fas fa-building mr-2 text-blue-400"></i>
                        Laboratory 524
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-100 pt-5">
                <div class="grid grid-cols-2 gap-5">
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide">PC Name</div>
                        <div class="font-medium text-base" id="modal-name">PC-01</div>
                    </div>
                    <div class="bg-green-50 p-3 rounded-lg">
                        <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Status</div>
                        <div class="font-medium text-base text-green-600 flex items-center" id="modal-status-detail">
                            <i class="fas fa-circle text-xs mr-2"></i>
                            Available
                        </div>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Last Used</div>
                        <div class="font-medium text-base flex items-center" id="modal-last-used">
                            <i class="far fa-clock text-gray-400 mr-2"></i>
                            Today, 9:30 AM
                        </div>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Reservations Today</div>
                        <div class="font-medium text-base flex items-center" id="modal-reservations">
                            <i class="far fa-calendar-check text-gray-400 mr-2"></i>
                            2
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button class="px-4 py-2.5 bg-gray-50 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors font-medium text-sm flex items-center">
                <i class="fas fa-history mr-2"></i>
                View History
            </button>
            <button class="px-4 py-2.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors font-medium text-sm flex items-center">
                <i class="fas fa-cog mr-2"></i>
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
            const pcId = item.querySelector('div:nth-child(2)').textContent;
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
</script>

<style>
    /* Improved scrollbar styling */
    .scrollbar::-webkit-scrollbar {
        width: 8px;
    }
    
    .scrollbar::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 4px;
    }
    
    .scrollbar::-webkit-scrollbar-thumb:hover {
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
