<?php 
session_start();
require_once('db.php'); 

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) { // Change from 'username' to 'user_id'
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; 

// Fetch user details from database (including USERNAME)
$stmt = $conn->prepare("SELECT IDNO, LASTNAME, FIRSTNAME, USERNAME, COURSE, YEAR, PROFILE_PIC FROM USERS WHERE USER_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// If no user is found
if (!$user) {
    echo "User not found!";
    exit();
}

// Assign username variable
$username = $user['USERNAME']; // ✅ Fix: Now we fetch the username

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

            <!-- NEW FULL-WIDTH CARD WITH LOGOS -->
            <div class="full-width-card">
                <img src="src/images/uc_logo.png" alt="Left Logo" class="logo-img">
                <div class="title-container">
                    <h2>University of Cebu</h2>
                    <h3>COLLEGE OF INFORMATION & COMPUTER STUDIES</h3>
                </div>
                <img src="src/images/ccs_logo.png" alt="Right Logo" class="logo-img" style="width: 50px; height: 50px;">
            </div>

            <div class="card">
                <h2>Lab Rules & Regulations</h2>
                <p>To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:

                    1. Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.

                    2. Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.

                    3. Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.

                    4. Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.

                    5. Deleting computer files and changing the set-up of the computer is a major offense.

                    6. Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".

                    7. Observe proper decorum while inside the laboratory.
                    a. Do not get inside the lab unless the instructor is present.
                    b. All bags, knapsacks, and the likes must be deposited at the counter.
                    c. Follow the seating arrangement of your instructor.
                    d. At the end of class, all software programs must be closed.
                    e. Return all chairs to their proper places after using.

                    8. Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.

                    9. Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.

                    10. Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.

                    11. For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.

                    12. Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.

                    <b>DISCIPLINARY ACTION</b>

                    <b>First Offense</b> - The Head or the Dean or OIC recommends to the Guidance Center for a suspension from classes for each offender.
                    <b>Second and Subsequent Offenses</b> - A recommendation for a heavier sanction will be endorsed to the Guidance Center.</p>
            </div>
            <div class="card">
                <h2>Announcements</h2>
                <p>CCS Admin | 2025-Feb-25

                    UC did it again.

                    CCS Admin | 2025-Feb-03

                    The College of Computer Studies will open the registration of students for the Sit-in privilege starting tomorrow. Thank you! Lab Supervisor

                    CCS Admin | 2024-May-08

                    Important Announcement We are excited to announce the launch of our new website! 🎉 Explore our latest products and services now!</p>
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