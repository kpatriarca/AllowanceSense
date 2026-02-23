<?php
$conn = new mysqli("localhost", "root", "", "AllowanceSense");

if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}
?>
