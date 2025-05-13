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

// Get pending reservations for this user (mock data for now)
$pending_reservations = [
    [
        'lab_name' => 'Lab 524',
        'date' => '2024-06-15',
        'time' => '10:00 AM - 11:00 AM',
        'status' => 'PENDING'
    ],
    [
        'lab_name' => 'Lab 530',
        'date' => '2024-06-17',
        'time' => '2:00 PM - 4:00 PM',
        'status' => 'APPROVED'
    ]
];

// Get reservation history (mock data for now)
$reservation_history = [
    [
        'lab_name' => 'Lab 526',
        'date' => '2024-06-01',
        'time' => '9:00 AM - 10:00 AM',
        'status' => 'COMPLETED'
    ],
    [
        'lab_name' => 'Lab 542',
        'date' => '2024-05-28',
        'time' => '1:00 PM - 3:00 PM',
        'status' => 'CANCELLED'
    ]
];

$reservation_success = false;
$reservation_error = '';

// Handle form submission
if (isset($_POST['submit_reservation'])) {
    $lab_id = $_POST['lab_id'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $session_count = $_POST['session_count'] ?? 1;
    
    // Validate input
    if (empty($lab_id) || empty($purpose)) {
        $reservation_error = 'Please fill in all required fields.';
    } elseif ($has_active_session) {
        $reservation_error = 'You already have an active session.';
    } elseif ($remaining_sessions < $session_count) {
        $reservation_error = 'You do not have enough remaining sessions.';
    } else {
        // Submit reservation request
        // Note: This would typically be processed by an admin
        // For now, just show a success message
        $reservation_success = true;
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
        <div class="container mx-auto max-w-5xl">
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-secondary">Laboratory Reservation</h1>
                    <p class="text-gray-600 mt-1">Book computer laboratory time for academic work</p>
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
                <div class="bg-primary bg-opacity-10 border-l-4 border-primary text-secondary px-4 py-3 rounded-lg mb-6 shadow-sm">
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
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Create Reservation Form Card -->
                <div class="lg:col-span-2">
                    <div class="section-card p-6 mb-6">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-calendar-plus text-primary mr-3 text-xl"></i>
                            <h2 class="text-xl font-semibold text-secondary">Create Reservation</h2>
                        </div>
                        
                        <div class="scrollable-section form-scroll">
                            <form action="reservation.php" method="post" class="space-y-5 p-1">
                                <div>
                                    <label for="lab_id" class="block text-sm font-medium text-gray-700 mb-1">Select Laboratory</label>
                                    <select id="lab_id" name="lab_id" class="form-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none sm:text-sm" required <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                        <option value="">Choose a laboratory</option>
                                        <?php foreach ($laboratories as $lab): ?>
                                            <option value="<?php echo $lab['LAB_ID']; ?>">
                                                <?php echo htmlspecialchars($lab['LAB_NAME']); ?> - 
                                                <?php if ($lab['STATUS'] === 'AVAILABLE'): ?>
                                                    <span class="text-green-500">Available</span>
                                                <?php elseif ($lab['STATUS'] === 'MAINTENANCE'): ?>
                                                    <span class="text-yellow-500">Under Maintenance</span>
                                                <?php else: ?>
                                                    <span class="text-primary">Occupied</span>
                                                <?php endif; ?>
                                                (Capacity: <?php echo $lab['CAPACITY']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                                    <select id="purpose" name="purpose" class="form-input block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none sm:text-sm" required <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                        <option value="">Select Purpose</option>
                                        <option value="Java">Java</option>
                                        <option value="Python">Python</option>
                                        <option value="C++">C++</option>
                                        <option value="C#">C#</option>
                                        <option value="PHP">PHP</option>
                                        <option value="SQL">SQL</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="mb-6 bg-blue-50 p-4 rounded-md border border-blue-200">
                                    <div class="flex items-center">
                                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                        <span class="text-sm text-blue-800">Your Remaining Sessions: <span class="font-bold"><?php echo $remaining_sessions; ?></span></span>
                                    </div>
                                    <p class="text-xs text-blue-600 mt-1">Session will be deducted based on the duration of your reservation.</p>
                                </div>
                                
                                <div class="pt-2">
                                    <button type="submit" name="submit_reservation" class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-secondary hover:bg-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary transition-all duration-300" <?php echo $has_active_session ? 'disabled' : ''; ?>>
                                        <i class="fas fa-paper-plane mr-2"></i> Submit Reservation Request
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Labs Status Card -->
                <div class="lg:col-span-1">
                    <div class="section-card mb-6">
                        <div class="bg-secondary text-white px-6 py-3 font-semibold flex items-center">
                            <i class="fas fa-desktop mr-2"></i> Laboratory Status
                        </div>
                        <div class="scrollable-section labs-scroll divide-y divide-gray-100">
                            <?php $mockLabs = ['Lab 524', 'Lab 526', 'Lab 528', 'Lab 530', 'Lab 542', 'Lab 544']; ?>
                            <?php $mockStatus = ['Available', 'Occupied', 'Available', 'Under Maintenance', 'Available', 'Occupied']; ?>
                            <?php $mockComputers = [28, 25, 17, 0, 22, 15]; ?>
                            <?php $mockCapacity = [30, 25, 20, 30, 25, 15]; ?>
                            
                            <?php for($i = 0; $i < count($mockLabs); $i++): ?>
                                <div class="p-4 lab-card hover:bg-gray-50">
                                    <div class="flex justify-between items-center mb-1">
                                        <h3 class="font-medium text-secondary truncate-text"><?php echo $mockLabs[$i]; ?></h3>
                                        <?php if($mockStatus[$i] === 'Available'): ?>
                                            <span class="status-badge bg-green-100 text-green-800 ml-2 flex-shrink-0">Available</span>
                                        <?php elseif($mockStatus[$i] === 'Occupied'): ?>
                                            <span class="status-badge bg-primary bg-opacity-20 text-secondary ml-2 flex-shrink-0">Occupied</span>
                                        <?php else: ?>
                                            <span class="status-badge bg-yellow-100 text-yellow-800 ml-2 flex-shrink-0">Maintenance</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($mockStatus[$i] !== 'Under Maintenance'): ?>
                                        <div class="flex items-center">
                                            <div class="flex-1">
                                                <div class="h-2 bg-gray-200 rounded-full">
                                                    <div class="h-2 rounded-full bg-primary" style="width: <?php echo ($mockComputers[$i] / $mockCapacity[$i]) * 100; ?>%"></div>
                                                </div>
                                            </div>
                                            <span class="ml-2 text-xs text-gray-500 flex-shrink-0"><?php echo $mockComputers[$i]; ?>/<?php echo $mockCapacity[$i]; ?> PCs</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-xs text-gray-500 italic">Temporarily unavailable</div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Computer Status Grid Section -->
                    <div class="section-card mb-6">
                        <div class="bg-secondary text-white px-6 py-3 font-semibold flex items-center">
                            <i class="fas fa-laptop mr-2"></i> Computer Status (Lab 524)
                        </div>
                        <div class="scrollable-section pc-scroll p-3">
                            <div class="pc-grid">
                                <?php 
                                // Create an array with 24 PCs for more realistic lab simulation
                                $pcStatus = array_fill(0, 24, 'Available');
                                // Set some PCs to different statuses
                                $pcStatus[3] = 'In Use'; 
                                $pcStatus[7] = 'In Use'; 
                                $pcStatus[11] = 'Maintenance';
                                $pcStatus[15] = 'In Use';
                                $pcStatus[18] = 'Maintenance';
                                $pcStatus[21] = 'In Use';
                                ?>
                                
                                <?php for($i = 1; $i <= count($pcStatus); $i++): ?>
                                    <?php 
                                        $pcClasses = 'pc-card';
                                        $statusColor = 'text-green-600';
                                        $bgColor = 'bg-green-50 border-green-100';
                                        
                                        if($pcStatus[$i-1] === 'In Use') {
                                            $statusColor = 'text-primary';
                                            $bgColor = 'bg-primary bg-opacity-10 border-primary border-opacity-20';
                                        } else if($pcStatus[$i-1] === 'Maintenance') {
                                            $statusColor = 'text-yellow-600';
                                            $bgColor = 'bg-yellow-50 border-yellow-100';
                                        }
                                        $pcClasses .= ' ' . $bgColor;
                                    ?>
                                    <div class="<?php echo $pcClasses; ?>">
                                        <i class="fas fa-desktop text-secondary mb-1"></i>
                                        <div class="pc-id">PC-<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></div>
                                        <div class="pc-status <?php echo $statusColor; ?>"><?php echo $pcStatus[$i-1]; ?></div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Information Card -->
                    <div class="section-card bg-primary bg-opacity-10 border border-primary border-opacity-20">
                        <div class="px-6 py-3 bg-primary bg-opacity-20 text-secondary font-semibold flex items-center">
                            <i class="fas fa-info-circle mr-2"></i> Reservation Guidelines
                        </div>
                        <div class="scrollable-section guidelines-scroll p-4">
                            <ul class="space-y-3 text-sm text-gray-700">
                                <li class="flex items-start">
                                    <i class="fas fa-check text-primary mt-1 mr-2 flex-shrink-0"></i>
                                    <span>Reservations must be approved by a laboratory administrator before you can use the facility.</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-primary mt-1 mr-2 flex-shrink-0"></i>
                                    <span>Each student is allocated 30 sessions per semester. One session equals one hour of laboratory use.</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-primary mt-1 mr-2 flex-shrink-0"></i>
                                    <span>You can reserve up to 5 consecutive sessions in a single reservation.</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-primary mt-1 mr-2 flex-shrink-0"></i>
                                    <span>If you need to cancel a reservation, please do so at least 2 hours before the scheduled time.</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-primary mt-1 mr-2 flex-shrink-0"></i>
                                    <span>Students must vacate the laboratory promptly at the end of their reserved session.</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-primary mt-1 mr-2 flex-shrink-0"></i>
                                    <span>Food and drinks are not allowed inside the computer laboratories.</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check text-primary mt-1 mr-2 flex-shrink-0"></i>
                                    <span>Students are responsible for any damages to equipment during their session.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Reservations Section -->
            <div class="mb-8">
                <div class="flex items-center mb-4">
                    <i class="fas fa-clock text-primary mr-2 text-xl"></i>
                    <h2 class="text-xl font-semibold text-secondary">Pending Reservations</h2>
                </div>
                <div class="section-card">
                    <?php if (empty($pending_reservations)): ?>
                        <div class="p-6 text-center text-gray-500 italic">No pending reservations</div>
                    <?php else: ?>
                        <div class="scrollable-section table-scroll">
                            <div class="scroll-container">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="table-header sticky top-0 z-10">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php 
                                        // Add more mock data to demonstrate scrolling
                                        $mockReservations = [
                                            ['lab_name' => 'Lab 524', 'date' => '2024-06-15', 'time' => '10:00 AM - 11:00 AM', 'status' => 'PENDING'],
                                            ['lab_name' => 'Lab 530', 'date' => '2024-06-17', 'time' => '2:00 PM - 4:00 PM', 'status' => 'APPROVED'],
                                            ['lab_name' => 'Lab 526', 'date' => '2024-06-18', 'time' => '9:00 AM - 10:00 AM', 'status' => 'PENDING'],
                                            ['lab_name' => 'Lab 542', 'date' => '2024-06-19', 'time' => '3:00 PM - 5:00 PM', 'status' => 'APPROVED'],
                                            ['lab_name' => 'Lab 544', 'date' => '2024-06-20', 'time' => '1:00 PM - 2:00 PM', 'status' => 'PENDING'],
                                            ['lab_name' => 'Lab 528', 'date' => '2024-06-21', 'time' => '11:00 AM - 12:00 PM', 'status' => 'APPROVED'],
                                        ];
                                        ?>
                                        <?php foreach ($mockReservations as $reservation): ?>
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($reservation['lab_name']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($reservation['date']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($reservation['time']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <?php if ($reservation['status'] === 'PENDING'): ?>
                                                        <span class="status-badge bg-primary bg-opacity-10 text-secondary">
                                                            <i class="fas fa-clock mr-1"></i> Pending
                                                        </span>
                                                    <?php elseif ($reservation['status'] === 'APPROVED'): ?>
                                                        <span class="status-badge bg-green-100 text-green-800">
                                                            <i class="fas fa-check mr-1"></i> Approved
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <button class="text-red-500 hover:text-red-700">
                                                        <i class="fas fa-times-circle"></i> Cancel
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reservation History Section -->
            <div class="mb-8">
                <div class="flex items-center mb-4">
                    <i class="fas fa-history text-primary mr-2 text-xl"></i>
                    <h2 class="text-xl font-semibold text-secondary">Reservation History</h2>
                </div>
                <div class="section-card">
                    <?php if (empty($reservation_history)): ?>
                        <div class="p-6 text-center text-gray-500 italic">No reservation history</div>
                    <?php else: ?>
                        <div class="scrollable-section table-scroll">
                            <div class="scroll-container">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="table-header sticky top-0 z-10">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php 
                                        // Add more mock data to demonstrate scrolling
                                        $mockHistory = [
                                            ['lab_name' => 'Lab 526', 'date' => '2024-06-01', 'time' => '9:00 AM - 10:00 AM', 'status' => 'COMPLETED'],
                                            ['lab_name' => 'Lab 542', 'date' => '2024-05-28', 'time' => '1:00 PM - 3:00 PM', 'status' => 'CANCELLED'],
                                            ['lab_name' => 'Lab 524', 'date' => '2024-05-25', 'time' => '10:00 AM - 11:00 AM', 'status' => 'COMPLETED'],
                                            ['lab_name' => 'Lab 530', 'date' => '2024-05-20', 'time' => '2:00 PM - 3:00 PM', 'status' => 'COMPLETED'],
                                            ['lab_name' => 'Lab 528', 'date' => '2024-05-15', 'time' => '11:00 AM - 1:00 PM', 'status' => 'CANCELLED'],
                                            ['lab_name' => 'Lab 544', 'date' => '2024-05-10', 'time' => '3:00 PM - 4:00 PM', 'status' => 'COMPLETED'],
                                            ['lab_name' => 'Lab 526', 'date' => '2024-05-05', 'time' => '9:00 AM - 11:00 AM', 'status' => 'COMPLETED'],
                                        ];
                                        ?>
                                        <?php foreach ($mockHistory as $history): ?>
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($history['lab_name']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($history['date']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($history['time']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <?php if ($history['status'] === 'COMPLETED'): ?>
                                                        <span class="status-badge bg-primary bg-opacity-10 text-secondary">
                                                            <i class="fas fa-check-circle mr-1"></i> Completed
                                                        </span>
                                                    <?php elseif ($history['status'] === 'CANCELLED'): ?>
                                                        <span class="status-badge bg-red-100 text-red-800">
                                                            <i class="fas fa-ban mr-1"></i> Cancelled
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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
</script>

<?php include('includes/footer.php'); ?>