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
            <div class="flex items-center justify-center h-16 px-4 bg-dark text-white">
                <span class="text-xl font-semibold">CCS Admin Panel</span>
            </div>
            <div class="flex flex-col flex-grow px-4 py-4 overflow-y-auto">
                <nav class="flex-1 space-y-2">
                    <a href="dashboard.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-home mr-3"></i>
                        <span>Home</span>
                    </a>
                    <a href="search.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-search mr-3"></i>
                        <span>Search</span>
                    </a>
                    <a href="students.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-user-graduate mr-3"></i>
                        <span>Students</span>
                    </a>
                    <a href="sitin.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-desktop mr-3"></i>
                        <span>Sit-in</span>
                    </a>
                    <a href="reservation.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        <span>Reservation</span>
                    </a>
                    <hr class="my-4 border-gray-400 border-opacity-20">
                    <a href="announcements.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-bullhorn mr-3"></i>
                        <span>Announcements</span>
                    </a>
                    <a href="feedbacks.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-comments mr-3"></i>
                        <span>Feedbacks</span>
                    </a>
                    <a href="leaderboard.php" class="flex items-center px-4 py-3 text-white bg-primary bg-opacity-30 rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-trophy mr-3"></i>
                        <span>Leaderboard</span>
                    </a>
                </nav>
                <div class="mt-auto">
                    <a href="#" onclick="confirmLogout(event)" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 overflow-hidden">
        <!-- Top Navigation -->
        <header class="bg-white shadow-sm">
            <div class="flex items-center justify-between h-16 px-6">
                <!-- Mobile Menu Button -->
                <div class="flex items-center">
                    <button id="mobile-menu-button" class="text-gray-500 md:hidden focus:outline-none">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="ml-4 text-xl font-semibold text-secondary">Student Leaderboard</h1>
                </div>
                
                <!-- Admin Profile -->
                <div class="flex items-center">
                    <span class="mr-4 text-sm font-medium text-gray-700"><?php echo htmlspecialchars($username); ?></span>
                    <button class="flex items-center justify-center w-8 h-8 rounded-full bg-primary text-white">
                        <i class="fas fa-user"></i>
                    </button>
                </div>
            </div>
            
            <!-- Mobile Navigation -->
            <div id="mobile-menu" class="hidden md:hidden px-4 py-2 bg-secondary">
                <nav class="space-y-2">
                    <a href="dashboard.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-home mr-3"></i>
                        Home
                    </a>
                    <a href="search.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-search mr-3"></i>
                        Search
                    </a>
                    <a href="students.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-user-graduate mr-3"></i>
                        Students
                    </a>
                    <a href="sitin.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-desktop mr-3"></i>
                        Sit-in
                    </a>
                    <a href="reservation.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        Reservation
                    </a>
                    <hr class="my-2 border-gray-400 border-opacity-20">
                    <a href="announcements.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-bullhorn mr-3"></i>
                        Announcements
                    </a>
                    <a href="feedbacks.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-comments mr-3"></i>
                        Feedbacks
                    </a>
                    <a href="leaderboard.php" class="block px-4 py-2 text-white rounded-lg bg-primary bg-opacity-30 hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-trophy mr-3"></i>
                        Leaderboard
                    </a>
                    <a href="#" onclick="confirmLogout(event)" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-sign-out-alt mr-3"></i>
                        Logout
                    </a>
                </nav>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-6 bg-gray-50">
            <!-- Flash Messages -->
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'error' ? 'bg-red-100 border border-red-400 text-red-700' : 'bg-green-100 border border-green-400 text-green-700'; ?>" role="alert">
                    <?php echo $messageType === 'error' ? '<i class="fas fa-exclamation-circle mr-2"></i>' : '<i class="fas fa-check-circle mr-2"></i>'; ?>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - Leaderboard -->
                <div class="lg:col-span-2">
                    <!-- Leaderboard Section -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6" style="height: 400px; display: flex; flex-direction: column;">
                        <div class="bg-gradient-to-r from-secondary to-secondary p-4 flex justify-between items-center text-white">
                            <h2 class="text-lg font-semibold flex items-center">
                                <i class="fas fa-trophy mr-2"></i> Student Points Leaderboard
                            </h2>
                        </div>
                        
                        <div class="overflow-y-auto flex-grow">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Available</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sessions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="5" class="px-3 py-4 text-center text-sm text-gray-500">No students with points yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $rank = 1; foreach ($students as $student): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <?php if ($rank <= 3): ?>
                                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full 
                                                            <?php echo $rank == 1 ? 'bg-yellow-100 text-yellow-600' : 
                                                                    ($rank == 2 ? 'bg-gray-100 text-gray-600' : 
                                                                                'bg-orange-100 text-orange-600'); ?>">
                                                            <?php echo $rank; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-500"><?php echo $rank; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="ml-1">
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($student['FNAME'] . ' ' . $student['LNAME']); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                <?php echo htmlspecialchars($student['USER_ID']); ?> • <?php echo htmlspecialchars($student['COURSE']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                        <?php echo $student['POINTS']; ?> pts
                                                    </span>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        <?php echo isset($student['TOTAL_POINTS']) ? $student['TOTAL_POINTS'] : $student['POINTS']; ?> pts
                                                    </span>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500">
                                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                                        +<?php echo $student['rewards_earned']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php $rank++; endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Recent Activities Section -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden" style="height: 300px; display: flex; flex-direction: column;">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-500 p-4 flex justify-between items-center text-white">
                            <h2 class="text-lg font-semibold flex items-center">
                                <i class="fas fa-history mr-2"></i> Recent Point Activities
                            </h2>
                        </div>
                        
                        <div class="p-4 overflow-y-auto flex-grow">
                            <div class="flow-root">
                                <ul class="-my-4 divide-y divide-gray-200">
                                    <?php if (empty($recent_activities)): ?>
                                        <li class="py-4 text-center text-gray-500">No recent point activities.</li>
                                    <?php else: ?>
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <li class="py-4">
                                                <div class="flex items-center space-x-4">
                                                    <div class="flex-shrink-0">
                                                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                                            <i class="fas fa-plus"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            <?php echo htmlspecialchars($activity['student_name']); ?> (<?php echo htmlspecialchars($activity['USER_ID']); ?>)
                                                        </p>
                                                        <p class="text-sm text-gray-500">
                                                            Received <span class="font-medium text-blue-600"><?php echo $activity['POINTS_ADDED']; ?> points</span> from <?php echo htmlspecialchars($activity['admin_name']); ?>
                                                        </p>
                                                    </div>
                                                    <div class="flex-shrink-0 text-sm text-gray-400">
                                                        <?php echo date('M j, g:i A', strtotime($activity['CREATED_AT'])); ?>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Add Points Form -->
                <div>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden" style="height: 400px; display: flex; flex-direction: column;">
                        <div class="bg-gradient-to-r from-green-600 to-green-500 p-4 text-white">
                            <h2 class="text-lg font-semibold flex items-center">
                                <i class="fas fa-plus-circle mr-2"></i> Add Points
                            </h2>
                        </div>
                        
                        <div class="p-6 flex-grow overflow-y-auto">
                            <form method="POST" action="">
                                <div class="mb-4">
                                    <label for="student_input" class="block text-sm font-medium text-gray-700 mb-1">Student Name or ID</label>
                                    <div class="relative">
                                        <input type="text" id="student_input" name="student_input" required 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary"
                                              placeholder="Enter name or ID (e.g., John Doe or 2020-00001)" autocomplete="off">
                                        <div id="student-suggestions" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden mt-1 max-h-60 overflow-auto"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">Type at least 2 characters to search</p>
                                </div>
                                
                                <div class="mb-6">
                                    <label for="points" class="block text-sm font-medium text-gray-700 mb-1">Points to Add</label>
                                    <select id="points" name="points" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary">
                                        <option value="1">1 Point</option>
                                        <option value="2">2 Points</option>
                                        <option value="3">3 Points</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <button type="submit" name="add_points"
                                            class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-md transition-colors flex items-center justify-center">
                                        <i class="fas fa-plus mr-2"></i> Add Points
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Points Info Card -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6" style="height: 300px; display: flex; flex-direction: column;">
                        <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 p-4 text-white">
                            <h2 class="text-lg font-semibold flex items-center">
                                <i class="fas fa-info-circle mr-2"></i> Points System
                            </h2>
                        </div>
                        
                        <div class="p-6 overflow-y-auto flex-grow">
                            <div class="space-y-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-5 w-5 text-indigo-500">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <p class="ml-3 text-sm text-gray-600">
                                        Students can earn points for good behavior, helping others, or exceptional work.
                                    </p>
                                </div>
                                
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-5 w-5 text-indigo-500">
                                        <i class="fas fa-gift"></i>
                                    </div>
                                    <p class="ml-3 text-sm text-gray-600">
                                        <strong>Every 3 points</strong> automatically rewards the student with 1 extra Sit-in session.
                                    </p>
                                </div>
                                
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-5 w-5 text-indigo-500">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <p class="ml-3 text-sm text-gray-600">
                                        Top students on the leaderboard may receive additional privileges and recognition.
                                    </p>
                                </div>
                                
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-5 w-5 text-indigo-500">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <p class="ml-3 text-sm text-gray-600">
                                        Points are accumulated for ranking purposes even after being converted to sessions.
                                    </p>
                                </div>
                                
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 h-5 w-5 text-indigo-500">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <p class="ml-3 text-sm text-gray-600">
                                        Leaderboard rankings are based on total lifetime points earned.
                                    </p>
                                </div>
                            </div>
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
        
        if (query.length < 2) {
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
        }, 300);
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
