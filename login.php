<?php
include 'db.php';

session_start();    

if(isset($_POST['login'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = $conn->query($sql);

    if($result->num_rows > 0){
        $row = $result->fetch_assoc();
        $_SESSION['username'] = $row['username'];
        $_SESSION['id'] = $row['id'];
        header('Location: index.php');
    }else{
        echo "Invalid username or password";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCSSMS REGISTER</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://fonts.googleapis.com/css?family=Raleway' rel='stylesheet'>
</head>
<body>
  <div class="wrapper">
    <form action="login.php" method="POST">
      <h2>CCS Sitin Management System</h2>
        <div class="input-field">
        <input type="text" id="username" name="username" required>
        <label for="username">Username</label>
      </div>
      <div class="input-field">
        <input type="password" id="password" name="password" required>
        <label for="password">Password</label>
      </div>
      <div class="forget">
        <label for="remember">
          <input type="checkbox" id="remember">
          <p>Remember me</p>
        </label>
        <a href="#">Forgot password?</a>
      </div>
      <button type="submit">Log In</button>
      <div class="register">
        <p>Don't have an account? <a href="register.html">Register</a></p>
      </div>
    </form>
  </div>
</body>
</html>