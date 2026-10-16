<?php
date_default_timezone_set('Asia/Colombo'); // Sri Lanka time (UTC+5:30)
$host = "localhost";
$user = "root";
$password = "";
$database = "suwapiyasa_db";

$conn = mysqli_connect(
    $host,
    $user,
    $password,
    $database
);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
