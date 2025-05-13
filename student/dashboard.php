<?php 
session_start();
require_once('../config/db.php'); // Updated path to the database file

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

// Set up profile picture path handling - simplified approach
$default_pic = "images/snoopy.jpg";
$profile_pic = !empty($user['PROFILE_PIC']) ? $user['PROFILE_PIC'] : $default_pic;

// Fetch announcements from the database table
$announcements = [];
try {
    // Use the same table that the admin uses for announcements
    $stmt = $conn->prepare("SELECT a.*, admin.username FROM ANNOUNCEMENT a 
                           JOIN ADMIN admin ON a.ADMIN_ID = admin.ADMIN_ID 
                           ORDER BY a.CREATED_AT DESC LIMIT 10");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // If there's an error or the table doesn't exist yet, use sample data
    $announcements = [
        [
            'ANNOUNCE_ID' => 1,
            'TITLE' => 'System Maintenance Notice',
            'CONTENT' => 'The sit-in monitoring system will be undergoing maintenance this weekend. Please expect some downtime from Saturday 10 PM to Sunday 2 AM.',
            'CREATED_AT' => '2023-10-15 09:30:00',
            'username' => 'System Administrator'
        ],
        [
            'ANNOUNCE_ID' => 2,
            'TITLE' => 'New Programming Lab Rules',
            'CONTENT' => 'Starting next week, all students must register their sit-in requests at least 24 hours in advance. This is to ensure better resource allocation and lab availability.',
            'CREATED_AT' => '2023-10-12 14:45:00',
            'username' => 'Lab Coordinator'
        ],
        [
            'ANNOUNCE_ID' => 3,
            'TITLE' => 'UC did it again.',
            'CONTENT' => 'The College of Computer Studies will open the registration of students for the Sit-in privilege starting tomorrow. Thank you! Lab Supervisor',
            'CREATED_AT' => '2023-10-03 14:30:00',
            'username' => 'CCS Admin'
        ]
    ];
}

// Format date helper function
function formatAnnouncementDate($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 86400) { // Less than 24 hours
        return date('g:i A', $timestamp); // Show only time
    } elseif ($diff < 604800) { // Less than 7 days
        return date('l', $timestamp); // Show day name
    } else {
        return date('Y-M-d', $timestamp); // Show full date
    }
}

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
                        <li><a href="dashboard.php" class="text-white hover:text-primary transition font-semibold border-b-2 border-primary pb-1">Home</a></li>
                        <li><a href="notification.php" class="text-white hover:text-primary transition">Notification</a></li>
                        <li><a href="history.php" class="text-white hover:text-primary transition">History</a></li>
                        <li><a href="reservation.php" class="text-white hover:text-primary transition">Reservation</a></li>
                    </ul>
                </nav>
                
                <button onclick="confirmLogout(event)" 
                        class="bg-primary text-secondary px-4 py-2 rounded-full font-medium hover:bg-white hover:text-dark transition shadow-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow p-6 bg-light">
        <div class="container mx-auto max-w-7xl">
            <!-- Header Banner with Logos -->
            <div class="bg-blue-50 rounded-lg shadow-sm p-4 mb-6 flex items-center justify-between">
                <img src="../student/images/uc_logo.png" alt="UC Logo" class="h-12 w-auto">
                <div class="text-center">
                    <h2 class="text-secondary text-2xl font-bold">University of Cebu</h2>
                    <h3 class="text-dark text-lg">COLLEGE OF INFORMATION & COMPUTER STUDIES</h3>
                </div>
                <img src="../student/images/ccs_logo.png" alt="CCS Logo" class="h-14 w-auto">
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
                        <div class="space-y-6" id="announcements-container">
                            <?php if (empty($announcements)): ?>
                                <div class="bg-white p-4 rounded-lg shadow text-center">
                                    <p class="text-gray-500">No announcements available at this time.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($announcements as $index => $announcement): ?>
                                    <?php 
                                    // Check if announcement is recent (less than 24 hours old)
                                    $isRecent = (time() - strtotime($announcement['CREATED_AT']) < 86400);
                                    $bgClass = $index === 0 ? "bg-primary bg-opacity-20" : "bg-white";
                                    ?>
                                    <div class="<?php echo $bgClass; ?> p-4 rounded-lg shadow-sm" data-id="<?php echo $announcement['ANNOUNCE_ID']; ?>">
                                        <div class="flex justify-between items-start mb-2">
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($announcement['username']); ?> | 
                                                <?php echo date('Y-M-d', strtotime($announcement['CREATED_AT'])); ?>
                                            </p>
                                            <?php if($isRecent): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <h3 class="font-medium text-gray-800 mb-1"><?php echo htmlspecialchars($announcement['TITLE']); ?></h3>
                                        <p class="text-gray-800"><?php echo htmlspecialchars($announcement['CONTENT']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Confirmation Dialog -->
<div id="confirmation-dialog" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-secondary mb-4">Confirm Logout</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to log out from the admin panel?</p>
        <div class="flex justify-end space-x-4">
            <button id="cancel-logout" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <a href="logout.php" class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-dark transition-colors">
                Logout
            </a>
        </div>
    </div>
</div>

<script>
    // Confirmation dialog for logout
    function confirmLogout(event) {
        event.preventDefault();
        document.getElementById('confirmation-dialog').classList.remove('hidden');
    }
    
    document.getElementById('cancel-logout').addEventListener('click', () => {
        document.getElementById('confirmation-dialog').classList.add('hidden');
    });
    
    // Add real-time announcement updates using Ajax
    document.addEventListener('DOMContentLoaded', function() {
        // Function to periodically check for new announcements
        function checkForNewAnnouncements() {
            // Get the highest announcement ID currently displayed
            const announcements = document.querySelectorAll('#announcements-container > div[data-id]');
            let highestId = 0;
            
            if (announcements.length > 0) {
                announcements.forEach(announcement => {
                    const id = parseInt(announcement.getAttribute('data-id'));
                    if (id > highestId) highestId = id;
                });
            }
            
            // Make an AJAX request to check for new announcements
            fetch('ajax/get_announcements.php?last_id=' + highestId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.announcements && data.announcements.length > 0) {
                        // Handle new announcements
                        updateAnnouncementsDisplay(data.announcements);
                    }
                })
                .catch(error => console.error('Error checking for announcements:', error));
        }
        
        // Update the announcements display
        function updateAnnouncementsDisplay(newAnnouncements) {
            const container = document.getElementById('announcements-container');
            
            // Remove any "no announcements" message
            const emptyMessage = container.querySelector('.text-center');
            if (emptyMessage) {
                container.innerHTML = '';
            }
            
            // Add new announcements at the top
            newAnnouncements.forEach(announcement => {
                const newElement = document.createElement('div');
                newElement.className = 'bg-primary bg-opacity-20 p-4 rounded-lg shadow-sm';
                newElement.setAttribute('data-id', announcement.ANNOUNCE_ID);
                
                const date = new Date(announcement.CREATED_AT);
                const formattedDate = date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
                
                newElement.innerHTML = `
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-sm text-gray-500">
                            ${announcement.username} | 
                            ${formattedDate}
                        </p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">New</span>
                    </div>
                    <h3 class="font-medium text-gray-800 mb-1">${announcement.TITLE}</h3>
                    <p class="text-gray-800">${announcement.CONTENT}</p>
                `;
                
                // Add with fade-in animation
                newElement.style.opacity = '0';
                if (container.firstChild) {
                    container.insertBefore(newElement, container.firstChild);
                } else {
                    container.appendChild(newElement);
                }
                
                // Fade in with faster transition (5ms instead of 10ms)
                setTimeout(() => {
                    newElement.style.transition = 'opacity 0.3s ease';
                    newElement.style.opacity = '1';
                }, 5);
                
                // Change other announcements to white background
                document.querySelectorAll('#announcements-container > div:not(:first-child)').forEach(div => {
                    div.className = 'bg-white p-4 rounded-lg shadow-sm';
                });
            });
        }
        
        // Check for new announcements more frequently (every 15 seconds instead of 60)
        setInterval(checkForNewAnnouncements, 15000);
    });
</script>

<?php include('includes/footer.php'); ?>