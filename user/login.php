<?php
session_start();
require_once('../db.php');

if(isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT USER_ID, USERNAME, PASSWORD FROM USERS WHERE USERNAME = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($password === $user['PASSWORD']) {
            $_SESSION['user_id'] = $user['USER_ID'];
            $_SESSION['username'] = $user['USERNAME'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "Username not found. Please check your credentials.";
    }
    $stmt->close();
}

$pageTitle = "Login";
$bodyClass = "bg-light font-poppins";
include('includes/header.php');
?>

<div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 bg-cover bg-center relative" 
     style="background-image: url('../user/images/uc_bg.jpg')">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-dark opacity-50"></div>
    
    <div class="z-10 w-full max-w-md">
        <div class="bg-white shadow-lg rounded-xl overflow-hidden transform transition-all hover:scale-105 duration-300">
            <div class="px-8 py-8">
                <div class="flex justify-center space-x-8 mb-6">
                    <img src="../user/images/ccs_logo.png" alt="CCS Logo" class="h-16 w-auto">
                    <img src="../user/images/uc_logo.png" alt="UC Logo" class="h-16 w-auto">
                </div>
                
                <h2 class="text-center text-2xl font-bold text-secondary mb-8">CCS SITIN MONITORING SYSTEM</h2>
                
                <form class="space-y-6" action="login.php" method="post">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" id="username" name="username" required 
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary sm:text-sm"
                                placeholder="Enter your username">
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="password" name="password" required 
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary sm:text-sm"
                                placeholder="Enter your password">
                        </div>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="rounded-md bg-red-50 p-4 border border-red-300">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700"><?php echo $error; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div>
                        <button type="submit" name="login" 
                                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-secondary hover:bg-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary transition-all duration-300">
                            Sign In
                        </button>
                    </div>
                </form>
                
                <p class="mt-6 text-center text-sm text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="font-medium text-secondary hover:text-dark transition-colors">
                        Register Now
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>