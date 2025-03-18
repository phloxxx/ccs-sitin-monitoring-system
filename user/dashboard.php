<?php 
session_start();
require_once('../db.php'); // Updated path to the database file

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; 

// Fetch user details from database
$stmt = $conn->prepare("SELECT IDNO, LASTNAME, FIRSTNAME, USERNAME, COURSE, YEAR, PROFILE_PIC FROM USERS WHERE USER_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "User not found!";
    exit();
}

$username = $user['USERNAME'];

// Set up profile picture path handling - simplified and consistent approach
$default_pic = "images/snoopy.jpg";
$profile_pic = !empty($user['PROFILE_PIC']) ? $user['PROFILE_PIC'] : $default_pic;

// Use Poppins font consistently
$pageTitle = "Dashboard";
$bodyClass = "bg-light font-poppins";
include('includes/header.php');
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header class="bg-secondary px-6 py-4 shadow-md">
        <div class="container mx-auto flex flex-col md:flex-row items-center justify-between">
            <!-- Logo and Username -->
            <a href="profile.php" class="flex items-center space-x-4 mb-4 md:mb-0 group">
                <div class="h-12 w-12 rounded-full overflow-hidden border-2 border-primary">
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile" class="h-full w-full object-cover" 
                         onerror="this.onerror=null; this.src='images/snoopy.jpg';">
                </div>
                <span class="text-white font-semibold text-lg group-hover:text-primary transition"><?php echo htmlspecialchars($username); ?></span>
            </a>
            
            <!-- Navigation -->
            <div class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6">
                <nav>
                    <ul class="flex flex-wrap justify-center space-x-6">
                        <li><a href="dashboard.php" class="text-white hover:text-primary transition font-medium">Home</a></li>
                        <li><a href="notification.php" class="text-white hover:text-primary transition">Notification</a></li>
                        <li><a href="history.php" class="text-white hover:text-primary transition">History</a></li>
                        <li><a href="reservation.php" class="text-white hover:text-primary transition">Reservation</a></li>
                    </ul>
                </nav>
                
                <button onclick="confirmLogout(event)" 
                        class="bg-primary text-secondary px-4 py-2 rounded-full font-medium hover:bg-white hover:text-dark transition">
                    Logout
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow p-6 bg-light">
        <div class="container mx-auto max-w-7xl">
            <!-- Header Banner with Logos -->
            <div class="bg-blue-50 rounded-lg shadow-sm p-4 mb-6 flex items-center justify-between">
                <img src="../user/images/uc_logo.png" alt="UC Logo" class="h-12 w-auto">
                <div class="text-center">
                    <h2 class="text-secondary text-2xl font-bold">University of Cebu</h2>
                    <h3 class="text-dark text-lg">COLLEGE OF INFORMATION & COMPUTER STUDIES</h3>
                </div>
                <img src="../user/images/ccs_logo.png" alt="CCS Logo" class="h-14 w-auto">
            </div>
            
            <!-- Content Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Rules Card -->
                <div class="bg-blue-50 rounded-lg shadow-sm p-6 h-[600px] border border-gray-200">
                    <h2 class="text-secondary text-xl font-bold mb-4 text-center">Lab Rules & Regulations</h2>
                    <div class="overflow-y-auto h-[500px] pr-4 text-justify" style="scrollbar-width: thin;">
                        <p class="whitespace-pre-line text-gray-800">
                            To avoid embarrassment and maintain camaraderie with your friends and superiors at our laboratories, please observe the following:

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

                            <strong>DISCIPLINARY ACTION</strong>

                            <strong>First Offense</strong> - The Head or the Dean or OIC recommends to the Guidance Center for a suspension from classes for each offender.
                            <strong>Second and Subsequent Offenses</strong> - A recommendation for a heavier sanction will be endorsed to the Guidance Center.
                        </p>
                    </div>
                </div>
                
                <!-- Announcements Card -->
                <div class="bg-blue-50 rounded-lg shadow-sm p-6 h-[600px] border border-gray-200">
                    <h2 class="text-secondary text-xl font-bold mb-4 text-center">Announcements</h2>
                    <div class="overflow-y-auto h-[500px] pr-4" style="scrollbar-width: thin;">
                        <div class="space-y-6">
                            <div class="bg-primary bg-opacity-20 p-4 rounded-lg shadow-sm">
                                <p class="text-sm text-gray-500 mb-2">CCS Admin | 2025-Feb-25</p>
                                <p class="text-gray-800">UC did it again.</p>
                            </div>
                            
                            <div class="bg-white p-4 rounded-lg shadow">
                                <p class="text-sm text-gray-500 mb-2">CCS Admin | 2025-Feb-03</p>
                                <p class="text-gray-800">The College of Computer Studies will open the registration of students for the Sit-in privilege starting tomorrow. Thank you! Lab Supervisor</p>
                            </div>
                            
                            <div class="bg-white p-4 rounded-lg shadow">
                                <p class="text-sm text-gray-500 mb-2">CCS Admin | 2024-May-08</p>
                                <p class="text-gray-800">Important Announcement We are excited to announce the launch of our new website! 🎉 Explore our latest products and services now!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function confirmLogout(event) {
        event.preventDefault();
        var userConfirmed = confirm("Are you sure you want to logout?");
        if (userConfirmed) {
            window.location.href = "logout.php";
        }
    }
</script>

<?php include('includes/footer.php'); ?>