<?php
// This is a temporary script to create the reservation table
// Delete this file after you've run it once

require_once('../config/db.php');

// First, check the existing tables and columns to understand the problem
echo "<h3>Checking database structure...</h3>";

// Check the LABORATORY table structure
$lab_check_sql = "SHOW COLUMNS FROM LABORATORY";
try {
    $lab_result = $conn->query($lab_check_sql);
    echo "<p>LABORATORY table columns:</p><ul>";
    while ($row = $lab_result->fetch_assoc()) {
        echo "<li>{$row['Field']} - {$row['Type']} - {$row['Key']}</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>Error checking LABORATORY table: " . $e->getMessage() . "</p>";
}

// Check the USERS table structure
$users_check_sql = "SHOW COLUMNS FROM USERS";
try {
    $users_result = $conn->query($users_check_sql);
    echo "<p>USERS table columns:</p><ul>";
    while ($row = $users_result->fetch_assoc()) {
        echo "<li>{$row['Field']} - {$row['Type']} - {$row['Key']}</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>Error checking USERS table: " . $e->getMessage() . "</p>";
}

// Modified SQL to create RESERVATION table without foreign key constraints initially
$sql = "
CREATE TABLE IF NOT EXISTS `RESERVATION` (
  `RESERVATION_ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDNO` varchar(20) NOT NULL,
  `LAB_ID` int(11) NOT NULL,
  `PC_NUMBER` int(11) NOT NULL,
  `PURPOSE` varchar(255) NOT NULL,
  `START_DATETIME` datetime NOT NULL,
  `END_DATETIME` datetime NOT NULL,
  `STATUS` enum('PENDING','APPROVED','REJECTED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `REQUEST_DATE` datetime NOT NULL,
  `UPDATED_BY` int(11) DEFAULT NULL,
  `UPDATED_AT` datetime DEFAULT NULL,
  `NOTES` text DEFAULT NULL,
  PRIMARY KEY (`RESERVATION_ID`),
  KEY `IDNO` (`IDNO`),
  KEY `LAB_ID` (`LAB_ID`),
  KEY `STATUS` (`STATUS`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

// Execute the query to create the base table
try {
    if ($conn->query($sql)) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 5px;'>
              <h2>Success!</h2>
              <p>The basic RESERVATION table has been created successfully.</p>
              </div>";
        
        // Now try to add the foreign key constraints separately
        $add_fk_sql = "
        ALTER TABLE `RESERVATION`
        ADD CONSTRAINT `reservation_lab_fk` FOREIGN KEY (`LAB_ID`) REFERENCES `LABORATORY` (`LAB_ID`) ON DELETE CASCADE ON UPDATE CASCADE;
        ";
        
        try {
            if ($conn->query($add_fk_sql)) {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 5px;'>
                      <p>Added LAB_ID foreign key constraint successfully.</p>
                      </div>";
            } else {
                echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>
                      <p>Could not add LAB_ID foreign key: " . $conn->error . "</p>
                      <p>The table was created but without this constraint.</p>
                      </div>";
            }
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>
                  <p>Error adding LAB_ID foreign key: " . $e->getMessage() . "</p>
                  </div>";
        }
        
        $add_fk_sql2 = "
        ALTER TABLE `RESERVATION`
        ADD CONSTRAINT `reservation_user_fk` FOREIGN KEY (`IDNO`) REFERENCES `USERS` (`IDNO`) ON DELETE CASCADE ON UPDATE CASCADE;
        ";
        
        try {
            if ($conn->query($add_fk_sql2)) {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 5px;'>
                      <p>Added IDNO foreign key constraint successfully.</p>
                      <p>You can now delete this file for security purposes.</p>
                      <p><a href='reservation.php' style='color: #155724; text-decoration: underline;'>Go to Reservation Page</a></p>
                      </div>";
            } else {
                echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>
                      <p>Could not add IDNO foreign key: " . $conn->error . "</p>
                      <p>The table was created but without this constraint.</p>
                      <p><a href='reservation.php' style='color: #721c24; text-decoration: underline;'>Go to Reservation Page anyway</a></p>
                      </div>";
            }
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>
                  <p>Error adding IDNO foreign key: " . $e->getMessage() . "</p>
                  <p><a href='reservation.php' style='color: #721c24; text-decoration: underline;'>Go to Reservation Page anyway</a></p>
                  </div>";
        }
        
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>
              <h2>Error</h2>
              <p>Could not create the RESERVATION table: " . $conn->error . "</p>
              </div>";
    }
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>
          <h2>Exception Caught</h2>
          <p>" . $e->getMessage() . "</p>
          </div>";
}
?>

<p style="margin-top: 20px; text-align: center;">
    <a href="ajax/create_reservation_without_fk.php" style="background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">
        Alternative: Create Table Without Foreign Keys
    </a>
</p>
