<?php
session_start();
require_once('../../config/db.php');

// SQL to create RESERVATION table without foreign key constraints
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

try {
    // Try to drop the table if it exists
    $conn->query("DROP TABLE IF EXISTS `RESERVATION`");
    
    if ($conn->query($sql)) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 5px;'>
              <h2>Success!</h2>
              <p>The RESERVATION table has been created successfully (without foreign key constraints).</p>
              <p>The system will function, but without referential integrity checks.</p>
              <p><a href='../reservation.php' style='color: #155724; text-decoration: underline;'>Go to Reservation Page</a></p>
              </div>";
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
