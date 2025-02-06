<?php
 session_start();
 require_once('db.php');

    if(isset($_POST['login'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Prepare and bind
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $username, $password);

        // Execute the statement
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if the user exists
        if ($result->num_rows > 0) {
            // Fetch the user data
            $user = $result->fetch_assoc();

            // Set the session variables
            $_SESSION['id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // Redirect to the dashboard
            header('Location: dashboard.php');
        } else {
            $error = "Invalid username or password";
        }

        // Close the statement
        $stmt->close();
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <title>CCSSMS LOGIN</title>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="logos">
                    <img src="src/images/ccs_logo.png" alt="Logo 1" class="logo">
                    <img src="src/images/uc_logo.jpg" alt="Logo 2" class="logo">
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