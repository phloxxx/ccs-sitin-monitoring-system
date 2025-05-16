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

// Count pending reservations
$pendingCount = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM RESERVATION WHERE STATUS = 'PENDING'");
$stmt->execute();
$result = $stmt->get_result();
$pendingCount = $result->fetch_assoc()['count'];
$stmt->close();

// Set default filter values
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build SQL query based on filters
$whereClause = [];
$params = [];
$types = "";

// Add status filter if not "all"
if ($status !== 'all') {
    $whereClause[] = "r.STATUS = ?";
    $params[] = $status;
    $types .= "s";
}

// Add date range filter
$whereClause[] = "DATE(r.START_DATETIME) BETWEEN ? AND ?";
$params[] = $dateFrom;
$params[] = $dateTo;
$types .= "ss";

$where = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total 
    FROM RESERVATION r 
    $where
";

try {
    $stmt = $conn->prepare($countQuery);
    if (!empty($types)) {
        // Fix: Create a merged parameters array instead of using spread operator with positional arguments
        $bindParams = array_merge([$types], $params);
        $stmt->bind_param(...$bindParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $totalRecords = $result->fetch_assoc()['total'];
    $totalPages = ceil($totalRecords / $perPage);
    $stmt->close();
} catch (Exception $e) {
    $totalRecords = 0;
    $totalPages = 1;
}

// Get reservations with pagination
$reservations = [];
try {
    $query = "
        SELECT r.RESERVATION_ID, r.IDNO, r.START_DATETIME, r.END_DATETIME, 
               r.PC_NUMBER, r.PURPOSE, r.STATUS, r.REQUEST_DATE, r.UPDATED_AT,
               l.LAB_NAME, u.USERNAME, u.FIRSTNAME, u.LASTNAME, u.PROFILE_PIC
        FROM RESERVATION r
        JOIN LABORATORY l ON r.LAB_ID = l.LAB_ID
        JOIN USERS u ON r.IDNO = u.IDNO
        $where
        ORDER BY r.REQUEST_DATE DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($query);
    if (!empty($types)) {
        // Fix: Create a merged parameters array with limit and offset appended
        $allParams = $params;
        $allParams[] = $perPage;
        $allParams[] = $offset;
        $bindParams = array_merge([$types . "ii"], $allParams);
        $stmt->bind_param(...$bindParams);
    } else {
        $stmt->bind_param("ii", $perPage, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // If error, use empty array
    $reservations = [];
}

$pageTitle = "Reservation History";
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
                        Reservation History
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
        <main class="flex-1 overflow-y-auto p-5 bg-gray-50">
            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <a href="reservation.php" class="inline-flex items-center px-3 py-2 text-large font-medium text-secondary">
                    <i class="fas fa-arrow-left mr-2"></i>
                </a>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm mb-6 p-5">
                <h3 class="text-lg font-medium text-gray-700 mb-4">Filter Records</h3>
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="PENDING" <?php echo $status === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                            <option value="APPROVED" <?php echo $status === 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                            <option value="REJECTED" <?php echo $status === 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="CANCELLED" <?php echo $status === 'CANCELLED' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="COMPLETED" <?php echo $status === 'COMPLETED' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo $dateTo; ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md text-sm w-full">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Results Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-700">Reservations</h3>
                    <span class="text-sm text-gray-500">
                        <?php echo number_format($totalRecords); ?> records found
                    </span>
                </div>
                
                <?php if (count($reservations) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Student
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Schedule
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        PC / Lab
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Request Date
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8 rounded-full overflow-hidden bg-gray-100">
                                                    <?php if (!empty($reservation['PROFILE_PIC'])): ?>
                                                        <img src="<?php echo htmlspecialchars('../student/uploads/' . $reservation['PROFILE_PIC']); ?>" 
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
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">
                                                <?php 
                                                    $start = new DateTime($reservation['START_DATETIME']);
                                                    echo $start->format('M j, Y, g:i A'); 
                                                ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php 
                                                    $end = new DateTime($reservation['END_DATETIME']);
                                                    $duration = $start->diff($end);
                                                    echo $duration->format('%h hours');
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium">PC-<?php echo sprintf("%02d", $reservation['PC_NUMBER']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($reservation['LAB_NAME']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                                $statusClass = '';
                                                switch(strtoupper($reservation['STATUS'])) {
                                                    case 'PENDING':
                                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'APPROVED':
                                                        $statusClass = 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'REJECTED':
                                                        $statusClass = 'bg-red-100 text-red-800';
                                                        break;
                                                    case 'CANCELLED':
                                                        $statusClass = 'bg-gray-100 text-gray-800';
                                                        break;
                                                    case 'COMPLETED':
                                                        $statusClass = 'bg-blue-100 text-blue-800';
                                                        break;
                                                    default:
                                                        $statusClass = 'bg-gray-100 text-gray-800';
                                                }
                                            ?>
                                            <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($reservation['STATUS']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php 
                                                $requestDate = new DateTime($reservation['REQUEST_DATE']);
                                                echo $requestDate->format('M j, Y, g:i A'); 
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-xs">
                                            <button class="view-details text-blue-600 hover:text-blue-900 font-medium"
                                                    data-id="<?php echo $reservation['RESERVATION_ID']; ?>"
                                                    data-student="<?php echo htmlspecialchars($reservation['FIRSTNAME'] . ' ' . $reservation['LASTNAME']); ?>"
                                                    data-idno="<?php echo htmlspecialchars($reservation['IDNO']); ?>"
                                                    data-pc="<?php echo sprintf("%02d", $reservation['PC_NUMBER']); ?>"
                                                    data-lab="<?php echo htmlspecialchars($reservation['LAB_NAME']); ?>"
                                                    data-start="<?php echo htmlspecialchars($reservation['START_DATETIME']); ?>"
                                                    data-end="<?php echo htmlspecialchars($reservation['END_DATETIME']); ?>"
                                                    data-purpose="<?php echo htmlspecialchars($reservation['PURPOSE']); ?>"
                                                    data-status="<?php echo htmlspecialchars($reservation['STATUS']); ?>">
                                                Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-500">
                                    Showing <?php echo ($page - 1) * $perPage + 1; ?> to <?php echo min($page * $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> entries
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <!-- Previous Page Link -->
                                        <a href="?status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&page=<?php echo max(1, $page - 1); ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Previous</span>
                                            <i class="fas fa-chevron-left text-xs"></i>
                                        </a>
                                        
                                        <!-- Page Numbers -->
                                        <?php 
                                        $startPage = max(1, min($page - 2, $totalPages - 4));
                                        $endPage = min($startPage + 4, $totalPages);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++): 
                                        ?>
                                            <a href="?status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&page=<?php echo $i; ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?php echo ($i == $page) ? 'bg-blue-50 text-blue-600 z-10' : 'bg-white text-gray-500 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <!-- Next Page Link -->
                                        <a href="?status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&page=<?php echo min($totalPages, $page + 1); ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only">Next</span>
                                            <i class="fas fa-chevron-right text-xs"></i>
                                        </a>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="p-8 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 text-gray-400 mb-4">
                            <i class="fas fa-calendar-times text-xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-800 mb-1">No Reservations Found</h4>
                        <p class="text-gray-500 text-sm">Try adjusting your filters or create a new reservation.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
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
                
                <div class="bg-gray-50 p-3 rounded-lg">
                    <div class="text-xs text-gray-500 mb-1 uppercase tracking-wide">Status</div>
                    <div class="font-medium text-base" id="reservation-modal-status">
                        <span id="status-badge" class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Approved
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button id="reservation-modal-close" class="px-4 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium text-sm flex items-center">
                <i class="fas fa-times mr-2"></i>
                Close
            </button>
        </div>
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
    
    // View reservation details
    const reservationDetailsModal = document.getElementById('reservation-details-modal');
    const closeReservationModal = document.getElementById('close-reservation-modal');
    const reservationModalClose = document.getElementById('reservation-modal-close');
    
    // View details button click event
    document.querySelectorAll('.view-details').forEach(button => {
        button.addEventListener('click', function() {
            const student = this.getAttribute('data-student');
            const idno = this.getAttribute('data-idno');
            const pc = this.getAttribute('data-pc');
            const lab = this.getAttribute('data-lab');
            const start = new Date(this.getAttribute('data-start'));
            const end = new Date(this.getAttribute('data-end'));
            const purpose = this.getAttribute('data-purpose');
            const status = this.getAttribute('data-status');
            
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
            
            // Set status badge
            const statusBadge = document.getElementById('status-badge');
            statusBadge.textContent = status;
            
            // Set badge color based on status
            switch(status.toUpperCase()) {
                case 'PENDING':
                    statusBadge.className = 'px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800';
                    break;
                case 'APPROVED':
                    statusBadge.className = 'px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800';
                    break;
                case 'REJECTED':
                    statusBadge.className = 'px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800';
                    break;
                case 'CANCELLED':
                    statusBadge.className = 'px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800';
                    break;
                case 'COMPLETED':
                    statusBadge.className = 'px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800';
                    break;
                default:
                    statusBadge.className = 'px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800';
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
</script>

<?php include('includes/footer.php'); ?>
