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

// Get all completed sit-in sessions
$completed_sitins = [];
try {
    $query = "SELECT s.*, u.FIRSTNAME, u.LASTNAME, u.MIDNAME, u.COURSE, u.YEAR, l.LAB_NAME 
              FROM SITIN s
              JOIN USERS u ON s.IDNO = u.IDNO
              JOIN LABORATORY l ON s.LAB_ID = l.LAB_ID
              WHERE s.STATUS = 'COMPLETED'
              ORDER BY s.SESSION_END DESC";
    
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $completed_sitins[] = $row;
    }
} catch (Exception $e) {
    // Handle error
    $error_message = $e->getMessage();
}

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

$pageTitle = "Sit-In Records";
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
                    <h1 class="ml-4 text-xl font-semibold text-secondary">Sit-In Records</h1>
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
            <!-- Filter Options -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-secondary mb-4">Filter Records</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="lab-filter" class="block text-sm font-medium text-gray-700 mb-1">Laboratory</label>
                        <select id="lab-filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                            <option value="">All Laboratories</option>
                            <?php foreach ($laboratories as $lab): ?>
                                <option value="<?php echo $lab['LAB_NAME']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date-filter" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" id="date-filter" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
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

                    <div>
                        <label for="student-filter" class="block text-sm font-medium text-gray-700 mb-1">Student ID/Name</label>
                        <input type="text" id="student-filter" placeholder="Search by ID or name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
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

            <!-- Table Header -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-secondary">Completed Sit-In Sessions</h2>
                    <div class="flex space-x-3">
                        <a href="sitin_reports.php" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-opacity-90 transition-colors flex items-center">
                            <i class="fas fa-chart-bar mr-2"></i> Sit-In Reports
                        </a>
                        <a href="sitin.php" class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-opacity-90 transition-colors flex items-center">
                            <i class="fas fa-desktop mr-2"></i> Active Sessions
                        </a>
                    </div>
                </div>

                <!-- Records Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratory</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($completed_sitins as $sitin): ?>
                                <tr class="sitin-record-row"
                                    data-lab="<?php echo htmlspecialchars($sitin['LAB_NAME']); ?>"
                                    data-date="<?php echo date('Y-m-d', strtotime($sitin['SESSION_START'])); ?>"
                                    data-purpose="<?php echo htmlspecialchars($sitin['PURPOSE']); ?>"
                                    data-student="<?php echo htmlspecialchars($sitin['IDNO'] . ' ' . $sitin['LASTNAME'] . ' ' . $sitin['FIRSTNAME'] . ' ' . $sitin['MIDNAME']); ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($sitin['LASTNAME'] . ', ' . $sitin['FIRSTNAME'] . ' ' . $sitin['MIDNAME']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($sitin['IDNO']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($sitin['COURSE'] . ' - ' . $sitin['YEAR']); ?>
                                                </div>
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
                                        <?php echo date('M j, Y h:i A', strtotime($sitin['SESSION_START'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y h:i A', strtotime($sitin['SESSION_END'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($completed_sitins)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                        No completed sit-in sessions found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- No Results Message -->
                <div id="no-results-message" class="py-8 text-center text-gray-500 hidden">
                    <i class="fas fa-search mb-3 text-2xl"></i>
                    <p>No records found matching your filter criteria</p>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Confirmation Dialog for Logout -->
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
    
    // Close dialog when clicking outside
    document.getElementById('confirmation-dialog').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });

    // Filter functionality
    const labFilter = document.getElementById('lab-filter');
    const dateFilter = document.getElementById('date-filter');
    const purposeFilter = document.getElementById('purpose-filter');
    const studentFilter = document.getElementById('student-filter');
    const applyFiltersBtn = document.getElementById('apply-filters');
    const resetFiltersBtn = document.getElementById('reset-filters');
    const rows = document.querySelectorAll('.sitin-record-row');
    const noResultsMessage = document.getElementById('no-results-message');
    
    // Apply filters
    function applyFilters() {
        const labValue = labFilter.value.toLowerCase();
        const dateValue = dateFilter.value;
        const purposeValue = purposeFilter.value.toLowerCase();
        const studentValue = studentFilter.value.toLowerCase();
        
        let visibleCount = 0;
        
        rows.forEach(row => {
            let show = true;
            
            if (labValue && row.getAttribute('data-lab').toLowerCase() !== labValue) {
                show = false;
            }
            
            if (dateValue && row.getAttribute('data-date') !== dateValue) {
                show = false;
            }
            
            if (purposeValue && row.getAttribute('data-purpose').toLowerCase() !== purposeValue) {
                show = false;
            }
            
            if (studentValue && !row.getAttribute('data-student').toLowerCase().includes(studentValue)) {
                show = false;
            }
            
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });
        
        // Show "no results" message if no rows are visible
        if (visibleCount === 0) {
            noResultsMessage.classList.remove('hidden');
        } else {
            noResultsMessage.classList.add('hidden');
        }
    }
    
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', applyFilters);
    }
    
    // Reset filters
    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', () => {
            labFilter.value = '';
            dateFilter.value = '';
            purposeFilter.value = '';
            studentFilter.value = '';
            
            rows.forEach(row => {
                row.style.display = '';
            });
            
            noResultsMessage.classList.add('hidden');
        });
    }

    // Real-time filtering for student search
    if (studentFilter) {
        studentFilter.addEventListener('input', function() {
            if (this.value.length >= 2) {
                applyFilters();
            } else if (this.value.length === 0) {
                applyFilters();
            }
        });
    }
</script>

<?php include('includes/footer.php'); ?>
