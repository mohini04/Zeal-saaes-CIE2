<?php
$host = 'localhost';
$dbname = 'saaes_db';
$username = 'root';
$password = '';

// 1. Initialize PDO connection
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, $options);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// 2. Initialize MySQLi connection for scripts expecting $conn
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("MySQLi connection failed: " . $conn->connect_error);
}

// 3. Auto Schema Maintenance (ensure tables exist)
$createUsersTable = "CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `username` VARCHAR(150) NOT NULL UNIQUE,
    `email` VARCHAR(150) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` VARCHAR(50) NOT NULL,
    `linked_student_prn` VARCHAR(50) DEFAULT NULL,
    `is_first_login` TINYINT(1) DEFAULT 0,
    `security_question` VARCHAR(255) DEFAULT NULL,
    `security_answer` VARCHAR(255) DEFAULT NULL,
    `roll_no` VARCHAR(50) DEFAULT NULL,
    `division` VARCHAR(50) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
@mysqli_query($conn, $createUsersTable);

$createAccessReqTable = "CREATE TABLE IF NOT EXISTS `access_requests` (
    `request_id` INT AUTO_INCREMENT PRIMARY KEY,
    `prn_number` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `department` VARCHAR(100) NOT NULL,
    `parent_name` VARCHAR(100) NOT NULL,
    `parent_email` VARCHAR(150) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'PENDING',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
@mysqli_query($conn, $createAccessReqTable);

// Ensure email column exists in users
@mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email` VARCHAR(150) DEFAULT NULL");

// 4. Seed default accounts if users table is empty
$checkCount = @mysqli_query($conn, "SELECT COUNT(*) as c FROM `users`");
if ($checkCount) {
    $row = mysqli_fetch_assoc($checkCount);
    if ((int)($row['c'] ?? 0) === 0) {
        $defaultPass = password_hash('Zeal@2026', PASSWORD_BCRYPT);
        $seedUsers = [
            ["System Administrator", "admin", "admin@zeal.in", $defaultPass, "Admin", 0],
            ["Dr. Mohini Deore", "faculty@zeal.in", "faculty@zeal.in", $defaultPass, "Faculty", 0],
            ["HOD E&TC", "hod@zeal.in", "hod@zeal.in", $defaultPass, "HOD", 0],
            ["GFM Electronics", "gfm@zeal.in", "gfm@zeal.in", $defaultPass, "GFM", 0],
            ["Mohini Deore", "72202685E", "student@zeal.in", $defaultPass, "Student", 0],
            ["Mrs. Sunita Sharma", "parent@zeal.in", "parent@zeal.in", $defaultPass, "Parent", 0]
        ];
        $stmt = $conn->prepare("INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`, `is_first_login`) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($seedUsers as $u) {
            $stmt->bind_param("sssssi", $u[0], $u[1], $u[2], $u[3], $u[4], $u[5]);
            $stmt->execute();
        }
        $stmt->close();
    }
}

// Return the PDO instance for includes that expect it
return $pdo;
