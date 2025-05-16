<?php 
session_start();
require_once('../config/db.php'); 

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; 

// Check if notifications table exists, create it if not
$result = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($result->num_rows == 0) {
    // Create notifications table
    $sql = "CREATE TABLE `notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `message` text NOT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($sql);
    
    // Create a welcome notification for the user
    $title = "Welcome to Notifications!";
    $message = "Your notification system is now set up. You'll receive updates about your reservations and activities here.";
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
    $stmt->bind_param("iss", $user_id, $title, $message);
    $stmt->execute();
    $stmt->close();
}

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

// Check if notifications table exists
$table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_table->num_rows > 0) {
    $table_exists = true;
}

// Handle mark as read functionality
if ($table_exists) {
    if (isset($_POST['mark_all_read'])) {
        $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $update->bind_param("i", $user_id);
        $update->execute();
        $update->close();
        // Redirect to refresh the page
        header("Location: notification.php");
        exit();
    }

    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        $notification_id = $_POST['notification_id'];
        $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $update->bind_param("ii", $notification_id, $user_id);
        $update->execute();
        $update->close();
        // Redirect to refresh the page
        header("Location: notification.php");
        exit();
    }
}

// Fetch actual notifications from database
$notifications = [];

if ($table_exists) {
    try {
        $stmt = $conn->prepare("SELECT id, title, message, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'message' => $row['message'],
                'date' => $row['created_at'],
                'read' => $row['is_read'] == 1
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        // If there's an error, just continue with empty notifications
    }
} else {
    // Temporary fallback notifications for development/testing
    $notifications = [
        [
            'id' => 0,
            'title' => 'System Setup',
            'message' => 'The notification system is being set up. Real notifications will appear here once setup is complete.',
            'date' => date('Y-m-d H:i:s'),
            'read' => false
        ]
    ];
}

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
$bodyClass = "bg-light font-poppins";
include('includes/header.php');
?>

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
                        <li>
                            <a href="notification.php" class="text-white hover:text-primary transition font-semibold border-b-2 border-primary pb-1 flex items-center">
                                Notification
                                <?php
                                // Count unread notifications 
                                $unread_count = 0;
                                if ($table_exists) {
                                    try {
                                        $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
                                        $stmt->bind_param("i", $user_id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        $row = $result->fetch_assoc();
                                        $unread_count = $row['unread'];
                                        $stmt->close();
                                        
                                        if ($unread_count > 0) {
                                            echo '<span class="ml-1.5 bg-red-500 text-white text-xs font-medium px-1.5 py-0.5 rounded-full">' . $unread_count . '</span>';
                                        }
                                    } catch (Exception $e) {
                                        // Ignore errors
                                    }
                                }
                                ?>
                            </a>
                        </li>
                        <li><a href="history.php" class="text-white hover:text-primary transition">History</a></li>
                        <li><a href="reservation.php" class="text-white hover:text-primary transition">Reservation</a></li>
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
        <div class="container mx-auto max-w-4xl">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-secondary">Notifications</h1>
                <?php if ($table_exists && !empty($notifications)): ?>
                <form method="POST" action="notification.php">
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" class="text-sm text-secondary hover:text-dark underline">Mark all as read</button>
                </form>
                <?php endif; ?>
            </div>
            
            <?php if (!$table_exists): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4">
                <p class="font-bold">Notice</p>
                <p>The notification system is being set up. Please run the database setup script or contact the administrator.</p>
            </div>
            <?php endif; ?>
            
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
                                            <div class="flex items-center">
                                                <?php if ($table_exists && !$notification['read']): ?>
                                                <form method="POST" action="notification.php" class="mr-2">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <input type="hidden" name="mark_read" value="1">
                                                    <button type="submit" class="text-xs text-blue-500 hover:text-blue-700">Mark as read</button>
                                                </form>
                                                <?php endif; ?>
                                                <p class="text-xs text-gray-500"><?php echo formatTimeAgo($notification['date']); ?></p>
                                            </div>
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