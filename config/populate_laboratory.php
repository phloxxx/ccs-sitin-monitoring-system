<?php
// Connect to database
require_once('db.php');

// Define the laboratories to insert
$laboratories = [
    ['Lab 524', 'Computer Laboratory Room 524', 30, 'AVAILABLE'],
    ['Lab 526', 'Computer Laboratory Room 526', 25, 'AVAILABLE'],
    ['Lab 528', 'Computer Laboratory Room 528', 20, 'AVAILABLE'],
    ['Lab 530', 'Computer Laboratory Room 530', 30, 'AVAILABLE'],
    ['Lab 542', 'Computer Laboratory Room 542', 25, 'AVAILABLE'],
    ['Lab 544', 'Computer Laboratory Room 544', 15, 'AVAILABLE']
];

// Check if table exists, if not create it
$tableExists = $conn->query("SHOW TABLES LIKE 'LABORATORY'")->num_rows > 0;
if (!$tableExists) {
    $createTable = "CREATE TABLE `LABORATORY` (
        `LAB_ID` int(11) NOT NULL AUTO_INCREMENT,
        `LAB_NAME` varchar(100) NOT NULL,
        `LAB_DESCRIPTION` text DEFAULT NULL,
        `CAPACITY` int(11) NOT NULL DEFAULT 30,
        `STATUS` enum('AVAILABLE','MAINTENANCE','OCCUPIED') NOT NULL DEFAULT 'AVAILABLE',
        `CREATED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
        `UPDATED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`LAB_ID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($createTable)) {
        echo "LABORATORY table created successfully.<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
        exit;
    }
}

// Count labs in the database
$result = $conn->query("SELECT COUNT(*) as count FROM LABORATORY");
$row = $result->fetch_assoc();
$labCount = $row['count'];

echo "Found {$labCount} existing laboratories.<br>";

// Insert labs if none exist
if ($labCount == 0) {
    $insertCount = 0;
    
    // Prepare statement for bulk insert
    $stmt = $conn->prepare("INSERT INTO LABORATORY (LAB_NAME, LAB_DESCRIPTION, CAPACITY, STATUS) VALUES (?, ?, ?, ?)");
    
    foreach ($laboratories as $lab) {
        $stmt->bind_param("ssis", $lab[0], $lab[1], $lab[2], $lab[3]);
        
        if ($stmt->execute()) {
            $insertCount++;
            echo "Inserted: {$lab[0]}<br>";
        } else {
            echo "Error inserting {$lab[0]}: " . $stmt->error . "<br>";
        }
    }
    
    echo "<strong>Successfully inserted {$insertCount} laboratories.</strong><br>";
    $stmt->close();
} else {
    // Check each lab individually
    $insertCount = 0;
    
    foreach ($laboratories as $lab) {
        // Check if this specific lab exists
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM LABORATORY WHERE LAB_NAME = ?");
        $stmt->bind_param("s", $lab[0]);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            // Lab doesn't exist, insert it
            $insertStmt = $conn->prepare("INSERT INTO LABORATORY (LAB_NAME, LAB_DESCRIPTION, CAPACITY, STATUS) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param("ssis", $lab[0], $lab[1], $lab[2], $lab[3]);
            
            if ($insertStmt->execute()) {
                $insertCount++;
                echo "Inserted: {$lab[0]}<br>";
            } else {
                echo "Error inserting {$lab[0]}: " . $insertStmt->error . "<br>";
            }
            
            $insertStmt->close();
        } else {
            echo "Skipped (already exists): {$lab[0]}<br>";
        }
        
        $stmt->close();
    }
    
    if ($insertCount > 0) {
        echo "<strong>Successfully inserted {$insertCount} new laboratories.</strong><br>";
    } else {
        echo "<strong>No new laboratories were inserted.</strong><br>";
    }
}

echo "Done!";
$conn->close();
?>
