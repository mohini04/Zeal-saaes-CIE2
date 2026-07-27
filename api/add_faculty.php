<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../config/db.php');

// Security Check: Only HOD can onboard faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HOD') {
    die("Unauthorized access. Only HOD can perform this action.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST['name'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($name) && !empty($subject) && !empty($username) && !empty($password)) {
        // Check if username already exists
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            echo "<script>alert('Error: Username already exists!'); window.history.back();</script>";
            exit();
        }
        $checkStmt->close();

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = "Faculty";
        $is_first_login = 1; // Require password change on first login

        $stmt = $conn->prepare("INSERT INTO users (name, username, password, role, is_first_login) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $username, $hashed_password, $role, $is_first_login);
        
        if ($stmt->execute()) {
            echo "<script>alert('Faculty " . addslashes($name) . " onboarded successfully!'); window.location.href = '../hod_dashboard.php';</script>";
        } else {
            echo "<script>alert('Database Error: " . addslashes($conn->error) . "'); window.history.back();</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Please fill in all fields.'); window.history.back();</script>";
    }
} else {
    header("Location: ../hod_dashboard.php");
    exit();
}
