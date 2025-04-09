<?php 
session_start();
require_once('../config/db.php'); // Updated path to the database file

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; 

// Fetch user details from database
$stmt = $conn->prepare("SELECT USERNAME, PROFILE_PIC, IDNO FROM USERS WHERE USER_ID = ?");
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
$student_id = $user['IDNO'];
// Set up profile picture path handling - simplified approach
$default_pic = "images/snoopy.jpg";
$profile_pic = !empty($user['PROFILE_PIC']) ? $user['PROFILE_PIC'] : $default_pic;

// Get actual sit-in history for the user with feedback status
$history = [];
try {
    $stmt = $conn->prepare("
        SELECT s.SITIN_ID, s.SESSION_START, s.SESSION_END, s.PURPOSE, s.STATUS, l.LAB_NAME,
               (SELECT COUNT(*) FROM FEEDBACK f WHERE f.SITIN_ID = s.SITIN_ID) AS feedback_exists
        FROM SITIN s
        JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
        WHERE s.IDNO = ? AND s.STATUS = 'COMPLETED'
        ORDER BY s.SESSION_END DESC
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // Log error for debugging
    error_log("Error fetching history: " . $e->getMessage());
}

$pageTitle = "History";
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
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="h-full w-full object-cover">
                </div>
                <span class="text-white font-semibold text-lg group-hover:text-primary transition"><?php echo htmlspecialchars($username); ?></span>
            </a>
            
            <!-- Navigation -->
            <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6">
                <nav>
                    <ul class="flex flex-wrap justify-center space-x-6">
                        <li><a href="dashboard.php" class="text-white hover:text-primary transition">Home</a></li>
                        <li><a href="notification.php" class="text-white hover:text-primary transition">Notification</a></li>
                        <li><a href="history.php" class="text-white hover:text-primary transition font-medium">History</a></li>
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
        <div class="container mx-auto max-w-7xl">
            <h1 class="text-3xl font-bold text-secondary mb-6">Sit-in History</h1>
            
            <!-- History Table -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-secondary">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Time In</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Time Out</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Lab Room</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Purpose</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">No history records found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $record): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('Y-m-d', strtotime($record['SESSION_START']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('h:i A', strtotime($record['SESSION_START']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('h:i A', strtotime($record['SESSION_END']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($record['LAB_NAME']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($record['PURPOSE']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                <?php echo htmlspecialchars($record['STATUS']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if ($record['feedback_exists'] > 0): ?>
                                                <span class="text-green-600">
                                                    <i class="fas fa-check-circle mr-1"></i> Feedback Submitted
                                                </span>
                                            <?php else: ?>
                                                <button class="text-primary hover:text-dark feedback-btn" data-sitin-id="<?php echo $record['SITIN_ID']; ?>">
                                                    <i class="fas fa-comment-alt mr-1"></i> Feedback
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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

<!-- Feedback Modal -->
<div id="feedback-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center border-b pb-3">
            <h3 class="text-lg font-medium text-secondary">Submit Feedback</h3>
            <button id="close-feedback" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="feedback-form" class="mt-4">
            <input type="hidden" id="feedback-sitin-id" name="sitin_id" value="">
            
            <div class="mb-4">
                <label for="feedback-rating" class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                <div class="flex justify-center space-x-3 text-3xl">
                    <i class="far fa-star rating-star cursor-pointer text-primary" data-rating="1"></i>
                    <i class="far fa-star rating-star cursor-pointer text-primary" data-rating="2"></i>
                    <i class="far fa-star rating-star cursor-pointer text-primary" data-rating="3"></i>
                    <i class="far fa-star rating-star cursor-pointer text-primary" data-rating="4"></i>
                    <i class="far fa-star rating-star cursor-pointer text-primary" data-rating="5"></i>
                </div>
                <input type="hidden" id="feedback-rating-value" name="rating" value="0">
            </div>
            
            <div class="mb-4">
                <label for="feedback-comments" class="block text-sm font-medium text-gray-700 mb-1">Comments</label>
                <textarea id="feedback-comments" name="comments" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary" placeholder="Share your experience..."></textarea>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="button" id="cancel-feedback" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 mr-2">
                    Cancel
                </button>
                <button type="submit" id="submit-feedback" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-opacity-90 transition-colors">
                    Submit Feedback
                </button>
            </div>
        </form>
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
    
    // Feedback functionality
    const feedbackModal = document.getElementById('feedback-modal');
    const feedbackForm = document.getElementById('feedback-form');
    const feedbackSitinId = document.getElementById('feedback-sitin-id');
    const ratingStars = document.querySelectorAll('.rating-star');
    const ratingValue = document.getElementById('feedback-rating-value');
    
    // Show feedback modal when clicking the feedback button
    document.querySelectorAll('.feedback-btn').forEach(button => {
        button.addEventListener('click', () => {
            const sitinId = button.getAttribute('data-sitin-id');
            feedbackSitinId.value = sitinId;
            
            // Reset form
            feedbackForm.reset();
            resetStars();
            ratingValue.value = 0;
            
            // Show modal
            feedbackModal.classList.remove('hidden');
        });
    });
    
    // Close feedback modal
    document.getElementById('close-feedback').addEventListener('click', () => {
        feedbackModal.classList.add('hidden');
    });
    
    document.getElementById('cancel-feedback').addEventListener('click', () => {
        feedbackModal.classList.add('hidden');
    });
    
    // Star rating functionality
    function resetStars() {
        ratingStars.forEach(star => {
            star.classList.remove('fas');
            star.classList.add('far');
        });
    }
    
    ratingStars.forEach(star => {
        star.addEventListener('mouseover', () => {
            resetStars();
            const rating = parseInt(star.getAttribute('data-rating'));
            
            // Fill stars up to the hovered star
            ratingStars.forEach(s => {
                const starRating = parseInt(s.getAttribute('data-rating'));
                if (starRating <= rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                }
            });
        });
        
        star.addEventListener('click', () => {
            const rating = parseInt(star.getAttribute('data-rating'));
            ratingValue.value = rating;
        });
    });
    
    // Reset star appearance when mouse leaves the rating area
    const ratingContainer = document.querySelector('.rating-star').parentElement;
    ratingContainer.addEventListener('mouseleave', () => {
        resetStars();
        const currentRating = parseInt(ratingValue.value);
        
        // Refill stars based on the selected rating
        if (currentRating > 0) {
            ratingStars.forEach(s => {
                const starRating = parseInt(s.getAttribute('data-rating'));
                if (starRating <= currentRating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                }
            });
        }
    });
    
    // Form submission
    feedbackForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const rating = ratingValue.value;
        const comments = document.getElementById('feedback-comments').value;
        
        if (rating === '0') {
            alert('Please select a rating before submitting.');
            return;
        }
        
        // Submit form via AJAX
        const formData = new FormData(this);
        
        // Disable submit button
        const submitBtn = document.getElementById('submit-feedback');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
        
        fetch('ajax/submit_feedback.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Show success message
                alert('Thank you for your feedback!');
                // Close modal
                feedbackModal.classList.add('hidden');
                // Reload the page to show updated status
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error occurred'));
                console.error('Feedback submission error:', data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again. Details: ' + error.message);
        })
        .finally(() => {
            // Re-enable submit button
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Feedback';
        });
    });
    
    // Close modal when clicking outside
    feedbackModal.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
</script>

<?php include('includes/footer.php'); ?>
