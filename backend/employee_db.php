<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "mze_cellular";

// Try direct connection to target DB
$conn = @new mysqli($host, $user, $pass, $dbname);
if ($conn && !$conn->connect_error) {
  $conn->set_charset("utf8mb4");
  return;
}

// If the database may not exist yet, attempt to create it
$bootstrap = @new mysqli($host, $user, $pass);
if ($bootstrap->connect_error) {
  die("Database connection failed: " . $bootstrap->connect_error);
}
$bootstrap->query("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$bootstrap->close();

// Retry connecting to the target DB
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
  die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>