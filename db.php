<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "karepan";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>