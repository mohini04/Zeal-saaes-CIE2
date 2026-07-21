<?php
$host = 'localhost';
$dbname = 'saaes_db';
$username = 'root';
$password = '';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, $options);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbname} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Return the PDO instance for includes that expect it
return $pdo;
