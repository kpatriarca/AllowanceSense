<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// Fetch latest 20 activity log entries
$logQuery = $conn->query("SELECT action, details, log_time 
                          FROM activity_log 
                          WHERE user_id=$uid 
                          ORDER BY log_time DESC 
                          LIMIT 20");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Activity Log - AllowanceSense</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #2563EB;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="nav">
        <div>AllowanceSense</div>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_expenses.php">Expenses</a>
            <a href="allowances.php">Allowance</a>
            <a href="budgets.php">Budget</a>
            <a href="reports.php">Reports</a>
            <a href="categories.php">Categories</a>
            <a href="activity_log.php">Activity Log</a>
            <a href="settings.php">Settings</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h2>Activity Log</h2>
        <table>
            <tr>
                <th>Action</th>
                <th>Details</th>
                <th>Date & Time</th>
            </tr>
            <?php while($row = $logQuery->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['action']); ?></td>
                <td><?= htmlspecialchars($row['details']); ?></td>
                <td><?= date("F j, Y, g:i A", strtotime($row['log_time'])); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
