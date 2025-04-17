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

// Pagination settings
$studentsPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $studentsPerPage;

// Count total students
$totalStudents = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM USERS");
if ($result && $row = $result->fetch_assoc()) {
    $totalStudents = $row['count'];
}

// Get active students count
$activeStudents = 0;
$result = $conn->query("SELECT COUNT(DISTINCT IDNO) as count FROM SITIN WHERE STATUS = 'ACTIVE'");
if ($result && $row = $result->fetch_assoc()) {
    $activeStudents = $row['count'];
}

// Get today's registrations - Fixed query with proper error handling
$todayRegistrations = 0;
$todayDate = date('Y-m-d');
// Check if CREATED_AT column exists
$columnCheck = $conn->query("SHOW COLUMNS FROM USERS LIKE 'CREATED_AT'");
if ($columnCheck && $columnCheck->num_rows > 0) {
    // Column exists, count today's registrations
    $result = $conn->query("SELECT COUNT(*) as count FROM USERS WHERE DATE(CREATED_AT) = '$todayDate'");
    if ($result && $row = $result->fetch_assoc()) {
        $todayRegistrations = $row['count'];
    }
}

// Get students with pagination
$students = [];
$query = "SELECT * FROM USERS ORDER BY LASTNAME, FIRSTNAME LIMIT $offset, $studentsPerPage";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Calculate pagination
$totalPages = ceil($totalStudents / $studentsPerPage);

$pageTitle = "Students Management";
$bodyClass = "bg-gray-100 font-poppins";
include('includes/header.php');
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <div class="hidden md:flex md:flex-shrink-0">
        <div class="flex flex-col w-64 bg-secondary">
            <!-- Logo -->
            <div class="flex items-center justify-center h-16 px-4 bg-dark text-white">
                <span class="text-xl font-semibold">CCS Admin Panel</span>
            </div>
            <!-- Navigation -->
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
                    <a href="students.php" class="flex items-center px-4 py-3 text-white bg-primary bg-opacity-30 rounded-lg">
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
        <header class="bg-white shadow">
            <div class="flex items-center justify-between h-16 px-6">
                <div class="flex items-center">
                    <button id="mobile-menu-button" class="text-gray-500 md:hidden focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="ml-4 text-xl font-semibold text-secondary">Students Management</h1>
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
                    <a href="students.php" class="block px-4 py-2 text-white rounded-lg bg-primary bg-opacity-30 hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-user-graduate mr-3"></i>
                        Students
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Total Students -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600 text-sm">Total Students</h2>
                            <p class="text-2xl font-semibold text-gray-800"><?php echo $totalStudents; ?></p>
                        </div>
                    </div>
                </div>
                <!-- Active Students -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-500">
                            <i class="fas fa-user-check text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600 text-sm">Active Session</h2>
                            <p class="text-2xl font-semibold text-gray-800">
                                <?php echo isset($activeStudents) ? $activeStudents : '0'; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Today's Registrations -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-500">
                            <i class="fas fa-user-plus text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h2 class="text-gray-600 text-sm">New Today</h2>
                            <p class="text-2xl font-semibold text-gray-800">
                                <?php echo $todayRegistrations; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Actions and Search Bar -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
                    <!-- Add Student Button -->
                    <button id="add-student-btn" class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-dark transition-colors flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        Add New Student
                    </button>
                    <!-- Search and Filters -->
                    <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4 w-full md:w-auto">
                        <div class="relative flex-1 md:w-64">
                            <input type="text" 
                                   id="search-input" 
                                   placeholder="Search students..."
                                   class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                        <!-- Course Filter -->
                        <select id="course-filter"
                                class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">All Courses</option>
                            <option value="Bachelor of Science in Information Technology">BSIT</option>
                            <option value="Bachelor of Science in Computer Science">BSCS</option>
                            <option value="Associate in Computer Technology">ACT</option>
                            <option value="Bachelor of Science in Human Resourse Management">BSHRM</option>
                            <option value="Bachelor of Science in Criminology">BSCRIM</option>
                        </select>
                        <!-- Year Level Filter -->
                        <select id="year-filter"
                                class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">All Years</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                        <!-- Reset All Sessions Button -->
                        <button id="reset-sessions-btn"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            Reset All Sessions
                        </button>
                    </div>
                </div>
            </div>
            <!-- Students Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Student ID
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Course
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Year
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Sessions
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($students as $student): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($student['IDNO']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($student['LASTNAME'] . ', ' . $student['FIRSTNAME']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($student['COURSE']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($student['YEAR']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $student['SESSION'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $student['SESSION']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button class="text-primary hover:text-secondary mr-3 edit-student-btn" 
                                            data-id="<?php echo $student['IDNO']; ?>"
                                            data-firstname="<?php echo htmlspecialchars($student['FIRSTNAME']); ?>"
                                            data-lastname="<?php echo htmlspecialchars($student['LASTNAME']); ?>"
                                            data-midname="<?php echo htmlspecialchars($student['MIDNAME'] ?? ''); ?>"
                                            data-username="<?php echo htmlspecialchars($student['USERNAME']); ?>"
                                            data-course="<?php echo htmlspecialchars($student['COURSE']); ?>"
                                            data-year="<?php echo htmlspecialchars($student['YEAR']); ?>"
                                            data-session="<?php echo $student['SESSION']; ?>"
                                            title="Edit Student">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-green-600 hover:text-green-800 mr-3 reset-student-session-btn" 
                                            data-id="<?php echo $student['IDNO']; ?>"
                                            data-name="<?php echo htmlspecialchars($student['LASTNAME'] . ', ' . $student['FIRSTNAME']); ?>"
                                            title="Reset Student Sessions">
                                        <i class="fas fa-redo-alt"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-student-btn" 
                                            data-id="<?php echo $student['IDNO']; ?>"
                                            data-name="<?php echo htmlspecialchars($student['LASTNAME'] . ', ' . $student['FIRSTNAME']); ?>"
                                            title="Delete Student">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- No Results Message -->
                <div id="no-results-message" class="py-8 text-center text-gray-500 hidden">
                    <i class="fas fa-search mb-3 text-2xl"></i>
                    <p>No students found matching your search criteria</p>
                </div>
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                    <span class="font-medium"><?php echo min($offset + $studentsPerPage, $totalStudents); ?></span> of
                                    <span class="font-medium"><?php echo $totalStudents; ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <a href="?page=<?php echo $i; ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-primary bg-primary bg-opacity-10 z-10' : 'text-gray-500 hover:bg-gray-50'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
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

<!-- Add Reset Sessions Confirmation Dialog -->
<div id="reset-sessions-dialog" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-red-600 mb-4">Reset All Student Sessions</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to reset sessions for ALL students? This action cannot be undone.</p>
        <div class="flex justify-end space-x-4">
            <button id="cancel-reset-sessions" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <button id="confirm-reset-sessions" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                Reset All Sessions
            </button>
        </div>
    </div>
</div>

<!-- Add Individual Reset Sessions Confirmation Dialog -->
<div id="reset-individual-dialog" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-blue-600 mb-4">Reset Student Sessions</h3>
        <p class="text-gray-600 mb-2">Are you sure you want to reset sessions for:</p>
        <p class="font-bold text-gray-800 mb-6" id="reset-student-name"></p>
        <div class="flex justify-end space-x-4">
            <button id="cancel-reset-individual" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <button id="confirm-reset-individual" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                Reset Sessions
            </button>
        </div>
    </div>
</div>

<!-- Add Delete Student Confirmation Dialog -->
<div id="delete-student-dialog" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-red-600 mb-4">Delete Student</h3>
        <p class="text-gray-600 mb-2">Are you sure you want to delete this student:</p>
        <p class="font-bold text-gray-800 mb-6" id="delete-student-name"></p>
        <div class="flex justify-end space-x-4">
            <button id="cancel-delete-student" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <button id="confirm-delete-student" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                Delete Student
            </button>
        </div>
    </div>
</div>

<div id="student-form-modal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-lg">
        <div class="flex justify-between items-center p-4 border-b">
            <h2 id="student-form-title" class="text-xl font-semibold">Add New Student</h2>
            <button id="close-student-form" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="student-form" class="p-4">
            <input type="hidden" id="form-mode" name="mode" value="create">
            <div id="student-form-error" class="bg-red-100 text-red-700 p-2 rounded-md mb-4 hidden"></div>
            <div id="student-form-success" class="bg-green-100 text-green-700 p-2 rounded-md mb-4 hidden"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="student-idno" class="block text-sm font-medium text-gray-700 mb-1">Student ID *</label>
                    <input type="text" id="student-idno" name="idno" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="student-username" class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                    <input type="text" id="student-username" name="username" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="student-firstname" class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                    <input type="text" id="student-firstname" name="firstname" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="student-lastname" class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                    <input type="text" id="student-lastname" name="lastname" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="student-midname" class="block text-sm font-medium text-gray-700 mb-1">Middle Name (Optional)</label>
                    <input type="text" id="student-midname" name="midname"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="student-password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                    <input type="password" id="student-password" name="password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="student-confirm-password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                    <input type="password" id="student-confirm-password" name="confirm_password" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label for="student-course" class="block text-sm font-medium text-gray-700 mb-1">Course *</label>
                    <select id="student-course" name="course" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                        <option value="" disabled>Select Course</option>
                        <option value="Bachelor of Science in Information Technology">BSIT</option>
                        <option value="Bachelor of Science in Computer Science">BSCS</option>
                        <option value="Associate in Computer Technology">ACT</option>
                        <option value="Bachelor of Science in Human Resourse Management">BSHRM</option>
                        <option value="Bachelor of Science in Criminology">BSCRIM</option>
                    </select>
                </div>
                <div>
                    <label for="student-year" class="block text-sm font-medium text-gray-700 mb-1">Year Level *</label>
                    <select id="student-year" name="year" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                        <option value="" disabled>Select Year</option>
                        <option value="First Year">1st Year</option>
                        <option value="Second Year">2nd Year</option>
                        <option value="Third Year">3rd Year</option>
                        <option value="Fourth Year">4th Year</option>
                    </select>
                </div>
                <div>
                    <label for="student-sessions" class="block text-sm font-medium text-gray-700 mb-1">Initial Sessions *</label>
                    <input type="number" id="student-sessions" name="sessions" required min="0" max="30" value="30"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-4">
                <button type="button" id="cancel-student-form" 
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" id="submit-student-form" 
                        class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-dark transition-colors"> 
                    Add Student
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    // Mobile menu toggle
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

    // Student form handling
    const studentFormModal = document.getElementById('student-form-modal');
    const studentForm = document.getElementById('student-form');
    const formMode = document.getElementById('form-mode');
    const formTitle = document.getElementById('student-form-title');
    const submitButton = document.getElementById('submit-student-form');
    const errorElement = document.getElementById('student-form-error');
    const successElement = document.getElementById('student-form-success');

    // Open form for new student
    document.getElementById('add-student-btn').addEventListener('click', () => {
        formMode.value = 'create';
        formTitle.textContent = 'Add New Student';
        submitButton.textContent = 'Add Student';
        studentForm.reset();
        showStudentForm();
    });

    // Open form for editing
    document.querySelectorAll('.edit-student-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            formMode.value = 'edit';
            formTitle.textContent = 'Edit Student';
            submitButton.textContent = 'Update Student';
            // Fill form with student data
            document.getElementById('student-idno').value = this.dataset.id;
            document.getElementById('student-idno').readOnly = true;
            document.getElementById('student-username').value = this.dataset.username;
            document.getElementById('student-firstname').value = this.dataset.firstname;
            document.getElementById('student-lastname').value = this.dataset.lastname;
            document.getElementById('student-midname').value = this.dataset.midname || '';
            document.getElementById('student-course').value = this.dataset.course;
            document.getElementById('student-year').value = this.dataset.year;
            document.getElementById('student-sessions').value = this.dataset.session || '30';
            // Hide password fields for edit mode
            document.getElementById('student-password').parentNode.style.display = 'none';
            document.getElementById('student-confirm-password').parentNode.style.display = 'none';
            // Remove required attribute from password fields
            document.getElementById('student-password').removeAttribute('required');
            document.getElementById('student-confirm-password').removeAttribute('required');
            showStudentForm();
        });
    });

    function showStudentForm() {
        errorElement.classList.add('hidden');
        successElement.classList.add('hidden');
        studentFormModal.classList.remove('hidden');
    }

    function closeStudentForm() {
        studentFormModal.classList.add('hidden');
        studentForm.reset();
        // Reset form to default state
        document.getElementById('student-idno').readOnly = false;
        document.getElementById('student-password').setAttribute('required', 'required');
        document.getElementById('student-confirm-password').setAttribute('required', 'required');
        // Show password fields again (they might be hidden if coming from edit mode)
        document.getElementById('student-password').parentNode.style.display = '';
        document.getElementById('student-confirm-password').parentNode.style.display = '';
    }

    // Close form buttons
    document.getElementById('close-student-form').addEventListener('click', closeStudentForm);
    document.getElementById('cancel-student-form').addEventListener('click', closeStudentForm);

    // Form submission
    studentForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Basic validation - only for create mode
        if (formMode.value === 'create') {
            const password = document.getElementById('student-password').value;
            const confirmPassword = document.getElementById('student-confirm-password').value;
            if (password !== confirmPassword) {
                errorElement.textContent = 'Passwords do not match';
                errorElement.classList.remove('hidden');
                return;
            }
        }

        // Improved validation for required fields
        const requiredFields = [
            { id: 'student-idno', name: 'Student ID' },
            { id: 'student-username', name: 'Username' },
            { id: 'student-firstname', name: 'First Name' },
            { id: 'student-lastname', name: 'Last Name' },
            { id: 'student-course', name: 'Course' },
            { id: 'student-year', name: 'Year Level' },
        ];
        let missingFields = [];
        for (const field of requiredFields) {
            const input = document.getElementById(field.id);
            if (!input.value.trim()) {
                missingFields.push(field.name);
            }
        }
        if (missingFields.length > 0) {
            errorElement.textContent = 'Required fields missing: ' + missingFields.join(', ');
            errorElement.classList.remove('hidden');
            return;
        }

        // Disable submit button
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

        // Prepare form data
        const formData = new FormData(this);

        // Send AJAX request
        fetch('ajax/manage_student.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                successElement.textContent = data.message;
                successElement.classList.remove('hidden');
                errorElement.classList.add('hidden');
                // Close form and reload page after short delay
                setTimeout(() => {
                    closeStudentForm();
                    window.location.reload();
                }, 1000);
            } else {
                errorElement.textContent = data.message;
                errorElement.classList.remove('hidden');
                successElement.classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            errorElement.textContent = 'An error occurred. Please try again.';
            errorElement.classList.remove('hidden');
        })
        .finally(() => {
            submitButton.disabled = false;
            submitButton.textContent = formMode.value === 'create' ? 'Add Student' : 'Update Student';
        });
    });

    // Reset Sessions functionality
    const resetSessionsBtn = document.getElementById('reset-sessions-btn');
    const resetSessionsDialog = document.getElementById('reset-sessions-dialog');
    const cancelResetSessions = document.getElementById('cancel-reset-sessions');
    const confirmResetSessions = document.getElementById('confirm-reset-sessions');

    // Show confirmation dialog when Reset All Sessions button is clicked
    resetSessionsBtn.addEventListener('click', function() {
        resetSessionsDialog.classList.remove('hidden');
    });

    // Hide confirmation dialog when Cancel is clicked
    cancelResetSessions.addEventListener('click', function() {
        resetSessionsDialog.classList.add('hidden');
    });

    // Handle Reset All Sessions confirmation
    confirmResetSessions.addEventListener('click', function() {
        // Show loading state
        confirmResetSessions.disabled = true;
        confirmResetSessions.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Resetting...';

        // Send AJAX request to reset sessions
        fetch('ajax/reset_sessions.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert('All student sessions have been reset successfully!');
                // Reload the page to show updated session counts
                window.location.reload();
            } else {
                // Show error message
                alert('Error: ' + (data.message || 'Failed to reset sessions.'));
                // Reset button state
                confirmResetSessions.disabled = false;
                confirmResetSessions.innerHTML = 'Reset All Sessions';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
            // Reset button state
            confirmResetSessions.disabled = false;
            confirmResetSessions.innerHTML = 'Reset All Sessions';
        })
        .finally(() => {
            // Hide the dialog regardless of result
            resetSessionsDialog.classList.add('hidden');
        });
    });

    // Close Reset Sessions dialog when clicking outside
    resetSessionsDialog.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });

    // Individual student session reset functionality
    const resetIndividualDialog = document.getElementById('reset-individual-dialog');
    const resetStudentName = document.getElementById('reset-student-name');
    const cancelResetIndividual = document.getElementById('cancel-reset-individual');
    const confirmResetIndividual = document.getElementById('confirm-reset-individual');
    let currentResetStudentId = null;

    // Add event listeners to all Reset Session buttons
    document.querySelectorAll('.reset-student-session-btn').forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.getAttribute('data-id');
            const studentName = this.getAttribute('data-name');
            currentResetStudentId = studentId;
            resetStudentName.textContent = studentName;
            resetIndividualDialog.classList.remove('hidden');
        });
    });

    // Cancel individual reset
    cancelResetIndividual.addEventListener('click', () => {
        resetIndividualDialog.classList.add('hidden');
        currentResetStudentId = null;
    });

    // Confirm individual reset
    confirmResetIndividual.addEventListener('click', () => {
        if (!currentResetStudentId) return;

        // Show loading state
        confirmResetIndividual.disabled = true;
        confirmResetIndividual.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Resetting...';

        // Send AJAX request to reset the individual student's sessions
        fetch('ajax/reset_individual_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `student_id=${encodeURIComponent(currentResetStudentId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert(`Sessions have been reset successfully for ${resetStudentName.textContent}!`);
                // Reload the page to show updated session counts
                window.location.reload();
            } else {
                // Show error message
                alert('Error: ' + (data.message || 'Failed to reset sessions.'));
                // Reset button state
                confirmResetIndividual.disabled = false;
                confirmResetIndividual.innerHTML = 'Reset Sessions';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
            // Reset button state
            confirmResetIndividual.disabled = false;
            confirmResetIndividual.innerHTML = 'Reset Sessions';
        })
        .finally(() => {
            // Hide the dialog regardless of result
            resetIndividualDialog.classList.add('hidden');
            currentResetStudentId = null;
        });
    });

    // Close dialog when clicking outside
    resetIndividualDialog.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
            currentResetStudentId = null;
        }
    });

    // Delete Student functionality
    const deleteStudentButtons = document.querySelectorAll('.delete-student-btn');
    const deleteStudentDialog = document.getElementById('delete-student-dialog');
    const deleteStudentName = document.getElementById('delete-student-name');
    const cancelDeleteStudent = document.getElementById('cancel-delete-student');
    const confirmDeleteStudent = document.getElementById('confirm-delete-student');
    let currentDeleteStudentId = null;

    // Add event listeners to all Delete Student buttons
    deleteStudentButtons.forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.getAttribute('data-id');
            const studentName = this.getAttribute('data-name');
            currentDeleteStudentId = studentId;
            deleteStudentName.textContent = studentName;
            deleteStudentDialog.classList.remove('hidden');
        });
    });

    // Cancel student deletion
    cancelDeleteStudent.addEventListener('click', () => {
        deleteStudentDialog.classList.add('hidden');
        currentDeleteStudentId = null;
    });

    // Confirm student deletion
    confirmDeleteStudent.addEventListener('click', () => {
        if (!currentDeleteStudentId) return;

        // Show loading state
        confirmDeleteStudent.disabled = true;
        confirmDeleteStudent.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Deleting...';

        // Send AJAX request to delete the student
        fetch('ajax/delete_student.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `student_id=${encodeURIComponent(currentDeleteStudentId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert(`Student ${deleteStudentName.textContent} has been deleted successfully!`);
                // Reload the page to update the student list
                window.location.reload();
            } else {
                // Show error message
                alert('Error: ' + (data.message || 'Failed to delete student.'));
                // Reset button state
                confirmDeleteStudent.disabled = false;
                confirmDeleteStudent.innerHTML = 'Delete Student';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error. Please try again.');
            // Reset button state
            confirmDeleteStudent.disabled = false;
            confirmDeleteStudent.innerHTML = 'Delete Student';
        })
        .finally(() => {
            // Hide the dialog regardless of result
            deleteStudentDialog.classList.add('hidden');
            currentDeleteStudentId = null;
        });
    });

    // Close Delete Student dialog when clicking outside
    deleteStudentDialog.addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
            currentDeleteStudentId = null;
        }
    });

    // Search functionality
    const searchInput = document.getElementById('search-input');
    const courseFilter = document.getElementById('course-filter');
    const yearFilter = document.getElementById('year-filter');

    // Function to perform search and filtering
    function performSearch() {
        const searchTerm = searchInput.value.trim().toLowerCase();
        const selectedCourse = courseFilter.value;
        const selectedYear = yearFilter.value;
        let visibleCount = 0;

        // Get all student rows
        const studentRows = document.querySelectorAll('tbody tr');
        studentRows.forEach(row => {
            const studentId = row.querySelector('td:nth-child(1)').textContent.trim().toLowerCase();
            const studentName = row.querySelector('td:nth-child(2)').textContent.trim().toLowerCase();
            const studentCourse = row.querySelector('td:nth-child(3)').textContent.trim();
            const studentYear = row.querySelector('td:nth-child(4)').textContent.trim().toLowerCase();

            // Check if row matches search term and filters
            const matchesSearch = searchTerm === '' || 
                                 studentId.includes(searchTerm) || 
                                 studentName.includes(searchTerm);

            // Match course with direct comparison or abbreviation
            let matchesCourse = selectedCourse === '';
            if (!matchesCourse) {
                if (selectedCourse === "Bachelor of Science in Information Technology" && studentCourse === "Bachelor of Science in Information Technology") {
                    matchesCourse = true;
                } else if (selectedCourse === "Bachelor of Science in Computer Science" && studentCourse === "Bachelor of Science in Computer Science") {
                    matchesCourse = true;
                } else if (selectedCourse === "Associate in Computer Technology" && studentCourse === "Associate in Computer Technology") {
                    matchesCourse = true;
                } else if (selectedCourse === "Bachelor of Science in Human Resourse Management" && studentCourse === "Bachelor of Science in Human Resourse Management") {
                    matchesCourse = true;
                } else if (selectedCourse === "Bachelor of Science in Criminology" && studentCourse === "Bachelor of Science in Criminology") {
                    matchesCourse = true;
                }
            }

            // Match year with various formats
            let matchesYear = selectedYear === '';
            if (!matchesYear) {
                if (selectedYear === '1' && (studentYear.includes('first') || studentYear.includes('1st'))) {
                    matchesYear = true;
                } else if (selectedYear === '2' && (studentYear.includes('second') || studentYear.includes('2nd'))) {
                    matchesYear = true;
                } else if (selectedYear === '3' && (studentYear.includes('third') || studentYear.includes('3rd'))) {
                    matchesYear = true;
                } else if (selectedYear === '4' && (studentYear.includes('fourth') || studentYear.includes('4th'))) {
                    matchesYear = true;
                } else {
                    matchesYear = studentYear.includes(selectedYear);
                }
            }

            // Show or hide row based on search and filter matches
            if (matchesSearch && matchesCourse && matchesYear) {
                row.classList.remove('hidden');
                visibleCount++;
            } else {
                row.classList.add('hidden');
            }
        });

        // Display a message if no students match the filters
        const noResultsMessage = document.getElementById('no-results-message');
        if (noResultsMessage) {
            if (visibleCount === 0) {
                noResultsMessage.classList.remove('hidden');
            } else {
                noResultsMessage.classList.add('hidden');
            }
        }
    }

    // Add event listeners to search and filter elements
    searchInput.addEventListener('input', performSearch);
    courseFilter.addEventListener('change', performSearch);
    yearFilter.addEventListener('change', performSearch);
</script>
<?php include('includes/footer.php'); ?>