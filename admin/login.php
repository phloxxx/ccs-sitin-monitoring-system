<?php
session_start();
require_once('../config/db.php');

if(isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT ADMIN_ID, USERNAME, PASSWORD FROM ADMIN WHERE USERNAME = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if ($password === $admin['PASSWORD']) {
            $_SESSION['admin_id'] = $admin['ADMIN_ID'];
            $_SESSION['username'] = $admin['USERNAME'];
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
                
                <h2 class="text-center text-2xl font-bold text-secondary">ADMIN</h2>
                
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
                            Login
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>