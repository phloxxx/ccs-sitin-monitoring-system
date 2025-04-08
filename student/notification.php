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
$stmt = $conn->prepare("SELECT USERNAME, PROFILE_PIC FROM USERS WHERE USER_ID = ?");
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
// Set up profile picture path handling - simplified approach
$default_pic = "images/snoopy.jpg";
$profile_pic = !empty($user['PROFILE_PIC']) ? $user['PROFILE_PIC'] : $default_pic;

// Mock data for notifications (this would come from a database in a real app)
$notifications = [
    [
        'id' => 1,
        'title' => 'Reservation Confirmed',
        'message' => 'Your reservation for CCS Lab 1, PC-15 on May 20, 2024 at 9:00 AM has been confirmed.',
        'date' => '2024-05-18 14:30:00',
        'read' => false
    ],
    [
        'id' => 2,
        'title' => 'Lab Schedule Update',
        'message' => 'CCS Lab 2 will be closed for maintenance on May 25, 2024. Please reschedule any reservations for that day.',
        'date' => '2024-05-15 10:15:00',
        'read' => true
    ],
    [
        'id' => 3,
        'title' => 'Session Reminder',
        'message' => 'Your sit-in session is scheduled to begin in 30 minutes.',
        'date' => '2024-05-10 08:30:00',
        'read' => true
    ],
];

// Format date helper function
function formatTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("M j, Y", $timestamp);
    }
}

$pageTitle = "Notifications";
$bodyClass = "bg-light font-montserrat";
include('includes/header.php');
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header class="bg-secondary px-6 py-4 shadow-md">
        <div class="container mx-auto flex flex-col md:flex-row items-center justify-between">
            <!-- Logo and Username -->
            <a href="profile.php" class="flex items-center space-x-4 mb-4 md:mb-0 group">
                <div class="h-12 w-12 rounded-full overflow-hidden border-2 border-primary">
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="h-full w-full object-cover">
                </div>
                <span class="text-white font-semibold text-lg group-hover:text-primary transition"><?php echo htmlspecialchars($username); ?></span>
            </a>
            
            <!-- Navigation -->
            <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6">
                <nav>
                    <ul class="flex flex-wrap justify-center space-x-6">
                        <li><a href="dashboard.php" class="text-white hover:text-primary transition">Home</a></li>
                        <li><a href="notification.php" class="text-white hover:text-primary transition font-medium">Notification</a></li>
                        <li><a href="history.php" class="text-white hover:text-primary transition">History</a></li>
                        <li><a href="reservation.php" class="text-white hover:text-primary transition">Reservation</a></li>
                    </ul>
                </nav>
                
                <button onclick="confirmLogout(event)" 
                        class="bg-primary text-secondary px-4 py-2 rounded-full font-medium hover:bg-white hover:text-dark transition">
                    Logout
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow p-6 bg-light">
        <div class="container mx-auto max-w-4xl">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-secondary">Notifications</h1>
                <button class="text-sm text-secondary hover:text-dark underline">Mark all as read</button>
            </div>
            
            <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
                <ul class="divide-y divide-gray-200">
                    <?php if (empty($notifications)): ?>
                        <li class="p-6 text-center text-gray-500">No notifications found</li>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <li class="p-4 <?php echo $notification['read'] ? 'bg-white' : 'bg-blue-50'; ?> hover:bg-gray-50 transition">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 mt-0.5">
                                        <?php if (!$notification['read']): ?>
                                            <span class="h-2 w-2 rounded-full bg-blue-600 block"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center justify-between">
                                            <p class="text-sm font-medium text-primary"><?php echo htmlspecialchars($notification['title']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo formatTimeAgo($notification['date']); ?></p>
                                        </div>
                                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </main>
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
