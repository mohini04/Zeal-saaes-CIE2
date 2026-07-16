<?php
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "cie2_system";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>