<?php
// Connect to database
require_once('db.php');

// Check if PC table exists
$table_check = $conn->query("SHOW TABLES LIKE 'pc'");

if ($table_check->num_rows == 0) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE `PC` (
        `PC_ID` int(11) NOT NULL AUTO_INCREMENT,
        `LAB_ID` int(11) NOT NULL,
        `PC_NUMBER` varchar(20) NOT NULL,
        `PC_NAME` varchar(50) DEFAULT NULL,
        `STATUS` enum('AVAILABLE','IN_USE','MAINTENANCE') NOT NULL DEFAULT 'AVAILABLE',
        `SPECS` text DEFAULT NULL,
        `LAST_MAINTENANCE` date DEFAULT NULL,
        `CREATED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
        `UPDATED_AT` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`PC_ID`),
        UNIQUE KEY `UNQ_PC_LAB_NUMBER` (`LAB_ID`,`PC_NUMBER`),
        CONSTRAINT `FK_PC_LAB` FOREIGN KEY (`LAB_ID`) REFERENCES `LABORATORY` (`LAB_ID`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($sql) === TRUE) {
        echo "PC table created successfully!";
    } else {
        echo "Error creating PC table: " . $conn->error;
    }
} else {
    echo "PC table already exists.";
}

// Now let's add some sample PCs to each lab for testing
$labs_result = $conn->query("SELECT LAB_ID, CAPACITY FROM LABORATORY");

if ($labs_result->num_rows > 0) {
    // For each lab, create PCs based on capacity
    while ($lab = $labs_result->fetch_assoc()) {
        $lab_id = $lab['LAB_ID'];
        $capacity = $lab['CAPACITY'];
        
        // Check if this lab already has PCs
        $pc_check = $conn->prepare("SELECT COUNT(*) as count FROM PC WHERE LAB_ID = ?");
        $pc_check->bind_param("i", $lab_id);
        $pc_check->execute();
        $result = $pc_check->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            // Add PCs for this lab
            $stmt = $conn->prepare("INSERT INTO PC (LAB_ID, PC_NUMBER, PC_NAME, STATUS, SPECS) VALUES (?, ?, ?, ?, ?)");
            
            for ($i = 1; $i <= $capacity; $i++) {
                $pc_number = sprintf("PC-%02d", $i);
                $pc_name = sprintf("PC-%02d", $i); // Set PC_NAME to be the same as PC_NUMBER by default
                $status = 'AVAILABLE';
                $specs = 'Intel Core i5, 16GB RAM, 512GB SSD';
                
                $stmt->bind_param("issss", $lab_id, $pc_number, $pc_name, $status, $specs);
                $stmt->execute();
            }
            
            echo "<br>Added {$capacity} PCs to lab ID {$lab_id}";
            $stmt->close();
        } else {
            echo "<br>Lab ID {$lab_id} already has PCs. Skipping.";
        }
        
        $pc_check->close();
    }
} else {
    echo "<br>No laboratories found. Please run populate_laboratory.php first.";
}

$conn->close();
?>