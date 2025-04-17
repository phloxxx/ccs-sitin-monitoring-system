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

// Get all laboratories for the filter dropdown
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

// Get active sit-in sessions
$active_sitins = [];
try {
    $query = "SELECT s.*, u.FIRSTNAME, u.LASTNAME, u.MIDNAME, u.COURSE, u.YEAR, l.LAB_NAME 
              FROM SITIN s
              JOIN USERS u ON s.IDNO = u.IDNO
              JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
              WHERE s.STATUS = 'ACTIVE'
              ORDER BY s.SESSION_START DESC";
    
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $active_sitins[] = $row;
    }
} catch (Exception $e) {
    // Handle error
    $error_message = $e->getMessage();
}

$pageTitle = "Sit-In Management";
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
                    <a href="sitin.php" class="flex items-center px-4 py-3 text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
                    <h1 class="ml-4 text-xl font-semibold text-secondary">Sit-In Management</h1>
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
                        Students
                    </a>
                    <a href="sitin.php" class="block px-4 py-2 text-white rounded-lg bg-primary bg-opacity-30 hover:bg-primary hover:bg-opacity-20">
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
            <!-- Active Sessions -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-600 to-blue-500 p-4 flex justify-between items-center text-white">
                    <h2 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-desktop mr-2"></i> Active Sessions
                    </h2>
                    <div class="flex space-x-3">
                        <a href="sitin_records.php" class="bg-white text-blue-600 px-3 py-1 rounded-md text-sm flex items-center shadow-sm hover:bg-gray-100 transition-colors">
                            <i class="fas fa-history mr-1"></i> View Records
                        </a>
                        <a href="sitin_reports.php" class="bg-white text-blue-600 px-3 py-1 rounded-md text-sm flex items-center shadow-sm hover:bg-gray-100 transition-colors">
                            <i class="fas fa-chart-bar mr-1"></i> View Reports
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600 text-sm">Active Sessions</h2>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo count($active_sitins); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-500">
                            <i class="fas fa-desktop text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600 text-sm">Laboratories</h2>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo count($laboratories); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600 text-sm">Today's Sessions</h2>
                            <?php
                            // Count today's sessions
                            $today_count = 0;
                            $today = date('Y-m-d');
                            foreach ($active_sitins as $sitin) {
                                if (date('Y-m-d', strtotime($sitin['SESSION_START'])) == $today) {
                                    $today_count++;
                                }
                            }
                            ?>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo $today_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Options -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-secondary mb-4">Filter Options</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="lab-filter" class="block text-sm font-medium text-gray-700 mb-1">Laboratory</label>
                        <select id="lab-filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                            <option value="">All Laboratories</option>
                            <?php foreach ($laboratories as $lab): ?>
                                <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date-filter" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" id="date-filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary"
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div>
                        <label for="purpose-filter" class="block text-sm font-medium text-gray-700 mb-1">Programming Language</label>
                        <select id="purpose-filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                            <option value="">All Languages</option>
                            <?php
                            try {
                                $purposeQuery = "SELECT DISTINCT PURPOSE FROM SITIN WHERE PURPOSE != '' ORDER BY PURPOSE";
                                $purposeResult = $conn->query($purposeQuery);
                                while ($purpose = $purposeResult->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($purpose['PURPOSE']) . '">' . 
                                         htmlspecialchars($purpose['PURPOSE']) . '</option>';
                                }
                            } catch (Exception $e) {
                                // Fallback to default options if query fails
                                $defaultOptions = ['Java', 'Python', 'C++', 'C#', 'JavaScript', 'PHP', 'SQL', 'Other'];
                                foreach ($defaultOptions as $option) {
                                    echo '<option value="' . $option . '">' . $option . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4 flex justify-end">
                    <button id="reset-filters" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 mr-2">
                        Reset Filters
                    </button>
                    <button id="apply-filters" class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-dark transition-colors">
                        Apply Filters
                    </button>
                </div>
            </div>
            
            <!-- Sit-In List -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-secondary">Active Sit-In Sessions</h2>
                    <div class="flex space-x-3">
                        <a href="sitin_records.php" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-opacity-90 transition-colors flex items-center">
                            <i class="fas fa-history mr-2"></i> Sit-in Records
                        </a>
                        <a href="search.php" class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-opacity-90 transition-colors flex items-center">
                            <i class="fas fa-plus mr-2"></i> New Sit-In
                        </a>
                    </div>
                </div>
                
                <div id="sitin-list" class="overflow-x-auto">
                    <?php if (empty($active_sitins)): ?>
                    <div class="p-6 text-center text-gray-500">
                        <p>No active sit-in sessions found.</p>
                    </div>
                    <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Language</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($active_sitins as $sitin): ?>
                            <tr class="sitin-row" 
                                data-lab="<?php echo $sitin['LAB_ID']; ?>" 
                                data-date="<?php echo date('Y-m-d', strtotime($sitin['SESSION_START'])); ?>"
                                data-purpose="<?php echo $sitin['PURPOSE']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($sitin['LASTNAME'] . ', ' . $sitin['FIRSTNAME'] . ' ' . $sitin['MIDNAME']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($sitin['COURSE'] . ' - ' . $sitin['YEAR']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($sitin['IDNO']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($sitin['LAB_NAME']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($sitin['PURPOSE']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('h:i A', strtotime($sitin['SESSION_START'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="end-session-btn text-red-600 hover:text-red-900" data-id="<?php echo $sitin['SITIN_ID']; ?>">
                                        End Session
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
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

<!-- End Session Confirmation Dialog -->
<div id="end-session-dialog" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-secondary mb-4">End Sit-In Session</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to end this sit-in session?</p>
        <div class="flex justify-end space-x-4">
            <button id="cancel-end-session" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <button id="confirm-end-session" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                End Session
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
    
    // End Session functionality
    let currentSitinId = null;
    const endSessionDialog = document.getElementById('end-session-dialog');
    const cancelEndSession = document.getElementById('cancel-end-session');
    const confirmEndSession = document.getElementById('confirm-end-session');
    
    // Add event listeners to all End Session buttons
    document.querySelectorAll('.end-session-btn').forEach(button => {
        button.addEventListener('click', function() {
            currentSitinId = this.getAttribute('data-id');
            endSessionDialog.classList.remove('hidden');
        });
    });
    
    // Cancel End Session
    cancelEndSession.addEventListener('click', () => {
        endSessionDialog.classList.add('hidden');
        currentSitinId = null;
    });
    
    // Confirm End Session
    confirmEndSession.addEventListener('click', () => {
        if (currentSitinId) {
            // Show loading state
            confirmEndSession.disabled = true;
            confirmEndSession.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            
            // Send AJAX request to end the session
            fetch('ajax/end_sitin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `sitin_id=${encodeURIComponent(currentSitinId)}`
            })
            .then(response => response.json())
            .then(data => {
                endSessionDialog.classList.add('hidden');
                
                if (data.success) {
                    // Show more detailed success message with session information
                    const message = `Session ended successfully! ${data.session_deducted} session(s) deducted. Student now has ${data.remaining_sessions} remaining session(s).`;
                    alert(message);
                    
                    // Reload the page to show updated data
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to end the session.'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
                confirmEndSession.disabled = false;
                confirmEndSession.innerHTML = 'End Session';
            });
        }
    });
    
    // Close dialog when clicking outside
    endSessionDialog.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
            currentSitinId = null;
        }
    });
    
    // Filter functionality
    const labFilter = document.getElementById('lab-filter');
    const dateFilter = document.getElementById('date-filter');
    const purposeFilter = document.getElementById('purpose-filter');
    const applyFiltersBtn = document.getElementById('apply-filters');
    const resetFiltersBtn = document.getElementById('reset-filters');
    const sitinRows = document.querySelectorAll('.sitin-row');
    
    // Apply filters
    applyFiltersBtn.addEventListener('click', () => {
        const labValue = labFilter.value;
        const dateValue = dateFilter.value;
        const purposeValue = purposeFilter.value;
        
        sitinRows.forEach(row => {
            let show = true;
            
            if (labValue && row.getAttribute('data-lab') !== labValue) {
                show = false;
            }
            
            if (dateValue && row.getAttribute('data-date') !== dateValue) {
                show = false;
            }
            
            if (purposeValue && row.getAttribute('data-purpose') !== purposeValue) {
                show = false;
            }
            
            row.style.display = show ? '' : 'none';
        });
    });
    
    // Reset filters
    resetFiltersBtn.addEventListener('click', () => {
        labFilter.value = '';
        dateFilter.value = '';
        purposeFilter.value = '';
        
        sitinRows.forEach(row => {
            row.style.display = '';
        });
    });
</script>

<?php include('includes/footer.php'); ?>
