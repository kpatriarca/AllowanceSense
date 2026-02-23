<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Greeting based on time of day
$hour = date("H");
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// Current date/time
$currentDate = date("F j, Y, g:i A");

// Monthly allowance
$allowanceQuery = $conn->query("SELECT SUM(amount) AS allowance 
                                FROM allowances 
                                WHERE user_id=$uid 
                                AND month = MONTH(CURDATE()) 
                                AND year = YEAR(CURDATE())");
$allowanceRow = $allowanceQuery->fetch_assoc();
$allowance = $allowanceRow['allowance'] ?? 0;

// Total expenses
$totalQuery = $conn->query("SELECT SUM(amount) AS total 
                            FROM expenses 
                            WHERE user_id=$uid 
                            AND MONTH(expense_date) = MONTH(CURDATE()) 
                            AND YEAR(expense_date) = YEAR(CURDATE())");
$totalRow = $totalQuery->fetch_assoc();
$total = $totalRow['total'] ?? 0;

// Remaining balance
$remaining = $allowance - $total;

// Savings goal (optional, static for now)
$savings_goal = 1000;

// Recent expenses (last 5)
$recentExpenses = $conn->query("SELECT description, amount, expense_date, c.category_name 
                                FROM expenses e 
                                LEFT JOIN categories c ON e.category_id = c.id 
                                WHERE e.user_id=$uid 
                                ORDER BY e.expense_date DESC 
                                LIMIT 5");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - AllowanceSense</title>
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
        <a href="reports.php">Reports</a>
        <a href="categories.php">Categories</a>
        <a href="activity_log.php">Activity Log</a>
        <a href="settings.php">Settings</a>
        <a href="logout.php">Logout</a>
    </div>
</div>


    <div class="container">
        <h2><?= $greeting ?>, <?= htmlspecialchars($full_name) ?>!</h2>
        <p>It's <?= $currentDate ?></p>

        <div class="summary">
            <div class="card">
                <h4>Remaining Balance</h4>
                <h2>₱<?= number_format($remaining, 2) ?></h2>
            </div>
            <div class="card">
                <h4>Total Expenses</h4>
                <h2>₱<?= number_format($total, 2) ?></h2>
            </div>
            <div class="card">
                <h4>Monthly Allowance</h4>
                <h2>₱<?= number_format($allowance, 2) ?></h2>
            </div>
            <div class="card">
                <h4>Savings Goal</h4>
                <h2>₱<?= number_format($savings_goal, 2) ?></h2>
            </div>
        </div>

        <div class="actions">
            <a href="allowances.php" class="btn">Set Allowance</a>
            <a href="add_expense.php" class="btn">Add Expense</a>
        </div>

        <h3>Recent Expenses</h3>
        <ul>
            <?php while($row = $recentExpenses->fetch_assoc()): ?>
                <li><?= htmlspecialchars($row['description']) ?> 
                    (<?= $row['category_name'] ?>) – ₱<?= number_format($row['amount'], 2) ?> 
                    on <?= $row['expense_date'] ?>
                </li>
            <?php endwhile; ?>
        </ul>

        <!-- Spending by Category + Budget Status can be added with charts later -->
    </div>
</body>
</html>
