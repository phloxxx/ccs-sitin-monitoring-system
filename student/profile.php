<?php 
session_start();
require_once('../config/db.php'); // Updated path to the database file

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT IDNO, LASTNAME, FIRSTNAME, USERNAME, COURSE, YEAR, SESSION, PROFILE_PIC FROM USERS WHERE USER_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fix profile picture path handling
$default_pic = "images/snoopy.jpg";  // Default relative to user directory
$profile_pic = !empty($user['PROFILE_PIC']) ? $user['PROFILE_PIC'] : $default_pic;

$edit_mode = isset($_GET['edit']);
$error_messages = array(); // Array to store different error messages

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idno = $_POST['idno'];
    $lastname = $_POST['lastname'];
    $firstname = $_POST['firstname'];
    $username = $_POST['username'];
    $course = $_POST['course'];
    $year = $_POST['year'];
    $profile_pic_path = $user['PROFILE_PIC']; // Default to current profile picture
    
    // Password change fields (optional)
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $password_changed = false;

    // Profile Picture Upload - Update to use uploads directory in user folder
    if (!empty($_FILES['profile_pic']['name'])) {
        $upload_dir = "uploads/";  // Physical directory path for saving (in user folder)
        $db_path = "uploads/";  // Database path (relative to user directory)
        
        // Ensure the directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate a unique filename
        $file_extension = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
        $new_filename = "profile_" . $user_id . "_" . time() . "." . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        // Store relative path in database
        $profile_pic_path = $db_path . $new_filename;

        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_types)) {
            $error_messages['profile'] = "Invalid file type! Only JPG, JPEG, PNG, and GIF are allowed.";
        } else {
            if (!move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $upload_path)) {
                $error_messages['profile'] = "Error uploading profile picture.";
            }
        }
    }

    // Check for unique ID Number
    $check_id_stmt = $conn->prepare("SELECT USER_ID FROM USERS WHERE IDNO = ? AND USER_ID != ?");
    $check_id_stmt->bind_param("si", $idno, $user_id);
    $check_id_stmt->execute();
    $check_id_result = $check_id_stmt->get_result();
    
    if ($check_id_result->num_rows > 0) {
        $error_messages['idno'] = "ID Number is already taken!";
    }
    $check_id_stmt->close();
    
    // Check for unique Username
    $check_username_stmt = $conn->prepare("SELECT USER_ID FROM USERS WHERE USERNAME = ? AND USER_ID != ?");
    $check_username_stmt->bind_param("si", $username, $user_id);
    $check_username_stmt->execute();
    $check_username_result = $check_username_stmt->get_result();
    
    if ($check_username_result->num_rows > 0) {
        $error_messages['username'] = "Username is already taken!";
    }
    $check_username_stmt->close();
    
    // Handle password change if requested
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        // Verify all password fields are filled
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_messages['password'] = "All password fields are required to change your password.";
        } else {
            // Verify current password
            $verify_stmt = $conn->prepare("SELECT PASSWORD FROM USERS WHERE USER_ID = ?");
            $verify_stmt->bind_param("i", $user_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $user_data = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            if (!password_verify($current_password, $user_data['PASSWORD'])) {
                $error_messages['password'] = "Current password is incorrect.";
            } elseif ($new_password !== $confirm_password) {
                $error_messages['password'] = "New passwords don't match.";
            } elseif (strlen($new_password) < 8) {
                $error_messages['password'] = "New password must be at least 8 characters long.";
            } else {
                // Password change is valid
                $password_changed = true;
            }
        }
    }

    // If no errors, update the user information
    if (empty($error_messages)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update user details
            $update_stmt = $conn->prepare("UPDATE USERS SET IDNO = ?, LASTNAME = ?, FIRSTNAME = ?, USERNAME = ?, COURSE = ?, YEAR = ?, PROFILE_PIC = ? WHERE USER_ID = ?");
            $update_stmt->bind_param("sssssssi", $idno, $lastname, $firstname, $username, $course, $year, $profile_pic_path, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Update password if changed
            if ($password_changed) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_stmt = $conn->prepare("UPDATE USERS SET PASSWORD = ? WHERE USER_ID = ?");
                $password_stmt->bind_param("si", $hashed_password, $user_id);
                $password_stmt->execute();
                $password_stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Update session variables with new info
            $_SESSION['username'] = $username;
            
            // Redirect to profile page after successful update
            header("Location: profile.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback the transaction if any part fails
            $conn->rollback();
            $error_messages['general'] = "Error updating profile: " . $e->getMessage();
        }
    }
}

$pageTitle = "Profile";
$bodyClass = "bg-light font-poppins"; // Changed from Montserrat to Poppins
include('includes/header.php');
?>

<!-- Add Poppins font and custom color scheme -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    :root {
        --primary-color: #94B0DF;    /* Soft blue */
        --secondary-color: #356480;  /* Medium blue-gray */
        --dark-color: #2c3e50;       /* Dark blue-gray */
        --light-color: #FCFDFF;      /* Very light blue-white */
    }
    
    body {
        font-family: 'Poppins', sans-serif;
        background-color: var(--light-color);
    }
    
    /* Fix placeholder color consistency */
    ::placeholder {
        color: #6B7280;
        opacity: 0.7;
    }
    
    /* Define consistent form field styling */
    .form-input {
        width: 100%;
        padding: 0.75rem;
        border: 2px solid var(--secondary-color);
        border-radius: 0.5rem;
        outline: none;
        transition: all 0.3s ease;
    }
    
    .form-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(148, 176, 223, 0.25);
    }
    
    /* Style select dropdowns consistently */
    select.form-input {
        appearance: none;
        background-color: white;
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
        padding-right: 2.5rem;
        color: #374151;
    }
    
    select.form-input option {
        color: #374151;
    }
    
    /* Custom utility classes for the new color scheme */
    .bg-primary { background-color: var(--primary-color) !important; }
    .bg-secondary { background-color: var(--secondary-color) !important; }
    .bg-dark { background-color: var(--dark-color) !important; }
    .bg-light { background-color: var(--light-color) !important; }
    
    .text-primary { color: var(--primary-color) !important; }
    .text-secondary { color: var(--secondary-color) !important; }
    .text-dark { color: var(--dark-color) !important; }
    .text-light { color: var(--light-color) !important; }
    
    .border-primary { border-color: var(--primary-color) !important; }
    .border-secondary { border-color: var(--secondary-color) !important; }
    
    .hover-bg-dark:hover { background-color: var(--dark-color) !important; }
    .hover-text-light:hover { color: var(--light-color) !important; }

    /* Ensure buttons properly change text color on hover */
    .hover-text-white:hover {
        color: white !important;
    }
    
    /* Dropdown styling with primary color */
    select.form-input {
        appearance: none;
        background-color: white;
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
        padding-right: 2.5rem;
        color: #374151;
        /* Fix the SVG encoding for dropdown arrow */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2394B0DF' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
    }

    /* Password field styling */
    .password-section {
        border-top: 1px solid #e5e7eb;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
    }
    
    /* Error message styling */
    .field-error {
        color: #EF4444;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
    
    /* Input error state */
    .input-error {
        border-color: #EF4444 !important;
    }
    
    /* Optional section styling */
    .optional-section {
        position: relative;
    }
    
    .optional-badge {
        position: absolute;
        top: 0;
        right: 0;
        background-color: var(--primary-color);
        color: white;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        transform: translateY(-50%);
    }
</style>

<div class="min-h-screen flex items-center justify-center p-4 bg-blue-50">
    <div class="bg-white rounded-lg shadow-lg max-w-3xl w-full p-8 border border-gray-200">
        <?php if (!empty($error_messages['general'])): ?>
            <div class="bg-red-50 border-l-4 border-red-300 text-red-700 p-4 mb-6 rounded" role="alert">
                <p class="font-medium"><?php echo $error_messages['general']; ?></p>
            </div>
        <?php endif; ?>

        <!-- View Mode -->
        <?php if (!$edit_mode): ?>
            <div class="text-center">
                <div class="mb-8 inline-block relative">
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                        alt="Profile Picture" 
                        class="w-36 h-36 rounded-full border-4 border-primary object-cover shadow-md"
                        onerror="this.src='../student/images/snoopy.jpg'">
                </div>
                
                <h2 class="text-2xl font-bold text-secondary mb-8"><?php echo htmlspecialchars($user['USERNAME']); ?></h2>
                
                <div class="bg-gray-50 rounded-lg p-6 mb-8 text-left space-y-4 shadow-sm">
                    <p class="text-gray-700 flex justify-between"><span class="font-semibold text-secondary">ID Number:</span> <span class="text-dark"><?php echo htmlspecialchars($user['IDNO']); ?></span></p>
                    <p class="text-gray-700 flex justify-between"><span class="font-semibold text-secondary">Name:</span> <span class="text-dark"><?php echo htmlspecialchars($user['LASTNAME'] . ", " . $user['FIRSTNAME']); ?></span></p>
                    <p class="text-gray-700 flex justify-between"><span class="font-semibold text-secondary">Course:</span> <span class="text-dark"><?php echo htmlspecialchars($user['COURSE']); ?></span></p>
                    <p class="text-gray-700 flex justify-between"><span class="font-semibold text-secondary">Year:</span> <span class="text-dark"><?php echo htmlspecialchars($user['YEAR']); ?></span></p>
                    <p class="text-gray-700 flex justify-between"><span class="font-semibold text-secondary">Session Time:</span> <span class="text-dark"><?php echo htmlspecialchars($user['SESSION'] ?? '30'); ?></span></p>
                </div>
                
                <div class="flex justify-between space-x-4">
                    <a href="dashboard.php" class="flex-1 bg-secondary text-light py-3 px-6 rounded-lg text-center hover:bg-dark transition shadow-md flex items-center justify-center">
                        <i class="fas fa-home mr-2"></i> Dashboard
                    </a>
                    <a href="profile.php?edit=true" class="flex-1 bg-white text-secondary py-3 px-6 rounded-lg text-center border-2 border-secondary hover:bg-secondary hover-text-white transition shadow-md flex items-center justify-center">
                        <i class="fas fa-pencil-alt mr-2"></i> Edit Profile
                    </a>
                </div>
            </div>

        <!-- Edit Mode -->
        <?php else: ?>
            <h2 class="text-2xl font-bold text-secondary mb-6 text-center">Edit Profile</h2>
            
            <form method="POST" enctype="multipart/form-data" id="profileForm">
                <div class="text-center mb-8 relative">
                    <div class="inline-block">
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                            alt="Profile Picture" id="profilePicPreview"
                            class="w-36 h-36 rounded-full border-4 border-primary object-cover shadow-md"
                            onerror="this.src='../student/images/snoopy.jpg'">
                            
                        <div class="absolute bottom-0 right-0 left-0 mx-auto w-10 h-10 bg-secondary rounded-full flex items-center justify-center cursor-pointer shadow-lg transform translate-y-2" 
                            onclick="triggerFileInput()">
                            <i class="fas fa-pencil text-light"></i>
                        </div>
                    </div>
                    
                    <input type="file" id="profilePicInput" name="profile_pic" accept="image/*" class="hidden">
                </div>
                
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="idno" class="block text-secondary font-medium mb-2">ID Number</label>
                            <input type="text" id="idno" name="idno" value="<?php echo htmlspecialchars($user['IDNO']); ?>" 
                                class="form-input text-secondary <?php echo isset($error_messages['idno']) ? 'input-error' : ''; ?>"
                                placeholder="Enter ID Number">
                            <?php if (isset($error_messages['idno'])): ?>
                                <p class="field-error"><i class="fas fa-exclamation-circle mr-1"></i><?php echo $error_messages['idno']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label for="username" class="block text-secondary font-medium mb-2">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['USERNAME']); ?>" 
                                class="form-input text-secondary <?php echo isset($error_messages['username']) ? 'input-error' : ''; ?>"
                                placeholder="Enter Username">
                            <?php if (isset($error_messages['username'])): ?>
                                <p class="field-error"><i class="fas fa-exclamation-circle mr-1"></i><?php echo $error_messages['username']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="lastname" class="block text-secondary font-medium mb-2">Last Name</label>
                            <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['LASTNAME']); ?>" 
                                class="form-input text-secondary"
                                placeholder="Enter Last Name">
                        </div>
                        <div>
                            <label for="firstname" class="block text-secondary font-medium mb-2">First Name</label>
                            <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['FIRSTNAME']); ?>" 
                                class="form-input text-secondary"
                                placeholder="Enter First Name">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="course" class="block text-secondary font-medium mb-2">Course</label>
                            <select id="course" name="course" 
                                class="form-input text-secondary">
                                <option value="<?php echo htmlspecialchars($user['COURSE']); ?>" disabled><?php echo htmlspecialchars($user['COURSE']); ?></option>
                                <option value="Bachelor of Science in Information Technology">BSIT</option>
                                <option value="Bachelor of Science in Information Systems">BSIS</option>
                                <option value="Bachelor of Science in Computer Science">BSCS</option>
                                <option value="Associate in Computer Technology">ACT</option>
                            </select>
                        </div>
                        <div>
                            <label for="year" class="block text-secondary font-medium mb-2">Year Level</label>
                            <select id="year" name="year" 
                                class="form-input text-secondary">
                                <option value="<?php echo htmlspecialchars($user['YEAR']); ?>" disabled><?php echo htmlspecialchars($user['YEAR']); ?></option>
                                <option value="First Year">1st Year</option>
                                <option value="Second Year">2nd Year</option>
                                <option value="Third Year">3rd Year</option>
                                <option value="Fourth Year">4th Year</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Change password section -->
                    <div class="password-section pt-6 optional-section">
                        <h3 class="text-xl font-bold text-secondary mb-4">Change Password</h3>
                        <span class="optional-badge">Optional</span>
                        <p class="text-gray-600 mb-4">You can leave these fields blank if you don't want to change your password.</p>
                        
                        <?php if (isset($error_messages['password'])): ?>
                            <div class="bg-red-50 border-l-4 border-red-300 text-red-700 p-3 mb-4 rounded-md">
                                <p><i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error_messages['password']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="current_password" class="block text-secondary font-medium mb-2">Current Password</label>
                                <input type="password" id="current_password" name="current_password" 
                                    class="form-input text-secondary <?php echo isset($error_messages['password']) ? 'input-error' : ''; ?>"
                                    placeholder="Current Password">
                            </div>
                            <div>
                                <label for="new_password" class="block text-secondary font-medium mb-2">New Password</label>
                                <input type="password" id="new_password" name="new_password" 
                                    class="form-input text-secondary <?php echo isset($error_messages['password']) ? 'input-error' : ''; ?>"
                                    placeholder="New Password">
                                <p class="text-gray-500 text-xs mt-1">Must be at least 8 characters long</p>
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-secondary font-medium mb-2">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                    class="form-input text-secondary <?php echo isset($error_messages['password']) ? 'input-error' : ''; ?>"
                                    placeholder="Confirm Password">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between space-x-4 pt-6">
                        <a href="#" onclick="confirmCancel(event)" class="flex-1 bg-gray-200 text-gray-700 py-3 px-6 rounded-lg text-center hover:bg-gray-300 transition shadow-md flex items-center justify-center">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                        <button type="button" onclick="confirmSave()" class="flex-1 bg-secondary text-light py-3 px-6 rounded-lg text-center hover:bg-dark transition shadow-md flex items-center justify-center">
                            <i class="fas fa-check mr-2"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Add confirmation dialog with updated colors -->
<div id="confirmDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-secondary mb-4" id="dialogTitle">Confirm Action</h3>
        <p class="text-dark mb-6" id="dialogMessage">Are you sure you want to proceed?</p>
        <div class="flex justify-end space-x-3">
            <button id="cancelBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                Cancel
            </button>
            <button id="confirmBtn" class="px-4 py-2 bg-secondary text-light rounded-lg hover:bg-dark transition">
                Confirm
            </button>
        </div>
    </div>
</div>

<script>
function triggerFileInput() {
    document.getElementById('profilePicInput').click();
}

document.getElementById('profilePicInput').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePicPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Confirmation dialog functions
function showDialog(title, message, confirmCallback) {
    const dialog = document.getElementById('confirmDialog');
    const dialogTitle = document.getElementById('dialogTitle');
    const dialogMessage = document.getElementById('dialogMessage');
    const confirmBtn = document.getElementById('confirmBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    
    dialogTitle.textContent = title;
    dialogMessage.textContent = message;
    
    // Reset event listeners
    const newConfirmBtn = confirmBtn.cloneNode(true);
    const newCancelBtn = cancelBtn.cloneNode(true);
    
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    
    newConfirmBtn.addEventListener('click', function() {
        dialog.classList.add('hidden');
        if (confirmCallback) confirmCallback();
    });
    
    newCancelBtn.addEventListener('click', function() {
        dialog.classList.add('hidden');
    });
    
    dialog.classList.remove('hidden');
}

function confirmCancel(event) {
    event.preventDefault();
    showDialog(
        "Cancel Changes", 
        "Are you sure you want to cancel? Any unsaved changes will be lost.",
        function() {
            window.location.href = "profile.php";
        }
    );
}

function confirmSave() {
    showDialog(
        "Save Changes", 
        "Are you sure you want to save these changes to your profile?",
        function() {
            document.getElementById('profileForm').submit();
        }
    );
}

// Password validation
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordField = document.getElementById('new_password');
    const confirmPasswordField = document.getElementById('confirm_password');
    
    function validatePasswordMatch() {
        if (newPasswordField.value && confirmPasswordField.value) {
            if (newPasswordField.value !== confirmPasswordField.value) {
                confirmPasswordField.setCustomValidity("Passwords don't match");
            } else {
                confirmPasswordField.setCustomValidity('');
            }
        }
    }
    
    if (newPasswordField && confirmPasswordField) {
        newPasswordField.addEventListener('change', validatePasswordMatch);
        confirmPasswordField.addEventListener('keyup', validatePasswordMatch);
    }
});
</script>

<?php include('includes/footer.php'); ?>
