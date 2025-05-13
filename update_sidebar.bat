@echo off
echo Updating sidebar and mobile navigation in all admin pages...
echo This will make all admin pages consistent with the new sidebar layout.

REM Run the PowerShell script with elevated permissions
powershell -ExecutionPolicy Bypass -File "update_sidebar.ps1"

echo.
echo Update completed! Press any key to exit.
pause > nul
echo     $oldTitle = '<h1 class="text-white font-bold text-sm mt-1">CCS Sit-In</h1>' >> update_sidebar.ps1
echo     $newTitle = '<h1 class="text-white font-bold text-sm">CCS Sit-In</h1>' >> update_sidebar.ps1
echo     $content = $content -replace [regex]::Escape($oldTitle), $newTitle >> update_sidebar.ps1

echo     # Pattern 4: Update sidebar content padding >> update_sidebar.ps1
echo     $oldPadding = '<div class="flex flex-col flex-grow px-4 py-2 overflow-hidden">' >> update_sidebar.ps1
echo     $newPadding = '<div class="flex flex-col flex-grow px-4 py-1 overflow-hidden">' >> update_sidebar.ps1
echo     $content = $content -replace [regex]::Escape($oldPadding), $newPadding >> update_sidebar.ps1

echo     # Pattern 5: Update nav spacing >> update_sidebar.ps1
echo     $oldNav = 'nav class="flex-1 space-y-2"' >> update_sidebar.ps1
echo     $newNav = 'nav class="flex-1 space-y-1"' >> update_sidebar.ps1
echo     $content = $content -replace $oldNav, $newNav >> update_sidebar.ps1

echo     # Pattern 6: Update menu item styling >> update_sidebar.ps1
echo     $oldItem = 'flex items-center px-4 py-3 text-white' >> update_sidebar.ps1
echo     $newItem = 'flex items-center px-3 py-2.5 text-sm text-white' >> update_sidebar.ps1
echo     $content = $content -replace $oldItem, $newItem >> update_sidebar.ps1

echo     # Pattern 7: Update menu text styling >> update_sidebar.ps1
echo     $oldText = '<span>' >> update_sidebar.ps1
echo     $newText = '<span class="font-medium">' >> update_sidebar.ps1
echo     $content = $content -replace $oldText, $newText >> update_sidebar.ps1

echo     # Pattern 8: Update icon sizing >> update_sidebar.ps1
echo     $oldIcon = 'fa-[a-z-]+ mr-3' >> update_sidebar.ps1
echo     $newIcon = '$0 text-lg' >> update_sidebar.ps1
echo     $content = $content -replace $oldIcon, $newIcon >> update_sidebar.ps1

echo     # Pattern 9: Update logout button section >> update_sidebar.ps1
echo     $oldLogout = '<div class="mt-4">' >> update_sidebar.ps1
echo     $newLogout = '<div class="mt-1 border-t border-gray-700 pt-2">' >> update_sidebar.ps1
echo     $content = $content -replace [regex]::Escape($oldLogout), $newLogout >> update_sidebar.ps1

echo     # Save the updated content >> update_sidebar.ps1
echo     Set-Content -Path $filePath -Value $content >> update_sidebar.ps1
echo     Write-Host "Updated $file" >> update_sidebar.ps1
echo   } else { >> update_sidebar.ps1
echo     Write-Host "File not found: $file" >> update_sidebar.ps1
echo   } >> update_sidebar.ps1
echo } >> update_sidebar.ps1

REM Execute the PowerShell script
powershell -ExecutionPolicy Bypass -File update_sidebar.ps1

REM Clean up
del update_sidebar.ps1

echo Navigation update complete!
