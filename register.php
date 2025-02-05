<?php

include 'db.php';

if(isset($_POST['register'])){
    $idno = $_POST['idno'];
    $lastname = $_POST['lastname'];
    $firstname = $_POST['firstname'];
    $midname = $_POST['midname'];
    $course = $_POST['course'];
    $year = $_POST['year'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare an SQL statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO users (idno, lastname, firstname, midname, course, year, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $idno, $lastname, $firstname, $midname, $course, $year, $username, $password);

    if($stmt->execute()){
        echo "<script>alert('New record created successfully'); window.location.href='login.html';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCSSMS REGISTER</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrapper">
        <form action="register.php" method="POST">
          <h2>CCS Sitin Management System</h2>
        <div class="input-field">
            <input type="text" id="idno" name="idno" required>
            <label>IDNO</label>
        </div>
        <div class="input-field">
            <input type="text" id="lastname" name="lastname" required>
            <label>Lastname</label>
        </div>
        <div class="input-field">
            <input type="text" id="firstname" name="firstname" required>
            <label>Firstname</label>
        </div>
        <div class="input-field">
            <input type="text" id="midname" name="midname" required>
            <label>Midname</label>
        </div>
        <div class="input-field">
            <input type="text" id="course" name="course" required>
            <label>Course</label>
        </div>
        <div class="input-field">
            <input type="text" id="year" name="year" required>
            <label>Year</label>
        </div>
        <div class="input-field">
            <input type="text" id="username" name="username" required>
            <label>Username</label>
        </div>
        <div class="input-field">
            <input type="password" id="password" name="password" required>
            <label>Enter your password</label>
        </div>
        <div class="forget">
            <label for="remember">
              <input type="checkbox" id="remember">
              <p>Remember me</p>
            </label>
            <a href="#">Forgot password?</a>
        </div>
          <button type="submit" name="register">Register</button>
          <div class="register">
            <p>Already have an account? <a href="login.html">Login</a></p>
          </div>
        </form>
    </div>
</body>
</html>