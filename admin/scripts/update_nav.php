<?php
// filepath: c:\xampp\htdocs\ccs-sitin-monitoring-system\admin\scripts\update_nav.php
// This script updates navigation menus across all admin pages to include the Lab Resources link

// Function to update sidebar navigation
function updateSidebar($content) {
    $pattern = '/<a href="reservation\.php".*?<\/a>\s*<a href="feedbacks\.php"/s';
    $replacement = '<a href="reservation.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        <span>Reservation</span>
                    </a>
                    <a href="lab_resources.php" class="flex items-center px-4 py-3 text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
                        <i class="fas fa-book-open mr-3"></i>
                        <span>Lab Resources</span>
                    </a>
                    <a href="feedbacks.php"';
    
    return preg_replace($pattern, $replacement, $content);
}

// Function to update mobile menu navigation
function updateMobileMenu($content) {
    $pattern = '/<a href="reservation\.php".*?<hr class="my-2 border-gray-400 border-opacity-20">/s';
    $replacement = '<a href="reservation.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        Reservation
                    </a>
                    <a href="lab_resources.php" class="block px-4 py-2 text-white rounded-lg hover:bg-primary hover:bg-opacity-20">
                        <i class="fas fa-book-open mr-3"></i>
                        Lab Resources
                    </a>
                    <hr class="my-2 border-gray-400 border-opacity-20">';
    
    return preg_replace($pattern, $replacement, $content);
}

// Admin pages to update
$adminPages = [
    'dashboard.php',
    'search.php',
    'students.php',
    'sitin.php',
    'feedbacks.php',
    'leaderboard.php'
    // reservation.php already updated
];

// Update each file
foreach ($adminPages as $file) {
    $filePath = __DIR__ . '/../' . $file;
    
    if (file_exists($filePath)) {
        // Read file
        $content = file_get_contents($filePath);
        
        // Update navigation
        $content = updateSidebar($content);
        $content = updateMobileMenu($content);
        
        // Write back to file
        file_put_contents($filePath, $content);
        echo "Updated $file\n";
    } else {
        echo "File not found: $file\n";
    }
}

echo "Navigation update complete!\n";
