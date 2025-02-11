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

    <!-- Header -->
    <header>
        <!-- Left Side: Logo or Image -->
        <img class="logo" src="your-logo.png" alt="Logo">
        <?php
            // Assuming you have the ID stored in a session or a variable
            session_start();
            if (isset($_SESSION['id'])) {
            echo '<span class="user-id">ID: ' . htmlspecialchars($_SESSION['id']) . '</span>';
            }
        ?>

        <!-- Right Side: Navigation Links -->
        <nav>
            <ul class="nav-links">
                <li><a href="/history">History</a></li>
                <li><a href="/reservation">Reservation</a></li>
            </ul>
        </nav>
            <a class="logout" href="/logout"><button>Logout</button></a>         
    </header>
</body>
</html>