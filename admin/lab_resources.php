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

// Fetch lab resources from database
$resources = [];
try {
    // First check if the table exists
    $tableExistsQuery = "SHOW TABLES LIKE 'LAB_RESOURCES'";
    $tableResult = $conn->query($tableExistsQuery);
    
    if ($tableResult->num_rows > 0) {
        // Table exists, fetch resources
        $stmt = $conn->prepare("SELECT r.*, l.LAB_NAME 
                               FROM LAB_RESOURCES r 
                               LEFT JOIN LABORATORY l ON r.LAB_ID = l.LAB_ID 
                               ORDER BY r.RESOURCE_NAME");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // For debugging, check if FILE_PATH exists in the result
            if (isset($row['FILE_PATH'])) {
                error_log("Resource {$row['RESOURCE_ID']} has file: {$row['FILE_PATH']}");
            }
            $resources[] = $row;
        }
        $stmt->close();
    } else {
        // Table doesn't exist yet
        $resources = [];
    }
} catch (Exception $e) {
    // If error, use empty array
    error_log("Error fetching resources: " . $e->getMessage());
    $resources = [];
}

// Get current date for calendar
$currentDate = date('Y-m-d');

$pageTitle = "Lab Resources & Schedule";
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
                    <a href="reservation.php" class="flex items-center justify-between px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                            <span class="font-medium">Reservation</span>
                        </div>
                        <?php if ($pendingCount > 0): ?>
                        <span class="bg-red-500 text-white text-xs rounded-full px-2 py-1 font-semibold"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="lab_resources.php" class="flex items-center justify-between px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <div class="flex items-center">
                            <i class="fas fa-book-open mr-3 text-lg"></i>
                            <span class="font-medium">Lab Resources</span>
                        </div>
                    </a>
                    <a href="feedbacks.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-comments mr-3 text-lg"></i>
                        <span class="font-medium">Feedbacks</span>
                    </a>
                    <a href="leaderboard.php" class="flex items-center justify-between px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <div class="flex items-center">
                            <i class="fas fa-trophy mr-3 text-lg"></i>
                            <span class="font-medium">Leaderboard</span>
                        </div>
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
                        Lab Resources & Schedule
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
                    <a href="reservation.php" class="flex items-center justify-between px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                            <span class="font-medium">Reservation</span>
                        </div>
                        <?php if ($pendingCount > 0): ?>
                        <span class="bg-red-500 text-white text-xs rounded-full px-2 py-1 font-semibold"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="lab_resources.php" class="flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-book-open mr-3 text-lg"></i>
                        <span class="font-medium">Lab Resources</span>
                    </a>
                    <a href="feedbacks.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-comments mr-3 text-lg"></i>
                        <span class="font-medium">Feedbacks</span>
                    </a>
                <a href="leaderboard.php" class="flex items-center justify-between px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <div class="flex items-center">
                            <i class="fas fa-trophy mr-3 text-lg"></i>
                            <span class="font-medium">Leaderboard</span>
                        </div>
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
            <div class="grid grid-cols-1 gap-8">
                <!-- Weekly Schedule Overview -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white p-4 flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-calendar-week mr-3"></i> Weekly Lab Schedule
                        </h3>
                        <div class="flex space-x-3">
                            <button class="bg-white text-blue-600 px-3 py-1.5 rounded-md text-sm flex items-center shadow-sm hover:bg-gray-100 transition-colors" id="upload-schedule-btn">
                                <i class="fas fa-upload mr-2"></i> Upload Schedule
                            </button>
                        </div>
                    </div>
                    
                    <div class="p-5">
                        <!-- Schedule upload form (hidden by default) -->
                        <div id="schedule-upload-form" class="mb-6 p-4 border border-blue-200 rounded-lg bg-blue-50 hidden">
                            <h4 class="font-medium text-blue-700 mb-3">Upload Lab Schedule Image</h4>
                            <form id="lab-schedule-form" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <label for="schedule-image" class="block text-sm font-medium text-gray-700 mb-2">Select Image File</label>
                                    <input type="file" id="schedule-image" name="schedule_image" accept="image/png, image/jpeg, image/jpg" 
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                        file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    <p class="mt-1 text-xs text-gray-500">Accepted formats: JPG, JPEG, PNG. Max file size: 2MB</p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="schedule-title" class="block text-sm font-medium text-gray-700 mb-2">Schedule Title</label>
                                        <input type="text" id="schedule-title" name="schedule_title" placeholder="e.g., Spring 2025 Lab Schedule" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="schedule-lab" class="block text-sm font-medium text-gray-700 mb-2">Laboratory</label>
                                        <select id="schedule-lab" name="schedule_lab" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <option value="all">All Laboratories (General Schedule)</option>
                                            <?php foreach ($laboratories as $lab): ?>
                                                <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <button type="button" id="cancel-upload" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors">
                                        Cancel
                                    </button>
                                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                                        Upload Schedule
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Laboratory selection for schedules -->
                        <div class="mb-5">
                            <label for="view-schedule-lab" class="block text-sm font-medium text-gray-700 mb-2">Select Laboratory Schedule</label>
                            <select id="view-schedule-lab" class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="all">All Laboratories (General)</option>
                                <?php foreach ($laboratories as $lab): ?>
                                    <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Current schedule display -->
                        <div id="schedule-display" class="text-center">
                            <div id="schedule-loading" class="py-8 hidden">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                                <p class="mt-3 text-gray-600">Loading schedule...</p>
                            </div>
                            
                            <div id="schedule-content">
                                <?php
                                // Check if schedule image exists for general (all labs)
                                $schedulePath = "../uploads/lab_schedules/all.jpg";
                                $schedulePathPng = "../uploads/lab_schedules/all.png";
                                $scheduleExists = file_exists($schedulePath) || file_exists($schedulePathPng);
                                $actualPath = file_exists($schedulePath) ? $schedulePath : (file_exists($schedulePathPng) ? $schedulePathPng : "");
                                $lastUpdated = $scheduleExists ? date("F d, Y", filemtime($actualPath)) : "";
                                
                                // Debug information (will be visible in HTML source)
                                echo "<!-- Debug: Schedule JPG path: $schedulePath exists: " . (file_exists($schedulePath) ? "Yes" : "No") . " -->";
                                echo "<!-- Debug: Schedule PNG path: $schedulePathPng exists: " . (file_exists($schedulePathPng) ? "Yes" : "No") . " -->";
                                echo "<!-- Debug: Actual path used: $actualPath -->";
                                
                                // Make sure uploads directory exists, create if not
                                if (!is_dir("../uploads/lab_schedules/")) {
                                    mkdir("../uploads/lab_schedules/", 0777, true);
                                    echo "<!-- Debug: Created missing uploads directory -->";
                                }
                                ?>

                                <?php if ($scheduleExists): ?>
                                    <div class="mb-3 flex items-center justify-between">
                                        <h4 class="font-medium text-gray-800">Current Lab Schedule</h4>
                                        <div class="text-sm text-gray-500">Last updated: <?php echo $lastUpdated; ?></div>
                                    </div>
                                    <div class="border rounded-lg overflow-hidden shadow-sm">
                                        <img src="<?php echo $actualPath; ?>?v=<?php echo time(); ?>" alt="Laboratory Schedule" class="max-w-full h-auto">
                                    </div>
                                    <div class="mt-4 flex justify-end space-x-3">
                                        <a href="<?php echo $actualPath; ?>" download="lab_schedule.<?php echo pathinfo($actualPath, PATHINFO_EXTENSION); ?>" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors inline-flex items-center shadow-sm">
                                            <i class="fas fa-download mr-2"></i> Download Schedule
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="py-12 flex flex-col items-center">
                                        <div class="bg-blue-100 p-6 rounded-full mb-4">
                                            <i class="fas fa-calendar-alt text-4xl text-blue-500"></i>
                                        </div>
                                        <h4 class="text-xl font-medium text-secondary mb-2">No Schedule Uploaded Yet</h4>
                                        <p class="text-base text-gray-500 max-w-sm mx-auto mb-6">Upload a schedule image to display the weekly laboratory schedule for students and staff.</p>
                                        <button id="upload-schedule-btn-empty" class="px-5 py-2.5 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors inline-flex items-center shadow-sm">
                                            <i class="fas fa-upload mr-2"></i> Upload Schedule Image
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lab Resources Panel -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-purple-500 text-white p-4 flex justify-between items-center">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-book-open mr-3"></i> Lab Resources & Materials
                        </h3>
                        <button class="bg-white text-purple-600 px-3 py-1.5 rounded-md text-sm flex items-center hover:bg-gray-100 transition-colors shadow-sm" id="add-resource-btn">
                            <i class="fas fa-plus mr-2"></i> Add Resource
                        </button>
                    </div>
                    
                    <div class="p-5">
                        <!-- Resource addition form (hidden by default) -->
                        <div id="resource-form" class="mb-6 p-4 border border-purple-200 rounded-lg bg-purple-50 hidden">
                            <h4 class="font-medium text-purple-700 mb-3">Add Lab Resource</h4>
                            <form id="lab-resource-form">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="resource-name" class="block text-sm font-medium text-gray-700 mb-2">Resource Name</label>
                                        <input type="text" id="resource-name" name="resource_name" placeholder="e.g., Programming Manual" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                                    </div>
                                    <div>
                                        <label for="resource-type" class="block text-sm font-medium text-gray-700 mb-2">Resource Type</label>
                                        <select id="resource-type" name="resource_type" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                                            <option value="document">Document</option>
                                            <option value="software">Software</option>
                                            <option value="hardware">Hardware</option>
                                            <option value="reference">Reference Material</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="resource-description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                    <textarea id="resource-description" name="resource_description" rows="3" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500"
                                        placeholder="Briefly describe this resource..."></textarea>
                                </div>
                                <div class="mb-4">
                                    <label for="resource-link" class="block text-sm font-medium text-gray-700 mb-2">Link or Location (Optional)</label>
                                    <input type="text" id="resource-link" name="resource_link" placeholder="URL or physical location in the lab" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="resource-lab" class="block text-sm font-medium text-gray-700 mb-2">Associated Laboratory</label>
                                        <select id="resource-lab" name="resource_lab" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500">
                                            <option value="all">All Laboratories</option>
                                            <?php foreach ($laboratories as $lab): ?>
                                                <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="resource-file" class="block text-sm font-medium text-gray-700 mb-2">Attach File (Optional)</label>
                                        <input type="file" id="resource-file" name="resource_file" 
                                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                            file:text-sm file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <button type="button" id="cancel-resource" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors">
                                        Cancel
                                    </button>
                                    <button type="submit" class="px-4 py-2 bg-purple-500 text-white rounded-md hover:bg-purple-600 transition-colors">
                                        Add Resource
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Resource edit form (hidden by default) -->
                        <div id="resource-edit-form" class="mb-6 p-4 border border-blue-200 rounded-lg bg-blue-50 hidden">
                            <h4 class="font-medium text-blue-700 mb-3">Edit Lab Resource</h4>
                            <form id="edit-resource-form">
                                <input type="hidden" id="edit-resource-id" name="resource_id">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="edit-resource-name" class="block text-sm font-medium text-gray-700 mb-2">Resource Name</label>
                                        <input type="text" id="edit-resource-name" name="resource_name" placeholder="e.g., Programming Manual" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label for="edit-resource-type" class="block text-sm font-medium text-gray-700 mb-2">Resource Type</label>
                                        <select id="edit-resource-type" name="resource_type" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <option value="document">Document</option>
                                            <option value="software">Software</option>
                                            <option value="hardware">Hardware</option>
                                            <option value="reference">Reference Material</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="edit-resource-description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                    <textarea id="edit-resource-description" name="resource_description" rows="3" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Briefly describe this resource..."></textarea>
                                </div>
                                <div class="mb-4">
                                    <label for="edit-resource-link" class="block text-sm font-medium text-gray-700 mb-2">Link or Location (Optional)</label>
                                    <input type="text" id="edit-resource-link" name="resource_link" placeholder="URL or physical location in the lab" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="edit-resource-lab" class="block text-sm font-medium text-gray-700 mb-2">Associated Laboratory</label>
                                        <select id="edit-resource-lab" name="resource_lab" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <option value="all">All Laboratories</option>
                                            <?php foreach ($laboratories as $lab): ?>
                                                <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="edit-resource-file" class="block text-sm font-medium text-gray-700 mb-2">Replace File (Optional)</label>
                                        <input type="file" id="edit-resource-file" name="resource_file" 
                                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                            file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        <p class="mt-1 text-xs text-gray-500">Leave empty to keep current file</p>
                                    </div>
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <button type="button" id="cancel-edit-resource" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors">
                                        Cancel
                                    </button>
                                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                                        Update Resource
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Resources list -->
                        <div class="mb-4">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-3">
                                <h4 class="font-medium text-gray-800">Available Resources</h4>
                                <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                                    <div class="relative w-full md:w-64">
                                        <input type="text" id="resource-search" placeholder="Search resources..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-sm w-full">
                                        <div class="absolute left-0 inset-y-0 flex items-center pl-3">
                                            <i class="fas fa-search text-gray-400"></i>
                                        </div>
                                    </div>
                                    <select id="filter-lab" class="w-full md:w-48 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-sm">
                                        <option value="all">All Labs</option>
                                        <?php foreach ($laboratories as $lab): ?>
                                            <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Filter tabs -->
                            <div class="flex mb-4 border-b border-gray-200 overflow-x-auto py-1 scrollbar-thin">
                                <button data-filter="all" class="resource-type-filter px-4 py-2 border-b-2 border-purple-500 text-purple-600 font-medium text-sm">All Resources</button>
                                <button data-filter="document" class="resource-type-filter px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Documents</button>
                                <button data-filter="software" class="resource-type-filter px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Software</button>
                                <button data-filter="hardware" class="resource-type-filter px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Hardware</button>
                                <button data-filter="reference" class="resource-type-filter px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">References</button>
                                <button data-filter="other" class="resource-type-filter px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Other</button>
                            </div>
                            
                            <!-- Resource cards container -->
                            <div id="resource-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php if (count($resources) > 0): ?>
                                    <?php foreach ($resources as $resource): ?>
                                        <div class="resource-card border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow"
                                             data-type="<?php echo htmlspecialchars($resource['RESOURCE_TYPE']); ?>"
                                             data-lab="<?php echo $resource['LAB_ID'] ?? 'all'; ?>">
                                            <div class="p-4">
                                                <div class="flex justify-between items-start mb-3">
                                                    <h5 class="font-medium text-gray-900"><?php echo htmlspecialchars($resource['RESOURCE_NAME']); ?></h5>
                                                    <span class="<?php echo getTypeClass($resource['RESOURCE_TYPE']); ?>"><?php echo ucfirst(htmlspecialchars($resource['RESOURCE_TYPE'])); ?></span>
                                                </div>
                                                <p class="text-sm text-gray-600 mb-4"><?php echo htmlspecialchars($resource['DESCRIPTION']); ?></p>
                                                <div class="text-xs text-gray-500 mb-3">Lab: <?php echo $resource['LAB_ID'] === 'all' ? 'All Laboratories' : htmlspecialchars($resource['LAB_NAME'] ?? 'Unknown'); ?></div>
                                                
                                                <!-- File & Link Actions -->
                                                <div class="flex flex-col gap-2 mb-3">
                                                    <?php if (!empty($resource['FILE_PATH'])): ?>
                                                        <?php 
                                                            $filePath = $resource['FILE_PATH'];
                                                            $fileName = basename($filePath);
                                                            $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                                                            $fileIcon = getFileIcon($fileExt);
                                                        ?>
                                                        <a href="ajax/download_resource.php?id=<?php echo $resource['RESOURCE_ID']; ?>" 
                                                           class="text-indigo-600 hover:text-indigo-800 text-sm flex items-center py-1 px-2 bg-indigo-50 rounded-md hover:bg-indigo-100 transition-colors">
                                                            <i class="<?php echo $fileIcon; ?> mr-2"></i> 
                                                            Download Attachment
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($resource['RESOURCE_LINK'])): ?>
                                                        <?php if (filter_var($resource['RESOURCE_LINK'], FILTER_VALIDATE_URL)): ?>
                                                            <a href="<?php echo htmlspecialchars($resource['RESOURCE_LINK']); ?>" target="_blank" 
                                                               class="text-purple-600 hover:text-purple-800 text-sm flex items-center py-1 px-2 bg-purple-50 rounded-md hover:bg-purple-100 transition-colors">
                                                                <i class="fas fa-external-link-alt mr-2"></i> 
                                                                Open Resource
                                                            </a>
                                                        <?php else: ?>
                                                            <div class="text-gray-600 text-sm flex items-center py-1 px-2 bg-gray-50 rounded-md">
                                                                <i class="fas fa-map-marker-alt mr-2"></i> 
                                                                Location: <?php echo htmlspecialchars($resource['RESOURCE_LINK']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Admin Actions -->
                                                <div class="flex justify-end items-center">
                                                    <div class="flex space-x-2">
                                                        <button class="edit-resource-btn text-blue-600 hover:text-blue-700 p-1" 
                                                                data-id="<?php echo $resource['RESOURCE_ID']; ?>"
                                                                data-name="<?php echo htmlspecialchars($resource['RESOURCE_NAME']); ?>"
                                                                data-type="<?php echo htmlspecialchars($resource['RESOURCE_TYPE']); ?>"
                                                                data-description="<?php echo htmlspecialchars($resource['DESCRIPTION']); ?>"
                                                                data-link="<?php echo htmlspecialchars($resource['RESOURCE_LINK'] ?? ''); ?>"
                                                                data-lab="<?php echo htmlspecialchars($resource['LAB_ID'] ?? 'all'); ?>"
                                                                data-has-file="<?php echo !empty($resource['FILE_PATH']) ? 'true' : 'false'; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="delete-resource-btn text-red-600 hover:text-red-700 p-1" data-id="<?php echo $resource['RESOURCE_ID']; ?>">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Empty state (shown if no resources exist) -->
                                    <div id="empty-resources" class="col-span-3 text-center py-10">
                                        <div class="bg-purple-100 inline-block p-6 rounded-full mb-4">
                                            <i class="fas fa-book-open text-4xl text-purple-500"></i>
                                        </div>
                                        <h4 class="text-xl font-medium text-secondary mb-2">No Resources Added Yet</h4>
                                        <p class="text-base text-gray-500 max-w-sm mx-auto mb-6">Start adding lab resources to make them available for students and staff.</p>
                                        <button id="add-first-resource" class="px-5 py-2.5 bg-purple-500 text-white rounded-md hover:bg-purple-600 transition-colors inline-flex items-center shadow-sm">
                                            <i class="fas fa-plus mr-2"></i> Add Your First Resource
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <!-- No results found message (hidden by default) -->
                                <div id="no-results" class="col-span-3 text-center py-10 hidden">
                                    <div class="bg-gray-100 inline-block p-6 rounded-full mb-4">
                                        <i class="fas fa-search text-4xl text-gray-500"></i>
                                    </div>
                                    <h4 class="text-xl font-medium text-secondary mb-2">No Matching Resources</h4>
                                    <p class="text-base text-gray-500 max-w-sm mx-auto">Try adjusting your search or filters to find what you're looking for.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Delete Resource Confirmation Dialog -->
<div id="delete-dialog" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-red-600 mb-4">Delete Resource</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to delete this resource? This action cannot be undone.</p>
        <div class="flex justify-end space-x-4">
            <button id="cancel-delete" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <button id="confirm-delete" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                Delete
            </button>
        </div>
    </div>
</div>

<!-- Confirmation Dialog -->
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

<?php
// Helper function to get CSS class for resource type badge
function getTypeClass($type) {
    switch ($type) {
        case 'document':
            return 'bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-md';
        case 'software':
            return 'bg-green-100 text-green-800 text-xs px-2 py-1 rounded-md';
        case 'hardware':
            return 'bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-md';
        case 'reference':
            return 'bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-md';
        default:
            return 'bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-md';
    }
}

// Helper function to get Font Awesome icon based on file extension
function getFileIcon($ext) {
    switch (strtolower($ext)) {
        case 'pdf':
            return 'fas fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fas fa-file-word';
        case 'xls':
        case 'xlsx':
            return 'fas fa-file-excel';
        case 'ppt':
        case 'pptx':
            return 'fas fa-file-powerpoint';
        case 'zip':
        case 'rar':
        case '7z':
            return 'fas fa-file-archive';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return 'fas fa-file-image';
        case 'mp4':
        case 'avi':
        case 'mov':
            return 'fas fa-file-video';
        case 'mp3':
        case 'wav':
        case 'ogg':
            return 'fas fa-file-audio';
        default:
            return 'fas fa-file';
    }
}
?>

<script>
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
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
    
    // Lab Schedule Upload and Selection Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const uploadScheduleBtn = document.getElementById('upload-schedule-btn');
        const uploadScheduleBtnEmpty = document.getElementById('upload-schedule-btn-empty');
        const cancelUploadBtn = document.getElementById('cancel-upload');
        const scheduleUploadForm = document.getElementById('schedule-upload-form');
        const scheduleDisplay = document.getElementById('schedule-display');
        const labScheduleForm = document.getElementById('lab-schedule-form');
        const viewScheduleLab = document.getElementById('view-schedule-lab');
        const scheduleLoading = document.getElementById('schedule-loading');
        const scheduleContent = document.getElementById('schedule-content');
        
        // Show upload form when buttons are clicked
        if (uploadScheduleBtn) {
            uploadScheduleBtn.addEventListener('click', () => {
                scheduleUploadForm.classList.remove('hidden');
            });
        }
        
        if (uploadScheduleBtnEmpty) {
            uploadScheduleBtnEmpty.addEventListener('click', () => {
                scheduleUploadForm.classList.remove('hidden');
            });
        }
        
        // Hide upload form when cancel button is clicked
        if (cancelUploadBtn) {
            cancelUploadBtn.addEventListener('click', () => {
                scheduleUploadForm.classList.add('hidden');
            });
        }
        
        // Handle form submission
        if (labScheduleForm) {
            labScheduleForm.addEventListener('submit', function(event) {
                event.preventDefault();
                
                const formData = new FormData(labScheduleForm);
                
                // Show loading indicator
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Uploading...';
                submitBtn.disabled = true;
                
                // AJAX request to upload schedule
                fetch('ajax/upload_schedule.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                    
                    if (data.success) {
                        alert('Schedule uploaded successfully!');
                        console.log('Upload response:', data);
                        // Force reload to show the new schedule
                        window.location.href = window.location.href.split('#')[0] + '?cache=' + new Date().getTime();
                    } else {
                        alert('Error uploading schedule: ' + data.message);
                        console.error('Upload error:', data);
                    }
                })
                .catch(error => {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                    console.error('Error:', error);
                    alert('An error occurred while uploading the schedule.');
                });
            });
        }
        
        // Lab schedule selection change event
        if (viewScheduleLab) {
            viewScheduleLab.addEventListener('change', function() {
                const labId = this.value;
                scheduleContent.classList.add('hidden');
                scheduleLoading.classList.remove('hidden');
                
                // AJAX request to get schedule for selected lab
                fetch(`ajax/get_schedule.php?lab_id=${labId}&t=${new Date().getTime()}`)
                .then(response => response.json())
                .then(data => {
                    scheduleLoading.classList.add('hidden');
                    scheduleContent.classList.remove('hidden');
                    
                    console.log('Schedule data:', data);
                    
                    if (data.success) {
                        scheduleContent.innerHTML = data.html;
                        
                        // Attach event listener to dynamically created "Upload" button if it exists
                        const dynamicBtn = document.getElementById('upload-schedule-btn-empty-dynamic');
                        if (dynamicBtn) {
                            dynamicBtn.addEventListener('click', () => {
                                scheduleUploadForm.classList.remove('hidden');
                                document.getElementById('schedule-lab').value = labId;
                            });
                        }
                    } else {
                        scheduleContent.innerHTML = `
                            <div class="py-12 flex flex-col items-center">
                                <div class="bg-blue-100 p-6 rounded-full mb-4">
                                    <i class="fas fa-calendar-alt text-4xl text-blue-500"></i>
                                </div>
                                <h4 class="text-xl font-medium text-secondary mb-2">No Schedule Available</h4>
                                <p class="text-base text-gray-500 max-w-sm mx-auto mb-6">${data.message || 'No schedule has been uploaded for this laboratory yet.'}</p>
                                <button id="upload-schedule-btn-empty-dynamic" class="px-5 py-2.5 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors inline-flex items-center shadow-sm">
                                    <i class="fas fa-upload mr-2"></i> Upload Schedule Image
                                </button>
                            </div>
                        `;
                        
                        // Attach event listener to the dynamically created button
                        const dynamicBtn = document.getElementById('upload-schedule-btn-empty-dynamic');
                        if (dynamicBtn) {
                            dynamicBtn.addEventListener('click', () => {
                                scheduleUploadForm.classList.remove('hidden');
                                document.getElementById('schedule-lab').value = labId;
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    scheduleLoading.classList.add('hidden');
                    scheduleContent.classList.remove('hidden');
                    scheduleContent.innerHTML = `
                        <div class="py-8 text-center">
                            <div class="bg-red-100 p-5 rounded-lg inline-block mb-4">
                                <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
                            </div>
                            <h4 class="text-xl font-medium text-red-600 mb-2">Error Loading Schedule</h4>
                            <p class="text-base text-gray-500">There was a problem loading the schedule. Please try again later.</p>
                        </div>
                    `;
                });
            });
        }
    });
    
    // Lab Resources Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const addResourceBtn = document.getElementById('add-resource-btn');
        const addFirstResourceBtn = document.getElementById('add-first-resource');
        const cancelResourceBtn = document.getElementById('cancel-resource');
        const resourceForm = document.getElementById('resource-form');
        const labResourceForm = document.getElementById('lab-resource-form');
        const resourceSearch = document.getElementById('resource-search');
        const filterLab = document.getElementById('filter-lab');
        const resourceTypeFilters = document.querySelectorAll('.resource-type-filter');
        const resourceCards = document.querySelectorAll('.resource-card');
        const noResults = document.getElementById('no-results');
        const emptyResources = document.getElementById('empty-resources');
        
        // Edit form elements
        const resourceEditForm = document.getElementById('resource-edit-form');
        const editResourceForm = document.getElementById('edit-resource-form');
        const cancelEditResourceBtn = document.getElementById('cancel-edit-resource');
        
        let currentTypeFilter = 'all';
        
        // Show form when add resource button is clicked
        if (addResourceBtn) {
            addResourceBtn.addEventListener('click', () => {
                resourceForm.classList.remove('hidden');
            });
        }
        
        if (addFirstResourceBtn) {
            addFirstResourceBtn.addEventListener('click', () => {
                resourceForm.classList.remove('hidden');
            });
        }
        
        // Hide form when cancel button is clicked
        if (cancelResourceBtn) {
            cancelResourceBtn.addEventListener('click', () => {
                resourceForm.classList.add('hidden');
            });
        }
        
        // Handle resource form submission
        if (labResourceForm) {
            labResourceForm.addEventListener('submit', function(event) {
                event.preventDefault();
                
                const formData = new FormData(labResourceForm);
                
                // AJAX request to add resource
                fetch('ajax/add_resource.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Resource added successfully!');
                        window.location.reload();
                    } else {
                        alert('Error adding resource: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while adding the resource.');
                });
            });
        }
        
        // Edit resource functionality
        const editButtons = document.querySelectorAll('.edit-resource-btn');
        
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Get resource data from data attributes
                const resourceId = this.getAttribute('data-id');
                const resourceName = this.getAttribute('data-name');
                const resourceType = this.getAttribute('data-type');
                const resourceDescription = this.getAttribute('data-description');
                const resourceLink = this.getAttribute('data-link');
                const resourceLab = this.getAttribute('data-lab');
                
                // Fill the edit form with current resource data
                document.getElementById('edit-resource-id').value = resourceId;
                document.getElementById('edit-resource-name').value = resourceName;
                document.getElementById('edit-resource-type').value = resourceType;
                document.getElementById('edit-resource-description').value = resourceDescription;
                document.getElementById('edit-resource-link').value = resourceLink;
                document.getElementById('edit-resource-lab').value = resourceLab;
                
                // Show the edit form and hide the add form
                resourceForm.classList.add('hidden');
                resourceEditForm.classList.remove('hidden');
                
                // Scroll to form
                resourceEditForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
        
        // Hide edit form when cancel button is clicked
        if (cancelEditResourceBtn) {
            cancelEditResourceBtn.addEventListener('click', () => {
                resourceEditForm.classList.add('hidden');
            });
        }
        
        // Handle edit form submission
        if (editResourceForm) {
            editResourceForm.addEventListener('submit', function(event) {
                event.preventDefault();
                
                const formData = new FormData(editResourceForm);
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
                submitBtn.disabled = true;
                
                // AJAX request to update resource
                fetch('ajax/update_resource.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                    
                    if (data.success) {
                        alert('Resource updated successfully!');
                        window.location.reload();
                    } else {
                        alert('Error updating resource: ' + data.message);
                    }
                })
                .catch(error => {
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                    console.error('Error:', error);
                    alert('An error occurred while updating the resource.');
                });
            });
        }
        
        // Delete resource functionality
        const deleteButtons = document.querySelectorAll('.delete-resource-btn');
        const deleteDialog = document.getElementById('delete-dialog');
        const cancelDelete = document.getElementById('cancel-delete');
        const confirmDelete = document.getElementById('confirm-delete');
        let resourceToDelete = null;
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                resourceToDelete = this.getAttribute('data-id');
                deleteDialog.classList.remove('hidden');
            });
        });
        
        cancelDelete.addEventListener('click', () => {
            deleteDialog.classList.add('hidden');
            resourceToDelete = null;
        });
        
        deleteDialog.addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.add('hidden');
                resourceToDelete = null;
            }
        });
        
        confirmDelete.addEventListener('click', function() {
            if (resourceToDelete) {
                // AJAX request to delete resource
                fetch(`ajax/delete_resource.php?id=${resourceToDelete}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Resource deleted successfully!');
                        window.location.reload();
                    } else {
                        alert('Error deleting resource: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the resource.');
                });
            }
            deleteDialog.classList.add('hidden');
        });
        
        // Resource filtering functionality
        function filterResources() {
            const searchTerm = resourceSearch.value.toLowerCase();
            const selectedLab = filterLab.value;
            let visibleCount = 0;
            
            resourceCards.forEach(card => {
                const type = card.getAttribute('data-type');
                const lab = card.getAttribute('data-lab');
                const content = card.textContent.toLowerCase();
                
                const typeMatch = currentTypeFilter === 'all' || type === currentTypeFilter;
                const labMatch = selectedLab === 'all' || lab === selectedLab;
                const searchMatch = !searchTerm || content.includes(searchTerm);
                
                if (typeMatch && labMatch && searchMatch) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });
            
            // Show or hide "no results" message
            if (visibleCount === 0 && resourceCards.length > 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
            
            // Show or hide empty state
            if (resourceCards.length === 0) {
                emptyResources.classList.remove('hidden');
            } else {
                emptyResources.classList.add('hidden');
            }
        }
        
        // Search input event
        if (resourceSearch) {
            resourceSearch.addEventListener('input', filterResources);
        }
        
        // Lab filter change event
        if (filterLab) {
            filterLab.addEventListener('change', filterResources);
        }
        
        // Resource type filter buttons
        resourceTypeFilters.forEach(button => {
            button.addEventListener('click', function() {
                const filterType = this.getAttribute('data-filter');
                currentTypeFilter = filterType;
                
                // Update active filter UI
                resourceTypeFilters.forEach(btn => {
                    btn.classList.remove('border-purple-500', 'text-purple-600');
                    btn.classList.add('text-gray-600');
                });
                
                this.classList.remove('text-gray-600');
                this.classList.add('border-b-2', 'border-purple-500', 'text-purple-600');
                
                filterResources();
            });
        });
        
        // Run initial filtering
        filterResources();
    });
</script>

<style>
    /* Custom scrollbar styles */
    .scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    
    .scrollbar::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .scrollbar::-webkit-scrollbar-thumb {
        background: #c5c5c5;
        border-radius: 10px;
    }
    
    .scrollbar::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    
    /* For Firefox */
    .scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #c5c5c5 #f1f1f1;
    }
    
    .scrollbar-thin::-webkit-scrollbar {
        height: 4px;
    }
</style>

<?php include('includes/footer.php'); ?>
