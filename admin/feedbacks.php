<?php
session_start();
require_once('../config/db.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$username = $_SESSION['username'];

// Initialize rating counters
$rating_counts = [
    1 => 0,
    2 => 0,
    3 => 0,
    4 => 0,
    5 => 0
];

// Fetch all feedback
$feedbacks = [];
// Fix: Use the correct column name CREATED_AT instead of SUBMISSION_DATE
$query = "SELECT f.*, u.USERNAME, u.PROFILE_PIC, l.LAB_NAME, s.SESSION_START, s.SESSION_END
          FROM FEEDBACK f
          JOIN SITIN s ON f.SITIN_ID = s.SITIN_ID
          JOIN USERS u ON s.IDNO = u.IDNO
          JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
          ORDER BY f.CREATED_AT DESC";

// Try executing the query to see the specific error
try {
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $feedbacks[] = $row;
            
            // Count ratings
            if (isset($row['RATING']) && $row['RATING'] >= 1 && $row['RATING'] <= 5) {
                $rating_counts[$row['RATING']]++;
            }
        }
    } else {
        // Fallback query with no joins
        $fallback_query = "SELECT * FROM FEEDBACK ORDER BY CREATED_AT DESC";
        $fallback_result = $conn->query($fallback_query);
        
        if ($fallback_result) {
            while ($row = $fallback_result->fetch_assoc()) {
                // Add placeholder data for missing JOIN fields
                $row['USERNAME'] = 'Unknown User';
                $row['PROFILE_PIC'] = '';
                $row['LAB_NAME'] = 'Unknown Lab';
                $row['SESSION_START'] = $row['CREATED_AT'];
                $row['SESSION_END'] = $row['CREATED_AT'];
                
                // Count ratings
                if (isset($row['RATING']) && $row['RATING'] >= 1 && $row['RATING'] <= 5) {
                    $rating_counts[$row['RATING']]++;
                }
                
                $feedbacks[] = $row;
            }
        }
    }
} catch (Exception $e) {
    // Try a simpler query as fallback
    $columns_query = "SHOW COLUMNS FROM FEEDBACK";
    $columns_result = $conn->query($columns_query);
    $feedback_columns = [];
    
    if ($columns_result) {
        while ($col = $columns_result->fetch_assoc()) {
            $feedback_columns[] = $col['Field'];
        }
    }
    
    // Try another query approach
    if (in_array('SITIN_ID', $feedback_columns)) {
        $query2 = "SELECT f.* FROM FEEDBACK f ORDER BY f.CREATED_AT DESC";
        $result2 = $conn->query($query2);
        if ($result2) {
            while ($row = $result2->fetch_assoc()) {
                // Add placeholder data for missing JOIN fields
                $row['USERNAME'] = 'Unknown User';
                $row['PROFILE_PIC'] = '';
                $row['LAB_NAME'] = 'Unknown Lab';
                $row['SESSION_START'] = $row['CREATED_AT'];
                $row['SESSION_END'] = $row['CREATED_AT'];
                
                // Count ratings
                if (isset($row['RATING']) && $row['RATING'] >= 1 && $row['RATING'] <= 5) {
                    $rating_counts[$row['RATING']]++;
                }
                
                $feedbacks[] = $row;
            }
        }
    }
}

$pageTitle = "Student Feedbacks";
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
                    <a href="feedbacks.php" class="flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
                        Student Feedbacks
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
                    <a href="lab_resources.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-book-open mr-3 text-lg"></i>
                        <span class="font-medium">Lab Resources</span>
                    </a>
                    <a href="feedbacks.php" class="flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
            <!-- Feedback Dashboard Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-primary">
                    <h3 class="text-lg font-semibold text-gray-700">Total Feedback</h3>
                    <p class="text-2xl font-bold"><?php echo count($feedbacks); ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-sm border-l-4 border-yellow-500">
                    <h3 class="text-lg font-semibold text-gray-700">Average Rating</h3>
                    <p class="text-2xl font-bold">
                        <?php 
                        $total_rating = 0;
                        $count = 0;
                        foreach ($feedbacks as $feedback) {
                            if (isset($feedback['RATING']) && $feedback['RATING'] > 0) {
                                $total_rating += $feedback['RATING'];
                                $count++;
                            }
                        }
                        echo $count > 0 ? number_format($total_rating / $count, 1) : 'N/A';
                        ?>
                        <?php if($count > 0): ?>
                            <span class="text-yellow-500 text-xl">
                                <i class="fas fa-star"></i>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <!-- Rating Breakdown -->
            <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-3">Rating Breakdown</h3>
                <div class="space-y-2">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div class="flex items-center">
                            <div class="flex items-center w-20">
                                <span class="text-sm font-medium text-gray-700 mr-2"><?php echo $i; ?></span>
                                <div class="flex text-yellow-500">
                                    <?php for ($j = 1; $j <= $i; $j++): ?>
                                        <i class="fas fa-star text-sm"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="flex-grow mx-4">
                                <div class="bg-gray-200 rounded-full h-2.5">
                                    <?php 
                                    $percentage = $count > 0 ? ($rating_counts[$i] / $count) * 100 : 0;
                                    ?>
                                    <div class="bg-yellow-500 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                            <div class="w-16 text-right">
                                <span class="text-sm font-medium text-gray-700"><?php echo $rating_counts[$i]; ?></span>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Feedback List -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-4 border-b">
                    <h2 class="text-xl font-semibold">All Student Feedback</h2>
                </div>
                <?php if (empty($feedbacks)): ?>
                <div class="p-6 text-center text-gray-500">
                    <p>No feedback available</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lab & Session</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comments</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($feedbacks as $feedback): ?>
                            <tr>
                                <!-- Student Info -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <?php if ($feedback['PROFILE_PIC']): ?>
                                            <img class="h-10 w-10 rounded-full" src="../student/<?php echo htmlspecialchars($feedback['PROFILE_PIC']); ?>" alt="Profile">
                                            <?php else: ?>
                                            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                <i class="fas fa-user text-gray-500"></i>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($feedback['USERNAME']); ?></div>
                                            <div class="text-sm text-gray-500">Submitted: <?php echo date('M d, Y', strtotime($feedback['CREATED_AT'])); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <!-- Lab & Session Info -->
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($feedback['LAB_NAME']); ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?php 
                                        $start = date('M d, Y h:i A', strtotime($feedback['SESSION_START']));
                                        echo $start;
                                        ?>
                                    </div>
                                </td>

                                <!-- Rating -->
                                <td class="px-6 py-4">
                                    <div class="flex text-yellow-500">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="<?php echo ($i <= $feedback['RATING']) ? 'fas' : 'far'; ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                </td>

                                <!-- Comments -->
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs truncate">
                                        <?php echo htmlspecialchars($feedback['COMMENTS']); ?>
                                    </div>
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

<!-- View Feedback Modal -->
<div id="view-feedback-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
        <div class="flex justify-between items-center border-b pb-3">
            <h3 class="text-lg font-medium text-secondary">Feedback Details</h3>
            <button class="close-modal text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mt-4">
            <div class="mb-4">
                <p class="text-sm text-gray-500" id="view-username-lab"></p>
                <p class="text-sm text-gray-500" id="view-date"></p>
            </div>
            <div class="mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-1">Rating</h4>
                <div class="flex text-yellow-500" id="view-rating">
                    <!-- Stars will be inserted here -->
                </div>
            </div>
            <div class="mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-1">Comments</h4>
                <p class="text-sm text-gray-900 p-3 bg-gray-50 rounded" id="view-comments"></p>
            </div>
            <div class="mt-6 flex justify-end">
                <button class="close-modal px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Close
                </button>
            </div>
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

    // View feedback modal
    const viewFeedbackModal = document.getElementById('view-feedback-modal');
    const viewButtons = document.querySelectorAll('.view-feedback');

    viewButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Populate modal with feedback data
            const rating = button.getAttribute('data-rating');
            const comments = button.getAttribute('data-comments');
            const username = button.getAttribute('data-username');
            const lab = button.getAttribute('data-lab');
            const date = button.getAttribute('data-date');

            document.getElementById('view-username-lab').textContent = `${username} - ${lab}`;
            document.getElementById('view-date').textContent = `Submitted on ${date}`;

            // Display stars based on rating
            const ratingDiv = document.getElementById('view-rating');
            ratingDiv.innerHTML = '';
            for (let i = 1; i <= 5; i++) {
                const starIcon = document.createElement('i');
                starIcon.classList.add(i <= rating ? 'fas' : 'far', 'fa-star');
                ratingDiv.appendChild(starIcon);
            }

            document.getElementById('view-comments').textContent = comments;
            
            // Show modal
            viewFeedbackModal.classList.remove('hidden');
        });
    });

    // Close modals when clicking close button or outside
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', () => {
            viewFeedbackModal.classList.add('hidden');
        });
    });

    // Close modal when clicking outside
    [viewFeedbackModal].forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.add('hidden');
            }
        });
    });
</script>
<?php include('includes/footer.php'); ?>