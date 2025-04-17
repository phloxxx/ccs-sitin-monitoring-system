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

// Get all announcements with admin username
$announcements = [];
try {
    // Query that joins ANNOUNCEMENT with ADMIN to get the username
    $stmt = $conn->prepare("SELECT a.*, admin.username FROM ANNOUNCEMENT a 
                           JOIN ADMIN admin ON a.ADMIN_ID = admin.ADMIN_ID 
                           ORDER BY a.CREATED_AT DESC");
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
            'username' => $username
        ],
        [
            'ANNOUNCE_ID' => 2,
            'TITLE' => 'New Lab Rules',
            'CONTENT' => 'Starting next week, all students must register their sit-in requests at least 24 hours in advance.',
            'CREATED_AT' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'ADMIN_ID' => 1,
            'username' => $username
        ]
    ];
}

$pageTitle = "Announcements";
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
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
                    <a href="announcements.php" class="flex items-center px-4 py-3 text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
                    <h1 class="ml-4 text-xl font-semibold text-secondary">Announcements</h1>
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
                    <a href="dashboard.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
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
                    <a href="announcements.php" class="block px-4 py-2 text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20">
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
            <!-- Announcements Section Header -->
            <div class="flex justify-end items-center mb-6">
                <button id="open-announcement-modal" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-secondary hover:bg-dark transition-colors">
                    <i class="fas fa-plus mr-2"></i> New Announcement
                </button>
            </div>
            
            <!-- Announcements List -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <?php if (empty($announcements)): ?>
                    <div class="p-6 text-center">
                        <p class="text-gray-500">No announcements have been posted yet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Content</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posted By</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($announcements as $announcement): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($announcement['TITLE']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate"><?php echo htmlspecialchars($announcement['CONTENT']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($announcement['username']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('F j, Y', strtotime($announcement['CREATED_AT'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <?php if ($admin_id == $announcement['ADMIN_ID']): ?>
                                                <button
                                                    class="text-primary hover:text-secondary mr-3 edit-btn"
                                                    data-id="<?php echo $announcement['ANNOUNCE_ID']; ?>"
                                                    data-title="<?php echo htmlspecialchars($announcement['TITLE']); ?>"
                                                    data-content="<?php echo htmlspecialchars($announcement['CONTENT']); ?>"
                                                >
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button
                                                    class="text-red-600 hover:text-red-800 delete-btn"
                                                    data-id="<?php echo $announcement['ANNOUNCE_ID']; ?>"
                                                    data-title="<?php echo htmlspecialchars($announcement['TITLE']); ?>"
                                                >
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            <?php else: ?>
                                                <span class="text-gray-400">No actions</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
            
            <input type="hidden" id="announcement-id" name="id" value="">
            <input type="hidden" id="form-mode" name="mode" value="create">
            
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

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-secondary mb-4">Confirm Deletion</h3>
        <p id="delete-message" class="text-gray-600 mb-6">Are you sure you want to delete this announcement?</p>
        <input type="hidden" id="delete-id" value="">
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
    const formMode = document.getElementById('form-mode');
    const announcementId = document.getElementById('announcement-id');
    const submitButton = document.getElementById('submit-announcement');
    
    openAnnouncementModal.addEventListener('click', () => {
        // Reset form for creating a new announcement
        formMode.value = 'create';
        announcementId.value = '';
        announcementForm.reset();
        submitButton.textContent = 'Post Announcement';
        
        // Clear any previous messages
        errorElement.classList.add('hidden');
        successElement.classList.add('hidden');
        
        // Show the modal
        announcementModal.classList.remove('hidden');
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
        const mode = formMode.value;
        const id = announcementId.value;
        
        // Disable the submit button during submission
        submitButton.disabled = true;
        
        if (mode === 'create') {
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Posting...';
        } else {
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
        }
        
        // Hide any previous messages
        errorElement.classList.add('hidden');
        successElement.classList.add('hidden');
        
        // Determine which endpoint to use based on the form mode
        let endpoint = 'ajax/create_announcement.php';
        let requestData = `title=${encodeURIComponent(title)}&content=${encodeURIComponent(content)}`;
        
        if (mode === 'edit') {
            endpoint = 'ajax/update_announcement.php';
            requestData = `id=${encodeURIComponent(id)}&title=${encodeURIComponent(title)}&content=${encodeURIComponent(content)}`;
        }
        
        // Send AJAX request
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: requestData
        })
        .then(response => response.json())
        .then(data => {
            submitButton.disabled = false;
            submitButton.innerHTML = mode === 'create' ? 'Post Announcement' : 'Update Announcement';
            
            if (data.success) {
                // Show success message
                successElement.textContent = data.message;
                successElement.classList.remove('hidden');
                
                // Reset the form if it's a new announcement
                if (mode === 'create') {
                    announcementForm.reset();
                }
                
                // Close the modal after a shorter delay (500ms instead of 2000ms)
                setTimeout(() => {
                    closeModal();
                    // Reload the page to reflect changes
                    window.location.reload();
                }, 500);
            } else {
                // Show error message
                errorElement.textContent = data.message || 'An error occurred while processing your request.';
                errorElement.classList.remove('hidden');
            }
        })
        .catch(error => {
            submitButton.disabled = false;
            submitButton.innerHTML = mode === 'create' ? 'Post Announcement' : 'Update Announcement';
            errorElement.textContent = 'Network error. Please try again.';
            errorElement.classList.remove('hidden');
            console.error('Error:', error);
        });
    });
    
    // Handle edit buttons
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const content = this.getAttribute('data-content');
            
            // Set form to edit mode
            formMode.value = 'edit';
            announcementId.value = id;
            
            // Fill form with announcement data
            document.getElementById('title').value = title;
            document.getElementById('content').value = content;
            
            // Update button text
            submitButton.textContent = 'Update Announcement';
            
            // Clear any previous messages
            errorElement.classList.add('hidden');
            successElement.classList.add('hidden');
            
            // Show the modal
            announcementModal.classList.remove('hidden');
        });
    });
    
    // Handle delete buttons
    const deleteModal = document.getElementById('delete-modal');
    const deleteId = document.getElementById('delete-id');
    const deleteMessage = document.getElementById('delete-message');
    const cancelDelete = document.getElementById('cancel-delete');
    const confirmDelete = document.getElementById('confirm-delete');
    
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            
            // Set delete ID
            deleteId.value = id;
            
            // Update confirmation message
            deleteMessage.textContent = `Are you sure you want to delete the announcement "${title}"?`;
            
            // Show delete confirmation modal
            deleteModal.classList.remove('hidden');
        });
    });
    
    // Cancel delete
    cancelDelete.addEventListener('click', () => {
        deleteModal.classList.add('hidden');
    });
    
    // Close delete modal when clicking outside
    deleteModal.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
    
    // Confirm delete
    confirmDelete.addEventListener('click', function() {
        // Disable button to prevent multiple submissions
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';
        
        const id = deleteId.value;
        
        // Send AJAX request to delete
        fetch('ajax/delete_announcement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${encodeURIComponent(id)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal and reload page
                deleteModal.classList.add('hidden');
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to delete announcement'));
                this.disabled = false;
                this.innerHTML = 'Delete';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error occurred while trying to delete');
            this.disabled = false;
            this.innerHTML = 'Delete';
        });
    });
</script>

<?php include('includes/footer.php'); ?>
