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
        <div class="logo">
            <img src="images/ccs_logo.png" alt="Logo">
        </div>


        <!-- Right Side: Navigation Links -->
        <nav>
            <ul class="nav-links">
                <li><a href="/dashboard.php" style="color: #FFF2AF;">Home</a></li>
                <li><a href="/notification">Notification</a></li>
                <li><a href="/history">History</a></li>
                <li><a href="/reservation">Reservation</a></li>
            </ul>
        </nav>
            <a class="logout" href="#" onclick="confirmLogout(event)"><button>Logout</button></a>         
    </header>

    <!-- Main Content -->
     <main>
        <div class="card">
            
    </main>
    <!-- ...existing code... -->

    <script>
        function confirmLogout(event) {
            event.preventDefault();
            var userConfirmed = confirm("Are you sure you want to logout?");
            if (userConfirmed) {
                window.location.href = "login.php";
            } else {
                window.location.href = "dashboard.php";
            }
        }
    </script>
</body>
</html>