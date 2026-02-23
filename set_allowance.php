<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uid = $_SESSION['user_id'];
    $amount = $_POST['amount'];
    $month = date("m");
    $year  = date("Y");

    $stmt = $conn->prepare("INSERT INTO allowances (user_id, amount, month, year) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("idis", $uid, $amount, $month, $year);

    if ($stmt->execute()) {
        header("Location: allowances.php");
        exit();
    } else {
        echo "Failed to update allowance.";
    }
}
?>
