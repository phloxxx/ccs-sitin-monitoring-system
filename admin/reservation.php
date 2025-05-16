<?php 
session_start();
require_once('../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
// Make sure we're getting the admin username from the correct session variable
$username = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : $_SESSION['username'];

// Also fetch complete admin data from database to ensure correct information
try {
    $stmt = $conn->prepare("SELECT * FROM ADMIN WHERE ADMIN_ID = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Override the username with correct admin username from database
        $username = $row['USERNAME'];
        // Store correct username back into session
        $_SESSION['username'] = $username;
    }
    $stmt->close();
} catch (Exception $e) {
    // If error, continue with session username
}

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

// Get PC statistics
$available_count = 0;
$reserved_count = 0;
$in_use_count = 0;
$maintenance_count = 0;

try {
    // Count PCs by status
    $stmt = $conn->prepare("SELECT STATUS, COUNT(*) as count FROM PC GROUP BY STATUS");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $status = strtoupper(trim($row['STATUS']));
        switch($status) {
            case 'AVAILABLE':
                $available_count = $row['count'];
                break;
            case 'RESERVED':
                $reserved_count = $row['count'];
                break;
            case 'IN_USE':
                $in_use_count = $row['count'];
                break;
            case 'MAINTENANCE':
                $maintenance_count = $row['count'];
                break;
        }
    }
    $stmt->close();
} catch (Exception $e) {
    // If error, counts remain at default 0
}

// Default lab ID to the first lab
$selected_lab_id = isset($_GET['lab']) ? intval($_GET['lab']) : 
                  (!empty($laboratories) ? $laboratories[0]['LAB_ID'] : 0);

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
        
        // Get lab name for selected lab
        $lab_name = "";
        foreach($laboratories as $lab) {
            if ($lab['LAB_ID'] == $selected_lab_id) {
                $lab_name = $lab['LAB_NAME'];
                break;
            }
        }
    } catch (Exception $e) {
        // If error, use empty array
        $pcs = [];
    }
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

// Fetch pending reservation requests from database
$pendingRequests = [];
try {
    $stmt = $conn->prepare("
        SELECT r.RESERVATION_ID, r.IDNO, r.START_DATETIME, r.END_DATETIME, r.PC_NUMBER, r.PURPOSE, 
               l.LAB_NAME, u.USERNAME, u.FIRSTNAME, u.LASTNAME, u.PROFILE_PIC
        FROM RESERVATION r
        JOIN LABORATORY l ON r.LAB_ID = l.LAB_ID
        JOIN USERS u ON r.IDNO = u.IDNO
        WHERE r.STATUS = 'PENDING'
        ORDER BY r.REQUEST_DATE DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pendingRequests[] = $row;
    }
    $stmt->close();
    
    // Update pendingCount to actual count
    $pendingCount = count($pendingRequests);
} catch (Exception $e) {
    // If error, default count is 0
    $pendingRequests = [];
}

// Fetch today's scheduled reservations
$todaysReservations = [];
try {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT r.RESERVATION_ID, r.IDNO, r.START_DATETIME, r.END_DATETIME, r.PC_NUMBER, r.PURPOSE, r.STATUS,
               l.LAB_NAME, u.USERNAME, u.FIRSTNAME, u.LASTNAME, u.PROFILE_PIC
        FROM RESERVATION r
        JOIN LABORATORY l ON r.LAB_ID = l.LAB_ID
        JOIN USERS u ON r.IDNO = u.IDNO
        WHERE DATE(r.START_DATETIME) = ? AND r.STATUS = 'APPROVED'
        ORDER BY r.START_DATETIME ASC
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $todaysReservations[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // If error, use empty array
    $todaysReservations = [];
}

// Get current date for calendar
$currentDate = date('Y-m-d');

$pageTitle = "Reservation Management";
$bodyClass = "bg-gray-50 font-poppins";
include('includes/header.php');
?>

<div class="flex h-screen bg-gray-50">
    <!-- Sidebar -->
    <div class="hidden md:flex md:flex-shrink-0">
        <div class="flex flex-col w-64 bg-secondary shadow-lg">
            <!-- Added Logos -->                
            <div class="flex flex-col items-center pt-5 pb-2">
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
        <main class="flex-1 overflow-y-auto p-5 bg-gray-50">
            <!-- Dashboard Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500">Total Laboratories</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?php echo count($laboratories); ?></h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full text-blue-500">
                            <i class="fas fa-building text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-green-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500">Available PCs</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?php echo $available_count; ?></h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full text-green-500">
                            <i class="fas fa-desktop text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-amber-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500">Reserved PCs</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?php echo $reserved_count; ?></h3>
                        </div>
                        <div class="bg-amber-100 p-3 rounded-full text-amber-500">
                            <i class="fas fa-user-clock text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500">Pending Requests</p>
                            <h3 class="text-2xl font-bold text-gray-800"><?php echo $pendingCount; ?></h3>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full text-purple-500">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100 lg:col-span-5">
                    <div class="bg-white px-5 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-desktop text-blue-500 mr-2"></i> Computer Lab Status
                        </h3>
                    </div>
                    
                    <!-- Rest of Computer Lab Status panel content remains the same -->
                    <div class="p-5">
                        <div class="mb-5">
                            <div class="flex items-center justify-between mb-3">
                                <label for="lab-select" class="block text-sm font-medium text-gray-700">
                                    Select Laboratory:
                                </label>
                                <a href="manage_pcs.php" class="text-blue-600 text-xs font-medium hover:underline">
                                    <i class="fas fa-cog mr-1"></i> Configure
                                </a>
                            </div>
                            <div class="relative">
                                <select id="lab-select" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 appearance-none text-sm transition-shadow duration-150">
                                    <?php foreach ($laboratories as $lab): ?>
                                        <option value="<?php echo $lab['LAB_ID']; ?>" <?php echo ($lab['LAB_ID'] == $selected_lab_id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lab['LAB_NAME']); ?> (<?php echo $lab['CAPACITY']; ?> PCs)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-medium text-gray-500"><?php echo htmlspecialchars($lab_name); ?></h4>
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-green-500 mr-1.5"></span>
                                    <span class="text-xs text-gray-600">Available</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-amber-500 mr-1.5"></span>
                                    <span class="text-xs text-gray-600">Reserved</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-blue-500 mr-1.5"></span>
                                    <span class="text-xs text-gray-600">In Use</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="h-3 w-3 rounded-full bg-red-500 mr-1.5"></span>
                                    <span class="text-xs text-gray-600">Maintenance</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-5 gap-2 h-48 overflow-y-auto custom-scrollbar" id="pc-grid">
                            <?php if (count($pcs) > 0): ?>
                                <?php foreach ($pcs as $pc):
                                    $statusClass = '';
                                    $statusLabel = '';
                                    switch(strtoupper(trim($pc['STATUS'] ?? 'AVAILABLE'))) {
                                        case 'AVAILABLE':
                                            $statusClass = 'bg-green-100 text-green-600 ring-green-200';
                                            $statusLabel = 'Available';
                                            break;
                                        case 'RESERVED':
                                            $statusClass = 'bg-amber-100 text-amber-600 ring-amber-200';
                                            $statusLabel = 'Reserved';
                                            break;
                                        case 'IN_USE':
                                            $statusClass = 'bg-blue-100 text-blue-600 ring-blue-200';
                                            $statusLabel = 'In Use';
                                            break;
                                        case 'MAINTENANCE':
                                            $statusClass = 'bg-red-100 text-red-600 ring-red-200';
                                            $statusLabel = 'Maintenance';
                                            break;
                                        default:
                                            $statusClass = 'bg-gray-100 text-gray-600 ring-gray-200';
                                            $statusLabel = 'Unknown';
                                    }
                                ?>
                                <div class="pc-item text-center p-2 rounded-lg ring-1 cursor-pointer hover:shadow-md transition-all <?php echo $statusClass; ?>"
                                     data-pc-id="<?php echo $pc['PC_ID']; ?>" 
                                     data-pc-number="<?php echo $pc['PC_NUMBER']; ?>"
                                     data-pc-status="<?php echo htmlspecialchars($pc['STATUS']); ?>">
                                    <div class="text-xs font-medium mb-1">PC-<?php echo sprintf("%02d", $pc['PC_NUMBER']); ?></div>
                                    <div class="text-[10px]"><?php echo $statusLabel; ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-span-5 text-center py-12">
                                    <p class="text-gray-500">No PCs configured for this laboratory.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-4 flex justify-center">
                            <button class="bg-blue-500 hover:bg-blue-600 text-white text-sm py-2 px-4 rounded-lg transition-colors" id="refresh-status">
                                <i class="fas fa-sync-alt mr-2"></i> Refresh Status
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Requests Panel - Adjusted to 7/12 columns -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100 lg:col-span-7">
                    <div class="bg-white px-5 py-4 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-clock text-purple-500 mr-2"></i> Pending Requests
                        </h3>
                        <div class="bg-purple-100 text-purple-600 px-2.5 py-1 rounded-lg text-xs font-medium">
                            <?php echo $pendingCount; ?> Pending
                        </div>
                    </div>
                    
                    <!-- Rest of Pending Requests panel content remains the same -->
                    <div class="p-4">
                        <?php if ($pendingCount > 0): ?>
                            <div class="grid grid-cols-1 gap-3">
                                <?php foreach($pendingRequests as $request): ?>
                                    <div class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
                                        <div class="flex items-start gap-4">
                                            <div class="flex-shrink-0">
                                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-500 overflow-hidden">
                                                    <?php if (!empty($request['PROFILE_PIC'])): ?>
                                                        <img src="../student/<?php echo htmlspecialchars($request['PROFILE_PIC']); ?>" 
                                                             alt="Profile" class="h-full w-full object-cover rounded-full"
                                                             onerror="this.onerror=null; this.src='../student/images/default-avatar.png';">
                                                    <?php else: ?>
                                                        <i class="fas fa-user-graduate"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="flex-grow">
                                                <div class="flex justify-between items-start">
                                                    <div>
                                                        <h4 class="font-medium text-gray-800">
                                                            <?php echo htmlspecialchars($request['FIRSTNAME'] . ' ' . $request['LASTNAME']); ?>
                                                        </h4>
                                                        <p class="text-xs text-gray-500">Student ID: <?php echo htmlspecialchars($request['IDNO']); ?></p>
                                                    </div>
                                                    <div class="text-xs bg-blue-100 text-blue-600 px-2.5 py-1 rounded-md">
                                                        <?php 
                                                            $requestDate = new DateTime($request['START_DATETIME']);
                                                            echo $requestDate->format('M j, g:i A'); 
                                                        ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex flex-wrap gap-2 mt-3">
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                                                        <i class="fas fa-desktop mr-1.5 text-blue-500"></i> PC-<?php echo sprintf("%02d", $request['PC_NUMBER']); ?>
                                                    </span>
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                                                        <i class="fas fa-clock mr-1.5 text-green-500"></i> 
                                                        <?php 
                                                            $start = new DateTime($request['START_DATETIME']);
                                                            $end = new DateTime($request['END_DATETIME']);
                                                            $duration = $start->diff($end);
                                                            echo $duration->format('%h hours');
                                                        ?>
                                                    </span>
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                                                        <i class="fas fa-building mr-1.5 text-amber-500"></i> <?php echo htmlspecialchars($request['LAB_NAME']); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="mt-3 text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                                    Purpose: <?php echo htmlspecialchars($request['PURPOSE']); ?>
                                                </div>
                                                
                                                <div class="mt-3 flex items-center gap-3">
                                                    <button class="approve-request bg-green-500 hover:bg-green-600 text-white text-xs px-3 py-1.5 rounded flex items-center" 
                                                            data-id="<?php echo $request['RESERVATION_ID']; ?>">
                                                        <i class="fas fa-check mr-1.5"></i> Approve
                                                    </button>
                                                    <button class="reject-request bg-red-500 hover:bg-red-600 text-white text-xs px-3 py-1.5 rounded flex items-center"
                                                            data-id="<?php echo $request['RESERVATION_ID']; ?>">
                                                        <i class="fas fa-times mr-1.5"></i> Reject
                                                    </button>
                                                    <button class="view-details bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs px-3 py-1.5 rounded flex items-center ml-auto"
                                                            data-id="<?php echo $request['RESERVATION_ID']; ?>"
                                                            data-student="<?php echo htmlspecialchars($request['FIRSTNAME'] . ' ' . $request['LASTNAME']); ?>"
                                                            data-idno="<?php echo htmlspecialchars($request['IDNO']); ?>"
                                                            data-pc="<?php echo sprintf("%02d", $request['PC_NUMBER']); ?>"
                                                            data-lab="<?php echo htmlspecialchars($request['LAB_NAME']); ?>"
                                                            data-start="<?php echo htmlspecialchars($request['START_DATETIME']); ?>"
                                                            data-end="<?php echo htmlspecialchars($request['END_DATETIME']); ?>"
                                                            data-purpose="<?php echo htmlspecialchars($request['PURPOSE']); ?>">
                                                        <i class="fas fa-eye mr-1.5"></i> Details
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-500 mb-4">
                                    <i class="fas fa-check-circle text-3xl"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-800 mb-2">All Caught Up!</h3>
                                <p class="text-sm text-gray-500 max-w-sm mx-auto">No pending reservation requests at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Reservation Schedule Panel - Full width -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100 lg:col-span-12">
                    <div class="bg-white px-5 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-calendar-alt text-indigo-500 mr-2"></i> Today's Reservation Schedule
                        </h3>
                    </div>
                    
                    <div class="p-4">
                        <div class="flex justify-between items-center mb-4">
                            <div class="flex gap-2">
                                <button class="bg-indigo-500 hover:bg-indigo-600 text-white text-xs px-3 py-1.5 rounded">
                                    Today
                                </button>
                            </div>
                            <a href="reservation_history.php" class="text-white bg-purple-500 hover:bg-purple-600 transition-colors px-5 py-2 rounded text-sm font-medium inline-flex items-center shadow-sm">
                                <i class="fas fa-list mr-2"></i> View Complete History
                            </a>
                        </div>
                        
                        <?php if (count($todaysReservations) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Student
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                PC / Lab
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Time
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Purpose
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach($todaysReservations as $reservation): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 rounded-full overflow-hidden bg-gray-100">
                                                            <?php if (!empty($reservation['PROFILE_PIC'])): ?>
                                                                <img src="../student/<?php echo htmlspecialchars($reservation['PROFILE_PIC']); ?>" 
                                                                     alt="Profile" class="h-full w-full object-cover"
                                                                     onerror="this.onerror=null; this.src='../student/images/default-avatar.png';">
                                                            <?php else: ?>
                                                                <div class="h-full w-full flex items-center justify-center text-gray-500">
                                                                    <i class="fas fa-user-graduate"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($reservation['FIRSTNAME'] . ' ' . $reservation['LASTNAME']); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                <?php echo htmlspecialchars($reservation['IDNO']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium">PC-<?php echo sprintf("%02d", $reservation['PC_NUMBER']); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($reservation['LAB_NAME']); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php 
                                                        $start = new DateTime($reservation['START_DATETIME']);
                                                        $end = new DateTime($reservation['END_DATETIME']);
                                                    ?>
                                                    <div class="text-sm"><?php echo $start->format('g:i A'); ?> - <?php echo $end->format('g:i A'); ?></div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php 
                                                            $duration = $start->diff($end);
                                                            echo $duration->format('%h hours');
                                                        ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900 max-w-xs truncate">
                                                        <?php echo htmlspecialchars($reservation['PURPOSE']); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Approved
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                                                    <button class="view-details text-blue-600 hover:text-blue-900 font-medium mr-2"
                                                            data-id="<?php echo $reservation['RESERVATION_ID']; ?>"
                                                            data-student="<?php echo htmlspecialchars($reservation['FIRSTNAME'] . ' ' . $reservation['LASTNAME']); ?>"
                                                            data-idno="<?php echo htmlspecialchars($reservation['IDNO']); ?>"
                                                            data-pc="<?php echo sprintf("%02d", $reservation['PC_NUMBER']); ?>"
                                                            data-lab="<?php echo htmlspecialchars($reservation['LAB_NAME']); ?>"
                                                            data-start="<?php echo htmlspecialchars($reservation['START_DATETIME']); ?>"
                                                            data-end="<?php echo htmlspecialchars($reservation['END_DATETIME']); ?>"
                                                            data-purpose="<?php echo htmlspecialchars($reservation['PURPOSE']); ?>">
                                                        Details
                                                    </button>
                                                    <button class="cancel-reservation text-red-600 hover:text-red-900 font-medium"
                                                            data-id="<?php echo $reservation['RESERVATION_ID']; ?>">
                                                        Cancel
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="bg-gray-50 rounded-lg p-8 text-center">
                                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 text-indigo-500 mb-4">
                                    <i class="fas fa-calendar-day text-xl"></i>
                                </div>
                                <h4 class="text-lg font-medium text-gray-800 mb-1">No Reservations Today</h4>
                                <p class="text-gray-500 text-sm">There are no approved reservations scheduled for today.</p>
                            </div>
                        <?php endif; ?>
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

<!-- Reservation Details Modal -->
<div id="reservation-details-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold text-gray-800 flex items-center" id="modal-reservation-title">
                <i class="fas fa-calendar-check text-blue-500 mr-3"></i>
                Reservation Details
            </h3>
            <button id="close-reservation-modal" class="text-gray-400 hover:text-gray-600 p-1.5 hover:bg-gray-100 rounded-full transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mb-6" id="reservation-modal-content">
            <div class="flex items-center mb-5">
                <div class="w-16 h-16 bg-blue-50 flex items-center justify-center rounded-full mr-5 ring-1 ring-blue-100">
                    <i class="fas fa-user-graduate text-blue-500 text-2xl"></i>
                </div>
                <div>
                    <div class="text-lg font-medium text-gray-800" id="reservation-modal-student">John Doe</div>
                    <div class="text-sm text-gray-500 mt-1" id="reservation-modal-idno">20001234</div>
                </div>
            </div>
            
            <div class="border-t border-gray-100 pt-5">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide">PC Number</div>
                        <div class="font-medium text-base" id="reservation-modal-pc">PC-01</div>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Laboratory</div>
                        <div class="font-medium text-base" id="reservation-modal-lab">Laboratory 524</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Start Time</div>
                        <div class="font-medium text-base" id="reservation-modal-start">10:00 AM</div>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide">End Time</div>
                        <div class="font-medium text-base" id="reservation-modal-end">12:00 PM</div>
                    </div>
                </div>
                
                <div class="bg-gray-50 p-3 rounded-lg mb-4">
                    <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Purpose</div>
                    <div class="font-medium text-base" id="reservation-modal-purpose">Programming assignment for CCS104</div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3" id="reservation-modal-actions">
            <button id="reservation-modal-approve" class="px-4 py-2.5 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors font-medium text-sm flex items-center hidden">
                <i class="fas fa-check mr-2"></i>
                Approve
            </button>
            <button id="reservation-modal-reject" class="px-4 py-2.5 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors font-medium text-sm flex items-center hidden">
                <i class="fas fa-times mr-2"></i>
                Reject
            </button>
            <button id="reservation-modal-cancel" class="px-4 py-2.5 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors font-medium text-sm flex items-center hidden">
                <i class="fas fa-ban mr-2"></i>
                Cancel Reservation
            </button>
            <button id="reservation-modal-close" class="px-4 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium text-sm flex items-center">
                <i class="fas fa-times mr-2"></i>
                Close
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
    
    // Lab selection change - immediate redirection
    document.getElementById('lab-select').addEventListener('change', function() {
        const labId = this.value;
        window.location.href = `reservation.php?lab=${labId}`;
    });
    
    // Refresh status button functionality
    document.getElementById('refresh-status').addEventListener('click', function() {
        // Reload the current page to refresh PC status
        window.location.reload();
    });
    
    // Helper function to handle reservation actions with error handling
    function handleReservationAction(action, id, successMessage) {
        // Show loading state or disable button if needed
        
        // Send AJAX request
        fetch('ajax/update_reservation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=${action}&id=${id}`
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(successMessage);
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error occurred'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request. Please try again.');
        });
    }
    
    // Approve reservation request
    document.querySelectorAll('.approve-request').forEach(button => {
        button.addEventListener('click', function() {
            const reservationId = this.getAttribute('data-id');
            if (confirm('Are you sure you want to approve this reservation request?')) {
                handleReservationAction('approve', reservationId, 'Reservation approved successfully! The student has been notified.');
            }
        });
    });
    
    // Reject reservation request
    document.querySelectorAll('.reject-request').forEach(button => {
        button.addEventListener('click', function() {
            const reservationId = this.getAttribute('data-id');
            if (confirm('Are you sure you want to reject this reservation request?')) {
                handleReservationAction('reject', reservationId, 'Reservation rejected successfully! The student has been notified.');
            }
        });
    });
    
    // Reservation Details Modal
    const reservationDetailsModal = document.getElementById('reservation-details-modal');
    const closeReservationModal = document.getElementById('close-reservation-modal');
    const reservationModalClose = document.getElementById('reservation-modal-close');
    const reservationModalApprove = document.getElementById('reservation-modal-approve');
    const reservationModalReject = document.getElementById('reservation-modal-reject');
    const reservationModalCancel = document.getElementById('reservation-modal-cancel');
    
    // View details button click event
    document.querySelectorAll('.view-details').forEach(button => {
        button.addEventListener('click', function() {
            const reservationId = this.getAttribute('data-id');
            const student = this.getAttribute('data-student');
            const idno = this.getAttribute('data-idno');
            const pc = this.getAttribute('data-pc');
            const lab = this.getAttribute('data-lab');
            const start = new Date(this.getAttribute('data-start'));
            const end = new Date(this.getAttribute('data-end'));
            const purpose = this.getAttribute('data-purpose');
            
            // Format dates
            const startFormatted = start.toLocaleString('en-US', {
                hour: 'numeric',
                minute: 'numeric',
                hour12: true,
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
            
            const endFormatted = end.toLocaleString('en-US', {
                hour: 'numeric',
                minute: 'numeric',
                hour12: true,
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
            
            // Update modal content
            document.getElementById('reservation-modal-student').textContent = student;
            document.getElementById('reservation-modal-idno').textContent = idno;
            document.getElementById('reservation-modal-pc').textContent = 'PC-' + pc;
            document.getElementById('reservation-modal-lab').textContent = lab;
            document.getElementById('reservation-modal-start').textContent = startFormatted;
            document.getElementById('reservation-modal-end').textContent = endFormatted;
            document.getElementById('reservation-modal-purpose').textContent = purpose;
            
            // Show/hide action buttons based on status
            if (this.closest('tr') && this.closest('tr').querySelector('.bg-green-100')) {
                // Approved reservation - show cancel button
                reservationModalApprove.classList.add('hidden');
                reservationModalReject.classList.add('hidden');
                reservationModalCancel.classList.remove('hidden');
                reservationModalCancel.setAttribute('data-id', reservationId);
            } else {
                // Pending reservation - show approve/reject buttons
                reservationModalApprove.classList.remove('hidden');
                reservationModalReject.classList.remove('hidden');
                reservationModalCancel.classList.add('hidden');
                reservationModalApprove.setAttribute('data-id', reservationId);
                reservationModalReject.setAttribute('data-id', reservationId);
            }
            
            // Show the modal
            reservationDetailsModal.classList.remove('hidden');
        });
    });
    
    // Close modal buttons
    [closeReservationModal, reservationModalClose].forEach(button => {
        button.addEventListener('click', () => {
            reservationDetailsModal.classList.add('hidden');
        });
    });
    
    // Click outside modal to close
    reservationDetailsModal.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
    
    // Approve reservation from modal
    reservationModalApprove.addEventListener('click', function() {
        const reservationId = this.getAttribute('data-id');
        if (confirm('Are you sure you want to approve this reservation request?')) {
            handleReservationAction('approve', reservationId, 'Reservation approved successfully! The student has been notified.');
        }
    });
    
    // Reject reservation from modal
    reservationModalReject.addEventListener('click', function() {
        const reservationId = this.getAttribute('data-id');
        if (confirm('Are you sure you want to reject this reservation request?')) {
            handleReservationAction('reject', reservationId, 'Reservation rejected successfully! The student has been notified.');
        }
    });
    
    // Cancel reservation
    reservationModalCancel.addEventListener('click', function() {
        const reservationId = this.getAttribute('data-id');
        if (confirm('Are you sure you want to cancel this approved reservation?')) {
            handleReservationAction('cancel', reservationId, 'Reservation cancelled successfully! The student has been notified.');
        }
    });
    
    // Cancel reservation from the schedule table
    document.querySelectorAll('.cancel-reservation').forEach(button => {
        button.addEventListener('click', function() {
            const reservationId = this.getAttribute('data-id');
            if (confirm('Are you sure you want to cancel this reservation?')) {
                handleReservationAction('cancel', reservationId, 'Reservation cancelled successfully! The student has been notified.');
            }
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
