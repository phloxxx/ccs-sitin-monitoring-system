<?php
session_start();
require_once('../db.php'); // Ensure this correctly connects to your database

if(isset($_POST['login'])) {
    $username = trim($_POST['username']); // Trim to remove spaces
    $password = trim($_POST['password']);

    // Prepare query
    $stmt = $conn->prepare("SELECT USER_ID, USERNAME, PASSWORD FROM USERS WHERE USERNAME = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if user exists
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Direct match (if password is not hashed)
        if ($password === $user['PASSWORD']) {
            // Set session variables
            $_SESSION['user_id'] = $user['USER_ID']; // IMPORTANT: Ensure this is set
            $_SESSION['username'] = $user['USERNAME'];

            // Debugging: Check if session is set
            // var_dump($_SESSION); exit();

            // Redirect properly
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } else {
        $error = "Invalid username or password";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./styles/auth.css">
    <title>CCSSMS LOGIN</title>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="logos">
                    <img src="./src/images/ccs_logo.png" alt="Logo 1" class="logo">
                    <img src="./src/images/uc_logo.png" alt="Logo 2" class="logo">
                </div>
                <h2>CCS SITIN MONITORING SYSTEM</h2>
            </div>
            <form class="form" action="login.php" method="post">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <!-- Error messages -->
                <?php if (!empty($error)): ?>
                    <p style="color: red;"><?php echo $error; ?></p>
                <?php endif; ?>

                <button type="submit" class="btn" name="login">Login</button>
                <p>Don't have an account? <a href="register.php">Register</a></p>
            </form>
        </div>
    </div>
</body>
</html>