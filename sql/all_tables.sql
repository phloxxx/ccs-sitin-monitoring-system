-- CCS SIT-IN MONITORING SYSTEM DATABASE SCHEMA
-- This file contains all tables used in the system

-- -----------------------------------------------------
-- Table `ADMIN` - Stores administrator account details
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ADMIN` (
  `ADMIN_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USERNAME` varchar(50) NOT NULL,
  `PASSWORD` varchar(255) NOT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ADMIN_ID`),
  UNIQUE KEY `USERNAME` (`USERNAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `USERS` - Stores student account information
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `USERS` (
  `USER_ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDNO` varchar(20) NOT NULL,
  `LASTNAME` varchar(50) NOT NULL,
  `FIRSTNAME` varchar(50) NOT NULL,
  `MIDNAME` varchar(50) DEFAULT NULL,
  `COURSE` varchar(50) NOT NULL,
  `YEAR` varchar(10) NOT NULL,
  `USERNAME` varchar(50) NOT NULL,
  `PASSWORD` varchar(255) NOT NULL,
  `PROFILE_PIC` varchar(255) DEFAULT NULL,
  `SESSION` int(11) DEFAULT 30,
  `CREATED_AT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`USER_ID`),
  UNIQUE KEY `IDNO` (`IDNO`),
  UNIQUE KEY `USERNAME` (`USERNAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `LABORATORY` - Stores laboratory details
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `LABORATORY` (
  `LAB_ID` int(11) NOT NULL AUTO_INCREMENT,
  `LAB_NAME` varchar(50) NOT NULL,
  `CAPACITY` int(11) NOT NULL,
  PRIMARY KEY (`LAB_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `PC` - Stores PC information for each laboratory
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `PC` (
  `PC_ID` int(11) NOT NULL AUTO_INCREMENT,
  `LAB_ID` int(11) NOT NULL,
  `PC_NUMBER` int(11) NOT NULL,
  `PC_NAME` varchar(50) DEFAULT NULL,
  `STATUS` enum('AVAILABLE','MAINTENANCE','IN_USE','RESERVED') NOT NULL DEFAULT 'AVAILABLE',
  PRIMARY KEY (`PC_ID`),
  UNIQUE KEY `LAB_PC` (`LAB_ID`,`PC_NUMBER`),
  CONSTRAINT `pc_lab_fk` FOREIGN KEY (`LAB_ID`) REFERENCES `LABORATORY` (`LAB_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `SITIN` - Stores session tracking information
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `SITIN` (
  `SITIN_ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDNO` varchar(20) NOT NULL,
  `LAB_ID` int(11) NOT NULL,
  `PC_ID` int(11) DEFAULT NULL,
  `ADMIN_ID` int(11) DEFAULT NULL,
  `PURPOSE` varchar(255) NOT NULL,
  `SESSION_START` datetime NOT NULL,
  `SESSION_END` datetime DEFAULT NULL,
  `SESSION_DURATION` int(11) DEFAULT 1,
  `STATUS` enum('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`SITIN_ID`),
  KEY `IDNO` (`IDNO`),
  KEY `LAB_ID` (`LAB_ID`),
  KEY `PC_ID` (`PC_ID`),
  KEY `ADMIN_ID` (`ADMIN_ID`),
  CONSTRAINT `sitin_admin_fk` FOREIGN KEY (`ADMIN_ID`) REFERENCES `ADMIN` (`ADMIN_ID`) ON DELETE SET NULL,
  CONSTRAINT `sitin_lab_fk` FOREIGN KEY (`LAB_ID`) REFERENCES `LABORATORY` (`LAB_ID`),
  CONSTRAINT `sitin_pc_fk` FOREIGN KEY (`PC_ID`) REFERENCES `PC` (`PC_ID`) ON DELETE SET NULL,
  CONSTRAINT `sitin_user_fk` FOREIGN KEY (`IDNO`) REFERENCES `USERS` (`IDNO`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `RESERVATION` - Manages PC reservations
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `RESERVATION` (
  `RESERVATION_ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDNO` varchar(20) NOT NULL,
  `LAB_ID` int(11) NOT NULL,
  `PC_NUMBER` int(11) NOT NULL,
  `PURPOSE` varchar(255) NOT NULL,
  `START_DATETIME` datetime NOT NULL,
  `END_DATETIME` datetime NOT NULL,
  `STATUS` enum('PENDING','APPROVED','REJECTED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `REQUEST_DATE` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_BY` int(11) DEFAULT NULL,
  `UPDATED_AT` datetime DEFAULT NULL,
  `NOTES` text DEFAULT NULL,
  PRIMARY KEY (`RESERVATION_ID`),
  KEY `IDNO` (`IDNO`),
  KEY `LAB_ID` (`LAB_ID`),
  KEY `STATUS` (`STATUS`),
  CONSTRAINT `reservation_lab_fk` FOREIGN KEY (`LAB_ID`) REFERENCES `LABORATORY` (`LAB_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `reservation_user_fk` FOREIGN KEY (`IDNO`) REFERENCES `USERS` (`IDNO`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `FEEDBACK` - Stores student feedback on sessions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `FEEDBACK` (
  `FEEDBACK_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SITIN_ID` int(11) NOT NULL,
  `RATING` int(11) NOT NULL,
  `COMMENTS` text,
  `SUBMISSION_DATE` datetime DEFAULT CURRENT_TIMESTAMP,
  `STATUS` varchar(20) DEFAULT 'PENDING',
  `ADMIN_RESPONSE` text NULL,
  `RESPONSE_DATE` datetime NULL,
  `CREATED_AT` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`FEEDBACK_ID`),
  KEY `SITIN_ID` (`SITIN_ID`),
  CONSTRAINT `feedback_sitin_fk` FOREIGN KEY (`SITIN_ID`) REFERENCES `SITIN` (`SITIN_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `NOTIFICATIONS` - Manages user notifications
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `NOTIFICATIONS` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` int(11) NOT NULL,
  `TITLE` varchar(100) NOT NULL,
  `MESSAGE` text NOT NULL,
  `IS_READ` tinyint(1) DEFAULT 0,
  `CREATED_AT` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `USER_ID` (`USER_ID`),
  CONSTRAINT `notifications_user_fk` FOREIGN KEY (`USER_ID`) REFERENCES `USERS` (`USER_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `ANNOUNCEMENT` - Stores system announcements
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `ANNOUNCEMENT` (
  `ANNOUNCE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ADMIN_ID` int(11) NOT NULL,
  `TITLE` varchar(255) NOT NULL,
  `CONTENT` text NOT NULL,
  `CREATED_AT` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ANNOUNCE_ID`),
  KEY `ADMIN_ID` (`ADMIN_ID`),
  CONSTRAINT `announcement_admin_fk` FOREIGN KEY (`ADMIN_ID`) REFERENCES `ADMIN` (`ADMIN_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `STUDENT_POINTS` - Tracks student rewards points
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `STUDENT_POINTS` (
  `POINT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` varchar(20) NOT NULL,
  `POINTS` int(11) DEFAULT 0,
  `TOTAL_POINTS` int(11) DEFAULT 0,
  `ADMIN_ID` int(11) DEFAULT NULL,
  `CREATED_AT` datetime DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_AT` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`POINT_ID`),
  UNIQUE KEY `USER_ID` (`USER_ID`),
  CONSTRAINT `points_user_fk` FOREIGN KEY (`USER_ID`) REFERENCES `USERS` (`IDNO`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `POINTS_HISTORY` - Tracks point transactions
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `POINTS_HISTORY` (
  `HISTORY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` varchar(20) NOT NULL,
  `POINTS_ADDED` int(11) NOT NULL,
  `ADMIN_ID` int(11) DEFAULT NULL,
  `CREATED_AT` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`HISTORY_ID`),
  KEY `USER_ID` (`USER_ID`),
  CONSTRAINT `history_user_fk` FOREIGN KEY (`USER_ID`) REFERENCES `USERS` (`IDNO`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `STUDENT_REWARDS` - Tracks student earned rewards
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `STUDENT_REWARDS` (
  `REWARD_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` varchar(20) NOT NULL,
  `REWARD_TYPE` enum('EXTRA_SESSION','OTHER') NOT NULL DEFAULT 'EXTRA_SESSION',
  `ADMIN_ID` int(11) DEFAULT NULL,
  `CREATED_AT` datetime DEFAULT CURRENT_TIMESTAMP,
  `REDEEMED_AT` datetime DEFAULT NULL,
  PRIMARY KEY (`REWARD_ID`),
  KEY `USER_ID` (`USER_ID`),
  CONSTRAINT `rewards_user_fk` FOREIGN KEY (`USER_ID`) REFERENCES `USERS` (`IDNO`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `LAB_RESOURCES` - Stores resources for each lab
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `LAB_RESOURCES` (
  `RESOURCE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `RESOURCE_NAME` varchar(255) NOT NULL,
  `RESOURCE_TYPE` varchar(50) DEFAULT 'other',
  `DESCRIPTION` text,
  `RESOURCE_LINK` text,
  `FILE_PATH` text,
  `LAB_ID` varchar(50) DEFAULT 'all',
  `CREATED_AT` timestamp DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_AT` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`RESOURCE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `SCHEDULE_UPLOADS` - Tracks lab schedule uploads
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `SCHEDULE_UPLOADS` (
  `UPLOAD_ID` int(11) NOT NULL AUTO_INCREMENT,
  `LAB_ID` varchar(10) NOT NULL,
  `TITLE` varchar(255) NOT NULL,
  `FILENAME` varchar(255) NOT NULL,
  `UPLOADED_BY` int(11) NOT NULL,
  `UPLOAD_DATE` datetime NOT NULL,
  PRIMARY KEY (`UPLOAD_ID`),
  KEY `UPLOADED_BY` (`UPLOADED_BY`),
  CONSTRAINT `schedule_admin_fk` FOREIGN KEY (`UPLOADED_BY`) REFERENCES `ADMIN` (`ADMIN_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
