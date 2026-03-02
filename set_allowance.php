<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $uid    = $_SESSION['user_id'];
    $amount = floatval($_POST['amount']); // ensure number
    $month  = date("m");
    $year   = date("Y");

    // Check if allowance already exists for this month
    $check = $conn->prepare("SELECT id FROM allowances WHERE user_id=? AND month=? AND year=?");
    $check->bind_param("iii", $uid, $month, $year);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // UPDATE existing allowance (replace value)
        $update = $conn->prepare("UPDATE allowances SET amount=? WHERE user_id=? AND month=? AND year=?");
        $update->bind_param("diii", $amount, $uid, $month, $year);
        $update->execute();
    } else {
        // INSERT new allowance
        $insert = $conn->prepare("INSERT INTO allowances (user_id, amount, month, year) VALUES (?, ?, ?, ?)");
        $insert->bind_param("idii", $uid, $amount, $month, $year);
        $insert->execute();
    }

    header("Location: allowances.php");
    exit();
}
?>