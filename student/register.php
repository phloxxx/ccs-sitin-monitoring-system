<?php
session_start();
require_once('../config/db.php'); // Updated path to correctly point to db.php in parent directory

// Clear form data when page loads directly (not from a form submission)
if(!isset($_POST['register'])) {
    unset($_SESSION['form_data']);
    unset($_SESSION['error']);
}

if(isset($_POST['register'])){
    $idno = $_POST['idno'];
    $lastname = $_POST['lastname'];
    $firstname = $_POST['firstname'];
    $midname = $_POST['midname'];
    $course = $_POST['course'];
    $year = $_POST['year'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Save form data to session in case of error
    $_SESSION['form_data'] = [
        'idno' => $idno,
        'lastname' => $lastname,
        'firstname' => $firstname,
        'midname' => $midname,
        'course' => $course,
        'year' => $year,
        'username' => $username
    ];

    // Check if ID number already exists
    $check_idno = "SELECT * FROM users WHERE idno = '$idno'";
    $idno_result = $conn->query($check_idno);
    
    // Check if username already exists
    $check_username = "SELECT * FROM users WHERE username = '$username'";
    $username_result = $conn->query($check_username);
    
    if($idno_result->num_rows > 0) {
        $_SESSION['error'] = "ID Number already exists. Please use a different ID Number.";
        // Clear only the ID field
        $_SESSION['form_data']['idno'] = '';
    } else if($username_result->num_rows > 0) {
        $_SESSION['error'] = "Username already exists. Please choose a different username.";
        // Clear only the username field
        $_SESSION['form_data']['username'] = '';
    } else if($password == $confirm_password){
        $sql = "INSERT INTO users (idno, lastname, firstname, midname, course, year, username, password) VALUES ('$idno', '$lastname', '$firstname', '$midname', '$course', '$year', '$username', '$password')";
        $result = $conn->query($sql);

        if($result === TRUE){
            // Clear session data on successful registration
            unset($_SESSION['form_data']);
            echo "<script>
            alert('You have successfully registered!');
            window.location.href='login.php';
            </script>";
        }else{
            $_SESSION['error'] = "Error: " . $sql . "<br>" . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Password does not match.";
    }
}

// Initialize variables from session if they exist
$form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [
    'idno' => '', 'lastname' => '', 'firstname' => '', 'midname' => '', 
    'course' => '', 'year' => '', 'username' => ''
];

$pageTitle = "Register";
$bodyClass = "bg-light font-poppins";
include('includes/header.php');
?>

<div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 bg-cover bg-center relative py-10" 
     style="background-image: url('../user/images/uc_bg.jpg')">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-dark opacity-50"></div>
    
    <div class="z-10 w-full max-w-xl">
        <div class="bg-white shadow-lg rounded-xl overflow-hidden transform transition-all hover:scale-[1.01] duration-300">
            <div class="px-8 py-8">
                <h2 class="text-center text-2xl font-bold text-secondary mb-8">CREATE YOUR ACCOUNT</h2>
                
                <form class="space-y-6" action="register.php" method="post">
                    <!-- ID Number -->
                    <div>
                        <label for="idno" class="block text-sm font-medium text-gray-700">ID Number</label>
                        <input type="text" id="idno" name="idno" required 
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary"
                               placeholder="Enter your ID number" value="<?php echo htmlspecialchars($form_data['idno']); ?>">
                    </div>
                    
                    <!-- Name Fields (Last Name, First Name) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="lastname" class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" id="lastname" name="lastname" required 
                                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary"
                                   placeholder="Enter your last name" value="<?php echo htmlspecialchars($form_data['lastname']); ?>">
                        </div>
                        <div>
                            <label for="firstname" class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" id="firstname" name="firstname" required 
                                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary"
                                   placeholder="Enter your first name" value="<?php echo htmlspecialchars($form_data['firstname']); ?>">
                        </div>
                    </div>
                    
                    <!-- Middle Name and Course -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="midname" class="block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" id="midname" name="midname" 
                                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary"
                                   placeholder="Enter your middle name (optional)" value="<?php echo htmlspecialchars($form_data['midname']); ?>">
                        </div>
                        <div>
                            <label for="course" class="block text-sm font-medium text-gray-700">Course</label>
                            <div class="relative">
                                <select id="course" name="course" required 
                                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary appearance-none bg-white text-gray-700">
                                    <option value="" class="text-gray-700" disabled>Select Course</option>
                                    <option value="Bachelor of Science in Information Technology" <?php echo ($form_data['course'] == 'Bachelor of Science in Information Technology') ? 'selected' : ''; ?> class="text-gray-700">BSIT</option>
                                    <option value="Bachelor of Science in Computer Science" <?php echo ($form_data['course'] == 'Bachelor of Science in Computer Science') ? 'selected' : ''; ?> class="text-gray-700">BSCS</option>
                                    <option value="Associate in Computer Technology" <?php echo ($form_data['course'] == 'Associate in Computer Technology') ? 'selected' : ''; ?> class="text-gray-700">ACT</option>
                                    <option value="Bachelor of Science in Human Resourse Management" <?php echo ($form_data['course'] == 'Bachelor of Science in Human Resourse Management') ? 'selected' : ''; ?> class="text-gray-700">BSHRM</option>
                                    <option value="Bachelor of Science in Criminology" <?php echo ($form_data['course'] == 'Bachelor of Science in Criminology') ? 'selected' : ''; ?> class="text-gray-700">BSCRIM</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-700">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Year Level and Username -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="year" class="block text-sm font-medium text-gray-700">Year Level</label>
                            <div class="relative">
                                <select id="year" name="year" required 
                                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary appearance-none bg-white text-gray-700">
                                    <option value="" class="text-gray-700" disabled>Select Year</option>
                                    <option value="First Year" <?php echo ($form_data['year'] == 'First Year') ? 'selected' : ''; ?> class="text-gray-700">1st Year</option>
                                    <option value="Second Year" <?php echo ($form_data['year'] == 'Second Year') ? 'selected' : ''; ?> class="text-gray-700">2nd Year</option>
                                    <option value="Third Year" <?php echo ($form_data['year'] == 'Third Year') ? 'selected' : ''; ?> class="text-gray-700">3rd Year</option>
                                    <option value="Fourth Year" <?php echo ($form_data['year'] == 'Fourth Year') ? 'selected' : ''; ?> class="text-gray-700">4th Year</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-700">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label for="username" class="block text-sm font-medium text-dark">Username</label>
                            <input type="text" id="username" name="username" required 
                                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                                   placeholder="Choose a username" value="<?php echo htmlspecialchars($form_data['username']); ?>">
                        </div>
                    </div>
                    
                    <!-- Password and Confirm Password -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block text-sm font-medium text-dark">Password</label>
                            <input type="password" id="password" name="password" required 
                                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                                   placeholder="Create a password">
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-dark">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                                   placeholder="Confirm your password">
                        </div>
                    </div>

                    <!-- Error messages -->
                    <?php if(isset($_SESSION['error'])): ?>
                        <div class="rounded-md bg-red-50 p-4 border border-red-300">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div>
                        <button type="submit" name="register" 
                                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-secondary hover:bg-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary transition-all duration-300">
                            Create Account
                        </button>
                    </div>
                </form>
                
                <p class="mt-6 text-center text-sm text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="font-medium text-secondary hover:text-dark transition-colors">
                        Sign In
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>