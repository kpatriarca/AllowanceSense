<?php
session_start();
date_default_timezone_set('Asia/Manila');
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

$selectedMonth = $_GET['month'] ?? date("m");
$year = date("Y");

$monthStart = "$year-$selectedMonth-01";
$monthEnd = date("Y-m-t", strtotime($monthStart));

/* UPDATE SAVINGS GOAL */
if (isset($_POST['update_goal'])) {

    $new_goal = floatval($_POST['new_goal']);

    $stmt = $conn->prepare("
        UPDATE users 
        SET savings_goal = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("di", $new_goal, $uid);
    $stmt->execute();

    header("Location: dashboard.php?month=".$selectedMonth."&goal_updated=1");
    exit();
}

$full_name = $_SESSION['full_name'];

/* GET USER SAVINGS GOAL */
$userQuery = $conn->query("
SELECT savings_goal, profile_pic 
FROM users 
WHERE id=$uid
");

$userRow = $userQuery->fetch_assoc();

$savings_goal = $userRow['savings_goal'] ?? 0;
$profile_pic = $userRow['profile_pic'] ?? "";

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

$allowanceQuery = $conn->query("
SELECT
    s.amount
    + (
        SELECT IFNULL(SUM(amount),0)
        FROM allowances
        WHERE user_id = $uid
        AND type='add'
        AND MONTH(start_date) = $selectedMonth
        AND YEAR(start_date) = $year
    )
    - (
        SELECT IFNULL(SUM(amount),0)
        FROM allowances
        WHERE user_id = $uid
        AND type='less'
        AND MONTH(start_date) = $selectedMonth
        AND YEAR(start_date) = $year
    ) AS total_allowance,
    s.start_date,
    s.end_date

FROM allowances s
WHERE s.user_id = $uid
AND s.type='set'
AND MONTH(s.start_date) = $selectedMonth
AND YEAR(s.start_date) = $year
ORDER BY s.id DESC
LIMIT 1
");

$allowanceRow = $allowanceQuery->fetch_assoc();

$allowance = $allowanceRow['total_allowance'] ?? 0;
$start_date = $allowanceRow['start_date'] ?? null;
$end_date = $allowanceRow['end_date'] ?? null;

/* TOTAL EXPENSES */
if ($start_date && $end_date) {
    $totalQuery = $conn->query("
        SELECT SUM(amount) AS total
        FROM expenses
        WHERE user_id = $uid
        AND expense_date BETWEEN '$monthStart' AND '$monthEnd'
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
    LIMIT 12
");

/* SPENDING BY CATEGORY */
$categoryLabels = [];
$categoryData = [];
$categoryColors = [];

$catQuery = $conn->query("
SELECT c.category_name, c.color, SUM(e.amount) as total
FROM expenses e
JOIN categories c ON e.category_id = c.id
WHERE e.user_id = $uid
AND e.expense_date BETWEEN '$monthStart' AND '$monthEnd'
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
        <title>Dashboard</title>
        <link rel="stylesheet" href="css/style.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </head>
    <body>
        <div class="dashboard">
            <?php
                $initial = strtoupper(substr($full_name, 0, 1));
            ?>
            <div class="sidebar">
                <div class="sidebar-logo">
                    <img src="img/logo-icon.png" class="logo-img">
                    <h2>AllowanceSense</h2>
                </div>
                <div class="sidebar-menu">
                    <a href="dashboard.php" class="active">🌐  Dashboard</a>
                    <a href="expenses.php">💸  Expenses</a>
                    <a href="allowances.php">💰  Allowance</a>
                    <a href="budgets.php">⚖️  Budget</a>
                    <a href="reports.php">📊  Reports</a>
                    <a href="categories.php">🏷️  Categories</a>
                    <a href="activity_log.php">📃  Activity Log</a>
                    <a href="settings.php">⚙️  Settings</a>
                </div>
                <div class="sidebar-bottom">
                    <a href="settings.php" class="user-profile">
                        <div class="avatar">
                        <?php if(!empty($profile_pic)){ ?>
                            <img src="<?= $profile_pic ?>" 
                            style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                        <?php }else{ ?>
                        <?= $initial ?>
                        <?php } ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($full_name) ?></strong>
                        </div>
                    </a>
                        <a href="logout.php" class="logout-btn">➜] Logout</a>
                </div>
            </div>
        
        <div class="content">
            <div class="top-header">
                <div>
                    <h1><?= $greeting ?>, <?= htmlspecialchars($full_name) ?>!</h1>
                    <div class="date-month-row">
                    <p>It's <?= $currentDate ?></p>
                    <form method="GET" class="month-form">
                    <select name="month" onchange="this.form.submit()" class="month-filter">
                        <?php
                    $currentMonth = date("m");
                    for ($m = 1; $m <= 12; $m++) {
                        $value = str_pad($m, 2, "0", STR_PAD_LEFT);
                        $selected = (isset($_GET['month']) && $_GET['month'] == $value) || (!isset($_GET['month']) && $value == $currentMonth) ? "selected" : "";
                        echo "<option value='$value' $selected>" . date("F", mktime(0,0,0,$m,1)) . "</option>";
                    }
                    ?>
                    </select>
                    </form>
                    </div>
                    
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
            <div class="cards">
                <div class="card dashboard-card">
                    <div class="card-logo blue">
                        <img src="img/logo-icon.png">
                    </div>
                <div class="card-info">
                    <h4>Remaining Balance</h4>
                    <h2>₱<?= number_format($remaining,2) ?></h2>
                </div>
                </div>
                <div class="card dashboard-card">
                    <div class="card-icon red">
                        <img src="img/down-icon.png">
                    </div>
                <div class="card-info">
                    <h4>Total Expenses</h4>
                    <h2>₱<?= number_format($total,2) ?></h2>
                </div>
                </div>
                <div class="card dashboard-card">
                    <div class="card-icon blue">
                        <img src="img/allowance-icon.png">
                    </div>
                <div class="card-info">
                    <h4>Monthly Allowance</h4>
                    <h2>₱<?= number_format($allowance,2) ?></h2>
                </div>
                </div>
                <?php
                $displayGoal = "₱" . number_format($savings_goal, 2);
                $savingsColor = "#057796";

                if ($savings_goal > $remaining) {
                    $displayGoal = "-₱" . number_format($savings_goal, 2);
                    $savingsColor = "#ef4444";
                }
                ?>
                <div class="card dashboard-card">
                    <div class="card-icon yellow">
                        <img src="img/savings-icon.png">
                    </div>
                    <div class="card-info">
                        <h4>Savings Goal</h4>
                        <h2 style="color:<?= $savingsColor ?>">
                            <?= $displayGoal ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="main-grid">
                <div>
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
                <div>
                    <div class="box chart-box">
                        <h3>Spending by Category</h3>
                        <canvas id="categoryChart"></canvas>
                    </div>
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
                            $isOver = $spent > $limit;
                            $barColor = $isOver ? "#ef4444" : "#057796";
                            $percent = min($percent, 100);
                        ?>

                            <p style="color:<?= $isOver ? '#ef4444' : '#222b36' ?>">
                                <?= $b['category_name'] ?> 
                                (₱<?= number_format($spent,2) ?> / ₱<?= number_format($limit,2) ?>)
                            </p>
                            <div class="progress">
                                <div class="progress-bar"
                                    style="width:<?= $percent ?>%; background:<?= $barColor ?>">
                                </div>
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

            window.onload = function() {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('goal_updated')) {
                    document.getElementById("goalForm").style.display = "none";
                    document.getElementById("goalBtn").style.display = "inline-block";
                }
            }

            if(localStorage.getItem("darkmode") === null){
                localStorage.setItem("darkmode","enabled");
            }

            if(localStorage.getItem("darkmode") === "enabled"){
                document.body.classList.add("dark-mode");
            }
        </script>
    </body>
</html>
