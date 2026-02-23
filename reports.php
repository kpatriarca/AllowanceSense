<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// Totals
$incomeQuery = $conn->query("SELECT SUM(amount) AS income FROM allowances WHERE user_id=$uid");
$incomeRow = $incomeQuery->fetch_assoc();
$totalIncome = $incomeRow['income'] ?? 0;

$spentQuery = $conn->query("SELECT SUM(amount) AS spent FROM expenses WHERE user_id=$uid");
$spentRow = $spentQuery->fetch_assoc();
$totalSpent = $spentRow['spent'] ?? 0;

$netSavings = $totalIncome - $totalSpent;

// Daily spending (last 7 days)
$dailyQuery = $conn->query("SELECT DATE(expense_date) AS day, SUM(amount) AS total
                            FROM expenses
                            WHERE user_id=$uid AND expense_date >= CURDATE() - INTERVAL 7 DAY
                            GROUP BY day ORDER BY day ASC");
$dailyData = [];
while($row = $dailyQuery->fetch_assoc()) {
    $dailyData[$row['day']] = $row['total'];
}

// Spending breakdown by category
$categoryQuery = $conn->query("SELECT c.category_name, SUM(e.amount) AS total
                               FROM expenses e
                               JOIN categories c ON e.category_id = c.id
                               WHERE e.user_id=$uid
                               GROUP BY c.category_name");
$categories = [];
$categoryTotals = [];
while($row = $categoryQuery->fetch_assoc()) {
    $categories[] = $row['category_name'];
    $categoryTotals[] = $row['total'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports - AllowanceSense</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h2>Financial Reports</h2>

        <!-- Summary -->
        <div class="summary">
            <div class="card"><h4>Total Income</h4><h2>₱<?= number_format($totalIncome, 2) ?></h2></div>
            <div class="card"><h4>Total Spent</h4><h2>₱<?= number_format($totalSpent, 2) ?></h2></div>
            <div class="card"><h4>Net Savings</h4><h2>₱<?= number_format($netSavings, 2) ?></h2></div>
        </div>

        <!-- Daily Spending Chart -->
        <h3>Daily Spending (Last 7 Days)</h3>
        <canvas id="dailyChart"></canvas>

        <!-- Spending Breakdown Pie Chart -->
        <h3>Spending Breakdown</h3>
        <canvas id="categoryChart"></canvas>

        <!-- Category Analysis Table -->
        <h3>Category Analysis</h3>
        <table>
            <tr><th>Category</th><th>Total Spent</th><th>% of Total</th></tr>
            <?php foreach($categories as $i => $cat): 
                $percent = $totalSpent > 0 ? round(($categoryTotals[$i] / $totalSpent) * 100, 1) : 0;
            ?>
            <tr>
                <td><?= htmlspecialchars($cat) ?></td>
                <td>₱<?= number_format($categoryTotals[$i], 2) ?></td>
                <td><?= $percent ?>%</td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <script>
        // Daily Spending Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($dailyData)) ?>,
                datasets: [{
                    label: 'Daily Spending',
                    data: <?= json_encode(array_values($dailyData)) ?>,
                    backgroundColor: '#2563EB'
                }]
            }
        });

        // Category Breakdown Pie Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($categories) ?>,
                datasets: [{
                    data: <?= json_encode($categoryTotals) ?>,
                    backgroundColor: ['#EF4444','#F97316','#22C55E','#A855F7','#EC4899','#3B82F6','#9CA3AF']
                }]
            }
        });
    </script>
</body>
</html>
