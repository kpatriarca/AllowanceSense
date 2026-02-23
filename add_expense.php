<?php
session_start();
require 'config/connection.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
}

$uid = $_SESSION['user_id'];

if(isset($_POST['add'])){
    $desc = $_POST['description'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];

    $stmt = $conn->prepare("INSERT INTO expenses(user_id,description,amount,expense_date) VALUES(?,?,?,?)");
    $stmt->bind_param("isds",$uid,$desc,$amount,$date);
    $stmt->execute();

    header("Location: view_expenses.php");
}
?>

<link rel="stylesheet" href="style.css">

<div class="nav">
    <div>AllowanceSense</div>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="view_expenses.php">Expenses</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h3>Add Expense</h3>
        <form method="POST">
            <input type="text" name="description" placeholder="Description" required>
            <input type="number" step="0.01" name="amount" placeholder="Amount" required>
            <input type="date" name="date" required>
            <button name="add">Add Expense</button>
        </form>
    </div>
</div>
