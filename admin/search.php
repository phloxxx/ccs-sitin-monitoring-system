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

// Check if LABORATORY table exists
$table_check = $conn->query("SHOW TABLES LIKE 'LABORATORY'");
if ($table_check->num_rows == 0) {
    // Create LABORATORY table
    $sql = "CREATE TABLE `LABORATORY` (
        `LAB_ID` int(11) NOT NULL AUTO_INCREMENT,
        `LAB_NAME` varchar(100) NOT NULL,
        `LAB_DESCRIPTION` text DEFAULT NULL,
        `CAPACITY` int(11) NOT NULL DEFAULT 30,
        `STATUS` enum('AVAILABLE','MAINTENANCE','OCCUPIED') NOT NULL DEFAULT 'AVAILABLE',
        `CREATED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
        `UPDATED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`LAB_ID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($sql) === TRUE) {
        // Insert default laboratory data
        $default_labs = [
            ['Lab 524', 'Computer Laboratory Room 524', 30, 'AVAILABLE'],
            ['Lab 526', 'Computer Laboratory Room 526', 25, 'AVAILABLE'],
            ['Lab 528', 'Computer Laboratory Room 528', 20, 'AVAILABLE'],
            ['Lab 530', 'Computer Laboratory Room 530', 30, 'AVAILABLE'],
            ['Lab 542', 'Computer Laboratory Room 542', 25, 'AVAILABLE'],
            ['Lab 544', 'Computer Laboratory Room 544', 15, 'AVAILABLE']
        ];
        
        foreach ($default_labs as $lab) {
            $insert_sql = "INSERT INTO `LABORATORY` (`LAB_NAME`, `LAB_DESCRIPTION`, `CAPACITY`, `STATUS`) 
                          VALUES ('{$lab[0]}', '{$lab[1]}', {$lab[2]}, '{$lab[3]}')";
            $conn->query($insert_sql);
        }
    }
}

// Check if SITIN table exists
$table_check = $conn->query("SHOW TABLES LIKE 'SITIN'");
if ($table_check->num_rows == 0) {
    // Create SITIN table
    $sql = "CREATE TABLE `SITIN` (
        `SITIN_ID` int(11) NOT NULL AUTO_INCREMENT,
        `IDNO` varchar(20) NOT NULL,
        `LAB_ID` int(11) NOT NULL,
        `ADMIN_ID` int(11) NOT NULL,
        `PURPOSE` varchar(100) NOT NULL,
        `SESSION_START` datetime NOT NULL,
        `SESSION_END` datetime DEFAULT NULL,
        `SESSION_DURATION` int(11) NOT NULL COMMENT 'Duration in hours',
        `STATUS` enum('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
        `REMARKS` text DEFAULT NULL,
        `CREATED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
        `UPDATED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`SITIN_ID`),
        KEY `IDX_SITIN_IDNO` (`IDNO`),
        KEY `IDX_SITIN_LAB_ID` (`LAB_ID`),
        KEY `IDX_SITIN_ADMIN_ID` (`ADMIN_ID`),
        KEY `IDX_SITIN_SESSION_START` (`SESSION_START`),
        KEY `IDX_SITIN_STATUS` (`STATUS`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->query($sql);
}

// Get all laboratories for the dropdown
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
    // If table doesn't exist or other error
    $laboratories = [
        ['LAB_ID' => 1, 'LAB_NAME' => 'Lab 524', 'CAPACITY' => 30, 'STATUS' => 'AVAILABLE'],
        ['LAB_ID' => 2, 'LAB_NAME' => 'Lab 526', 'CAPACITY' => 25, 'STATUS' => 'AVAILABLE'],
        ['LAB_ID' => 3, 'LAB_NAME' => 'Lab 528', 'CAPACITY' => 20, 'STATUS' => 'AVAILABLE'],
        ['LAB_ID' => 4, 'LAB_NAME' => 'Lab 530', 'CAPACITY' => 30, 'STATUS' => 'AVAILABLE'],
        ['LAB_ID' => 5, 'LAB_NAME' => 'Lab 542', 'CAPACITY' => 25, 'STATUS' => 'AVAILABLE'],
        ['LAB_ID' => 6, 'LAB_NAME' => 'Lab 544', 'CAPACITY' => 15, 'STATUS' => 'AVAILABLE']
    ];
}

$pageTitle = "Search Students";
$bodyClass = "bg-light font-poppins";
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
                    <a href="feedbacks.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
                        Search Students
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
                    <a href="feedbacks.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
            <!-- Search Box -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold text-secondary mb-4">Find a Student</h2>
                
                <?php include('includes/search_help.php'); ?>
                
                <div class="flex flex-col md:flex-row space-y-3 md:space-y-0 md:space-x-3">
                    <div class="flex-1">
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" id="search-input" 
                                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                                   placeholder="Search by ID, username, name...">
                        </div>
                    </div>
                    <button id="search-button" 
                            class="inline-flex items-center justify-center px-6 py-3 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-secondary hover:bg-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary transition-all duration-300">
                        Search
                    </button>
                </div>
            </div>
            
            <!-- Search Results -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-secondary">Search Results</h2>
                </div>
                      
                <div id="search-results" class="p-6">
                    <div class="flex items-center justify-center h-32 text-gray-500">
                        <p>Enter a search term to find students</p>
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

<!-- Sit-In Form Modal -->
<div id="sitin-form-modal" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-secondary">New Sit-In Session</h3>
            <button type="button" id="close-sitin-form" class="text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="sitin-form">
            <div id="sitin-error" class="mb-4 text-red-500 text-sm hidden"></div>
            <div id="sitin-success" class="mb-4 text-green-500 text-sm hidden"></div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="idno" class="block text-sm font-medium text-gray-700 mb-1">Student ID</label>
                    <input type="text" id="idno" name="idno" readonly
                           class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md focus:outline-none">
                </div>
                
                <div>
                    <label for="student-name" class="block text-sm font-medium text-gray-700 mb-1">Student Name</label>
                    <input type="text" id="student-name" name="student-name" readonly
                           class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md focus:outline-none">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="lab" class="block text-sm font-medium text-gray-700 mb-1">Laboratory No.</label>
                    <select id="lab" name="lab" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                        <option value="">Select Laboratory</option>
                        <?php foreach ($laboratories as $lab): ?>
                            <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="remaining-sessions-display" class="block text-sm font-medium text-gray-700 mb-1">Remaining Sessions</label>
                    <div class="flex items-center">
                        <div class="bg-gray-100 border border-gray-300 rounded-md px-3 py-2 text-gray-700 font-medium">
                            <span id="remaining-sessions-display">0</span>
                        </div>
                        <span class="ml-2 text-gray-600">sessions</span>
                        <span class="ml-auto text-sm text-gray-500">Using: 
                            <input type="hidden" id="session-count" name="session-count" value="1">
                            <span class="font-medium">1</span> session
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="mb-6">
                <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Programming Language</label>
                <select id="purpose" name="purpose" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                    <option value="">Select Purpose</option>
                    <?php
                    // Default programming languages that should always be available
                    $defaultLanguages = ['Java', 'Python', 'C++', 'C#', 'PHP', 'SQL', 'JavaScript', 'HTML/CSS', 'Ruby', 'Other'];
                    $purposes = $defaultLanguages;
                    
                    try {
                        // Get additional unique purposes from the database
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
                    
                    // Output all purposes
                    foreach ($purposes as $purpose) {
                        echo '<option value="' . htmlspecialchars($purpose) . '">' . htmlspecialchars($purpose) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div id="other-purpose-container" class="mb-6 hidden">
                <label for="other-purpose" class="block text-sm font-medium text-gray-700 mb-1">Specify Purpose</label>
                <input type="text" id="other-purpose" name="other-purpose"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary"
                       placeholder="Please specify your purpose">
            </div>
            
            <div class="flex justify-end space-x-4">
                <button type="button" id="cancel-sitin" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" id="submit-sitin"
                        class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-dark transition-colors">
                    Create Sit-In Session
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

    // Purpose dropdown handling
    const purposeSelect = document.getElementById('purpose');
    const otherPurposeContainer = document.getElementById('other-purpose-container');
    const otherPurposeInput = document.getElementById('other-purpose');
    
    purposeSelect.addEventListener('change', function() {
        if (this.value === 'Other') {
            otherPurposeContainer.classList.remove('hidden');
            otherPurposeInput.setAttribute('required', 'required');
        } else {
            otherPurposeContainer.classList.add('hidden');
            otherPurposeInput.removeAttribute('required');
        }
    });

    // Search functionality
    const searchInput = document.getElementById('search-input');
    const searchButton = document.getElementById('search-button');
    const searchResults = document.getElementById('search-results');
    
    // Search when button is clicked
    searchButton.addEventListener('click', performSearch);
    
    // Search when Enter key is pressed in the search input
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });

    // Add real-time search with shorter debounce
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 200); // Reduced from 300ms to 200ms for faster response
    });
    
    function performSearch() {
        const query = searchInput.value.trim();
        
        if (query.length === 0) {
            searchResults.innerHTML = '<div class="flex items-center justify-center h-32 text-gray-500"><p>Enter a search term to find students</p></div>';
            return;
        }
        
        // Show loading indicator
        searchResults.innerHTML = '<div class="flex justify-center"><i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i></div>';
        
        // Send AJAX request
        fetch(`ajax/search_students.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.students.length === 0) {
                        searchResults.innerHTML = '<div class="bg-blue-50 p-4 rounded-md"><p class="text-blue-700">No students found matching your search criteria.</p></div>';
                    } else {
                        // Create table to display results
                        let html = `
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Number</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course & Year</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">`;
                        
                        data.students.forEach(student => {
                            const hasActiveSession = student.ACTIVE_SESSION === 'ACTIVE';
                            const buttonClass = hasActiveSession ? 
                                "bg-gray-400 cursor-not-allowed" : 
                                "bg-secondary hover:bg-dark";
                            
                            html += `
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${student.IDNO}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${student.LASTNAME}, ${student.FIRSTNAME} ${student.MIDNAME || ''}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${student.COURSE} - ${student.YEAR}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    ${hasActiveSession ? 
                                        `<div class="text-amber-600 font-medium">
                                            <i class="fas fa-exclamation-circle mr-1"></i>
                                            Active session in ${student.ACTIVE_LAB}
                                        </div>` :
                                        `<button class="create-sitin-btn text-white ${buttonClass} px-3 py-1 rounded-md"
                                                data-id="${student.IDNO}"
                                                data-name="${student.LASTNAME}, ${student.FIRSTNAME} ${student.MIDNAME || ''}"
                                                data-session="${student.SESSION}">
                                            Sit-In
                                        </button>`
                                    }
                                </td>
                            </tr>`;
                        });
                        
                        html += `
                                </tbody>
                            </table>
                        </div>`;
                        
                        searchResults.innerHTML = html;
                        
                        // Add event listeners to Create Sit-In buttons
                        document.querySelectorAll('.create-sitin-btn').forEach(button => {
                            // Only add event listener if button is not disabled
                            if (!button.classList.contains('bg-gray-400')) {
                                button.addEventListener('click', function() {
                                    openSitInForm(
                                        this.getAttribute('data-id'),
                                        this.getAttribute('data-name'),
                                        this.getAttribute('data-session')
                                    );
                                });
                            }
                        });
                    }
                } else {
                    searchResults.innerHTML = `<div class="bg-red-50 p-4 rounded-md"><p class="text-red-700">${data.message || 'An error occurred during search.'}</p></div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                searchResults.innerHTML = '<div class="bg-red-50 p-4 rounded-md"><p class="text-red-700">Network error. Please try again.</p></div>';
            });
    }
    
    // Sit-In Form Modal handling
    const sitInModal = document.getElementById('sitin-form-modal');
    const closeSitInForm = document.getElementById('close-sitin-form');
    const cancelSitIn = document.getElementById('cancel-sitin');
    const sitInForm = document.getElementById('sitin-form');
    const errorElement = document.getElementById('sitin-error');
    const successElement = document.getElementById('sitin-success');
    
    function openSitInForm(idNo, name, remainingSessions) {
        // Fill form with student data
        document.getElementById('idno').value = idNo;
        document.getElementById('student-name').value = name;
        document.getElementById('remaining-sessions-display').textContent = remainingSessions;
        
        // Set session count to fixed value of 1 (hidden field)
        document.getElementById('session-count').value = 1;
        
        // Reset other form elements
        document.getElementById('lab').value = '';
        document.getElementById('purpose').value = '';
        otherPurposeContainer.classList.add('hidden');
        otherPurposeInput.value = '';
        otherPurposeInput.removeAttribute('required');
        
        // Clear any previous messages
        errorElement.classList.add('hidden');
        successElement.classList.add('hidden');
            
        // Show the modal (we're not checking remaining sessions now since deduction happens at end of session)
        sitInModal.classList.remove('hidden');
    }
    
    function closeSitInModal() {
        sitInModal.classList.add('hidden');
    }
    
    closeSitInForm.addEventListener('click', closeSitInModal);
    cancelSitIn.addEventListener('click', closeSitInModal);
    
    // Handle form submission via AJAX
    sitInForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const idno = document.getElementById('idno').value;
        const lab = document.getElementById('lab').value;
        const sessionCount = document.getElementById('session-count').value; // Using fixed value of 1
        const purpose = document.getElementById('purpose').value;
        const otherPurpose = document.getElementById('other-purpose').value;
        
        // Validate form
        if (!lab) {
            errorElement.textContent = 'Please select a laboratory.';
            errorElement.classList.remove('hidden');
            return;
        }
        
        if (!purpose) {
            errorElement.textContent = 'Please select a programming language.';
            errorElement.classList.remove('hidden');
            return;
        }
        
        if (purpose === 'Other' && !otherPurpose) {
            errorElement.textContent = 'Please specify the programming language.';
            errorElement.classList.remove('hidden');
            return;
        }
        
        // Disable the submit button during submission
        const submitButton = document.getElementById('submit-sitin');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating...';
        
        // Hide any previous messages
        errorElement.classList.add('hidden');
        successElement.classList.add('hidden');
        
        // Prepare purpose value (use specified purpose if 'Other' is selected)
        const finalPurpose = purpose === 'Other' ? otherPurpose : purpose;
        
        // Send AJAX request
        fetch('ajax/create_sitin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `idno=${encodeURIComponent(idno)}&lab_id=${encodeURIComponent(lab)}&purpose=${encodeURIComponent(finalPurpose)}&session_count=${encodeURIComponent(sessionCount)}`
        })
        .then(response => response.json())
        .then(data => {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Create Sit-In Session';
            
            if (data.success) {
                // Show success message
                successElement.textContent = data.message;
                successElement.classList.remove('hidden');
                
                // Close the modal after a shorter delay (500ms instead of 1500ms) 
                setTimeout(() => {
                    closeSitInModal();
                           
                    // Refresh search results to update remaining sessions
                    performSearch();
                }, 500); // Reduced from 1500ms to 500ms for faster response
            } else {
                // Show error message
                errorElement.textContent = data.message || 'An error occurred while creating the sit-in session.';
                errorElement.classList.remove('hidden');
            }
        })
        .catch(error => {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Create Sit-In Session';
            errorElement.textContent = 'Network error. Please try again.';
            errorElement.classList.remove('hidden');
            console.error('Error:', error);
        });
    });
</script>

<?php include('includes/footer.php'); ?>
