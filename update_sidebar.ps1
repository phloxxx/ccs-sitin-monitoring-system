# PowerShell script to update sidebar and mobile navigation in all admin PHP files
$adminDir = "c:\xampp\htdocs\ccs-sitin-monitoring-system\admin"
$excludeFiles = @("login.php", "logout.php")
$files = Get-ChildItem -Path "$adminDir\*.php" | Where-Object { $excludeFiles -notcontains $_.Name }
$logFile = "c:\xampp\htdocs\ccs-sitin-monitoring-system\sidebar_update.log"

"Starting sidebar and mobile navigation updates at $(Get-Date)" | Out-File -FilePath $logFile

# The sidebar HTML content
$sidebarContent = @'
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
                <div class="flex flex-col flex-grow px-4 py-3    overflow-hidden">
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
                    <a href="leaderboard.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
'@

# The mobile navigation HTML content
$mobileNavContent = @'
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
                    <a href="leaderboard.php" class="flex items-center px-3 py-2.5 text-sm text-white rounded-lg hover:bg-primary hover:bg-opacity-20 transition-colors">
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
'@

# Process each file
foreach ($file in $files) {
    $filePath = $file.FullName
    $fileName = $file.Name
    "Processing $fileName..." | Out-File -FilePath $logFile -Append
    
    # Read file content
    $content = Get-Content -Path $filePath -Raw
      # Skip files that don't have a sidebar section
    if (-not ($content -match "<!-- Sidebar -->")) {
        Write-Output ("Skipping " + $fileName + ": No sidebar section found") | Out-File -FilePath $logFile -Append
        continue
    }
    
    # Update the active state based on current file
    $updatedSidebar = $sidebarContent
    $updatedMobileNav = $mobileNavContent
    
    # Set active state for current page in sidebar
    $currentPage = $file.Name.ToLower()
    $pageName = $currentPage -replace ".php", ""
    
    # Highlight the current page in both sidebar and mobile navigation
    $updatedSidebar = $updatedSidebar -replace "href=`"$currentPage`" class=`"flex items-center px-3 py-2.5 text-sm text-white rounded-lg", "href=`"$currentPage`" class=`"flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg"
    $updatedMobileNav = $updatedMobileNav -replace "href=`"$currentPage`" class=`"flex items-center px-3 py-2.5 text-sm text-white rounded-lg", "href=`"$currentPage`" class=`"flex items-center px-3 py-2.5 text-sm text-white bg-primary bg-opacity-30 rounded-lg"
    
    # Create pattern to match and replace sidebar content
    $sidebarPattern = '<!-- Sidebar -->\s*<div class="hidden md:flex md:flex-shrink-0">.*?</div>\s*</div>\s*</div>'
    $content = $content -replace $sidebarPattern, $updatedSidebar
    
    # Create pattern to match and replace mobile navigation content
    $mobileNavPattern = '<!-- Mobile Navigation -->\s*<div id="mobile-menu".*?</div>\s*</nav>\s*</div>'
    $content = $content -replace $mobileNavPattern, $updatedMobileNav
    
    # Save the updated content
    Set-Content -Path $filePath -Value $content
    "Updated $fileName successfully" | Out-File -FilePath $logFile -Append
}

"Completed updates at $(Get-Date)" | Out-File -FilePath $logFile -Append
"Script completed! Check $logFile for details."
