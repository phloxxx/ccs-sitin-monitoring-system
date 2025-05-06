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

// Initialize stats array
$stats = [
    'total_students' => 0,
    'active_sessions' => 0,
    'total_sessions' => 0,
    'labs_in_use' => 0
];

try {
    // Count total students (from USERS)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM USERS");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_students'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Count active sessions from SITIN table
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM SITIN WHERE STATUS = 'ACTIVE'");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['active_sessions'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Count total sessions (all time) from SITIN table
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM SITIN");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_sessions'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Count labs in use (distinct labs with active sessions)
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT LAB_ID) as count FROM SITIN WHERE STATUS = 'ACTIVE'");
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['labs_in_use'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
} catch (Exception $e) {
    // Handle database errors - silently continue with zero values
    // You might want to log the error in a production environment
}

// Get recent announcements (last 5) with admin username
$announcements = [];
try {
    // Query that joins ANNOUNCEMENT with ADMIN to get the username
    $stmt = $conn->prepare("SELECT a.*, admin.username FROM ANNOUNCEMENT a 
                           JOIN ADMIN admin ON a.ADMIN_ID = admin.ADMIN_ID 
                           ORDER BY a.CREATED_AT DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // If table doesn't exist or other error, use placeholder data
    $announcements = [
        [
            'ANNOUNCE_ID' => 1,
            'TITLE' => 'System Maintenance Notice',
            'CONTENT' => 'The sit-in monitoring system will be undergoing maintenance this weekend.',
            'CREATED_AT' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'ADMIN_ID' => 1,
            'username' => $username // Use the logged-in admin's username for placeholder data
        ],
        [
            'ANNOUNCE_ID' => 2,
            'TITLE' => 'New Lab Rules',
            'CONTENT' => 'Starting next week, all students must register their sit-in requests at least 24 hours in advance.',
            'CREATED_AT' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'ADMIN_ID' => 1,
            'username' => $username // Use the logged-in admin's username for placeholder data
        ]
    ];
}

$pageTitle = "Admin Dashboard";
$bodyClass = "bg-light font-poppins";
include('includes/header.php');
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <div class="hidden md:flex md:flex-shrink-0">
        <div class="flex flex-col w-64 bg-secondary">
            <div class="flex items-center justify-center h-16 px-4 bg-dark text-white">
                <span class="text-xl font-semibold">CCS Admin Panel</span>
            </div>
            <div class="flex flex-col flex-grow px-4 py-4 overflow-y-auto">
                <nav class="flex-1 space-y-2">
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-home mr-3"></i>
                        <span>Home</span>
                    </a>
                    <a href="search.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-search mr-3"></i>
                        <span>Search</span>
                    </a>
                    <a href="students.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-user-graduate mr-3"></i>
                        <span>Students</span>
                    </a>
                    <a href="sitin.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3"></i>
                        <span>Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        <span>Reservation</span>
                    </a>
                    <hr class="my-4 border-gray-400 border-opacity-20">
                    <a href="announcements.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-bullhorn mr-3"></i>
                        <span>Announcements</span>
                    </a>
                    <a href="feedbacks.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-comments mr-3"></i>
                        <span>Feedbacks</span>
                    </a>
                </nav>
                
                <div class="mt-auto">
                    <a href="#" onclick="confirmLogout(event)" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="flex flex-col flex-1 overflow-hidden">
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm">
            <div class="flex items-center justify-between h-16 px-6">
                <!-- Mobile Menu Button -->
                <div class="flex items-center">
                    <button id="mobile-menu-button" class="text-gray-500 md:hidden focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="ml-4 text-xl font-semibold text-secondary">Dashboard</h1>
                </div>
                
                <!-- Admin Profile -->
                <div class="flex items-center">
                    <span class="mr-4 text-sm font-medium text-gray-700"><?php echo htmlspecialchars($username); ?></span>
                    <button class="flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white">
                        <i class="fas fa-user"></i>
                    </button>
                </div>
            </div>
            
            <!-- Mobile Navigation -->
            <div id="mobile-menu" class="hidden md:hidden px-4 py-2 bg-secondary">
                <nav class="space-y-2">
                    <a href="dashboard.php" class="block px-4 py-2 text-white rounded-lg bg-primary bg-opacity-30 hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-home mr-3"></i>
                        Home
                    </a>
                    <a href="search.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-search mr-3"></i>
                        Search
                    </a>
                    <a href="students.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-user-graduate mr-3"></i>
                        Student
                    </a>
                    <a href="sitin.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-desktop mr-3"></i>
                        Sit-in
                    </a>
                    <a href="reservation.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        Reservation
                    </a>
                    <hr class="my-2 border-gray-400 border-opacity-20">
                    <a href="announcements.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-bullhorn mr-3"></i>
                        Announcements
                    </a>
                    <a href="feedbacks.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-comments mr-3"></i>
                        Feedbacks
                    </a>
                    <a href="#" onclick="confirmLogout(event)" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        Logout
                    </a>
                </nav>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Students -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                            <i class="fas fa-user-graduate text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Students</h3>
                            <p class="text-lg font-semibold text-gray-800"><?php echo $stats['total_students']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Active Sessions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-500">
                            <i class="fas fa-desktop text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Active Sessions</h3>
                            <p class="text-lg font-semibold text-gray-800"><?php echo $stats['active_sessions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Total Sessions -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                            <i class="fas fa-history text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Total Sessions</h3>
                            <p class="text-lg font-semibold text-gray-800"><?php echo $stats['total_sessions']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Labs in Use -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                            <i class="fas fa-building text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-500">Labs in Use</h3>
                            <p class="text-lg font-semibold text-gray-800"><?php echo $stats['labs_in_use']; ?>/6</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Announcements -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-secondary to-secondary p-4 flex justify-between items-center text-white">
                    <h2 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-bullhorn mr-2"></i> Recent Announcements
                    </h2>
                    <a href="announcements.php" class="bg-white text-secondary px-3 py-1 rounded-md text-sm flex items-center hover:bg-gray-100 transition-colors shadow-sm">
                        <i class="fas fa-list mr-1"></i> View All
                    </a>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        <?php if (empty($announcements)): ?>
                            <p class="text-gray-500 text-center py-4">No recent announcements.</p>
                        <?php else: ?>
                            <?php foreach (array_slice($announcements, 0, 3) as $announcement): ?>
                                <div class="border-l-4 border-blue-500 pl-4 py-3 bg-blue-50 rounded-md">
                                    <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($announcement['TITLE']); ?></h3>
                                    <p class="text-sm text-gray-600 line-clamp-2 mt-1"><?php echo htmlspecialchars($announcement['CONTENT']); ?></p>
                                    <div class="flex justify-between mt-2">
                                        <p class="text-xs text-gray-500 flex items-center">
                                            <i class="fas fa-user mr-1"></i> <?php echo htmlspecialchars($announcement['username']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500 flex items-center">
                                            <i class="fas fa-calendar-alt mr-1"></i> <?php echo date('F j, Y', strtotime($announcement['CREATED_AT'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-5">
                        <button id="open-announcement-modal" class="bg-blue-500 hover:bg-blue-600 transition-colors px-4 py-2 rounded-md text-sm font-medium text-white inline-flex items-center shadow-sm">
                            <i class="fas fa-plus mr-2"></i> New Announcement
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- System Info -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-green-500 p-4 text-white">
                    <h2 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-info-circle mr-2"></i> System Information
                    </h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 flex items-center mb-2">
                                <span class="w-32 font-medium text-gray-700">System:</span> 
                                <span class="bg-blue-50 px-3 py-1 rounded-md">CCS Sit-in Monitoring System</span>
                            </p>
                            <p class="text-sm text-gray-600 flex items-center mb-2">
                                <span class="w-32 font-medium text-gray-700">Version:</span>
                                <span class="bg-blue-50 px-3 py-1 rounded-md">1.0.0</span>
                            </p>
                            <p class="text-sm text-gray-600 flex items-center">
                                <span class="w-32 font-medium text-gray-700">Last update:</span>
                                <span class="bg-blue-50 px-3 py-1 rounded-md"><?php echo date('F j, Y'); ?></span>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 flex items-center mb-2">
                                <span class="w-32 font-medium text-gray-700">Server time:</span>
                                <span class="bg-blue-50 px-3 py-1 rounded-md"><?php echo date('h:i:s A'); ?></span>
                            </p>
                            <p class="text-sm text-gray-600 flex items-center">
                                <span class="w-32 font-medium text-gray-700">Database status:</span>
                                <span class="bg-green-100 text-green-600 px-3 py-1 rounded-md flex items-center">
                                    <i class="fas fa-check-circle mr-1"></i> Connected
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
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

<!-- Announcement Modal -->
<div id="announcement-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-secondary">Create New Announcement</h3>
            <button type="button" id="close-announcement-modal" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="announcement-form">
            <div id="announcement-error" class="mb-4 text-red-500 text-sm hidden"></div>
            <div id="announcement-success" class="mb-4 text-green-500 text-sm hidden"></div>
            
            <div class="mb-4">
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input type="text" id="title" name="title" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
            </div>
            
            <div class="mb-6">
                <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                <textarea id="content" name="content" rows="4" required
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary"></textarea>
            </div>
            
            <div class="flex justify-end space-x-4">
                <button type="button" id="cancel-announcement" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" id="submit-announcement"
                        class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-dark transition-colors">
                    Post Announcement
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    mobileMenuButton.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });
    
    // Confirmation dialog for logout
    function confirmLogout(event) {
        event.preventDefault();
        document.getElementById('confirmation-dialog').classList.remove('hidden');
    }
    
    document.getElementById('cancel-logout').addEventListener('click', () => {
        document.getElementById('confirmation-dialog').classList.add('hidden');
    });
    
    // Close dialog when clicking outside
    document.getElementById('confirmation-dialog').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });

    // Announcement modal handling
    const announcementModal = document.getElementById('announcement-modal');
    const openAnnouncementModal = document.getElementById('open-announcement-modal');
    const closeAnnouncementModal = document.getElementById('close-announcement-modal');
    const cancelAnnouncement = document.getElementById('cancel-announcement');
    const announcementForm = document.getElementById('announcement-form');
    const errorElement = document.getElementById('announcement-error');
    const successElement = document.getElementById('announcement-success');
    
    openAnnouncementModal.addEventListener('click', () => {
        announcementModal.classList.remove('hidden');
        errorElement.classList.add('hidden');
        successElement.classList.add('hidden');
        announcementForm.reset();
    });
    
    function closeModal() {
        announcementModal.classList.add('hidden');
    }
    
    closeAnnouncementModal.addEventListener('click', closeModal);
    cancelAnnouncement.addEventListener('click', closeModal);
    
    // Close modal when clicking outside
    announcementModal.addEventListener('click', function(event) {
        if (event.target === this) {
            closeModal();
        }
    });
    
    // Handle form submission via AJAX
    announcementForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const title = document.getElementById('title').value;
        const content = document.getElementById('content').value;
        
        // Disable the submit button during submission
        const submitButton = document.getElementById('submit-announcement');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Posting...';
        
        // Hide any previous messages
        errorElement.classList.add('hidden');
        successElement.classList.add('hidden');
        
        // Send AJAX request
        fetch('ajax/create_announcement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `title=${encodeURIComponent(title)}&content=${encodeURIComponent(content)}`
        })
        .then(response => response.json())
        .then(data => {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Post Announcement';
            
            if (data.success) {
                // Show success message
                successElement.textContent = data.message;
                successElement.classList.remove('hidden');
                
                // Reset the form
                announcementForm.reset();
                
                // Refresh the announcements list
                refreshAnnouncements();
                
                // Close the modal after a shorter delay (500ms instead of 2000ms)
                setTimeout(closeModal, 500);
            } else {
                // Show error message
                errorElement.textContent = data.message || 'An error occurred while posting the announcement.';
                errorElement.classList.remove('hidden');
            }
        })
        .catch(error => {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Post Announcement';
            errorElement.textContent = 'Network error. Please try again.';
            errorElement.classList.remove('hidden');
            console.error('Error:', error);
        });
    });
    
    // Function to refresh announcements list
    function refreshAnnouncements() {
        fetch('ajax/get_announcements.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const announcementsContainer = document.querySelector('.space-y-4');
                    
                    if (data.announcements.length === 0) {
                        announcementsContainer.innerHTML = '<p class="text-gray-500 text-center py-4">No recent announcements.</p>';
                    } else {
                        let html = '';
                        
                        data.announcements.forEach(announcement => {
                            const date = new Date(announcement.CREATED_AT);
                            const formattedDate = date.toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                            
                            html += `
                            <div class="border-l-4 border-primary pl-4 py-2">
                                <h3 class="font-medium text-gray-800">${announcement.TITLE}</h3>
                                <p class="text-sm text-gray-600 line-clamp-2">${announcement.CONTENT}</p>
                                <div class="flex justify-between mt-1">
                                    <p class="text-xs text-gray-400">Posted by: ${announcement.username}</p>
                                    <p class="text-xs text-gray-400">${formattedDate}</p>
                                </div>
                            </div>`;
                        });
                        
                        announcementsContainer.innerHTML = html;
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }
</script>

<?php include('includes/footer.php'); ?>
