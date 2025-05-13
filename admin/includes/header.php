<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'CCS SITIN MONITORING SYSTEM'; ?></title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom Styles -->
    <?php if(isset($customStyles)): ?>
        <?php foreach($customStyles as $style): ?>
            <link rel="stylesheet" href="<?php echo $style; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#94B0DF', // Soft blue
                        secondary: '#356480', // Medium blue-gray
                        dark: '#2c3e50', // Dark blue-gray (tertiary)
                        light: '#FCFDFF', // Very light blue-white
                        success: '#22c55e', // Softer green
                        danger: '#ef4444', // Softer red
                    },
                    fontFamily: {
                        poppins: ['"Poppins"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #94B0DF;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #356480;
        }
        
        /* Hide scrollbar while maintaining scroll functionality */
        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        *::-webkit-scrollbar {
            display: none;
        }
        
        /* Global scrollable container styles */
        .scrollable-content {
            overflow-y: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollable-content::-webkit-scrollbar {
            display: none;
        }

        /* Dropdown menus */
        .dropdown-content {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .dropdown-content::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="<?php echo $bodyClass ?? 'font-poppins bg-light'; ?>">
