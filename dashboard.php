<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];
/* UPDATE SAVINGS GOAL */
if (isset($_POST['update_goal'])) {
    $new_goal = floatval($_POST['new_goal']);

    $updateGoal = $conn->prepare("UPDATE users SET savings_goal=? WHERE id=?");
    $updateGoal->bind_param("di", $new_goal, $uid);
    $updateGoal->execute();

    header("Location: dashboard.php");
    exit();
}

$full_name = $_SESSION['full_name'];

/* GET USER SAVINGS GOAL */
$userQuery = $conn->query("SELECT savings_goal FROM users WHERE id=$uid");
$userRow = $userQuery->fetch_assoc();
$savings_goal = $userRow['savings_goal'] ?? 0;

/* GREETING */
$hour = date("H");
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

$currentDate = date("F j, Y, g:i A");

/* MONTHLY ALLOWANCE */
/* CURRENT ACTIVE ALLOWANCE */
$allowanceQuery = $conn->query("
    SELECT * FROM allowances
    WHERE user_id = $uid
    AND CURDATE() BETWEEN start_date AND end_date
    ORDER BY id DESC
    LIMIT 1
");

$allowanceRow = $allowanceQuery->fetch_assoc();

$allowance = $allowanceRow['amount'] ?? 0;
$start_date = $allowanceRow['start_date'] ?? null;
$end_date = $allowanceRow['end_date'] ?? null;

/* TOTAL EXPENSES (THIS MONTH ONLY) */
/* TOTAL EXPENSES (WITHIN ALLOWANCE PERIOD) */
if ($start_date && $end_date) {
    $totalQuery = $conn->query("
        SELECT SUM(amount) AS total
        FROM expenses
        WHERE user_id = $uid
        AND expense_date BETWEEN '$start_date' AND '$end_date'
    ");
} else {
    $totalQuery = $conn->query("
        SELECT SUM(amount) AS total
        FROM expenses
        WHERE user_id = $uid
    ");
}

$totalRow = $totalQuery->fetch_assoc();
$total = $totalRow['total'] ?? 0;
$remaining = max(0, $allowance - $total);

/* RECENT EXPENSES */
$recentExpenses = $conn->query("
    SELECT e.description, e.amount, c.category_name, c.color 
    FROM expenses e 
    LEFT JOIN categories c ON e.category_id = c.id 
    WHERE e.user_id=$uid 
    ORDER BY e.expense_date DESC
");

/* SPENDING BY CATEGORY (THIS MONTH ONLY) */
$categoryLabels = [];
$categoryData = [];
$categoryColors = [];

$catQuery = $conn->query("
SELECT c.category_name, c.color, SUM(e.amount) as total
FROM expenses e
JOIN categories c ON e.category_id = c.id
WHERE e.user_id = $uid
AND e.expense_date BETWEEN '$start_date' AND '$end_date'
GROUP BY e.category_id
");

while ($row = $catQuery->fetch_assoc()) {

$categoryLabels[] = $row['category_name'];
$categoryData[] = $row['total'];
$categoryColors[] = $row['color'];

}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - AllowanceSense</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="dashboard">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h2>AllowanceSense</h2>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="expenses.php">Expenses</a>
        <a href="allowances.php">Allowance</a>
        <a href="budgets.php">Budget</a>
        <a href="reports.php">Reports</a>
        <a href="categories.php">Categories</a>
        <a href="activity_log.php">Activity Log</a>
        <a href="settings.php">Settings</a>
        <a href="logout.php">Logout</a>
    </div>

    <!-- CONTENT -->
    <div class="content">

        <!-- HEADER -->
        <div class="top-header">
            <div>
                <h1><?= $greeting ?>, <?= htmlspecialchars($full_name) ?>!</h1>
                <p>It's <?= $currentDate ?></p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <form method="POST" id="goalForm" class="goal-box" style="display:none; gap:5px; align-items:center;">
                    <input type="number" name="new_goal" step="0.01" 
                        placeholder="Enter amount" required>

                    <button type="submit" name="update_goal" class="btn">Save</button>
                </form>

                <button onclick="toggleGoalForm()" id="goalBtn" class="btn">
                    Set Savings Goal
                </button>

                <a href="allowances.php" class="btn">Set Allowance</a>
                <a href="expenses.php" class="btn">Add Expense</a>

            </div>
        </div>

        <!-- SUMMARY CARDS -->
        <div class="cards">
            <div class="card">
                <h4>Remaining Balance</h4>
                <h2>₱<?= number_format($remaining,2) ?></h2>
            </div>
            <div class="card">
                <h4>Total Expenses</h4>
                <h2>₱<?= number_format($total,2) ?></h2>
            </div>
            <div class="card">
                <h4>Monthly Allowance</h4>
                <h2>₱<?= number_format($allowance,2) ?></h2>
            </div>
            <div class="card">
                <h4>Savings Goal</h4>
                <h2>₱<?= number_format($savings_goal,2) ?></h2>
            </div>
        </div>

        <div class="main-grid">

            <!-- LEFT SIDE -->
            <div>

                <!-- RECENT EXPENSES -->
                <div class="box expense-box">
                    <h3>Recent Expenses</h3>

                    <?php while($row = $recentExpenses->fetch_assoc()): ?>

                    <div class="expense-item">

                        <div class="expense-left">
                            <div class="expense-icon" style="background:<?= $row['color'] ?? '#ccc' ?>">
                                <?= strtoupper(substr($row['category_name'],0,1)) ?>
                            </div>

                            <div>
                                <strong><?= htmlspecialchars($row['description']) ?></strong><br>
                                <small><?= $row['category_name'] ?></small>
                            </div>
                        </div>

                        <div>
                            ₱<?= number_format($row['amount'],2) ?>
                        </div>

                    </div>

                    <?php endwhile; ?>

                </div>

            </div>

            <!-- RIGHT SIDE -->
            <div>

                <!-- CHART -->
                <div class="box chart-box">
                    <h3>Spending by Category</h3>
                    <canvas id="categoryChart"></canvas>
                </div>

                <!-- BUDGET STATUS -->
                <div class="status-box">
                    <h3>Budget Status</h3>

                    <?php
                    $budgetQuery = $conn->query("
                        SELECT b.category_id, b.budget_limit, c.category_name
                        FROM budgets b
                        JOIN categories c ON b.category_id = c.id
                        WHERE b.user_id=$uid
                    ");

                    while ($b = $budgetQuery->fetch_assoc()) {

                        $category_id = $b['category_id'];
                        $limit = $b['budget_limit'];

                        $spentQuery = $conn->query("
                            SELECT SUM(amount) as spent
                            FROM expenses
                            WHERE user_id=$uid
                            AND category_id=$category_id
                        ");

                        $spentRow = $spentQuery->fetch_assoc();
                        $spent = $spentRow['spent'] ?? 0;

                        $percent = $limit > 0 ? ($spent / $limit) * 100 : 0;
                        $percent = min($percent, 100);
                    ?>

                        <p><?= $b['category_name'] ?> 
                           (₱<?= number_format($spent,2) ?> / ₱<?= number_format($limit,2) ?>)
                        </p>
                        <div class="progress">
                            <div class="progress-bar" style="width:<?= $percent ?>%"></div>
                        </div>

                    <?php } ?>
                    <div class="manage-budget-container">
                        <a href="budgets.php" class="manage-budget-btn">Manage Budgets</a>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<script>
const labels = <?= json_encode($categoryLabels) ?>;
const dataValues = <?= json_encode($categoryData) ?>;

if (labels.length > 0) {
        new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: dataValues,
                backgroundColor: <?= json_encode($categoryColors) ?>
            }]
        },
        options: {
            responsive:true,
            maintainAspectRatio:false,
            plugins:{
                legend:{
                    position:'bottom'
                }
            }
        }
    });
} else {
    document.getElementById('categoryChart').outerHTML =
        "<p style='text-align:center;color:gray;'>No expense data for this month</p>";
}
function toggleGoalForm() {
    const form = document.getElementById("goalForm");
    const btn = document.getElementById("goalBtn");

    if (form.style.display === "none") {
        form.style.display = "flex";
        btn.style.display = "none";
    }
}

// After saving, hide form automatically
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('goal_updated')) {
        document.getElementById("goalForm").style.display = "none";
        document.getElementById("goalBtn").style.display = "inline-block";
    }
}

</script>

</body>
</html>
