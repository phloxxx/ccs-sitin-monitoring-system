<?php
    session_start();
    require_once('db.php');

    if(isset($_POST['register'])){
        $idno = $_POST['idno'];
        $lastname = $_POST['lastname'];
        $firstname = $_POST['firstname'];
        $midname = $_POST['midname'];
        $course = $_POST['course'];
        $year = $_POST['year'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if($password == $confirm_password){
            $sql = "INSERT INTO users (idno, lastname, firstname, midname, course, year, username, password) VALUES ('$idno', '$lastname', '$firstname', '$midname', '$course', '$year', '$username', '$password')";
            $result = $conn->query($sql);

            if($result === TRUE){
                echo "<script>
                alert('You have successfully registered!');
                window.location.href='login.php';
                </script>";
            }else{
                $_SESSION['error'] = "Error: " . $sql . "<br>" . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Password does not match";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <title>CCSSMS REGISTER</title>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>REGISTRATION</h2>
            </div>
            <form class="form" action="register.php" method="post">
                <div class="form">
                    <!-- Row 1: ID Number -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="idno">ID Number</label>
                            <input type="text" id="idno" name="idno" required>
                        </div>
                    </div>          
                    <!-- Row 2: Last Name and First Name -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lastname">Last Name</label>
                            <input type="text" id="lastname" name="lastname" required>
                        </div>
                        <div class="form-group">
                            <label for="firstname">First Name</label>
                            <input type="text" id="firstname" name="firstname" required>
                        </div>
                    </div>               
                    <!-- Row 3: Middle Name and Course -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="midname">Middle Name</label>
                            <input type="text" id="midname" name="midname">
                        </div>
                        <div class="form-group">
                            <label for="course">Course</label>
                            <select id="course" name="course" required>
                                <option value="">Select Course</option>
                                <option value="Bachelor of Science in Information Technology">BSIT</option>
                                <option value="Bachelor of Science in Information Systems">BSIS</option>
                                <option value="Bachelor of Science in Computer Science">BSCS</option>
                                <option value="Associate in Computer Technology">ACT</option>
                                <!-- Add more options as needed -->
                            </select>
                        </div>
                    </div>               
                    <!-- Row 4: Year Level and Username -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="year">Year Level</label>
                            <select id="year" name="year" required>
                                <option value="">Select Year</option>
                                <option value="Freshman">1st Year</option>
                                <option value="Sophomore">2nd Year</option>
                                <option value="Junior">3rd Year</option>
                                <option value="Senior">4th Year</option>
                                <!-- Add more options as needed -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                    </div>               
                    <!-- Row 5: Password and Confirm Password -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <?php
                            if(isset($_SESSION['error'])){
                                echo "<p style='color:red;'>" . $_SESSION['error'] . "</p>";
                                unset($_SESSION['error']);
                            }
                            ?>
                        </div>
                    </div>               
                    <button type="submit" class="btn" name="register">Register</button>
                    <p>Already have an account? <a href="login.php">Login</a></p>
                </div>                
            </form>
        </div>
    </div>
</body>
</html>