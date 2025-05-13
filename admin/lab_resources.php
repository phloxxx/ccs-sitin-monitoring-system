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
                    <a href="reservation.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                        <span class="font-medium">Reservation</span>
                    </a>
                    <a href="lab_resources.php" class="flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
                    <a href="reservation.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                        <span class="font-medium">Reservation</span>
                    </a>
                    <a href="lab_resources.php" class="flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
                                <div class="mb-3">
                                    <label for="schedule-title" class="block text-sm font-medium text-gray-700 mb-2">Schedule Title</label>
                                    <input type="text" id="schedule-title" name="schedule_title" placeholder="e.g., Spring 2025 Lab Schedule" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
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

                        <!-- Current schedule display -->
                        <div id="schedule-display" class="text-center">
                            <?php
                            // Check if schedule image exists
                            $schedulePath = "../uploads/lab_schedule.jpg";
                            $schedulePathPng = "../uploads/lab_schedule.png";
                            $scheduleExists = file_exists($schedulePath) || file_exists($schedulePathPng);
                            $actualPath = file_exists($schedulePath) ? $schedulePath : (file_exists($schedulePathPng) ? $schedulePathPng : "");
                            $lastUpdated = $scheduleExists ? date("F d, Y", filemtime($actualPath)) : "";
                            ?>

                            <?php if ($scheduleExists): ?>
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="font-medium text-gray-800">Current Lab Schedule</h4>
                                    <div class="text-sm text-gray-500">Last updated: <?php echo $lastUpdated; ?></div>
                                </div>
                                <div class="border rounded-lg overflow-hidden shadow-sm">
                                    <img src="<?php echo $actualPath; ?>?v=<?php echo filemtime($actualPath); ?>" alt="Laboratory Schedule" class="max-w-full h-auto">
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
                                    <label for="resource-link" class="block text-sm font-medium text-gray-700 mb-2">Link or Location</label>
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
                        
                        <!-- Resources list -->
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="font-medium text-gray-800">Available Resources</h4>
                                <div class="relative">
                                    <input type="text" placeholder="Search resources..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-sm">
                                    <div class="absolute left-0 inset-y-0 flex items-center pl-3">
                                        <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Filter tabs -->
                            <div class="flex mb-4 border-b border-gray-200 overflow-x-auto py-1 scrollbar-thin">
                                <button class="px-4 py-2 border-b-2 border-purple-500 text-purple-600 font-medium text-sm">All Resources</button>
                                <button class="px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Documents</button>
                                <button class="px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Software</button>
                                <button class="px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Hardware</button>
                                <button class="px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">References</button>
                            </div>
                            
                            <!-- Resource cards -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <!-- Example resources - In a real app these would be populated from the database -->
                                <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                                    <div class="p-4">
                                        <div class="flex justify-between items-start mb-3">
                                            <h5 class="font-medium text-gray-900">Java Programming Guide</h5>
                                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-md">Document</span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-4">Comprehensive guide for Java programming with examples and exercises.</p>
                                        <div class="text-xs text-gray-500 mb-3">Lab: All Laboratories</div>
                                        <div class="flex justify-between items-center">
                                            <a href="#" class="text-purple-600 hover:text-purple-700 text-sm flex items-center">
                                                <i class="fas fa-external-link-alt mr-1"></i> Open Resource
                                            </a>
                                            <div class="flex space-x-2">
                                                <button class="text-blue-600 hover:text-blue-700 p-1">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-red-600 hover:text-red-700 p-1">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                                    <div class="p-4">
                                        <div class="flex justify-between items-start mb-3">
                                            <h5 class="font-medium text-gray-900">Visual Studio 2022</h5>
                                            <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-md">Software</span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-4">Latest version of Visual Studio for C# and .NET development.</p>
                                        <div class="text-xs text-gray-500 mb-3">Lab: Lab 524</div>
                                        <div class="flex justify-between items-center">
                                            <a href="#" class="text-purple-600 hover:text-purple-700 text-sm flex items-center">
                                                <i class="fas fa-external-link-alt mr-1"></i> Download Link
                                            </a>
                                            <div class="flex space-x-2">
                                                <button class="text-blue-600 hover:text-blue-700 p-1">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-red-600 hover:text-red-700 p-1">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                                    <div class="p-4">
                                        <div class="flex justify-between items-start mb-3">
                                            <h5 class="font-medium text-gray-900">Arduino Kit Inventory</h5>
                                            <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-md">Hardware</span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-4">List of Arduino components available for IoT and embedded systems projects.</p>
                                        <div class="text-xs text-gray-500 mb-3">Lab: Lab 522</div>
                                        <div class="flex justify-between items-center">
                                            <a href="#" class="text-purple-600 hover:text-purple-700 text-sm flex items-center">
                                                <i class="fas fa-external-link-alt mr-1"></i> View Inventory
                                            </a>
                                            <div class="flex space-x-2">
                                                <button class="text-blue-600 hover:text-blue-700 p-1">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="text-red-600 hover:text-red-700 p-1">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Empty state (hidden if resources exist) -->
                            <div class="hidden text-center py-10">
                                <div class="bg-purple-100 inline-block p-6 rounded-full mb-4">
                                    <i class="fas fa-book-open text-4xl text-purple-500"></i>
                                </div>
                                <h4 class="text-xl font-medium text-secondary mb-2">No Resources Added Yet</h4>
                                <p class="text-base text-gray-500 max-w-sm mx-auto mb-6">Start adding lab resources to make them available for students and staff.</p>
                                <button id="add-first-resource" class="px-5 py-2.5 bg-purple-500 text-white rounded-md hover:bg-purple-600 transition-colors inline-flex items-center shadow-sm">
                                    <i class="fas fa-plus mr-2"></i> Add Your First Resource
                                </button>
                            </div>
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
    
    document.getElementById('confirmation-dialog').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
    
    // Lab Schedule Upload Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const uploadScheduleBtn = document.getElementById('upload-schedule-btn');
        const uploadScheduleBtnEmpty = document.getElementById('upload-schedule-btn-empty');
        const cancelUploadBtn = document.getElementById('cancel-upload');
        const scheduleUploadForm = document.getElementById('schedule-upload-form');
        const scheduleDisplay = document.getElementById('schedule-display');
        const labScheduleForm = document.getElementById('lab-schedule-form');
        
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
                
                // AJAX request to upload schedule
                fetch('ajax/upload_schedule.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Schedule uploaded successfully!');
                        // Reload page to show the new schedule
                        window.location.reload();
                    } else {
                        alert('Error uploading schedule: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while uploading the schedule.');
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
        
        // Handle form submission
        if (labResourceForm) {
            labResourceForm.addEventListener('submit', function(event) {
                event.preventDefault();
                
                // In a real application, you would handle the form submission here
                alert('Resource added successfully! This is a placeholder for the actual functionality.');
                resourceForm.classList.add('hidden');
            });
        }
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
