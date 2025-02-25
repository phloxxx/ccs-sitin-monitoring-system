<?php 
session_start();
require_once('db.php'); // Ensure this connects to your database

// Ensure user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);

// Fetch user details from database
$stmt = $conn->prepare("SELECT IDNO, LASTNAME, FIRSTNAME, COURSE, YEAR, PROFILE_PIC FROM USERS WHERE USERNAME = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// If no user found
if (!$user) {
    echo "User not found!";
    exit();
}

// Profile Picture (use uploaded pic if available, otherwise default)
$profile_pic = !empty($user['PROFILE_PIC']) ? $user['PROFILE_PIC'] : "src/images/snoopy.jpg";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <title>DASHBOARD</title>
</head>
<body>

    <header>
        <!-- Clickable Logo and Username -->
        <a href="profile.php" class="logo-container">
            <div class="logo">
                <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture">
            </div>
            <span class="username"><?php echo htmlspecialchars($username); ?></span>
        </a>

        <div class="nav-container">
            <nav>
                <ul class="nav-links">
                    <li><a href="/dashboard.php">Home</a></li>
                    <li><a href="/notification">Notification</a></li>
                    <li><a href="/history">History</a></li>
                    <li><a href="/reservation">Reservation</a></li>
                </ul>
            </nav>
            <a class="logout" href="#" onclick="confirmLogout(event)">
                <button>Logout</button>
            </a>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="card">
                <h2>Rules & Regulations</h2>
                <p>Content for card 1.</p>
            </div>
            <div class="card">
                <h2>Announcements</h2>
                <p>Content for card 2.</p>
            </div>
        </div>
    </main>

    <script>
        function confirmLogout(event) {
            event.preventDefault();
            var userConfirmed = confirm("Are you sure you want to logout?");
            if (userConfirmed) {
                window.location.href = "logout.php";
            }
        }
    </script>
</body>
</html>