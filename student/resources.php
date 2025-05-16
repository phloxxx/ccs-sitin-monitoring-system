<?php 
session_start();
require_once('../config/db.php'); 

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; 

// Fetch user details from database
$stmt = $conn->prepare("SELECT USERNAME, PROFILE_PIC FROM USERS WHERE USER_ID = ?");
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
// Set up profile picture path handling
$default_pic = "images/snoopy.jpg";
$profile_pic = !empty($user['PROFILE_PIC']) ? $user['PROFILE_PIC'] : $default_pic;

// Fetch laboratories for dropdown
$laboratories = [];
try {
    $stmt = $conn->prepare("SELECT * FROM LABORATORY ORDER BY LAB_NAME");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $laboratories[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // If error, use default values
    $laboratories = [];
}

// Fetch lab resources from database
$resources = [];
try {
    // First check if the table exists
    $tableExistsQuery = "SHOW TABLES LIKE 'LAB_RESOURCES'";
    $tableResult = $conn->query($tableExistsQuery);
    
    if ($tableResult->num_rows > 0) {
        // Table exists, fetch resources
        $stmt = $conn->prepare("SELECT r.*, l.LAB_NAME 
                               FROM LAB_RESOURCES r 
                               LEFT JOIN LABORATORY l ON r.LAB_ID = l.LAB_ID 
                               ORDER BY r.RESOURCE_NAME");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $resources[] = $row;
        }
        $stmt->close();
    } else {
        // Table doesn't exist yet
        $resources = [];
    }
} catch (Exception $e) {
    // If error, use empty array
    $resources = [];
}

$pageTitle = "Laboratory Resources";
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
                        <li><a href="dashboard.php" class="text-white hover:text-primary transition">Home</a></li>
                        <li><a href="notification.php" class="text-white hover:text-primary transition">Notification</a></li>
                        <li><a href="history.php" class="text-white hover:text-primary transition">History</a></li>
                        <li><a href="reservation.php" class="text-white hover:text-primary transition">Reservation</a></li>
                        <li><a href="resources.php" class="text-white hover:text-primary transition font-semibold border-b-2 border-primary pb-1">Resources</a></li>
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
            <h1 class="text-3xl font-bold text-secondary mb-6">Laboratory Resources</h1>
            
            <div class="grid grid-cols-1 gap-8">
                <!-- Weekly Schedule Overview -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-500 text-white p-4">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-calendar-week mr-3"></i> Weekly Lab Schedule
                        </h3>
                    </div>
                    
                    <div class="p-5">
                        <!-- Laboratory selection for schedules -->
                        <div class="mb-5">
                            <label for="view-schedule-lab" class="block text-sm font-medium text-gray-700 mb-2">Select Laboratory Schedule</label>
                            <select id="view-schedule-lab" class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="all">All Laboratories (General)</option>
                                <?php foreach ($laboratories as $lab): ?>
                                    <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Current schedule display -->
                        <div id="schedule-display" class="text-center">
                            <div id="schedule-loading" class="py-8 hidden">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                                <p class="mt-3 text-gray-600">Loading schedule...</p>
                            </div>
                            
                            <div id="schedule-content">
                                <?php
                                // Check if schedule image exists for general (all labs)
                                $schedulePath = "../uploads/lab_schedules/all.jpg";
                                $schedulePathPng = "../uploads/lab_schedules/all.png";
                                $scheduleExists = file_exists($schedulePath) || file_exists($schedulePathPng);
                                $actualPath = file_exists($schedulePath) ? $schedulePath : (file_exists($schedulePathPng) ? $schedulePathPng : "");
                                $lastUpdated = $scheduleExists ? date("F d, Y", filemtime($actualPath)) : "";
                                ?>

                                <?php if ($scheduleExists): ?>
                                    <div class="mb-3 flex items-center justify-between">
                                        <h4 class="font-medium text-gray-800">Laboratory Schedule</h4>
                                        <div class="text-sm text-gray-500">Last updated: <?php echo $lastUpdated; ?></div>
                                    </div>
                                    <div class="border rounded-lg overflow-hidden shadow-sm">
                                        <img src="<?php echo $actualPath; ?>?v=<?php echo time(); ?>" alt="Laboratory Schedule" class="max-w-full h-auto">
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <a href="<?php echo $actualPath; ?>" download="lab_schedule.<?php echo pathinfo($actualPath, PATHINFO_EXTENSION); ?>" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors inline-flex items-center shadow-sm">
                                            <i class="fas fa-download mr-2"></i> Download Schedule
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="py-12 flex flex-col items-center">
                                        <div class="bg-blue-100 p-6 rounded-full mb-4">
                                            <i class="fas fa-calendar-alt text-4xl text-blue-500"></i>
                                        </div>
                                        <h4 class="text-xl font-medium text-secondary mb-2">No Schedule Available</h4>
                                        <p class="text-base text-gray-500 max-w-sm mx-auto">No laboratory schedule has been uploaded yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Lab Resources Panel -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-purple-500 text-white p-4">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fas fa-book-open mr-3"></i> Lab Resources & Materials
                        </h3>
                    </div>
                    
                    <div class="p-5">
                        <!-- Resources search and filter -->
                        <div class="mb-4">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-3">
                                <h4 class="font-medium text-gray-800">Available Resources</h4>
                                <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                                    <div class="relative w-full md:w-64">
                                        <input type="text" id="resource-search" placeholder="Search resources..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-sm w-full">
                                        <div class="absolute left-0 inset-y-0 flex items-center pl-3">
                                            <i class="fas fa-search text-gray-400"></i>
                                        </div>
                                    </div>
                                    <select id="filter-lab" class="w-full md:w-48 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-purple-500 focus:border-purple-500 text-sm">
                                        <option value="all">All Labs</option>
                                        <?php foreach ($laboratories as $lab): ?>
                                            <option value="<?php echo $lab['LAB_ID']; ?>"><?php echo htmlspecialchars($lab['LAB_NAME']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Filter tabs -->
                            <div class="flex mb-4 border-b border-gray-200 overflow-x-auto py-1 scrollbar-thin">
                                <button data-filter="all" class="resource-type-filter px-4 py-2 border-b-2 border-purple-500 text-purple-600 font-medium text-sm">All Resources</button>
                                <button data-filter="document" class="resource-type-filter px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Documents</button>
                                <button data-filter="software" class="resource-type-filter px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Software</button>
                                <button data-filter="hardware" class="resource-type-filter px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Hardware</button>
                                <button data-filter="reference" class="resource-type-filter px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">References</button>
                                <button data-filter="other" class="resource-type-filter px-4 py-2 text-gray-600 hover:text-purple-600 font-medium text-sm">Other</button>
                            </div>
                            
                            <!-- Resource cards container -->
                            <div id="resource-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php if (count($resources) > 0): ?>
                                    <?php foreach ($resources as $resource): ?>
                                        <div class="resource-card border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow"
                                             data-type="<?php echo htmlspecialchars($resource['RESOURCE_TYPE'] ?? 'other'); ?>"
                                             data-lab="<?php echo $resource['LAB_ID'] ?? 'all'; ?>">
                                            <div class="p-4">
                                                <div class="flex justify-between items-start mb-3">
                                                    <h5 class="font-medium text-gray-900"><?php echo htmlspecialchars($resource['RESOURCE_NAME'] ?? 'Untitled Resource'); ?></h5>
                                                    <span class="<?php echo getTypeClass($resource['RESOURCE_TYPE'] ?? 'other'); ?>"><?php echo ucfirst(htmlspecialchars($resource['RESOURCE_TYPE'] ?? 'Other')); ?></span>
                                                </div>
                                                <p class="text-sm text-gray-600 mb-4"><?php echo htmlspecialchars($resource['DESCRIPTION'] ?? 'No description available.'); ?></p>
                                                <div class="text-xs text-gray-500 mb-3">Lab: <?php echo $resource['LAB_ID'] === 'all' ? 'All Laboratories' : htmlspecialchars($resource['LAB_NAME'] ?? 'Unknown'); ?></div>
                                                
                                                <!-- File & Link Actions -->
                                                <div class="flex flex-col gap-2 mt-4">
                                                    <?php if (!empty($resource['FILE_PATH'])): ?>
                                                        <?php 
                                                            $filePath = $resource['FILE_PATH'];
                                                            $fileName = basename($filePath);
                                                            $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                                                            $fileIcon = getFileIcon($fileExt);
                                                        ?>
                                                        <a href="ajax/download_resource.php?id=<?php echo $resource['RESOURCE_ID']; ?>" 
                                                           class="text-indigo-600 hover:text-indigo-800 text-sm flex items-center py-1 px-2 bg-indigo-50 rounded-md hover:bg-indigo-100 transition-colors">
                                                            <i class="<?php echo $fileIcon; ?> mr-2"></i> 
                                                            Download Attachment
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($resource['RESOURCE_LINK'])): ?>
                                                        <?php if (filter_var($resource['RESOURCE_LINK'], FILTER_VALIDATE_URL)): ?>
                                                            <a href="<?php echo htmlspecialchars($resource['RESOURCE_LINK']); ?>" target="_blank" 
                                                               class="text-purple-600 hover:text-purple-800 text-sm flex items-center py-1 px-2 bg-purple-50 rounded-md hover:bg-purple-100 transition-colors">
                                                                <i class="fas fa-external-link-alt mr-2"></i> 
                                                                Open Resource
                                                            </a>
                                                        <?php else: ?>
                                                            <div class="text-gray-600 text-sm flex items-center py-1 px-2 bg-gray-50 rounded-md">
                                                                <i class="fas fa-map-marker-alt mr-2"></i> 
                                                                Location: <?php echo htmlspecialchars($resource['RESOURCE_LINK']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Empty state -->
                                    <div id="empty-resources" class="col-span-3 text-center py-10">
                                        <div class="bg-purple-100 inline-block p-6 rounded-full mb-4">
                                            <i class="fas fa-book-open text-4xl text-purple-500"></i>
                                        </div>
                                        <h4 class="text-xl font-medium text-secondary mb-2">No Resources Available</h4>
                                        <p class="text-base text-gray-500 max-w-md mx-auto">No laboratory resources have been added yet. Please check back later.</p>
                                    </div>
                                <?php endif; ?>

                                <!-- No results found message (hidden by default) -->
                                <div id="no-results" class="col-span-3 text-center py-10 hidden">
                                    <div class="bg-gray-100 inline-block p-6 rounded-full mb-4">
                                        <i class="fas fa-search text-4xl text-gray-500"></i>
                                    </div>
                                    <h4 class="text-xl font-medium text-secondary mb-2">No Matching Resources</h4>
                                    <p class="text-base text-gray-500 max-w-sm mx-auto">Try adjusting your search or filters to find what you're looking for.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Confirmation Dialog for Logout -->
<div id="confirmation-dialog" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-secondary mb-4">Confirm Logout</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to log out?</p>
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

<?php
// Helper function to get CSS class for resource type badge
function getTypeClass($type) {
    switch ($type) {
        case 'document':
            return 'bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-md';
        case 'software':
            return 'bg-green-100 text-green-800 text-xs px-2 py-1 rounded-md';
        case 'hardware':
            return 'bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-md';
        case 'reference':
            return 'bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-md';
        default:
            return 'bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-md';
    }
}

// Helper function to get Font Awesome icon based on file extension
function getFileIcon($ext) {
    switch (strtolower($ext)) {
        case 'pdf':
            return 'fas fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fas fa-file-word';
        case 'xls':
        case 'xlsx':
            return 'fas fa-file-excel';
        case 'ppt':
        case 'pptx':
            return 'fas fa-file-powerpoint';
        case 'zip':
        case 'rar':
        case '7z':
            return 'fas fa-file-archive';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return 'fas fa-file-image';
        case 'mp4':
        case 'avi':
        case 'mov':
            return 'fas fa-file-video';
        case 'mp3':
        case 'wav':
        case 'ogg':
            return 'fas fa-file-audio';
        default:
            return 'fas fa-file';
    }
}
?>

<script>
    // Confirmation dialog for logout
    function confirmLogout(event) {
        event.preventDefault();
        document.getElementById('confirmation-dialog').classList.remove('hidden');
    }
    
    document.getElementById('cancel-logout').addEventListener('click', () => {
        document.getElementById('confirmation-dialog').classList.add('hidden');
    });
    
    document.getElementById('confirmation-dialog').addEventListener('click', function(event) {
        if (event.target === this) {
            this.classList.add('hidden');
        }
    });
    
    // Lab Schedule Selection Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const viewScheduleLab = document.getElementById('view-schedule-lab');
        const scheduleLoading = document.getElementById('schedule-loading');
        const scheduleContent = document.getElementById('schedule-content');
        
        // Lab schedule selection change event
        if (viewScheduleLab) {
            viewScheduleLab.addEventListener('change', function() {
                const labId = this.value;
                scheduleContent.classList.add('hidden');
                scheduleLoading.classList.remove('hidden');
                
                // AJAX request to get schedule for selected lab
                fetch(`ajax/get_schedule.php?lab_id=${labId}&t=${new Date().getTime()}`)
                .then(response => response.json())
                .then(data => {
                    scheduleLoading.classList.add('hidden');
                    scheduleContent.classList.remove('hidden');
                    
                    if (data.success) {
                        scheduleContent.innerHTML = data.html;
                    } else {
                        scheduleContent.innerHTML = `
                            <div class="py-12 flex flex-col items-center">
                                <div class="bg-blue-100 p-6 rounded-full mb-4">
                                    <i class="fas fa-calendar-alt text-4xl text-blue-500"></i>
                                </div>
                                <h4 class="text-xl font-medium text-secondary mb-2">No Schedule Available</h4>
                                <p class="text-base text-gray-500 max-w-sm mx-auto">${data.message || 'No schedule has been uploaded for this laboratory yet.'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    scheduleLoading.classList.add('hidden');
                    scheduleContent.classList.remove('hidden');
                    scheduleContent.innerHTML = `
                        <div class="py-8 text-center">
                            <div class="bg-red-100 p-5 rounded-lg inline-block mb-4">
                                <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
                            </div>
                            <h4 class="text-xl font-medium text-red-600 mb-2">Error Loading Schedule</h4>
                            <p class="text-base text-gray-500">There was a problem loading the schedule. Please try again later.</p>
                        </div>
                    `;
                });
            });
        }
        
        // Lab Resources Filtering Functionality
        const resourceSearch = document.getElementById('resource-search');
        const filterLab = document.getElementById('filter-lab');
        const resourceTypeFilters = document.querySelectorAll('.resource-type-filter');
        const resourceCards = document.querySelectorAll('.resource-card');
        const noResults = document.getElementById('no-results');
        const emptyResources = document.getElementById('empty-resources');
        
        let currentTypeFilter = 'all';
        
        function filterResources() {
            const searchTerm = resourceSearch.value.toLowerCase();
            const selectedLab = filterLab.value;
            let visibleCount = 0;
            
            resourceCards.forEach(card => {
                const type = card.getAttribute('data-type');
                const lab = card.getAttribute('data-lab');
                const content = card.textContent.toLowerCase();
                
                const typeMatch = currentTypeFilter === 'all' || type === currentTypeFilter;
                const labMatch = selectedLab === 'all' || lab === selectedLab;
                const searchMatch = !searchTerm || content.includes(searchTerm);
                
                if (typeMatch && labMatch && searchMatch) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });
            
            // Show or hide "no results" message
            if (visibleCount === 0 && resourceCards.length > 0) {
                noResults.classList.remove('hidden');
                if (emptyResources) emptyResources.classList.add('hidden');
            } else {
                noResults.classList.add('hidden');
                if (emptyResources && resourceCards.length === 0) {
                    emptyResources.classList.remove('hidden');
                } else if (emptyResources) {
                    emptyResources.classList.add('hidden');
                }
            }
        }
        
        // Search input event
        if (resourceSearch) {
            resourceSearch.addEventListener('input', filterResources);
        }
        
        // Lab filter change event
        if (filterLab) {
            filterLab.addEventListener('change', filterResources);
        }
        
        // Resource type filter buttons
        resourceTypeFilters.forEach(button => {
            button.addEventListener('click', function() {
                const filterType = this.getAttribute('data-filter');
                currentTypeFilter = filterType;
                
                // Update active filter UI
                resourceTypeFilters.forEach(btn => {
                    btn.classList.remove('border-purple-500', 'text-purple-600');
                    btn.classList.add('text-gray-600');
                });
                
                this.classList.remove('text-gray-600');
                this.classList.add('border-b-2', 'border-purple-500', 'text-purple-600');
                
                filterResources();
            });
        });
    });
</script>

<style>
    /* Custom scrollbar styles */
    .scrollbar-thin::-webkit-scrollbar {
        height: 4px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: #c5c5c5;
        border-radius: 10px;
    }
</style>

<?php include('includes/footer.php'); ?>
