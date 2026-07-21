<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "saaes_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Auto-patch schema for PRN Access & Dual IDP Flow
function initOnboardingTables($conn) {
    // 1. Access Requests Queue Table with Parent Metadata
    $createRequestsSQL = "CREATE TABLE IF NOT EXISTS `access_requests` (
        `request_id` INT AUTO_INCREMENT PRIMARY KEY,
        `prn_number` VARCHAR(50) NOT NULL UNIQUE,
        `full_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `department` VARCHAR(100) NOT NULL,
        `parent_name` VARCHAR(100) NOT NULL,
        `parent_email` VARCHAR(150) NOT NULL,
        `status` VARCHAR(20) DEFAULT 'PENDING',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @mysqli_query($conn, $createRequestsSQL);

    // 2. Ensure users table has onboarding & role metadata columns
    $userColumns = [
        "is_first_login" => "TINYINT(1) DEFAULT 1",
        "roll_no" => "VARCHAR(50) DEFAULT NULL",
        "division" => "VARCHAR(10) DEFAULT NULL",
        "phone" => "VARCHAR(20) DEFAULT NULL",
        "security_question" => "VARCHAR(255) DEFAULT NULL",
        "security_answer" => "VARCHAR(255) DEFAULT NULL",
        "linked_student_prn" => "VARCHAR(50) DEFAULT NULL"
    ];

    foreach ($userColumns as $col => $definition) {
        $checkCol = @mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE '$col'");
        if ($checkCol && mysqli_num_rows($checkCol) === 0) {
            @mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `$col` $definition");
        }
    }
}
initOnboardingTables($conn);
?>