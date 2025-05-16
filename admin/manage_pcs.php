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

// Get count of pending reservations
$pendingCount = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM RESERVATION WHERE STATUS = 'PENDING'");
    $stmt->execute();
    $result = $stmt->get_result();
    $pendingCount = $result->fetch_assoc()['count'];
    $stmt->close();
} catch (Exception $e) {
    // If error, use default value
    $pendingCount = 0;
}

// Fetch laboratories
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

// Get selected lab (default to first lab if not specified)
$selected_lab_id = isset($_GET['lab']) ? intval($_GET['lab']) : ($laboratories ? $laboratories[0]['LAB_ID'] : 0);

// Fetch PCs for the selected lab
$pcs = [];
if ($selected_lab_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT * FROM PC WHERE LAB_ID = ? ORDER BY PC_NUMBER");
        $stmt->bind_param("i", $selected_lab_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $pcs[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        // If error, use default values
        $pcs = [];
    }
}

// Get the selected lab details
$lab_details = null;
if ($selected_lab_id > 0) {
    foreach ($laboratories as $lab) {
        if ($lab['LAB_ID'] == $selected_lab_id) {
            $lab_details = $lab;
            break;
        }
    }
}

$pageTitle = "Manage Laboratory PCs";
$bodyClass = "bg-light font-poppins";
include('includes/header.php');
?>

<title>Manage PCs | CCS SIT-IN</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    .pc-grid-container {
        max-height: 500px;
        overflow-y: auto;
        padding-right: 5px;
    }
    
    /* Custom scrollbar styling */
    .pc-grid-container::-webkit-scrollbar {
        width: 6px;
    }
    
    .pc-grid-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .pc-grid-container::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }
    
    .pc-grid-container::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    .action-btn {
        padding: 5px;
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        border-radius: 4px;
    }
    
    .action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .pc-card {
        transition: all 0.2s;
    }
    
    .pc-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
</style>

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
                    <a href="sitin.php" class="flex items-center px-3 py-3.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3 text-lg"></i>
                        <span class="font-medium">Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center justify-between px-3 py-3.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
            <div class="mb-6">
                <a href="reservation.php" class="inline-flex items-center px-3 py-2 text-large font-medium text-secondary">
                    <i class="fas fa-arrow-left mr-2"></i>
                </a>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Laboratory Selection & Management -->
                    <div class="md:col-span-1">
                        <div class="bg-gray-50 rounded-lg border border-gray-200 p-5 h-full">
                            <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                                <i class="fas fa-building mr-2 text-blue-600"></i>
                                Laboratories
                            </h3>
                            
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Laboratory</label>
                                <div class="relative">
                                    <select id="lab-select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 appearance-none">
                                        <?php foreach ($laboratories as $lab): ?>
                                            <option value="<?php echo $lab['LAB_ID']; ?>" <?php if ($lab['LAB_ID'] == $selected_lab_id) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($lab['LAB_NAME']); ?> (<?php echo $lab['CAPACITY']; ?> PCs)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4 border-gray-200">
                            
                            <div class="mb-4">
                                <button id="add-lab-btn" class="w-full px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors flex items-center justify-center">
                                    <i class="fas fa-plus-circle mr-2"></i>
                                    Add New Laboratory
                                </button>
                            </div>
                            
                            <?php if ($lab_details): ?>
                                <div class="bg-white rounded-md border p-4 mb-4">
                                    <h4 class="font-medium text-gray-700 mb-3"><?php echo htmlspecialchars($lab_details['LAB_NAME']); ?></h4>
                                    <div class="grid grid-cols-2 gap-2 text-sm mb-4">
                                        <div>
                                            <span class="text-gray-500">Capacity:</span>
                                            <span class="font-medium"><?php echo $lab_details['CAPACITY']; ?> PCs</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Available:</span>
                                            <span class="font-medium text-green-600">
                                                <?php 
                                                    $available_count = count(array_filter($pcs, function($pc) { 
                                                        return isset($pc['STATUS']) && strtoupper(trim($pc['STATUS'])) == 'AVAILABLE'; 
                                                    }));
                                                    echo $available_count; 
                                                ?> PCs
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-3 gap-10 text-sm mb-4">
                                        <div>
                                            <span class="text-gray-500">Reserved:</span>
                                            <span class="font-medium text-red-600">
                                                <?php 
                                                    $reserved_count = count(array_filter($pcs, function($pc) { 
                                                        return isset($pc['STATUS']) && strtoupper(trim($pc['STATUS'])) == 'RESERVED'; 
                                                    }));
                                                    echo $reserved_count; 
                                                ?> PCs
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">Maintain:</span>
                                            <span class="font-medium text-yellow-600">
                                                <?php 
                                                    $maintenance_count = count(array_filter($pcs, function($pc) { 
                                                        return isset($pc['STATUS']) && strtoupper(trim($pc['STATUS'])) == 'MAINTENANCE'; 
                                                    }));
                                                    echo $maintenance_count; 
                                                ?> PCs
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">In Use:</span>
                                            <span class="font-medium text-blue-600">
                                                <?php 
                                                    $in_use_count = count(array_filter($pcs, function($pc) { 
                                                        return isset($pc['STATUS']) && strtoupper(trim($pc['STATUS'])) == 'IN_USE'; 
                                                    }));
                                                    echo $in_use_count; 
                                                ?> PCs
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <button id="edit-lab-btn" data-lab-id="<?php echo $lab_details['LAB_ID']; ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors text-sm flex items-center">
                                            <i class="fas fa-edit mr-1"></i>
                                            Edit
                                        </button>
                                        <button id="delete-lab-btn" data-lab-id="<?php echo $lab_details['LAB_ID']; ?>" class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors text-sm flex items-center">
                                            <i class="fas fa-trash-alt mr-1"></i>
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="text-sm text-gray-500">
                                <p class="mb-2"><i class="fas fa-info-circle mr-1 text-blue-500"></i> Select a laboratory to manage its PCs</p>
                                <p><i class="fas fa-exclamation-triangle mr-1 text-yellow-500"></i> Deleting a laboratory will also remove all its PCs</p>
                            </div>
                        </div>
                    </div>

                    <!-- PC Management -->
                    <div class="md:col-span-2">
                        <?php if ($selected_lab_id > 0): ?>
                            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                                <div class="p-5 border-b border-gray-200">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($lab_details['LAB_NAME']); ?> - PC Management
                                        </h3>
                                        
                                        <div class="flex items-center">
                                            <button id="bulk-edit-btn" class="px-4 py-2 bg-blue-100 text-blue-600 rounded-md hover:bg-blue-200 transition-colors mr-3 flex items-center">
                                                <i class="fas fa-edit mr-2"></i>
                                                Bulk Edit
                                            </button>
                                            <button id="add-pc-btn" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition-colors flex items-center">
                                                <i class="fas fa-plus-circle mr-2"></i>
                                                Add PCs
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (empty($pcs)): ?>
                                    <!-- No PCs yet -->
                                    <div class="p-8 text-center">
                                        <div class="bg-blue-100 inline-block p-4 rounded-full mb-4">
                                            <i class="fas fa-desktop text-blue-500 text-2xl"></i>
                                        </div>
                                        <h4 class="text-lg font-medium text-gray-700 mb-2">No PCs Configured</h4>
                                        <p class="text-gray-500 mb-6 max-w-md mx-auto">This laboratory doesn't have any PCs configured yet. Add PCs to make them available for reservations and sit-ins.</p>
                                        <button id="initial-add-pc-btn" class="px-5 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors flex items-center mx-auto">
                                            <i class="fas fa-plus-circle mr-2"></i>
                                            Add PCs to this Laboratory
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <!-- PC List -->
                                    <div class="p-5">
                                        <div class="mb-4">
                                            <div class="relative">
                                                <input type="text" id="pc-search" class="w-full px-10 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="Search PCs...">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <i class="fas fa-search text-gray-400"></i>
                                                </div>
                                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                                    <select id="status-filter" class="text-sm border-0 focus:outline-none text-gray-500 bg-transparent">
                                                        <option value="all">All Status</option>
                                                        <option value="available">Available</option>
                                                        <option value="in_use">In Use</option>
                                                        <option value="reserved">Reserved</option>
                                                        <option value="maintenance">Maintenance</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="pc-grid-container scrollbar">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" id="pc-grid">
                                                <?php foreach ($pcs as $pc): 
                                                    $statusClass = '';
                                                    $statusText = isset($pc['STATUS']) ? $pc['STATUS'] : 'AVAILABLE';
                                                    
                                                    switch(strtoupper(trim($statusText))) {
                                                        case 'AVAILABLE':
                                                            $statusClass = 'bg-green-100 text-green-800 border-green-200';
                                                            break;
                                                        case 'RESERVED':
                                                            $statusClass = 'bg-red-100 text-red-800 border-red-200';
                                                            break;
                                                        case 'MAINTENANCE':
                                                            $statusClass = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                            break;
                                                        case 'IN_USE':
                                                            $statusClass = 'bg-blue-100 text-blue-800 border-blue-200';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-gray-100 text-gray-800 border-gray-200';
                                                    }
                                                ?>
                                                <div class="pc-card border rounded-lg shadow-sm overflow-hidden">
                                                    <div class="p-4 border-b">
                                                        <div class="flex justify-between items-center">
                                                            <h5 class="font-medium text-gray-800">PC #<?php echo htmlspecialchars($pc['PC_NUMBER']); ?></h5>
                                                        </div>
                                                    </div>
                                                    <div class="p-4">
                                                        <div class="flex flex-col">
                                                            <div class="mb-3">
                                                                <span class="text-sm text-gray-500">Status:</span>
                                                                <span class="block px-2 py-1 rounded text-xs font-medium inline-block mt-1 <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText); ?></span>
                                                            </div>
                                                            <div>
                                                                <span class="text-sm text-gray-500 mb-1">Actions:</span>
                                                                <div class="flex mt-1 space-x-2">
                                                                    <button class="edit-pc-btn action-btn bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors" 
                                                                            data-pc-id="<?php echo $pc['PC_ID']; ?>" 
                                                                            data-pc-number="<?php echo $pc['PC_NUMBER']; ?>" 
                                                                            data-status="<?php echo $statusText; ?>"
                                                                            title="Edit PC">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <button class="delete-pc-btn action-btn bg-red-500 text-white rounded hover:bg-red-600 transition-colors" 
                                                                            data-pc-id="<?php echo $pc['PC_ID']; ?>" 
                                                                            data-pc-name="PC #<?php echo htmlspecialchars($pc['PC_NUMBER']); ?>"
                                                                            title="Delete PC">
                                                                        <i class="fas fa-trash-alt"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="bg-white p-8 rounded-lg border border-gray-200 shadow-sm text-center">
                                <div class="bg-blue-100 inline-block p-6 rounded-full mb-4">
                                    <i class="fas fa-building text-blue-500 text-3xl"></i>
                                </div>
                                <h4 class="text-xl font-medium text-gray-700 mb-2">No Laboratories Found</h4>
                                <p class="text-gray-500 mb-6">There are no laboratories configured in the system yet. Add a laboratory to start managing PCs.</p>
                                <button id="no-lab-add-btn" class="px-5 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                                    <i class="fas fa-plus-circle mr-2"></i>
                                    Add Your First Laboratory
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Laboratory Modal -->
<div id="add-lab-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-medium text-gray-900" id="lab-modal-title">Add New Laboratory</h3>
            <button class="close-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="lab-form">
            <input type="hidden" id="lab-id" value="0">
            
            <div class="mb-4">
                <label for="lab-name" class="block text-sm font-medium text-gray-700 mb-1">Laboratory Name</label>
                <input type="text" id="lab-name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., Laboratory 524" required>
            </div>
            
            <div class="mb-4">
                <label for="lab-capacity" class="block text-sm font-medium text-gray-700 mb-1">Capacity (Number of PCs)</label>
                <input type="number" id="lab-capacity" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" min="1" placeholder="e.g., 30" required>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="close-modal px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" id="save-lab-btn" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Save Laboratory
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit PC Modal -->
<div id="pc-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-medium text-gray-900" id="pc-modal-title">Add PCs</h3>
            <button class="close-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="pc-form">
            <input type="hidden" id="pc-id" value="0">
            <input type="hidden" id="edit-mode" value="single">
            
            <div id="single-pc-fields">
                <div class="mb-4">
                    <label for="pc-number" class="block text-sm font-medium text-gray-700 mb-1">PC Number</label>
                    <input type="number" id="pc-number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" min="1" placeholder="e.g., 1">
                </div>
                
                <div class="mb-4">
                    <label for="pc-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="pc-status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="AVAILABLE">Available</option>
                        <option value="IN_USE">In Use</option>
                        <option value="RESERVED">Reserved</option>
                        <option value="MAINTENANCE">Maintenance</option>
                    </select>
                </div>
            </div>
            
            <div id="bulk-pc-fields" class="hidden">
                <div class="mb-4">
                    <label for="pc-count" class="block text-sm font-medium text-gray-700 mb-1">Number of PCs to Add</label>
                    <input type="number" id="pc-count" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" min="1" max="100" value="1">
                </div>
                
                <div class="mb-4">
                    <label for="pc-start-number" class="block text-sm font-medium text-gray-700 mb-1">Starting Number</label>
                    <input type="number" id="pc-start-number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" min="1" value="1">
                </div>
                
                <div class="mb-4">
                    <label for="bulk-pc-status" class="block text-sm font-medium text-gray-700 mb-1">Default Status</label>
                    <select id="bulk-pc-status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="AVAILABLE">Available</option>
                        <option value="IN_USE">In Use</option>
                        <option value="RESERVED">Reserved</option>
                        <option value="MAINTENANCE">Maintenance</option>
                    </select>
                </div>
                
                <div class="p-3 bg-blue-50 rounded-md mb-4">
                    <p class="text-sm text-blue-700"><i class="fas fa-info-circle mr-1"></i> This will create multiple PCs numbered as "PC #1", "PC #2", etc.</p>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="close-modal px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" id="save-pc-btn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-medium text-red-700" id="delete-modal-title">Delete Confirmation</h3>
            <button class="close-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mb-6">
            <div class="bg-red-50 p-4 rounded-md mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800" id="delete-warning-text">
                            Are you sure you want to delete this item?
                        </h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p id="delete-warning-detail">This action cannot be undone.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button type="button" class="close-modal px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <button type="button" id="confirm-delete-btn" data-type="" data-id="0" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                Yes, Delete
            </button>
        </div>
    </div>
</div>

<!-- Bulk Edit Modal -->
<div id="bulk-edit-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-medium text-gray-900">Bulk Edit PCs</h3>
            <button class="close-modal text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="bulk-edit-form">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Select Action</label>
                <select id="bulk-action" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="status">Update Status</option>
                    <option value="delete">Delete Selected PCs</option>
                </select>
            </div>
            
            <div id="bulk-status-fields">
                <div class="mb-4">
                    <label for="bulk-edit-status" class="block text-sm font-medium text-gray-700 mb-1">New Status</label>
                    <select id="bulk-edit-status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="AVAILABLE">Available</option>
                        <option value="IN_USE">In Use</option>
                        <option value="RESERVED">Reserved</option>
                        <option value="MAINTENANCE">Maintenance</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select PCs</label>
                <div class="p-3 border border-gray-300 rounded-md max-h-60 overflow-y-auto" id="bulk-pc-selection">
                    <!-- PC checkboxes will be loaded here -->
                    <div class="text-center py-4 text-gray-500">
                        <i class="fas fa-spinner fa-spin mr-2"></i> Loading PCs...
                    </div>
                </div>
                <div class="mt-2 text-sm text-gray-500 flex justify-between">
                    <button type="button" id="select-all-pcs" class="text-blue-600 hover:underline">Select All</button>
                    <button type="button" id="deselect-all-pcs" class="text-blue-600 hover:underline">Deselect All</button>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" class="close-modal px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" id="bulk-edit-submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Apply Changes
                </button>
            </div>
        </form>
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
    
    // Lab selection change
    document.getElementById('lab-select').addEventListener('change', function() {
        const labId = this.value;
        window.location.href = `manage_pcs.php?lab=${labId}`;
    });
    
    // Modal handling
    const modals = {
        addLab: document.getElementById('add-lab-modal'),
        pc: document.getElementById('pc-modal'),
        delete: document.getElementById('delete-modal'),
        bulkEdit: document.getElementById('bulk-edit-modal')
    };
    
    // Close modal buttons
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', () => {
            for (const modal in modals) {
                modals[modal].classList.add('hidden');
            }
        });
    });
    
    // Close modals when clicking outside
    Object.values(modals).forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.add('hidden');
            }
        });
    });
    
    // Add Lab Button
    document.getElementById('add-lab-btn')?.addEventListener('click', () => {
        document.getElementById('lab-modal-title').textContent = 'Add New Laboratory';
        document.getElementById('lab-id').value = '0';
        document.getElementById('lab-form').reset();
        modals.addLab.classList.remove('hidden');
    });
    
    // No Lab Add Button (when no laboratories exist)
    document.getElementById('no-lab-add-btn')?.addEventListener('click', () => {
        document.getElementById('lab-modal-title').textContent = 'Add New Laboratory';
        document.getElementById('lab-id').value = '0';
        document.getElementById('lab-form').reset();
        modals.addLab.classList.remove('hidden');
    });
    
    // Edit Lab Button
    document.getElementById('edit-lab-btn')?.addEventListener('click', () => {
        const labId = document.getElementById('edit-lab-btn').dataset.labId;
        
        // Fetch lab details via AJAX and populate the form
        fetch(`ajax/get_lab_details.php?lab_id=${labId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('lab-modal-title').textContent = 'Edit Laboratory';
                    document.getElementById('lab-id').value = data.lab.LAB_ID;
                    document.getElementById('lab-name').value = data.lab.LAB_NAME;
                    document.getElementById('lab-capacity').value = data.lab.CAPACITY;
                    modals.addLab.classList.remove('hidden');
                } else {
                    alert('Error fetching laboratory details. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
    });
    
    // Delete Lab Button
    document.getElementById('delete-lab-btn')?.addEventListener('click', () => {
        const labId = document.getElementById('delete-lab-btn').dataset.labId;
        const labName = document.querySelector('h4.font-medium').textContent;
        
        document.getElementById('delete-warning-text').textContent = `Are you sure you want to delete "${labName}"?`;
        document.getElementById('delete-warning-detail').textContent = 
            `This will permanently delete the laboratory and all its PCs. This action cannot be undone.`;
        
        document.getElementById('confirm-delete-btn').dataset.type = 'lab';
        document.getElementById('confirm-delete-btn').dataset.id = labId;
        
        modals.delete.classList.remove('hidden');
    });
    
    // Add PCs Button
    document.getElementById('add-pc-btn')?.addEventListener('click', () => {
        document.getElementById('pc-modal-title').textContent = 'Add PCs';
        document.getElementById('pc-id').value = '0';
        document.getElementById('edit-mode').value = 'bulk';
        document.getElementById('single-pc-fields').classList.add('hidden');
        document.getElementById('bulk-pc-fields').classList.remove('hidden');
        document.getElementById('pc-form').reset();
        
        // Make sure PC number field is enabled
        const pcNumberField = document.getElementById('pc-number');
        pcNumberField.disabled = false;
        pcNumberField.classList.remove('bg-gray-100');
        
        modals.pc.classList.remove('hidden');
        
        // Set default values
        document.getElementById('pc-count').value = '1';
        document.getElementById('pc-start-number').value = '1';
    });
    
    // Initial Add PC Button (when lab has no PCs)
    document.getElementById('initial-add-pc-btn')?.addEventListener('click', () => {
        document.getElementById('add-pc-btn').click();
    });
    
    // PC Count and Start Number change events
    ['pc-count', 'pc-start-number'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', updatePcNamePreview);
    });
    
    // Function to update PC name preview (removed since preview display was removed)
    function updatePcNamePreview() {
        // Preview functionality removed as it's no longer needed
    }
    
    // Edit PC Buttons
    document.querySelectorAll('.edit-pc-btn')?.forEach(button => {
        button.addEventListener('click', () => {
            const pcId = button.dataset.pcId;
            const pcNumber = button.dataset.pcNumber;
            const status = button.dataset.status;
            
            document.getElementById('pc-modal-title').textContent = 'Edit PC';
            document.getElementById('pc-id').value = pcId;
            document.getElementById('edit-mode').value = 'single';
            document.getElementById('single-pc-fields').classList.remove('hidden');
            document.getElementById('bulk-pc-fields').classList.add('hidden');
            
            // Set PC number and make it editable
            const pcNumberField = document.getElementById('pc-number');
            pcNumberField.value = pcNumber;
            pcNumberField.disabled = false; // Enable PC number field in edit mode
            pcNumberField.classList.remove('bg-gray-100'); // Remove visual indication
            
            document.getElementById('pc-status').value = status;
            
            modals.pc.classList.remove('hidden');
        });
    });
    
    // Delete PC Buttons
    document.querySelectorAll('.delete-pc-btn')?.forEach(button => {
        button.addEventListener('click', () => {
            const pcId = button.dataset.pcId;
            const pcName = button.dataset.pcName;
            
            document.getElementById('delete-warning-text').textContent = `Are you sure you want to delete "${pcName}"?`;
            document.getElementById('delete-warning-detail').textContent = 
                `This PC will be permanently removed from the system. This action cannot be undone.`;
            
            document.getElementById('confirm-delete-btn').dataset.type = 'pc';
            document.getElementById('confirm-delete-btn').dataset.id = pcId;
            
            modals.delete.classList.remove('hidden');
        });
    });
    
    // Bulk Edit Button
    document.getElementById('bulk-edit-btn')?.addEventListener('click', () => {
        // Load all PCs in the current lab for selection
        const labId = document.getElementById('lab-select').value;
        
        // Reset form
        document.getElementById('bulk-edit-form').reset();
        document.getElementById('bulk-action').value = 'status';
        document.getElementById('bulk-status-fields').style.display = 'block';
        
        // Show modal
        modals.bulkEdit.classList.remove('hidden');
        
        // Display loading state
        document.getElementById('bulk-pc-selection').innerHTML = 
            '<div class="text-center py-4 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Loading PCs...</div>';
            
        // Fetch PCs and populate the selection
        fetch(`ajax/get_lab_pcs.php?lab_id=${labId}`)
            .then(response => {
                // Check if the response is OK before parsing as JSON
                if (!response.ok) {
                    throw new Error(`Server responded with status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const container = document.getElementById('bulk-pc-selection');
                    
                    // Handle case where no PCs are returned
                    if (data.pcs.length === 0) {
                        container.innerHTML = '<div class="text-center py-4 text-gray-500">No PCs found in this laboratory.</div>';
                        return;
                    }
                    
                    let html = '';
                    
                    data.pcs.forEach(pc => {
                        let statusClass = '';
                        switch(pc.STATUS || 'AVAILABLE') {
                            case 'AVAILABLE':
                                statusClass = 'text-green-600';
                                break;
                            case 'RESERVED':
                                statusClass = 'text-red-600';
                                break;
                            case 'MAINTENANCE':
                                statusClass = 'text-yellow-600';
                                break;
                            case 'IN_USE':
                                statusClass = 'text-blue-600';
                                break;
                            default:
                                statusClass = 'text-gray-600';
                        }
                        
                        html += `
                        <div class="flex items-center p-2 hover:bg-gray-50 rounded">
                            <input type="checkbox" id="pc-check-${pc.PC_ID}" name="selected_pcs[]" value="${pc.PC_ID}" class="mr-3 h-5 w-5 text-blue-600 focus:ring-blue-500">
                            <label for="pc-check-${pc.PC_ID}" class="flex justify-between w-full">
                                <span>PC #${pc.PC_NUMBER}</span>
                                <span class="${statusClass}">${pc.STATUS || 'AVAILABLE'}</span>
                            </label>
                        </div>`;
                    });
                    
                    container.innerHTML = html;
                } else {
                    document.getElementById('bulk-pc-selection').innerHTML = 
                        '<div class="text-center py-4 text-red-500">Failed to load PCs: ' + (data.message || 'Unknown error') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('bulk-pc-selection').innerHTML = 
                    '<div class="text-center py-4 text-red-500">Failed to load PCs. Please try again.</div>';
            });
    });
    
    // Bulk Action Toggle
    document.getElementById('bulk-action')?.addEventListener('change', function() {
        if (this.value === 'status') {
            document.getElementById('bulk-status-fields').style.display = 'block';
        } else {
            document.getElementById('bulk-status-fields').style.display = 'none';
        }
    });
    
    // Select/Deselect All PCs
    document.getElementById('select-all-pcs')?.addEventListener('click', () => {
        document.querySelectorAll('#bulk-pc-selection input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = true;
        });
    });
    
    document.getElementById('deselect-all-pcs')?.addEventListener('click', () => {
        document.querySelectorAll('#bulk-pc-selection input[type="checkbox"]').forEach(checkbox => {
            checkbox.checked = false;
        });
    });
    
    // PC Search Filter
    document.getElementById('pc-search')?.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const statusFilter = document.getElementById('status-filter').value.toLowerCase();
        
        document.querySelectorAll('.pc-card').forEach(card => {
            const pcName = card.querySelector('h5').textContent.toLowerCase();
            const status = card.querySelector('.rounded.text-xs').textContent.toLowerCase();
            
            const matchesSearch = pcName.includes(searchTerm);
            const matchesStatus = statusFilter === 'all' || status === statusFilter;
            
            card.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    });
    
    // Status Filter Change
    document.getElementById('status-filter')?.addEventListener('change', function() {
        const searchEvent = new Event('input');
        document.getElementById('pc-search').dispatchEvent(searchEvent);
    });
    
    // Lab Form Submit
    document.getElementById('lab-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const labId = document.getElementById('lab-id').value;
        const labName = document.getElementById('lab-name').value;
        const capacity = document.getElementById('lab-capacity').value;
        
        const formData = new FormData();
        formData.append('lab_id', labId);
        formData.append('lab_name', labName);
        formData.append('capacity', capacity);
        
        fetch('ajax/save_lab.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to show changes
                window.location.href = `manage_pcs.php?lab=${data.lab_id}`;
            } else {
                alert(data.message || 'Error saving laboratory. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    });
    
    // PC Form Submit
    document.getElementById('pc-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const editMode = document.getElementById('edit-mode').value;
        const labId = document.getElementById('lab-select').value;
        
        if (editMode === 'single') {
            // Single PC edit/add
            const pcId = document.getElementById('pc-id').value;
            const pcNumber = document.getElementById('pc-number').value;
            const status = document.getElementById('pc-status').value;
            
            const formData = new FormData();
            formData.append('lab_id', labId);
            formData.append('pc_id', pcId);
            formData.append('pc_number', pcNumber);
            formData.append('status', status);
            
            fetch('ajax/save_pc.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to show changes
                    window.location.reload();
                } else {
                    alert(data.message || 'Error saving PC. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        } else {
            // Bulk PC add
            const count = parseInt(document.getElementById('pc-count').value) || 1;
            const startNumber = parseInt(document.getElementById('pc-start-number').value) || 1;
            const status = document.getElementById('bulk-pc-status').value;
            
            // Get lab capacity and current PC count
            const labCapacity = <?php echo $lab_details ? $lab_details['CAPACITY'] : 0; ?>;
            const currentPCCount = <?php echo count($pcs); ?>;
            
            // Check if adding these PCs would exceed capacity
            if (currentPCCount + count > labCapacity) {
                alert(`Cannot add ${count} PCs. This would exceed the laboratory capacity of ${labCapacity} PCs. Currently have ${currentPCCount} PCs.`);
                return;
            }
            
            // Check for valid count
            if (count <= 0) {
                alert("Please enter a valid number of PCs to add (greater than 0).");
                return;
            }
            
            const formData = new FormData();
            formData.append('lab_id', labId);
            formData.append('count', count);
            formData.append('start_number', startNumber);
            formData.append('status', status);
            
            fetch('ajax/bulk_add_pcs.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to show changes
                    window.location.reload();
                } else {
                    // Show specific error message from server if available
                    alert(data.message || 'Error adding PCs. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    });
    
    // Bulk Edit Form Submit
    document.getElementById('bulk-edit-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selectedPcs = Array.from(document.querySelectorAll('#bulk-pc-selection input[type="checkbox"]:checked')).map(cb => cb.value);
        
        if (selectedPcs.length === 0) {
            alert('Please select at least one PC.');
            return;
        }
        
        const action = document.getElementById('bulk-action').value;
        const formData = new FormData();
        formData.append('action', action);
        formData.append('pcs', JSON.stringify(selectedPcs));
        
        if (action === 'status') {
            const status = document.getElementById('bulk-edit-status').value;
            formData.append('status', status);
        }
        
        fetch('ajax/bulk_update_pcs.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload page to show changes
                window.location.reload();
            } else {
                alert(data.message || 'Error updating PCs. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    });
    
    // Delete Confirmation
    document.getElementById('confirm-delete-btn')?.addEventListener('click', function() {
        const type = this.dataset.type;
        const id = this.dataset.id;
        
        const formData = new FormData();
        formData.append('type', type);
        formData.append('id', id);
        
        fetch('ajax/delete_item.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (type === 'lab') {
                    // Redirect to the page without the lab parameter
                    window.location.href = 'manage_pcs.php';
                } else {
                    // Just reload the page
                    window.location.reload();
                }
            } else {
                alert(data.message || 'Error deleting item. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
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
    
    /* Fixed height for PC grid container with smooth scrolling */
    .pc-grid-container {
        max-height: 70vh;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e0 #f1f1f1;
        padding-right: 4px;
    }
    
    /* Action button styling */
    .action-btn {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.375rem;
        transition: all 0.2s;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
    }
    
    /* PC card styling */
    .pc-card {
        transition: all 0.2s ease;
    }
    
    .pc-card:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .pc-card {
        transition: all 0.2s ease;
    }
    
    .pc-card:hover { include('includes/footer.php'); ?>        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);    }</style><?php include('includes/footer.php'); ?>        .pc-card:hover { include('includes/footer.php'); ?>        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);    }</style><?php include('includes/footer.php'); ?>