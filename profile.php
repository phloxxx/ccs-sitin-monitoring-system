<?php 
session_start();
require_once('db.php');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT IDNO, LASTNAME, FIRSTNAME, USERNAME, COURSE, YEAR, SESSION, PROFILE_PIC FROM USERS WHERE USER_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$edit_mode = isset($_GET['edit']);
$error_message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idno = $_POST['idno'];
    $lastname = $_POST['lastname'];
    $firstname = $_POST['firstname'];
    $username = $_POST['username'];
    $course = $_POST['course'];
    $year = $_POST['year'];
    $profile_pic = $user['PROFILE_PIC']; // Default to current profile picture

    // Profile Picture Upload
    if (!empty($_FILES['profile_pic']['name'])) {
        $target_dir = "uploads/";

        // Ensure the directory exists
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Generate a unique filename
        $file_extension = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
        $new_filename = "profile_" . time() . "." . $file_extension;
        $profile_pic_path = $target_dir . $new_filename;

        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_types)) {
            $error_message = "Invalid file type! Only JPG, JPEG, PNG, and GIF are allowed.";
        } else {
            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $profile_pic_path)) {
                $profile_pic = $profile_pic_path; // Update profile picture if upload succeeds
            } else {
                $error_message = "Error uploading profile picture.";
            }
        }
    }

    // Stop execution if there is an error
    if (!empty($error_message)) {
        echo "<script>alert('$error_message');</script>";
    } else {
        // Check for unique ID Number & Username
        $check_stmt = $conn->prepare("SELECT USER_ID FROM USERS WHERE (IDNO = ? OR USERNAME = ?) AND USER_ID != ?");
        $check_stmt->bind_param("ssi", $idno, $username, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_message = "ID Number or Username is already taken!";
        } else {
            // Update user details
            $update_stmt = $conn->prepare("UPDATE USERS SET IDNO = ?, LASTNAME = ?, FIRSTNAME = ?, USERNAME = ?, COURSE = ?, YEAR = ?, PROFILE_PIC = ? WHERE USER_ID = ?");
            $update_stmt->bind_param("sssssssi", $idno, $lastname, $firstname, $username, $course, $year, $profile_pic, $user_id);
            
            if ($update_stmt->execute()) {
                header("Location: profile.php");
                exit();
            } else {
                $error_message = "Error updating profile.";
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="profile.css">
    <title>Profile</title>
</head>
<body>

    <div class="profile-container">
        <div class="profile-card">
            <!-- Display Profile Picture ONLY in View Mode -->
            <?php if (!$edit_mode): ?>
                <img src="<?php echo htmlspecialchars($user['PROFILE_PIC'] ?: 'src/images/snoopy.jpg'); ?>" 
                     alt="Profile Picture" class="profile-pic">
            <?php endif; ?>

            <?php if ($error_message): ?>
                <p class="error-message"><?php echo $error_message; ?></p>
            <?php endif; ?>

            <!-- View Mode -->
            <?php if (!$edit_mode): ?>
                <h2><?php echo htmlspecialchars($user['USERNAME']); ?></h2>
                <p><strong>ID Number:</strong> <?php echo htmlspecialchars($user['IDNO']); ?></p>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($user['LASTNAME'] . ", " . $user['FIRSTNAME']); ?></p>
                <p><strong>Course:</strong> <?php echo htmlspecialchars($user['COURSE']); ?></p>
                <p><strong>Year:</strong> <?php echo htmlspecialchars($user['YEAR']); ?></p>
                <p><strong>Session:</strong> <?php echo htmlspecialchars($user['SESSION']); ?></p>

                <div class="buttons">
                    <a href="dashboard.php" class="btn icon-btn" title="Back to Dashboard"><i class="fas fa-home-alt"></i></a>
                    <a href="profile.php?edit=true" class="btn edit-btn" title="Edit Profile"><i class="fas fa-pencil-alt"></i></a>
                </div>

            <!-- Edit Mode -->
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="profile-pic-container">
                        <img src="<?php echo htmlspecialchars($user['PROFILE_PIC'] ?: 'src/images/default-profile.png'); ?>" 
                            alt="Profile Picture" class="profile-pic" id="profilePicPreview">

                        <!-- Hidden file input -->
                        <input type="file" id="profilePicInput" name="profile_pic" accept="image/*" style="display: none;">

                        <!-- Edit Icon -->
                        <div class="edit-icon" onclick="triggerFileInput()">
                            <i class="fas fa-pencil-alt"></i>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="idno">ID Number</label>
                            <input type="text" id="idno" name="idno" value="<?php echo htmlspecialchars($user['IDNO']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['USERNAME']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="lastname">Last Name</label>
                            <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['LASTNAME']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="firstname">First Name</label>
                            <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['FIRSTNAME']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="course">Course</label>
                            <select id="course" name="course">
                                <option value="<?php echo htmlspecialchars($user['COURSE']); ?>">Change Course</option>
                                <option value="Bachelor of Science in Information Technology">BSIT</option>
                                <option value="Bachelor of Science in Information Systems">BSIS</option>
                                <option value="Bachelor of Science in Computer Science">BSCS</option>
                                <option value="Associate in Computer Technology">ACT</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="year">Year Level</label>
                            <select id="year" name="year">
                                <option value="<?php echo htmlspecialchars($user['YEAR']); ?>">Change Year</option>
                                <option value="Freshman">1st Year</option>
                                <option value="Sophomore">2nd Year</option>
                                <option value="Junior">3rd Year</option>
                                <option value="Senior">4th Year</option>
                            </select>
                        </div>
                    </div>

                    <div class="buttons">
                        <a href="profile.php" class="btn icon-btn" title="Cancel"><i class="fas fa-times"></i></a>
                        <button type="submit" class="btn edit-btn" title="Save Changes"><i class="fas fa-check"></i></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function triggerFileInput() {
        document.getElementById('profilePicInput').click();
    }

    // Optional: Show preview when user selects a new image
    document.getElementById('profilePicInput').addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePicPreview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
    </script>
</body>
</html>
