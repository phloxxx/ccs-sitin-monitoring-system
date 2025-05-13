<?php 
session_start();
require_once('../config/db.php');

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$username = $_SESSION['username'];

// Process point addition if form is submitted
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_points'])) {
    // Use student_input instead of student_id to support searching by name or ID
    $student_input = isset($_POST['student_input']) ? trim($_POST['student_input']) : '';
    $points = (int)$_POST['points'];

    // Validate inputs
    if (empty($student_input) || $points <= 0) {
        $message = 'Please provide a valid student name or ID and points.';
        $messageType = 'error';
    } else {
        try {
            // Begin transaction
            $conn->begin_transaction();

            // First try exact match with student ID
            $stmt = $conn->prepare("SELECT IDNO as USER_ID, CONCAT(FIRSTNAME, ' ', LASTNAME) as fullname FROM USERS WHERE IDNO = ?");
            $stmt->bind_param("s", $student_input);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Found by ID - exact match
                $student_data = $result->fetch_assoc();
                $student_id = $student_data['USER_ID'];
                $student_name = $student_data['fullname'];
                $stmt->close();
            } else {
                // Try to find by name (partial match)
                $stmt->close();
                $stmt = $conn->prepare("SELECT IDNO as USER_ID, CONCAT(FIRSTNAME, ' ', LASTNAME) as fullname 
                                       FROM USERS 
                                       WHERE CONCAT(FIRSTNAME, ' ', LASTNAME) LIKE ? OR FIRSTNAME LIKE ? OR LASTNAME LIKE ? 
                                       LIMIT 1");
                $search_pattern = "%" . $student_input . "%";
                $stmt->bind_param("sss", $search_pattern, $search_pattern, $search_pattern);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Found by name
                    $student_data = $result->fetch_assoc();
                    $student_id = $student_data['USER_ID'];
                    $student_name = $student_data['fullname'];
                    $stmt->close();
                } else {
                    $message = 'Student not found. Please verify the name or ID.';
                    $messageType = 'error';
                    $stmt->close();
                    $conn->rollback();
                    // Skip the rest of the code
                    goto end_process;
                }
            }
            
            // Continue with the existing process now that we have a valid student_id
            
            // Check if student has a record in the POINTS table
            $stmt = $conn->prepare("SELECT POINTS, TOTAL_POINTS FROM STUDENT_POINTS WHERE USER_ID = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $row = $result->fetch_assoc();
                $current_points = $row['POINTS'];
                $current_total = isset($row['TOTAL_POINTS']) ? $row['TOTAL_POINTS'] : 0;
                $new_points = $current_points + $points;
                $new_total = $current_total + $points;
                
                $stmt = $conn->prepare("UPDATE STUDENT_POINTS SET POINTS = ?, TOTAL_POINTS = ?, UPDATED_AT = NOW(), ADMIN_ID = ? WHERE USER_ID = ?");
                $stmt->bind_param("iiis", $new_points, $new_total, $admin_id, $student_id);
                $stmt->execute();
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO STUDENT_POINTS (USER_ID, POINTS, TOTAL_POINTS, ADMIN_ID, CREATED_AT, UPDATED_AT) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("siii", $student_id, $points, $points, $admin_id);
                $stmt->execute();
                $new_points = $points;
                $new_total = $points;
            }
            $stmt->close();
            
            // Create a history entry
            $stmt = $conn->prepare("INSERT INTO POINTS_HISTORY (USER_ID, POINTS_ADDED, ADMIN_ID, CREATED_AT) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sii", $student_id, $points, $admin_id);
            $stmt->execute();
            $stmt->close();
            
            $reward_earned = false;
            $reward_sessions = 0;
            
            // Check if student's points are divisible by 3 (any multiple of 3)
            // Calculate how many extra sessions to award
            $sessions_to_award = floor($new_points / 3);
            
            if ($sessions_to_award > 0) {
                // Reduce points by the multiple of 3
                $reduced_points = $new_points % 3; // Keep only the remainder
                
                $stmt = $conn->prepare("UPDATE STUDENT_POINTS SET POINTS = ?, UPDATED_AT = NOW() WHERE USER_ID = ?");
                $stmt->bind_param("is", $reduced_points, $student_id);
                $stmt->execute();
                $stmt->close();
                
                // Get current session count for the student
                $stmt = $conn->prepare("SELECT SESSION FROM USERS WHERE IDNO = ?");
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $student_data = $result->fetch_assoc();
                $current_sessions = $student_data['SESSION'];
                $stmt->close();
                
                // Add sessions to the student's account
                $new_sessions = $current_sessions + $sessions_to_award;
                $stmt = $conn->prepare("UPDATE USERS SET SESSION = ? WHERE IDNO = ?");
                $stmt->bind_param("is", $new_sessions, $student_id);
                $stmt->execute();
                $stmt->close();
                
                // Add records to the REWARDS table for each session awarded
                for ($i = 0; $i < $sessions_to_award; $i++) {
                    $stmt = $conn->prepare("INSERT INTO STUDENT_REWARDS (USER_ID, REWARD_TYPE, ADMIN_ID, CREATED_AT) VALUES (?, 'EXTRA_SESSION', ?, NOW())");
                    $stmt->bind_param("si", $student_id, $admin_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $reward_earned = true;
                $reward_sessions = $sessions_to_award;
                $new_points = $reduced_points;
            }
            
            $conn->commit();
            
            if ($reward_earned) {
                if ($reward_sessions == 1) {
                    $message = "Points added successfully! {$student_name} has earned an extra session as a reward for reaching a multiple of 3 points!";
                } else {
                    $message = "Points added successfully! {$student_name} has earned {$reward_sessions} extra sessions as a reward for reaching a multiple of 3 points!";
                }
                $messageType = 'success';
            } else {
                $message = "Points added successfully to {$student_name}!";
                $messageType = 'success';
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'An error occurred: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    end_process:
    // This label is used for skipping the remaining code when student is not found
}

// Get leaderboard data (top students by points)
$students = [];
try {
    // Check if TOTAL_POINTS column exists, if not, add it
    $columnCheck = $conn->query("SHOW COLUMNS FROM STUDENT_POINTS LIKE 'TOTAL_POINTS'");
    if (!$columnCheck || $columnCheck->num_rows === 0) {
        // Add TOTAL_POINTS column
        $conn->query("ALTER TABLE STUDENT_POINTS ADD COLUMN TOTAL_POINTS INT DEFAULT 0");
        // Copy current POINTS values to TOTAL_POINTS for existing records
        $conn->query("UPDATE STUDENT_POINTS SET TOTAL_POINTS = POINTS");
    }
    
    // Create STUDENT_POINTS table if it doesn't exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS STUDENT_POINTS (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            USER_ID VARCHAR(20) NOT NULL,
            POINTS INT DEFAULT 0,
            TOTAL_POINTS INT DEFAULT 0,
            ADMIN_ID INT NOT NULL,
            CREATED_AT DATETIME NOT NULL,
            UPDATED_AT DATETIME NOT NULL,
            UNIQUE(USER_ID)
        )
    ");
    
    // Create POINTS_HISTORY table if it doesn't exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS POINTS_HISTORY (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            USER_ID VARCHAR(20) NOT NULL,
            POINTS_ADDED INT NOT NULL,
            ADMIN_ID INT NOT NULL,
            CREATED_AT DATETIME NOT NULL
        )
    ");
    
    // Create STUDENT_REWARDS table if it doesn't exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS STUDENT_REWARDS (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            USER_ID VARCHAR(20) NOT NULL,
            REWARD_TYPE VARCHAR(50) NOT NULL,
            REDEEMED BOOLEAN DEFAULT 0,
            ADMIN_ID INT NOT NULL,
            CREATED_AT DATETIME NOT NULL
        )
    ");
    
    // Get top 30 students with the highest points
    $stmt = $conn->prepare("
        SELECT u.IDNO as USER_ID, u.FIRSTNAME as FNAME, u.LASTNAME as LNAME, u.COURSE, u.YEAR as YEAR_LEVEL, 
               sp.POINTS, sp.TOTAL_POINTS,
               (SELECT COUNT(*) FROM STUDENT_REWARDS WHERE USER_ID = u.IDNO AND REWARD_TYPE = 'EXTRA_SESSION') AS rewards_earned
        FROM USERS u
        JOIN STUDENT_POINTS sp ON u.IDNO = sp.USER_ID
        ORDER BY sp.TOTAL_POINTS DESC, u.LASTNAME ASC
        LIMIT 30
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    
} catch (Exception $e) {
    // If there's an error, we'll just show an empty leaderboard
    $error = $e->getMessage();
}

// Get recent point activities
$recent_activities = [];
try {
    $stmt = $conn->prepare("
        SELECT ph.ID, ph.USER_ID, CONCAT(u.FIRSTNAME, ' ', u.LASTNAME) AS student_name, 
               ph.POINTS_ADDED, ph.CREATED_AT, a.username AS admin_name
        FROM POINTS_HISTORY ph
        JOIN USERS u ON ph.USER_ID = u.IDNO
        JOIN ADMIN a ON ph.ADMIN_ID = a.ADMIN_ID
        ORDER BY ph.CREATED_AT DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
    $stmt->close();
    
} catch (Exception $e) {
    // Handle error
}

$pageTitle = "Student Leaderboard";
include('includes/header.php');
?>

<div class="flex h-screen bg-gray-100">
        <!-- Sidebar -->
    <div class="hidden md:flex md:flex-shrink-0">
        <div class="flex flex-col w-64 bg-secondary">
                <!-- Added Logos -->                <div class="flex flex-col items-center pt-5 pb-2">
                    <div class="relative w-16 h-16 mb-1">
                        <!-- UC Logo -->
                        <div class="absolute inset-0 rounded-full bg-white shadow-md overflow-hidden flex items-center justify-center">
                            <img src="../student/images/uc_logo.png" alt="University of Cebu Logo" class="h-13 w-13 object-contain">
                        </div>
                        <!-- CCS Logo (smaller and positioned at the bottom right) -->
                        <div class="absolute bottom-0 right-0 w-9 h-9 rounded-full bg-white shadow-md border-2 border-white overflow-hidden flex items-center justify-center">
                            <img src="../student/images/ccs_logo.png" alt="CCS Logo" class="h-7 w-7 object-contain">
                        </div>
                    </div>
                    <h1 class="text-white font-bold text-sm">CCS Sit-In</h1>
                    <p class="text-gray-300 text-xs">Monitoring System</p>
                </div>                
                <div class="flex flex-col flex-grow px-4 py-3 overflow-hidden">
                    <nav class="flex-1 space-y-1">
                    <a href="dashboard.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-home mr-3 text-lg"></i>
                        <span class="font-medium">Home</span>
                    </a>
                    <a href="search.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-search mr-3 text-lg"></i>
                        <span class="font-medium">Search</span>
                    </a>
                    <a href="students.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-user-graduate mr-3 text-lg"></i>
                        <span class="font-medium">Students</span>
                    </a>
                    <a href="sitin.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3 text-lg"></i>
                        <span class="font-medium">Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                        <span class="font-medium">Reservation</span>
                    </a>
                    <a href="lab_resources.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-book-open mr-3 text-lg"></i>
                        <span class="font-medium">Lab Resources</span>
                    </a>
                    <a href="feedbacks.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-comments mr-3 text-lg"></i>
                        <span class="font-medium">Feedbacks</span>
                    </a>
                    <a href="leaderboard.php" class="flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-trophy mr-3 text-lg"></i>
                        <span class="font-medium">Leaderboard</span>
                    </a>
                </nav>                  
                <div class="mt-1 border-t border-white-700 pt-2">
                    <a href="#" onclick="confirmLogout(event)" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-sign-out-alt mr-3 text-lg"></i>
                        <span class="font-medium">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 overflow-hidden">        
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm sticky top-0 z-10">
            <div class="flex items-center justify-between h-16 px-6">
                <!-- Mobile Menu Button -->
                <div class="flex items-center">
                    <button id="mobile-menu-button" class="text-gray-500 md:hidden focus:outline-none p-1 hover:bg-gray-100 rounded-md transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="ml-4 text-xl font-semibold text-secondary flex items-center">
                        Student Leaderboard
                    </h1>
                </div>
                
                <!-- Admin Profile -->
                <div class="flex items-center">
                    <span class="mr-3 text-sm font-medium text-gray-700 hidden sm:inline-block"><?php echo htmlspecialchars($username); ?></span>
                    <div class="relative group">
                        <button class="flex items-center justify-center w-9 h-9 rounded-full bg-primary text-white hover:bg-primary-dark transition-colors border-2 border-white shadow-sm">
                            <i class="fas fa-user"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden group-hover:block">
                            <div class="px-4 py-2 text-sm text-gray-700 border-b border-gray-100">
                                Signed in as <span class="font-semibold"><?php echo htmlspecialchars($username); ?></span>
                            </div>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile Settings</a>
                            <a href="#" onclick="confirmLogout(event)" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Logout</a>
                        </div>
                    </div>
                </div>
            </div>            
            
            <!-- Mobile Navigation -->
            <div id="mobile-menu" class="hidden md:hidden px-4 py-3 bg-secondary">
                <nav class="space-y-1 overflow-y-auto max-h-[calc(100vh-80px)]">
                    <a href="dashboard.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-home mr-3 text-lg"></i>
                        <span class="font-medium">Home</span>
                    </a>
                    <a href="search.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-search mr-3 text-lg"></i>
                        <span class="font-medium">Search</span>
                    </a>
                    <a href="students.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-user-graduate mr-3 text-lg"></i>
                        <span class="font-medium">Students</span>
                    </a>
                    <a href="sitin.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3 text-lg"></i>
                        <span class="font-medium">Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-calendar-alt mr-3 text-lg"></i>
                        <span class="font-medium">Reservation</span>
                    </a>
                    <a href="lab_resources.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-book-open mr-3 text-lg"></i>
                        <span class="font-medium">Lab Resources</span>
                    </a>
                    <a href="feedbacks.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-comments mr-3 text-lg"></i>
                        <span class="font-medium">Feedbacks</span>
                    </a>
                <a href="leaderboard.php" class="flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-trophy mr-3 text-lg"></i>
                        <span class="font-medium">Leaderboard</span>
                    </a>
                    
                    <div class="border-t border-gray-700 mt-2 pt-2">
                        <a href="#" onclick="confirmLogout(event)" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                            <i class="fas fa-sign-out-alt mr-3 text-lg"></i>
                            <span class="font-medium">Logout</span>
                        </a>
                    </div>
                </nav>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-8 bg-gray-50 hide-scrollbar">
    <!-- Flash Messages -->
    <?php if (!empty($message)): ?>
        <div class="mb-8 p-4 rounded-lg <?php echo $messageType === 'error' ? 'bg-red-100 border border-red-400 text-red-700' : 'bg-green-100 border border-green-400 text-green-700'; ?>" role="alert">
            <?php echo $messageType === 'error' ? '<i class="fas fa-exclamation-circle mr-2"></i>' : '<i class="fas fa-check-circle mr-2"></i>'; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <style>
        /* Hide scrollbar while maintaining scroll functionality */
        .hide-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;     /* Firefox */
        }
        .hide-scrollbar::-webkit-scrollbar {
            display: none;             /* Chrome, Safari and Opera */
        }

        /* Apply to all scrollable containers */
        .scroll-container {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scroll-container::-webkit-scrollbar {
            display: none;
        }
        
        /* Make content areas scrollable with hidden scrollbars */
        .scrollable-content {
            overflow-y: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollable-content::-webkit-scrollbar {
            display: none;
        }

        /* For student suggestions dropdown */
        #student-suggestions {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        #student-suggestions::-webkit-scrollbar {
            display: none;
        }
    </style>

    <div class="max-w-7xl mx-auto space-y-8">
        <!-- Top Rankings Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Most Sessions Used -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="bg-gradient-to-r from-violet-600 to-violet-500 p-6">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-clock mr-3"></i> Most Active Students
                    </h2>
                    <p class="text-violet-100 text-sm mt-1">Based on completed sessions</p>
                </div>
                <div class="p-6">
                    <?php 
                        $query = "SELECT u.IDNO, u.FIRSTNAME as FNAME, u.LASTNAME as LNAME, u.COURSE, 
                                 COUNT(s.SITIN_ID) as sessions_used,
                                 MAX(s.SESSION_END) as last_session
                                 FROM USERS u
                                 LEFT JOIN SITIN s ON u.IDNO = s.IDNO
                                 WHERE s.STATUS = 'COMPLETED'
                                 GROUP BY u.IDNO, u.FIRSTNAME, u.LASTNAME, u.COURSE
                                 ORDER BY sessions_used DESC
                                 LIMIT 5";
                        $result = $conn->query($query);
                        $index = 0;
                        while ($student = $result->fetch_assoc()):
                        ?>
                            <div class="flex items-center justify-between p-4 <?php echo $index === 0 ? 'bg-violet-50 border border-violet-100' : ''; ?> rounded-lg mb-4 last:mb-0 transition duration-150 hover:bg-gray-50">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-full flex items-center justify-center <?php echo $index === 0 ? 'bg-violet-100 text-violet-600' : 'bg-gray-100 text-gray-600'; ?>">
                                            <span class="text-xl font-bold">#<?php echo $index + 1; ?></span>
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($student['FNAME'] . ' ' . $student['LNAME']); ?></h3>
                                        <div class="flex items-center space-x-3 mt-1">
                                            <span class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($student['COURSE']); ?></span>
                                            <span class="text-sm text-gray-400">•</span>
                                            <span class="text-sm text-gray-500">Last session: <?php echo date('M j', strtotime($student['last_session'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-2xl font-bold <?php echo $index === 0 ? 'text-violet-600' : 'text-gray-700'; ?>"><?php echo $student['sessions_used']; ?></div>
                                    <div class="text-sm text-gray-500">sessions</div>
                                </div>
                            </div>
                        <?php 
                            $index++;
                        endwhile; 
                        // Fill empty slots if less than 5 students
                        while ($index < 5):
                        ?>
                            <div class="flex items-center justify-between p-4 rounded-lg mb-4 last:mb-0 bg-gray-50">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-gray-100 text-gray-400">
                                            <span class="text-xl font-bold">#<?php echo $index + 1; ?></span>
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-semibold text-gray-400 truncate">No student yet</h3>
                                        <div class="flex items-center space-x-3 mt-1">
                                            <span class="text-sm text-gray-400">-</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-2xl font-bold text-gray-400">0</div>
                                    <div class="text-sm text-gray-400">sessions</div>
                                </div>
                            </div>
                        <?php 
                            $index++;
                        endwhile;
                        ?>
                </div>
            </div>

            <!-- Most Points Earned -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="bg-gradient-to-r from-blue-600 to-blue-500 p-6">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-trophy mr-3"></i> Top Point Earners
                    </h2>
                    <p class="text-blue-100 text-sm mt-1">Based on total accumulated points</p>
                </div>
                <div class="p-6">
                    <?php 
                    usort($students, function($a, $b) {
                        return $b['TOTAL_POINTS'] - $a['TOTAL_POINTS'];
                    });
                    $topPoints = array_slice($students, 0, 5);
                    $index = 0;
                    foreach ($topPoints as $student): 
                    ?>
                        <div class="flex items-center justify-between p-4 <?php echo $index === 0 ? 'bg-blue-50 border border-blue-100' : ''; ?> rounded-lg mb-4 last:mb-0 transition duration-150 hover:bg-gray-50">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center <?php echo $index === 0 ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-600'; ?>">
                                        <span class="text-xl font-bold">#<?php echo $index + 1; ?></span>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($student['FNAME'] . ' ' . $student['LNAME']); ?></h3>
                                    <div class="flex items-center space-x-3 mt-1">
                                        <span class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($student['COURSE']); ?></span>
                                        <span class="text-sm text-gray-400">•</span>
                                        <span class="text-sm text-gray-500">Rewards: <?php echo $student['rewards_earned']; ?> sessions</span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-2xl font-bold <?php echo $index === 0 ? 'text-blue-600' : 'text-gray-700'; ?>"><?php echo $student['TOTAL_POINTS']; ?></div>
                                <div class="text-sm text-gray-500">points</div>
                            </div>
                        </div>
                    <?php 
                        $index++;
                    endforeach;
                    // Fill empty slots if less than 5 students
                    while ($index < 5):
                    ?>
                        <div class="flex items-center justify-between p-4 rounded-lg mb-4 last:mb-0 bg-gray-50">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-gray-100 text-gray-400">
                                        <span class="text-xl font-bold">#<?php echo $index + 1; ?></span>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold text-gray-400 truncate">No student yet</h3>
                                    <div class="flex items-center space-x-3 mt-1">
                                        <span class="text-sm text-gray-400">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-2xl font-bold text-gray-400">0</div>
                                <div class="text-sm text-gray-400">points</div>
                            </div>
                        </div>
                    <?php 
                        $index++;
                    endwhile;
                    ?>
                </div>
            </div>
        </div>

        <!-- Recent Activities and Add Points Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Recent Activities -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100" style="height: 600px;">
                <div class="bg-gradient-to-r from-gray-800 to-gray-700 p-6">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-history mr-3"></i> Recent Activities
                    </h2>
                    <p class="text-gray-300 text-sm mt-1">Latest point awards and achievements</p>
                </div>
                <div class="p-6 scrollable-content" style="height: calc(100% - 116px);">
                    <?php if (empty($recent_activities)): ?>
                            <div class="text-center text-gray-500 py-8">No recent activities to display</div>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="flex items-start space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                            <i class="fas fa-plus"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars($activity['student_name']); ?>
                                                </p>
                                                <p class="text-sm text-gray-500 mt-1">
                                                    Received <span class="font-medium text-blue-600"><?php echo $activity['POINTS_ADDED']; ?> points</span> from <?php echo htmlspecialchars($activity['admin_name']); ?>
                                                </p>
                                            </div>
                                            <span class="text-sm text-gray-400">
                                                <?php echo date('M j, g:i A', strtotime($activity['CREATED_AT'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                </div>
            </div>

            <!-- Add Points Form -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="bg-gradient-to-r from-green-600 to-green-500 p-6">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-plus-circle mr-3"></i> Add Points
                    </h2>
                    <p class="text-green-100 text-sm mt-1">Award points to students</p>
                </div>
                <div class="p-6">
                    <form method="POST" action="" class="space-y-6">
                        <div>
                            <label for="student_input" class="block text-sm font-medium text-gray-700 mb-2">Student Name or ID</label>
                            <div class="relative">
                                <input type="text" id="student_input" name="student_input" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                    placeholder="Search by name or ID number">
                                <div id="student-suggestions" class="absolute z-10 w-full bg-white border border-gray-300 rounded-lg shadow-lg hidden mt-1 max-h-60 overflow-auto"></div>
                            </div>
                        </div>

                        <div>
                            <label for="points" class="block text-sm font-medium text-gray-700 mb-2">Points to Award</label>
                            <select id="points" name="points" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="1">1 Point - Good Behavior</option>
                                <option value="2">2 Points - Helpful to Others</option>
                                <option value="3">3 Points - Outstanding Achievement</option>
                            </select>
                        </div>

                        <button type="submit" name="add_points"
                            class="w-full bg-green-500 hover:bg-green-600 text-white py-3 px-4 rounded-lg transition duration-150 flex items-center justify-center font-medium">
                            <i class="fas fa-plus mr-2"></i> Award Points
                        </button>

                        <!-- Points Info -->
                        <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Points System Info</h3>
                            <ul class="space-y-2 text-sm text-gray-600">
                                <li class="flex items-start">
                                    <i class="fas fa-star text-yellow-400 mt-1 mr-2"></i>
                                    <span>Every 3 points = 1 extra session</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-trophy text-blue-400 mt-1 mr-2"></i>
                                    <span>Points accumulate for rankings</span>
                                </li>
                            </ul>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

    </div>
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
    // Mobile menu toggle
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    mobileMenuButton.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });
    
    // Confirmation dialog for logout
    function confirmLogout(event) {
        event.preventDefault();
        document.getElementById('confirmation-dialog').classList.remove('hidden');
    }
    
    document.getElementById('cancel-logout').addEventListener('click', () => {
        document.getElementById('confirmation-dialog').classList.add('hidden');
    });
    
    // Close dialog when clicking outside
    document.getElementById('confirmation-dialog').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
    
    // Student search autocomplete
    const studentInput = document.getElementById('student_input');
    const suggestionsList = document.getElementById('student-suggestions');
    
    let debounceTimer;
    let selectedStudent = null;
    
    studentInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        selectedStudent = null;
        
        if (query.length < 1) {
            suggestionsList.innerHTML = '';
            suggestionsList.classList.add('hidden');
            return;
        }
        
        debounceTimer = setTimeout(() => {
            // Show loading indicator
            suggestionsList.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';
            suggestionsList.classList.remove('hidden');
            
            fetch(`ajax/search_suggestions.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    suggestionsList.innerHTML = '';
                    
                    if (data.success && data.suggestions.length > 0) {
                        data.suggestions.forEach(student => {
                            const item = document.createElement('div');
                            item.classList.add('px-4', 'py-3', 'cursor-pointer', 'hover:bg-gray-100', 'border-b', 'border-gray-100');
                            
                            const nameSpan = document.createElement('div');
                            nameSpan.classList.add('font-medium', 'text-base');
                            nameSpan.textContent = student.name;
                            
                            const detailsDiv = document.createElement('div');
                            detailsDiv.classList.add('flex', 'flex-wrap', 'justify-between', 'items-center', 'mt-2', 'gap-2');
                            
                            const idSpan = document.createElement('span');
                            idSpan.classList.add('text-gray-500', 'text-sm');
                            idSpan.textContent = student.id;
                            
                            detailsDiv.appendChild(idSpan);
                            
                            // Add course info
                            const courseSpan = document.createElement('span');
                            courseSpan.classList.add('text-gray-500', 'text-sm');
                            courseSpan.textContent = student.course + ' ' + student.year;
                            detailsDiv.appendChild(courseSpan);
                            
                            if (student.points > 0) {
                                const pointsSpan = document.createElement('span');
                                pointsSpan.classList.add('px-2', 'py-0.5', 'bg-blue-100', 'text-blue-800', 'rounded-full', 'text-sm');
                                pointsSpan.textContent = student.points + ' pts';
                                detailsDiv.appendChild(pointsSpan);
                            }
                            
                            item.appendChild(nameSpan);
                            item.appendChild(detailsDiv);
                            
                            item.addEventListener('click', () => {
                                studentInput.value = student.name;
                                selectedStudent = student;
                                suggestionsList.classList.add('hidden');
                            });
                            
                            suggestionsList.appendChild(item);
                        });
                        
                        suggestionsList.classList.remove('hidden');
                    } else {
                        suggestionsList.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500">No students found</div>';
                        suggestionsList.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error fetching suggestions:', error);
                    suggestionsList.innerHTML = '<div class="px-4 py-3 text-sm text-red-500">Error searching students</div>';
                });
        }, 200); // Reduced debounce time for faster response
    });
    
    // Trigger search on focus if there's already content in the input
    studentInput.addEventListener('focus', function() {
        if (this.value.trim().length > 0) {
            // Trigger the input event to show suggestions immediately
            this.dispatchEvent(new Event('input'));
        }
    });
    
    // Handle form submission
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        // If we have a selected student, use their ID
        if (selectedStudent) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'student_id';
            hiddenInput.value = selectedStudent.id;
            this.appendChild(hiddenInput);
        } else {
            // Prevent form submission if no student was selected from dropdown
            event.preventDefault();
            alert("Please select a student from the dropdown suggestions.");
            return false;
        }
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (e.target !== studentInput && e.target !== suggestionsList) {
            suggestionsList.classList.add('hidden');
        }
    });
</script>

<?php include('includes/footer.php'); ?>
