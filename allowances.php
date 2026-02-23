<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// Current allowance (latest record)
$currentAllowanceQuery = $conn->query("SELECT * FROM allowances 
                                       WHERE user_id=$uid 
                                       ORDER BY id DESC LIMIT 1");
$currentAllowance = $currentAllowanceQuery->fetch_assoc();

// Allowance history (last 5 records)
$historyQuery = $conn->query("SELECT * FROM allowances 
                              WHERE user_id=$uid 
                              ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Allowance - AllowanceSense</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="nav">
        <div>AllowanceSense</div>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_expenses.php">Expenses</a>
            <a href="allowances.php">Allowance</a>
            <a href="budgets.php">Budget</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h2>Allowance Management</h2>

        <!-- Current Allowance -->
        <div class="card">
            <h3>Current Allowance</h3>
            <?php if($currentAllowance): ?>
                <p>Period: <?= $currentAllowance['month'] ?>/<?= $currentAllowance['year'] ?></p>
                <h2>₱<?= number_format($currentAllowance['amount'], 2) ?></h2>
            <?php else: ?>
                <p>No allowance set yet.</p>
            <?php endif; ?>
        </div>

        <!-- Allowance Settings -->
        <div class="card">
            <h3>Allowance Settings</h3>
            <form method="POST" action="set_allowance.php">
                <label>Monthly Allowance</label>
                <input type="number" name="amount" step="0.01" required>
                <button type="submit">Update Allowance</button>
            </form>
        </div>

        <!-- Allowance History -->
        <div class="card">
            <h3>Allowance History</h3>
            <ul>
                <?php while($row = $historyQuery->fetch_assoc()): ?>
                    <li><?= $row['month'] ?> <?= $row['year'] ?>: ₱<?= number_format($row['amount'], 2) ?></li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>
</body>
</html>
