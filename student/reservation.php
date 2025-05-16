<?php 
session_start();
require_once('../config/db.php'); 

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; 

// Fetch user details from database
$stmt = $conn->prepare("SELECT IDNO, USERNAME, PROFILE_PIC, SESSION FROM USERS WHERE USER_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "User not found!";
    exit();
}

$username = $user['USERNAME'];
$idno = $user['IDNO'];
$remaining_sessions = $user['SESSION'];

// Set up profile picture path handling
$default_pic = "images/snoopy.jpg";
$profile_pic = !empty($user['PROFILE_PIC']) ? $user['PROFILE_PIC'] : $default_pic;

// Check if the user already has an active session
$stmt = $conn->prepare("SELECT COUNT(*) as active_count FROM SITIN WHERE IDNO = ? AND STATUS = 'ACTIVE'");
$stmt->bind_param("s", $idno);
$stmt->execute();
$result = $stmt->get_result();
$active_session = $result->fetch_assoc();
$has_active_session = $active_session['active_count'] > 0;
$stmt->close();

// Fetch available laboratories
$labs_query = "SELECT * FROM LABORATORY WHERE STATUS = 'AVAILABLE' ORDER BY LAB_NAME";
$labs_result = $conn->query($labs_query);
$laboratories = [];
while ($lab = $labs_result->fetch_assoc()) {
    $laboratories[] = $lab;
}

$reservation_success = false;
$reservation_error = '';

// Handle form submission
if (isset($_POST['submit_reservation'])) {
    $lab_id = $_POST['lab_id'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $reservation_date = $_POST['reservation_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $pc_number = $_POST['pc_number'] ?? '';
    
    // Handle "Other" purpose
    if ($purpose === 'Other' && !empty($_POST['other_purpose'])) {
        $purpose = $_POST['other_purpose'];
    }
    
    // Validate input
    if (empty($lab_id) || empty($purpose) || empty($reservation_date) || empty($start_time) || empty($end_time) || empty($pc_number)) {
        $reservation_error = 'Please fill in all required fields.';
    } elseif ($has_active_session) {
        $reservation_error = 'You already have an active session.';
    } elseif ($remaining_sessions <= 0) {
        $reservation_error = 'You do not have enough remaining sessions.';
    } else {
        // Validate time difference doesn't exceed 1 hour 30 minutes
        $start_time_obj = DateTime::createFromFormat('H:i', $start_time);
        $end_time_obj = DateTime::createFromFormat('H:i', $end_time);
        
        $interval = $start_time_obj->diff($end_time_obj);
        $total_minutes = ($interval->h * 60) + $interval->i;
        
        if ($total_minutes > 90) {
            $reservation_error = 'Reservation time cannot exceed 1 hour and 30 minutes.';
        } elseif ($end_time_obj <= $start_time_obj) {
            $reservation_error = 'End time must be after start time.';
        } else {
            $start_datetime = $reservation_date . ' ' . $start_time . ':00';
            $end_datetime = $reservation_date . ' ' . $end_time . ':00';
            
            // Insert reservation request into database
            try {
                $stmt = $conn->prepare("INSERT INTO RESERVATION (IDNO, LAB_ID, PC_NUMBER, PURPOSE, 
                                      START_DATETIME, END_DATETIME, STATUS, REQUEST_DATE) 
                                      VALUES (?, ?, ?, ?, ?, ?, 'PENDING', NOW())");
                
                $stmt->bind_param("siisss", $idno, $lab_id, $pc_number, $purpose, $start_datetime, $end_datetime);
                
                if ($stmt->execute()) {
                    // Set success message in session instead of local variable
                    $_SESSION['reservation_success'] = true;
                    // Redirect to the same page to prevent form resubmission
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $reservation_error = 'Failed to submit reservation: ' . $stmt->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $reservation_error = 'An error occurred: ' . $e->getMessage();
            }
        }
    }
}

// Get success flag from session and clear it
$reservation_success = false;
if (isset($_SESSION['reservation_success'])) {
    $reservation_success = true;
    unset($_SESSION['reservation_success']);
}

// Handle reservation cancellation
if (isset($_POST['cancel_reservation']) && isset($_POST['reservation_id'])) {
    $reservation_id = $_POST['reservation_id'];
    
    try {
        // Update reservation status to CANCELLED
        $stmt = $conn->prepare("UPDATE RESERVATION SET STATUS = 'CANCELLED', UPDATED_AT = NOW() WHERE RESERVATION_ID = ? AND IDNO = ? AND STATUS IN ('PENDING', 'APPROVED')");
        $stmt->bind_param("is", $reservation_id, $idno);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION['cancel_success'] = true;
        } else {
            $_SESSION['cancel_error'] = 'Failed to cancel reservation.';
        }
        $stmt->close();
        
        // Redirect to refresh the page with updated data
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['cancel_error'] = 'An error occurred: ' . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Pagination settings
$items_per_page = 10;
$current_page_pending = isset($_GET['pending_page']) ? intval($_GET['pending_page']) : 1;
$current_page_history = isset($_GET['history_page']) ? intval($_GET['history_page']) : 1;

// Get actual pending reservations AND history for this user with pagination
try {
    // Count total pending/approved reservations
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM RESERVATION 
        WHERE IDNO = ? AND STATUS IN ('PENDING', 'APPROVED')
    ");
    $stmt->bind_param("s", $idno);
    $stmt->execute();
    $result = $stmt->get_result();
    $pending_total = $result->fetch_assoc()['total'];
    $stmt->close();
    
    // Calculate pagination for pending reservations
    $pending_total_pages = ceil($pending_total / $items_per_page);
    if ($current_page_pending < 1) $current_page_pending = 1;
    if ($current_page_pending > $pending_total_pages && $pending_total_pages > 0) $current_page_pending = $pending_total_pages;
    $pending_offset = ($current_page_pending - 1) * $items_per_page;
    
    // Get paginated pending and approved reservations
    $stmt = $conn->prepare("
        SELECT r.RESERVATION_ID, r.START_DATETIME, r.END_DATETIME, 
               r.PC_NUMBER, r.PURPOSE, r.STATUS, l.LAB_NAME, r.REQUEST_DATE
        FROM RESERVATION r
        JOIN LABORATORY l ON r.LAB_ID = l.LAB_ID
        WHERE r.IDNO = ? AND r.STATUS IN ('PENDING', 'APPROVED')
        ORDER BY r.START_DATETIME DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sii", $idno, $items_per_page, $pending_offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $pending_reservations = [];
    while ($row = $result->fetch_assoc()) {
        $pending_reservations[] = $row;
    }
    $stmt->close();

    // Count total history entries
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM RESERVATION 
        WHERE IDNO = ? AND STATUS IN ('COMPLETED', 'CANCELLED', 'REJECTED')
    ");
    $stmt->bind_param("s", $idno);
    $stmt->execute();
    $result = $stmt->get_result();
    $history_total = $result->fetch_assoc()['total'];
    $stmt->close();
    
    // Calculate pagination for history
    $history_total_pages = ceil($history_total / $items_per_page);
    if ($current_page_history < 1) $current_page_history = 1;
    if ($current_page_history > $history_total_pages && $history_total_pages > 0) $current_page_history = $history_total_pages;
    $history_offset = ($current_page_history - 1) * $items_per_page;

    // Get paginated completed and cancelled reservations (history)
    $stmt = $conn->prepare("
        SELECT r.RESERVATION_ID, r.START_DATETIME, r.END_DATETIME, 
               r.PC_NUMBER, r.PURPOSE, r.STATUS, l.LAB_NAME, r.REQUEST_DATE
        FROM RESERVATION r
        JOIN LABORATORY l ON r.LAB_ID = l.LAB_ID
        WHERE r.IDNO = ? AND r.STATUS IN ('COMPLETED', 'CANCELLED', 'REJECTED')
        ORDER BY r.START_DATETIME DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sii", $idno, $items_per_page, $history_offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation_history = [];
    while ($row = $result->fetch_assoc()) {
        $reservation_history[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // If query fails, we'll use empty arrays
    $pending_reservations = [];
    $reservation_history = [];
    $pending_total = 0;
    $history_total = 0;
    $pending_total_pages = 0;
    $history_total_pages = 0;
    $reservation_error = 'Error fetching reservations: ' . $e->getMessage();
}

// Get messages from session and clear them
$cancel_success = false;
$cancel_error = '';
if (isset($_SESSION['cancel_success'])) {
    $cancel_success = true;
    unset($_SESSION['cancel_success']);
}
if (isset($_SESSION['cancel_error'])) {
    $cancel_error = $_SESSION['cancel_error'];
    unset($_SESSION['cancel_error']);
}

// Get PC status data for the selected lab
function getPCStatusData($conn, $lab_id) {
    $pcs = [];
    try {
        // Get all PCs for this lab with their status
        $stmt = $conn->prepare("SELECT PC_ID, PC_NUMBER, STATUS FROM PC WHERE LAB_ID = ? ORDER BY PC_NUMBER");
        $stmt->bind_param("i", $lab_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($pc = $result->fetch_assoc()) {
            $pcs[] = $pc;
        }
        $stmt->close();
    } catch(Exception $e) {
        // If error, return empty array
        error_log("Error fetching PC status: " . $e->getMessage());
    }
    return $pcs;
}

// Get default lab (first one if available)
$default_lab_id = isset($laboratories[0]) ? $laboratories[0]['LAB_ID'] : null;
$selected_lab_id = isset($_GET['lab']) ? intval($_GET['lab']) : $default_lab_id;

// Get PC status data for default/selected lab
$pc_status_data = [];
$pc_counts = [
    'available' => 0,
    'in_use' => 0,
    'reserved' => 0,
    'maintenance' => 0
];

if ($selected_lab_id) {
    $pc_status_data = getPCStatusData($conn, $selected_lab_id);
    
    // Count PCs by status
    foreach($pc_status_data as $pc) {
        $status = strtolower($pc['STATUS']);
        if (isset($pc_counts[$status])) {
            $pc_counts[$status]++;
        } else {
            // Default to "other" category if status doesn't match
            $pc_counts['maintenance']++;
        }
    }
    
    // Get lab name for selected lab
    $lab_name = "";
    foreach($laboratories as $lab) {
        if ($lab['LAB_ID'] == $selected_lab_id) {
            $lab_name = $lab['LAB_NAME'];
            break;
        }
    }
}

$pageTitle = "Laboratory Reservation";
$bodyClass = "bg-light font-poppins";
include('includes/header.php');
?>

<!-- Custom styles for this page -->
<style>
    .status-badge {
        @apply px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full;
    }
    .table-header {
        @apply bg-gray-50 shadow-sm border-b border-gray-200;
    }
    .lab-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .lab-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .section-card {
        @apply bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100;
        transition: all 0.2s ease;
    }
    .section-card:hover {
        @apply shadow-md border-primary border-opacity-50;
    }
    .form-input:focus {
        @apply border-secondary ring-2 ring-primary ring-opacity-20;
    }
    /* Fix text overflow issues */
    .truncate-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Make all sections scrollable */
    .scroll-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: #94B0DF #f1f1f1;
    }
    .scroll-container::-webkit-scrollbar {
        height: 6px;
        width: 6px;
    }
    .scroll-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .scroll-container::-webkit-scrollbar-thumb {
        background: #94B0DF;
        border-radius: 10px;
    }
    
    /* Make content areas scrollable with fixed heights */
    .scrollable-section {
        max-height: 300px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #94B0DF #f1f1f1;
    }
    .scrollable-section::-webkit-scrollbar {
        width: 6px;
    }
    .scrollable-section::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .scrollable-section::-webkit-scrollbar-thumb {
        background: #94B0DF;
        border-radius: 10px;
    }
    
    /* Adjust heights for different sections */
    .labs-scroll {
        max-height: 250px;
    }
    .pc-scroll {
        max-height: 320px;
    }
    .guidelines-scroll {
        max-height: 220px;
    }
    .form-scroll {
        max-height: 450px;
    }
    .table-scroll {
        max-height: 350px;
    }

    /* Computer status card grid */
    .pc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 0.75rem;
    }
    .pc-card {
        @apply bg-green-50 border border-green-100 rounded-lg p-3 flex flex-col items-center justify-center text-center;
        transition: all 0.2s ease;
    }
    .pc-card:hover {
        @apply shadow-md;
        transform: translateY(-2px);
    }
    .pc-id {
        @apply font-medium text-secondary;
        font-size: 0.9rem;
        line-height: 1.2;
    }
    .pc-status {
        @apply text-xs text-gray-600 mt-1;
    }
    
    /* Responsive fixes */
    @media (max-width: 640px) {
        .responsive-container {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        .responsive-heading {
            font-size: 1.5rem;
        }
        .scrollable-section {
            max-height: 250px;
        }
    }

    /* Add new CSS for landscape orientation boxes */
    .landscape-section {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        gap: 1rem;
        padding: 1rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: #94B0DF #f1f1f1;
        align-items: stretch;
        margin-bottom: 1.5rem;
    }
    .landscape-section::-webkit-scrollbar {
        height: 6px;
    }
    .landscape-section::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .landscape-section::-webkit-scrollbar-thumb {
        background: #94B0DF;
        border-radius: 10px;
    }
    
    .landscape-card {
        flex: 0 0 auto;
        width: 300px;
        transition: transform 0.2s ease;
    }
    .landscape-card:hover {
        transform: translateY(-3px);
    }
    
    /* Laboratory cards in landscape */
    .lab-landscape-card {
        @apply bg-white shadow-sm rounded-lg border border-gray-100 p-4;
        width: 220px;
        flex-shrink: 0;
    }
    .lab-landscape-card:hover {
        @apply shadow-md border-primary border-opacity-50;
    }
    
    /* Computer cards in landscape */
    .pc-landscape-section {
        display: flex;
        overflow-x: auto;
        gap: 0.5rem;
        padding: 0.5rem;
        min-height: 110px;
    }
    .pc-landscape-card {
        width: 100px;
        flex-shrink: 0;
    }
    
    /* Session timeline */
    .timeline-container {
        display: flex;
        overflow-x: auto;
        gap: 1rem;
        padding: 1rem;
        align-items: stretch;
    }
    .timeline-item {
        @apply bg-white shadow-sm rounded-lg border border-gray-100 p-4;
        width: 250px;
        flex-shrink: 0;
    }
    .timeline-item:hover {
        @apply shadow-md border-primary border-opacity-50;
    }
    .timeline-date {
        @apply text-xs text-gray-500 mb-1;
    }
    .timeline-title {
        @apply font-medium text-secondary mb-1;
    }
    .timeline-content {
        @apply text-sm text-gray-600;
    }
    
    /* History and pending cards */
    .session-card {
        @apply bg-white shadow-sm rounded-lg border border-gray-100 p-4;
        width: 300px;
        flex-shrink: 0;
    }
    .session-card:hover {
        @apply shadow-md border-primary border-opacity-50;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .landscape-card, .session-card {
            width: 85vw;
        }
        .lab-landscape-card {
            width: 200px;
        }
    }
</style>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header class="bg-secondary px-6 py-4 shadow-md">
        <div class="container mx-auto flex flex-col md:flex-row items-center justify-between">
            <!-- Logo and Username -->
            <a href="profile.php" class="flex items-center space-x-4 mb-4 md:mb-0 group">
                <div class="h-12 w-12 rounded-full overflow-hidden border-2 border-primary">
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="h-full w-full object-cover" 
                         onerror="this.onerror=null; this.src='images/snoopy.jpg';">
                </div>
                <span class="text-white font-semibold text-lg group-hover:text-primary transition"><?php echo htmlspecialchars($username); ?></span>
            </a>
            
            <!-- Navigation -->
            <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6">
                <nav>
                    <ul class="flex flex-wrap justify-center space-x-6">
                        <li><a href="dashboard.php" class="text-white hover:text-primary transition">Home</a></li>
                        <li><a href="notification.php" class="text-white hover:text-primary transition">Notification</a></li>
                        <li><a href="history.php" class="text-white hover:text-primary transition">History</a></li>
                        <li><a href="reservation.php" class="text-white hover:text-primary transition font-semibold border-b-2 border-primary pb-1">Reservation</a></li>
                        <li><a href="resources.php" class="text-white hover:text-primary transition">Resources</a></li>
                    </ul>
                </nav>
                
                <button onclick="confirmLogout(event)" 
                        class="bg-primary text-secondary px-4 py-2 rounded-full font-medium hover:bg-white hover:text-dark transition shadow-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow p-6 bg-light">
        <div class="container mx-auto max-w-7xl">
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-secondary">Laboratory Reservation</h1>
                </div>
                <div class="flex items-center bg-primary bg-opacity-10 text-secondary px-4 py-2 rounded-full shadow-sm">
                    <i class="fas fa-clock mr-2"></i>
                    <div>
                        <div class="text-xs text-gray-600">Available</div>
                        <div class="font-bold"><?php echo $remaining_sessions; ?> sessions</div>
                    </div>
                </div>
            </div>
            
            <?php if ($reservation_success): ?>
                <div id="success-alert" class="bg-primary bg-opacity-10 border-l-4 border-primary text-secondary px-4 py-3 rounded-lg mb-6 shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-primary mt-0.5"></i>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium">Reservation Request Submitted</p>
                            <p class="text-sm">Your request has been submitted successfully. Please wait for admin approval.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($cancel_success): ?>
                <div id="cancel-success-alert" class="bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-lg mb-6 shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500 mt-0.5"></i>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium">Reservation Cancelled</p>
                            <p class="text-sm">Your reservation has been cancelled successfully.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($cancel_error)): ?>
                <div id="cancel-error-alert" class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-6 shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium">Cancellation Error</p>
                            <p class="text-sm"><?php echo $cancel_error; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($reservation_error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-6 shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-500 mt-0.5"></i>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium">Unable to Process Request</p>
                            <p class="text-sm"><?php echo $reservation_error; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($has_active_session): ?>
                <div class="bg-primary bg-opacity-10 border-l-4 border-primary text-secondary px-4 py-3 rounded-lg mb-6 shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-primary mt-0.5"></i>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium">Active Session in Progress</p>
                            <p class="text-sm">You currently have an active lab session. You cannot make a new reservation until your current session ends.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Main Content Grid: 3-column layout with 1:2:1 ratio -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                <!-- Create Reservation Form Card -->
                <div class="lg:col-span-2">
                    <div class="section-card p-6 mb-6">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-calendar-plus text-primary mr-3 text-xl"></i>
                            <h2 class="text-xl font-semibold text-secondary">Create Reservation</h2>
                        </div>
                        
                        <div class="scrollable-section form-scroll">
                            <form action="reservation.php" method="post" class="space-y-5 p-1">
                                <!-- ID Number field (pre-filled with the user's ID) -->
                                <div>
                                    <label for="id_number" class="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                                    <input type="text" id="id_number" name="id_number" value="<?php echo htmlspecialchars($idno); ?>" class="form-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none sm:text-sm bg-gray-50" readonly>
                                </div>
                                
                                <!-- Reservation Date -->
                                <div>
                                    <label for="reservation_date" class="block text-sm font-medium text-gray-700 mb-1">Reservation Date</label>
                                    <input type="date" id="reservation_date" name="reservation_date" class="form-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none sm:text-sm" required <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                </div>
                                
                                <!-- Custom Time Selection -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="start_time" class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                                        <input type="time" id="start_time" name="start_time" 
                                              min="08:00" max="21:00"
                                              class="form-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none sm:text-sm"
                                              required <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                    </div>
                                    <div>
                                        <label for="end_time" class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                                        <input type="time" id="end_time" name="end_time" 
                                              min="08:00" max="21:00"
                                              class="form-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none sm:text-sm"
                                              required <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Lab hours: 8:00 AM - 9:00 PM (Maximum reservation time: 1.5 hours)</div>
                                
                                <!-- Laboratory Selection -->
                                <div>
                                    <label for="lab_id" class="block text-sm font-medium text-gray-700 mb-1">Laboratory</label>
                                    <select id="lab_id" name="lab_id" class="form-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none sm:text-sm" required <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                        <option value="">Choose a laboratory</option>
                                        <?php foreach ($laboratories as $lab): ?>
                                            <option value="<?php echo $lab['LAB_ID']; ?>">
                                                <?php echo htmlspecialchars($lab['LAB_NAME']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- PC Number Selection -->
                                <div>
                                    <label for="pc_number" class="block text-sm font-medium text-gray-700 mb-1">PC Number</label>
                                    <select id="pc_number" name="pc_number" class="form-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none sm:text-sm" required <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                        <option value="">Select PC</option>
                                        <!-- PC options will be populated via JavaScript when lab is selected -->
                                    </select>
                                </div>
                                
                                <!-- Purpose field with conditional "Other" input -->
                                <div>
                                    <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Programming Language</label>
                                    <select id="purpose" name="purpose" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary" <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                        <option value="">Select Purpose</option>
                                        <?php
                                        // Default programming languages that should always be available
                                        $defaultLanguages = ['Java', 'Python', 'C++', 'C#', 'PHP', 'SQL', 'JavaScript', 'HTML/CSS', 'Ruby', 'Other'];
                                        $purposes = $defaultLanguages;
                                        
                                        try {
                                            // Additional unique purposes from the database
                                            $purposeQuery = "SELECT DISTINCT PURPOSE FROM SITIN WHERE PURPOSE != '' AND PURPOSE NOT IN ('" . implode("','", $defaultLanguages) . "') ORDER BY PURPOSE";
                                            $purposeResult = $conn->query($purposeQuery);
                                            
                                            // Add any additional purposes from the database
                                            while ($purpose = $purposeResult->fetch_assoc()) {
                                                if (!in_array($purpose['PURPOSE'], $purposes)) {
                                                    $purposes[] = $purpose['PURPOSE'];
                                                }
                                            }
                                            
                                        } catch (Exception $e) {
                                            // If query fails, we still have the default languages
                                        }
                                        
                                        // Output all purposes except 'Other' which we'll add at the end
                                        foreach ($purposes as $purpose) {
                                            if ($purpose !== 'Other') {
                                                echo '<option value="' . htmlspecialchars($purpose) . '">' . htmlspecialchars($purpose) . '</option>';
                                            }
                                        }
                                        // Add 'Other' as the last option
                                        echo '<option value="Other">Other</option>';
                                        ?>
                                    </select>
                                    
                                    <!-- This input is shown only when "Other" is selected -->
                                    <div id="other-purpose-container" class="mt-2 hidden">
                                        <input type="text" id="other-purpose" name="other_purpose" 
                                               placeholder="Please specify programming language" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary"
                                               <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                                
                                <div class="mb-6 bg-blue-50 p-4 rounded-md border border-blue-200">
                                    <div class="flex items-center">
                                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                        <span class="text-sm text-blue-800">Your Remaining Sessions: <span class="font-bold"><?php echo $remaining_sessions; ?></span></span>
                                    </div>
                                    <p class="text-xs text-blue-600 mt-1">Session will be deducted based on the duration of your reservation.</p>
                                </div>
                                
                                <div class="pt-2">
                                    <div class="flex space-x-2">
                                        <button type="submit" name="submit_reservation" class="flex-1 flex justify-center items-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-secondary hover:bg-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary transition-all duration-300" <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                            <i class="fas fa-paper-plane mr-2"></i> Submit Reservation
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Computer Lab Status with Availability Summary -->
                <div class="lg:col-span-2">
                    <!-- Computer Lab Status Card -->
                    <div class="section-card mb-6">
                        <div class="bg-secondary text-white px-6 py-3 font-semibold flex items-center">
                            <i class="fas fa-desktop mr-2"></i> Computer Lab Status
                        </div>
                        
                        <div class="p-5">
                            <!-- Laboratory selector and PC grid -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Laboratory</label>
                                <div class="relative">
                                    <select id="status-lab-select" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 appearance-none text-sm">
                                        <?php foreach ($laboratories as $lab): ?>
                                            <option value="<?php echo $lab['LAB_ID']; ?>">
                                                <?php echo htmlspecialchars($lab['LAB_NAME']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Legend and Lab Name -->
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-medium text-gray-500"><?php echo htmlspecialchars($lab_name ?? 'Select Laboratory'); ?></h4>
                                <div class="flex items-center space-x-2">
                                    <div class="flex items-center">
                                        <span class="h-3 w-3 rounded-full bg-green-500 mr-1"></span>
                                        <span class="text-xs text-gray-600">Available</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="h-3 w-3 rounded-full bg-amber-500 mr-1"></span>
                                        <span class="text-xs text-gray-600">Reserved</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="h-3 w-3 rounded-full bg-blue-500 mr-1"></span>
                                        <span class="text-xs text-gray-600">In Use</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="h-3 w-3 rounded-full bg-red-500 mr-1"></span>
                                        <span class="text-xs text-gray-600">Maintenance</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- PC Grid -->
                            <div class="grid grid-cols-5 gap-2 h-48 overflow-y-auto custom-scrollbar" id="status-pc-grid">
                                <?php if (empty($pc_status_data)): ?>
                                    <div class="col-span-5 flex items-center justify-center h-full text-gray-500">
                                        <p>No PCs found for this laboratory</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($pc_status_data as $pc): ?>
                                        <?php 
                                            $statusClass = '';
                                            $statusLabel = $pc['STATUS'];
                                            switch(strtolower($pc['STATUS'])) {
                                                case 'available':
                                                    $statusClass = 'bg-green-100 text-green-600 ring-green-200';
                                                    break;
                                                case 'reserved':
                                                    $statusClass = 'bg-amber-100 text-amber-600 ring-amber-200';
                                                    break;
                                                case 'in_use':
                                                    $statusClass = 'bg-blue-100 text-blue-600 ring-blue-200';
                                                    break;
                                                case 'maintenance':
                                                    $statusClass = 'bg-red-100 text-red-600 ring-red-200';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-gray-100 text-gray-600 ring-gray-200';
                                            }
                                        ?>
                                        <div class="text-center p-2 rounded-lg ring-1 <?php echo $statusClass; ?>">
                                            <div class="text-xs font-medium mb-1">PC-<?php echo sprintf("%02d", $pc['PC_NUMBER']); ?></div>
                                            <div class="text-[10px]"><?php echo htmlspecialchars(ucfirst(strtolower($statusLabel))); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Availability Summary - Integrated alongside PC grid -->
                            <div class="mt-4">
                                <div class="grid grid-cols-4 gap-2">
                                    <div class="bg-green-50 p-2 rounded-lg border border-green-100 text-center">
                                        <div class="text-xs text-gray-500 mb-1">Available</div>
                                        <div class="font-medium text-green-600"><?php echo $pc_counts['available']; ?></div>
                                    </div>
                                    <div class="bg-amber-50 p-2 rounded-lg border border-amber-100 text-center">
                                        <div class="text-xs text-gray-500 mb-1">Reserved</div>
                                        <div class="font-medium text-amber-600"><?php echo $pc_counts['reserved']; ?></div>
                                    </div>
                                    <div class="bg-blue-50 p-2 rounded-lg border border-blue-100 text-center">
                                        <div class="text-xs text-gray-500 mb-1">In Use</div>
                                        <div class="font-medium text-blue-600"><?php echo $pc_counts['in_use']; ?></div>
                                    </div>
                                    <div class="bg-red-50 p-2 rounded-lg border border-red-100 text-center">
                                        <div class="text-xs text-gray-500 mb-1">Maintenance</div>
                                        <div class="font-medium text-red-600"><?php echo $pc_counts['maintenance']; ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button id="refresh-pc-status" class="w-full bg-blue-500 hover:bg-blue-600 text-white text-sm py-2 px-4 rounded-md transition-colors flex justify-center items-center">
                                    <i class="fas fa-sync-alt mr-2"></i> Refresh Status
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional information panel can go here if needed -->
                </div>
            </div>
            
            <!-- Combined Reservations and History Section - Full width -->
            <div class="section-card mb-8">
                <div class="bg-white border-b border-gray-100 px-6 py-4 flex flex-wrap justify-between items-center">
                    <h2 class="text-xl font-semibold text-secondary flex items-center">
                        <i class="fas fa-list-alt text-primary mr-2 text-xl"></i> 
                        My Reservations
                    </h2>
                    <div class="flex space-x-2 mt-2 sm:mt-0">
                        <button id="tab-pending" class="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white">
                            <i class="fas fa-clock mr-1"></i> Pending/Approved (<?php echo count($pending_reservations); ?>)
                        </button>
                        <button id="tab-history" class="px-4 py-2 text-sm font-medium rounded-md bg-gray-100 text-gray-700">
                            <i class="fas fa-history mr-1"></i> History (<?php echo count($reservation_history); ?>)
                        </button>
                    </div>
                </div>
                
                <!-- Pending Reservations Tab -->
                <div id="pending-content" class="scrollable-section table-scroll">
                    <?php if (empty($pending_reservations)): ?>
                        <div class="p-6 text-center text-gray-500 italic">No pending or approved reservations</div>
                    <?php else: ?>
                        <div class="scroll-container">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="table-header sticky top-0 z-10">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PC</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($pending_reservations as $reservation): ?>
                                        <?php
                                        $start = new DateTime($reservation['START_DATETIME']);
                                        $end = new DateTime($reservation['END_DATETIME']);
                                        $date = $start->format('Y-m-d');
                                        $time = $start->format('h:i A') . ' - ' . $end->format('h:i A');
                                        ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($reservation['LAB_NAME']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($date); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($time); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                PC-<?php echo sprintf("%02d", $reservation['PC_NUMBER']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($reservation['PURPOSE']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php if ($reservation['STATUS'] === 'PENDING'): ?>
                                                    <span class="status-badge bg-primary bg-opacity-10 text-secondary">
                                                        <i class="fas fa-clock mr-1"></i> Pending
                                                    </span>
                                                <?php elseif ($reservation['STATUS'] === 'APPROVED'): ?>
                                                    <span class="status-badge bg-green-100 text-green-800">
                                                        <i class="fas fa-check mr-1"></i> Approved
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['RESERVATION_ID']; ?>">
                                                    <button type="submit" name="cancel_reservation" class="text-red-500 hover:text-red-700" onclick="return confirm('Are you sure you want to cancel this reservation?')">
                                                        <i class="fas fa-times-circle"></i> Cancel
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- Pagination for Pending Reservations -->
                            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            Showing
                                            <span class="font-medium"><?php echo $pending_offset + 1; ?></span>
                                            to
                                            <span class="font-medium"><?php echo min($pending_offset + $items_per_page, $pending_total); ?></span>
                                            of
                                            <span class="font-medium"><?php echo $pending_total; ?></span>
                                            results
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                            <a href="#" 
                                               class="pagination-link relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" 
                                               data-page="<?php echo max($current_page_pending - 1, 1); ?>" 
                                               data-type="pending"
                                               <?php echo ($current_page_pending <= 1) ? 'aria-disabled="true"' : ''; ?>>
                                                <span class="sr-only">Previous</span>
                                                <!-- Heroicon name: solid/chevron-left -->
                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                            
                                            <?php for($i = 1; $i <= $pending_total_pages; $i++): ?>
                                                <a href="#" 
                                                   class="pagination-link <?php echo ($i == $current_page_pending) ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium" 
                                                   data-page="<?php echo $i; ?>"
                                                   data-type="pending">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>
                                            
                                            <a href="#" 
                                               class="pagination-link relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" 
                                               data-page="<?php echo min($current_page_pending + 1, $pending_total_pages); ?>" 
                                               data-type="pending"
                                               <?php echo ($current_page_pending >= $pending_total_pages) ? 'aria-disabled="true"' : ''; ?>>
                                                <span class="sr-only">Next</span>
                                                <!-- Heroicon name: solid/chevron-right -->
                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Reservation History Tab (Hidden by default) -->
                <div id="history-content" class="scrollable-section table-scroll hidden">
                    <?php if (empty($reservation_history)): ?>
                        <div class="p-6 text-center text-gray-500 italic">No reservation history</div>
                    <?php else: ?>
                        <div class="scroll-container">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="table-header sticky top-0 z-10">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PC</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($reservation_history as $history): ?>
                                        <?php
                                        $start = new DateTime($history['START_DATETIME']);
                                        $end = new DateTime($history['END_DATETIME']);
                                        $date = $start->format('Y-m-d');
                                        $time = $start->format('h:i A') . ' - ' . $end->format('h:i A');
                                        ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($history['LAB_NAME']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($date); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($time); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                PC-<?php echo sprintf("%02d", $history['PC_NUMBER']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($history['PURPOSE']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php if ($history['STATUS'] === 'COMPLETED'): ?>
                                                    <span class="status-badge bg-blue-100 text-blue-800">
                                                        <i class="fas fa-check-circle mr-1"></i> Completed
                                                    </span>
                                                <?php elseif ($history['STATUS'] === 'CANCELLED'): ?>
                                                    <span class="status-badge bg-red-100 text-red-800">
                                                        <i class="fas fa-ban mr-1"></i> Cancelled
                                                    </span>
                                                <?php elseif ($history['STATUS'] === 'REJECTED'): ?>
                                                    <span class="status-badge bg-red-100 text-red-800">
                                                        <i class="fas fa-times-circle mr-1"></i> Rejected
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- Pagination for History -->
                            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            Showing
                                            <span class="font-medium"><?php echo $history_offset + 1; ?></span>
                                            to
                                            <span class="font-medium"><?php echo min($history_offset + $items_per_page, $history_total); ?></span>
                                            of
                                            <span class="font-medium"><?php echo $history_total; ?></span>
                                            results
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                            <a href="#" 
                                               class="pagination-link relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" 
                                               data-page="<?php echo max($current_page_history - 1, 1); ?>" 
                                               data-type="history"
                                               <?php echo ($current_page_history <= 1) ? 'aria-disabled="true"' : ''; ?>>
                                                <span class="sr-only">Previous</span>
                                                <!-- Heroicon name: solid/chevron-left -->
                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                            
                                            <?php for($i = 1; $i <= $history_total_pages; $i++): ?>
                                                <a href="#" 
                                                   class="pagination-link <?php echo ($i == $current_page_history) ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium" 
                                                   data-page="<?php echo $i; ?>"
                                                   data-type="history">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>
                                            
                                            <a href="#" 
                                               class="pagination-link relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" 
                                               data-page="<?php echo min($current_page_history + 1, $history_total_pages); ?>" 
                                               data-type="history"
                                               <?php echo ($current_page_history >= $history_total_pages) ? 'aria-disabled="true"' : ''; ?>>
                                                <span class="sr-only">Next</span>
                                                <!-- Heroicon name: solid/chevron-right -->
                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Confirmation Dialog -->
<div id="confirmation-dialog" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-secondary mb-4">Confirm Logout</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to log out?</p>
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
    // Confirmation dialog for logout
    function confirmLogout(event) {
        event.preventDefault();
        document.getElementById('confirmation-dialog').classList.remove('hidden');
    }
    
    document.getElementById('cancel-logout').addEventListener('click', () => {
        document.getElementById('confirmation-dialog').classList.add('hidden');
    });
    
    // Populate PCs based on selected laboratory
    document.getElementById('lab_id').addEventListener('change', function() {
        const labId = this.value;
        const pcSelect = document.getElementById('pc_number');
        
        // Clear existing options
        pcSelect.innerHTML = '<option value="">Select PC</option>';
        
        if (labId) {
            // Show loading indicator
            pcSelect.innerHTML = '<option value="">Loading PCs...</option>';
            
            // Fetch available PCs for the selected lab via AJAX
            fetch('ajax/get_available_pcs.php?lab_id=' + labId)
                .then(response => response.json())
                .then(data => {
                    // Clear the loading option
                    pcSelect.innerHTML = '<option value="">Select PC</option>';
                    
                    if (data.success && data.pcs && data.pcs.length > 0) {
                        // Add real PCs from the database
                        data.pcs.forEach(pc => {
                            const option = document.createElement('option');
                            option.value = pc.PC_ID;
                            option.textContent = 'PC-' + pc.PC_NUMBER.toString().padStart(2, '0');
                            pcSelect.appendChild(option);
                        });
                    } else {
                        // No PCs available for this lab
                        const option = document.createElement('option');
                        option.value = "";
                        option.textContent = "No PCs available in this laboratory";
                        option.disabled = true;
                        pcSelect.appendChild(option);
                        
                        // Show an alert to inform the user
                        alert('There are no PCs available in the selected laboratory.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching PCs:', error);
                    // Clear the loading option
                    pcSelect.innerHTML = '<option value="">Select PC</option>';
                    
                    // Show error message
                    const option = document.createElement('option');
                    option.value = "";
                    option.textContent = "Error loading PCs";
                    option.disabled = true;
                    pcSelect.appendChild(option);
                });
        }
    });
    
    // Set minimum date for reservation date picker to today
    const today = new Date();
    const formattedToday = today.toISOString().split('T')[0];
    document.getElementById('reservation_date').min = formattedToday;
    
    // Set maximum date for reservation (e.g., 30 days from today)
    const maxDate = new Date();
    maxDate.setDate(today.getDate() + 30);
    const formattedMaxDate = maxDate.toISOString().split('T')[0];
    document.getElementById('reservation_date').max = formattedMaxDate;

    // Synchronize the laboratory selectors
    document.getElementById('lab_id').addEventListener('change', function() {
        // When the reservation form lab selector changes, update the status view
        const labId = this.value;
        const statusLabSelect = document.getElementById('status-lab-select');
        if (statusLabSelect) {
            statusLabSelect.value = labId;
            // Fetch PC status data for this lab
            refreshPCStatus(labId);
        }
    });
    
    document.getElementById('status-lab-select').addEventListener('change', function() {
        // When the status view lab selector changes, update the reservation form
        const labId = this.value;
        
        // Update the lab in the form reservation form
        const formLabSelect = document.getElementById('lab_id');
        if (formLabSelect) {
            formLabSelect.value = labId;
            
            // Trigger an event to refresh the PC dropdown
            const event = new Event('change');
            formLabSelect.dispatchEvent(event);
        }
        
        // Fetch PC status data for the selected lab directly
        refreshPCStatus(labId);
    });
    
    // Refresh PC status button
    document.getElementById('refresh-pc-status')?.addEventListener('click', function() {
        const labId = document.getElementById('status-lab-select').value;
        if (!labId) {
            alert('Please select a laboratory first');
            return;
        }
        
        // Show loading state
        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Loading...';
        this.disabled = true;
        
        refreshPCStatus(labId, this);
    });
    
    // Function to refresh PC status data
    function refreshPCStatus(labId, button = null) {
        // Show loading indicator in PC grid
        const pcGrid = document.getElementById('status-pc-grid');
        if (pcGrid) {
            pcGrid.innerHTML = `
                <div class="col-span-5 flex items-center justify-center h-full">
                    <div class="flex items-center">
                        <i class="fas fa-spinner fa-spin text-blue-500 mr-2"></i>
                        <p class="text-gray-500">Loading PCs...</p>
                    </div>
                </div>
            `;
        }
        
        // Fetch PC status data via AJAX
        fetch('ajax/get_pc_status.php?lab_id=' + labId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updatePCGrid(data.pcs, data.counts, data.lab_name);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                    
                    if (pcGrid) {
                        pcGrid.innerHTML = `
                            <div class="col-span-5 flex items-center justify-center h-full">
                                <p class="text-red-500">Error loading PC data</p>
                            </div>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while fetching PC status');
                
                if (pcGrid) {
                    pcGrid.innerHTML = `
                        <div class="col-span-5 flex items-center justify-center h-full">
                            <p class="text-red-500">Error loading PC data</p>
                        </div>
                    `;
                }
            })
            .finally(() => {
                // Reset button if provided
                if (button) {
                    button.innerHTML = '<i class="fas fa-sync-alt mr-2"></i> Refresh Status';
                    button.disabled = false;
                }
            });
    }
    
    // Function to update PC grid with new data
    function updatePCGrid(pcs, counts, labName) {
        // Update lab name
        const labNameElement = document.querySelector('.section-card h4.text-sm.font-medium.text-gray-500');
        if (labNameElement) {
            labNameElement.textContent = labName || 'Unknown Lab';
        }
        
        // Update PC grid
        const pcGrid = document.getElementById('status-pc-grid');
        if (!pcGrid) return;
        
        if (!pcs || !pcs.length) {
            pcGrid.innerHTML = `
                <div class="col-span-5 flex items-center justify-center h-full text-gray-500">
                    <p>No PCs found for this laboratory</p>
                </div>
            `;
            return;
        }
        
        let gridHTML = '';
        pcs.forEach(pc => {
            let statusClass = '';
            let statusLabel = pc.STATUS || 'Available';
            
            switch(statusLabel.toLowerCase()) {
                case 'available':
                    statusClass = 'bg-green-100 text-green-600 ring-green-200';
                    break;
                case 'reserved':
                    statusClass = 'bg-amber-100 text-amber-600 ring-amber-200';
                    break;
                case 'in_use':
                    statusClass = 'bg-blue-100 text-blue-600 ring-blue-200';
                    break;
                case 'maintenance':
                    statusClass = 'bg-red-100 text-red-600 ring-red-200';
                    break;
                default:
                    statusClass = 'bg-gray-100 text-gray-600 ring-gray-200';
            }
            
            gridHTML += `
                <div class="text-center p-2 rounded-lg ring-1 ${statusClass}">
                    <div class="text-xs font-medium mb-1">PC-${String(pc.PC_NUMBER).padStart(2, '0')}</div>
                    <div class="text-[10px]">${statusLabel.charAt(0).toUpperCase() + statusLabel.slice(1).toLowerCase()}</div>
                </div>
            `;
        });
        
        pcGrid.innerHTML = gridHTML;
        
        // Update counts
        document.querySelector('.bg-green-50 .font-medium').textContent = counts.available || 0;
        document.querySelector('.bg-amber-50 .font-medium').textContent = counts.reserved || 0;
        document.querySelector('.bg-blue-50 .font-medium').textContent = counts.in_use || 0;
        document.querySelector('.bg-red-50 .font-medium').textContent = counts.maintenance || 0;
    }
    
    // Show/hide the "Other" purpose input field based on selection
    document.getElementById('purpose').addEventListener('change', function() {
        const otherPurposeContainer = document.getElementById('other-purpose-container');
        const otherPurposeInput = document.getElementById('other-purpose');
        
        if (this.value === 'Other') {
            otherPurposeContainer.classList.remove('hidden');
            otherPurposeInput.setAttribute('required', 'required');
        } else {
            otherPurposeContainer.classList.add('hidden');
            otherPurposeInput.removeAttribute('required');
        }
    });
    
    // Check form submission to ensure other purpose is included
    document.querySelector('form').addEventListener('submit', function(e) {
        const purposeSelect = document.getElementById('purpose');
        const otherPurposeInput = document.getElementById('other-purpose');
        
        if (purposeSelect.value === 'Other' && otherPurposeInput.value.trim() === '') {
            e.preventDefault();
            alert('Please specify the programming language.');
            otherPurposeInput.focus();
        }
    });

    // Validate reservation time to ensure it falls within lab hours
    document.getElementById('start_time').addEventListener('change', function() {
        const startTime = this.value;
        if (startTime) {
            const timeParts = startTime.split(':');
            const hours = parseInt(timeParts[0], 10);
            const minutes = parseInt(timeParts[1], 10);

            // Validate lab hours (8:00 AM - 9:00 PM)
            if (hours < 8 || hours > 21 || (hours === 21 && minutes > 0)) {
                alert('Please select a time between 8:00 AM and 9:00 PM.');
                this.value = '';
            }
        }
    });

    // Validate end time to ensure it falls within lab hours and is after start time
    document.getElementById('end_time').addEventListener('change', function() {
        const endTime = this.value;
        const startTime = document.getElementById('start_time').value;
        if (endTime && startTime) {
            const endTimeParts = endTime.split(':');
            const endHours = parseInt(endTimeParts[0], 10);
            const endMinutes = parseInt(endTimeParts[1], 10);

            const startTimeParts = startTime.split(':');
            const startHours = parseInt(startTimeParts[0], 10);
            const startMinutes = parseInt(startTimeParts[1], 10);

            // Validate lab hours (8:00 AM - 9:00 PM)
            if (endHours < 8 || endHours > 21 || (endHours === 21 && endMinutes > 0)) {
                alert('Please select a time between 8:00 AM and 9:00 PM.');
                this.value = '';
            } else if (endHours < startHours || (endHours === startHours && endMinutes <= startMinutes)) {
                alert('End time must be after start time.');
                this.value = '';
            } else {
                // Validate time difference doesn't exceed 1 hour 30 minutes
                const startTimeObj = new Date();
                startTimeObj.setHours(startHours, startMinutes, 0, 0);

                const endTimeObj = new Date();
                endTimeObj.setHours(endHours, endMinutes, 0, 0);

                const diffMs = endTimeObj - startTimeObj;
                const diffMins = diffMs / (1000 * 60);

                if (diffMins > 90) {
                    alert('Reservation time cannot exceed 1 hour and 30 minutes.');
                    this.value = '';
                }
            }
        }
    });

    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = [
            document.getElementById('success-alert'),
            document.getElementById('cancel-success-alert'),
            document.getElementById('cancel-error-alert')
        ];
        
        alerts.forEach(alert => {
            if (alert) {
                setTimeout(() => {
                    alert.style.transition = 'opacity 1s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 1000);
                }, 5000); // 5 seconds before starting fade
            }
        });
        
        // Add close buttons to alerts
        document.querySelectorAll('#success-alert, #cancel-success-alert, #cancel-error-alert').forEach(alert => {
            if (alert) {
                const closeButton = document.createElement('button');
                closeButton.innerHTML = '<i class="fas fa-times"></i>';
                closeButton.className = 'text-gray-400 hover:text-gray-600 ml-auto';
                closeButton.addEventListener('click', () => {
                    alert.style.display = 'none';
                });
                
                // Insert close button at the beginning of the flex container
                const flexContainer = alert.querySelector('.flex');
                if (flexContainer) {
                    flexContainer.style.justifyContent = 'space-between';
                    flexContainer.appendChild(closeButton);
                }
            }
        });
        
        // Tab switching functionality
        const tabPending = document.getElementById('tab-pending');
        const tabHistory = document.getElementById('tab-history');
        const pendingContent = document.getElementById('pending-content');
        const historyContent = document.getElementById('history-content');
        
        if (tabPending && tabHistory && pendingContent && historyContent) {
            // Tab switching
            tabPending.addEventListener('click', function() {
                // Update active tab styles
                tabPending.classList.remove('bg-gray-100', 'text-gray-700');
                tabPending.classList.add('bg-primary', 'text-white');
                tabHistory.classList.remove('bg-primary', 'text-white');
                tabHistory.classList.add('bg-gray-100', 'text-gray-700');
                
                // Show/hide content
                pendingContent.classList.remove('hidden');
                historyContent.classList.add('hidden');
            });
            
            tabHistory.addEventListener('click', function() {
                // Update active tab styles
                tabHistory.classList.remove('bg-gray-100', 'text-gray-700');
                tabHistory.classList.add('bg-primary', 'text-white');
                tabPending.classList.remove('bg-primary', 'text-white');
                tabPending.classList.add('bg-gray-100', 'text-gray-700');
                
                // Show/hide content
                historyContent.classList.remove('hidden');
                pendingContent.classList.add('hidden');
            });
        }
        
        // Load initial PC status for the selected laboratory
        const labId = document.getElementById('status-lab-select').value;
        if (labId) {
            refreshPCStatus(labId);
        }
        
        // Pagination functionality
        const paginationLinks = document.querySelectorAll('.pagination-link');
        
        paginationLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const page = this.getAttribute('data-page');
                const type = this.getAttribute('data-type');
                
                // Check if the link is disabled
                if (this.hasAttribute('aria-disabled') && this.getAttribute('aria-disabled') === 'true') {
                    return;
                }
                
                // Preserve the current tab state
                const activeTab = document.querySelector('#tab-pending').classList.contains('bg-primary') ? 'pending' : 'history';
                
                // Build the new URL with query parameters
                let url = new URL(window.location.href);
                url.searchParams.set(`${type}_page`, page);
                
                // Keep other query parameters including lab
                if (url.searchParams.has('lab')) {
                    const labId = url.searchParams.get('lab');
                    url.searchParams.set('lab', labId);
                }
                
                // Navigate to the new URL
                window.location.href = url.toString() + (activeTab === 'history' ? '#history' : '#pending');
            });
        });
        
        // Check if we need to show the history tab based on hash or query params
        if (window.location.hash === '#history' || window.location.search.includes('history_page=')) {
            document.getElementById('tab-history').click();
        }
    });
</script>

<?php include('includes/footer.php'); ?>