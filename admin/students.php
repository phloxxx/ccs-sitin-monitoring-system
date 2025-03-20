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

// Get today's registrations - Modified to avoid CREATED_AT column error
$todayRegistrations = 0;
// Since CREATED_AT doesn't exist in USERS table, we'll set a default value
// If you want accurate counts, you should add CREATED_AT to your USERS table

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
                    <hr class="my-4 border-gray-400 border-opacity-20">
                    <a href="announcements.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-bullhorn mr-3"></i>
                        <span>Announcements</span>
                    </a>
                </nav>
                
                <!-- Logout Button -->
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
                            <h2 class="text-gray-600 text-sm">Active Students</h2>
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
                                <?php echo isset($todayRegistrations) ? $todayRegistrations : '0'; ?>
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
                            <option value="BSIT">BSIT</option>
                            <option value="BSCS">BSCS</option>
                            <option value="BSDA">BSDA</option>
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
                                            data-course="<?php echo htmlspecialchars($student['COURSE']); ?>"
                                            data-year="<?php echo htmlspecialchars($student['YEAR']); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="text-secondary hover:text-dark" 
                                            onclick="openSitInForm('<?php echo $student['IDNO']; ?>', '<?php echo htmlspecialchars($student['FIRSTNAME'] . ' ' . $student['LASTNAME']); ?>', <?php echo $student['SESSION']; ?>)">
                                        <i class="fas fa-desktop"></i> Sit-In
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                    <label for="student-midname" class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
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
                        <option value="">Select Course</option>
                        <option value="BSIT">BSIT</option>
                        <option value="BSCS">BSCS</option>
                        <option value="BSDA">BSDA</option>
                        <option value="ACT">ACT</option>
                        <option value="MIT">MIT</option>
                    </select>
                </div>
                
                <div>
                    <label for="student-year" class="block text-sm font-medium text-gray-700 mb-1">Year Level *</label>
                    <select id="student-year" name="year" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                        <option value="">Select Year</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                        <option value="5">5th Year</option>
                    </select>
                </div>
                
                <div>
                    <label for="student-sessions" class="block text-sm font-medium text-gray-700 mb-1">Initial Sessions *</label>
                    <input type="number" id="student-sessions" name="sessions" required min="0" max="100" value="10"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                </div>
            </div>
            
            <div class="flex justify-end space-x-4">
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
            document.getElementById('student-midname').value = this.dataset.midname;
            document.getElementById('student-course').value = this.dataset.course;
            document.getElementById('student-year').value = this.dataset.year;
            document.getElementById('student-sessions').value = this.dataset.session;
            
            // Password fields not required for edit
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
        document.getElementById('student-idno').readOnly = false;
        document.getElementById('student-password').setAttribute('required', 'required');
        document.getElementById('student-confirm-password').setAttribute('required', 'required');
    }
    
    // Close form buttons
    document.getElementById('close-student-form').addEventListener('click', closeStudentForm);
    document.getElementById('cancel-student-form').addEventListener('click', closeStudentForm);
    
    // Form submission
    studentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Basic validation
        const password = document.getElementById('student-password').value;
        const confirmPassword = document.getElementById('student-confirm-password').value;
        
        if (formMode.value === 'create' && password !== confirmPassword) {
            errorElement.textContent = 'Passwords do not match';
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
</script>

<?php include('includes/footer.php'); ?>
