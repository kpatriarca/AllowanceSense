<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

$full_name = $_SESSION['full_name'];
$userPicQuery = $conn->prepare("SELECT profile_pic FROM users WHERE id=?");
$userPicQuery->bind_param("i", $uid);
$userPicQuery->execute();
$userPicResult = $userPicQuery->get_result()->fetch_assoc();
$profile_pic = $userPicResult['profile_pic'] ?? "";

/* TOTALS */
$incomeQuery = $conn->query("

SELECT
    s.amount
    + (
        SELECT IFNULL(SUM(amount),0)
        FROM allowances
        WHERE user_id = $uid
        AND type='add'
        AND id > s.id
    )
    - (
        SELECT IFNULL(SUM(amount),0)
        FROM allowances
        WHERE user_id = $uid
        AND type='less'
        AND id > s.id
    ) AS income

FROM allowances s

WHERE s.user_id = $uid
AND s.type='set'

ORDER BY s.id DESC
LIMIT 1

");

$totalIncome = $incomeQuery->fetch_assoc()['income'] ?? 0;

$spentQuery = $conn->query("SELECT SUM(amount) AS spent FROM expenses WHERE user_id=$uid");
$totalSpent = $spentQuery->fetch_assoc()['spent'] ?? 0;

$netSavings = $totalIncome - $totalSpent;

/* DAILY SPENDING */
$dailyQuery = $conn->query("
SELECT DATE(expense_date) AS day, SUM(amount) AS total
FROM expenses
WHERE user_id=$uid AND expense_date >= CURDATE() - INTERVAL 7 DAY
GROUP BY day ORDER BY day ASC
");

$dailyLabels = [];
$dailyTotals = [];

while($row = $dailyQuery->fetch_assoc()){
    $dailyLabels[] = $row['day'];
    $dailyTotals[] = $row['total'];
}

/* CATEGORY BREAKDOWN */
$categoryQuery = $conn->query("
SELECT c.category_name, c.color, SUM(e.amount) AS total
FROM expenses e
JOIN categories c ON e.category_id = c.id
WHERE e.user_id=$uid
GROUP BY e.category_id
");

$categories = [];
$categoryTotals = [];
$categoryColors = [];

while($row = $categoryQuery->fetch_assoc()){
    $categories[] = $row['category_name'];
    $categoryTotals[] = $row['total'];
    $categoryColors[] = $row['color'];
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
                    <a href="dashboard.php">🌐  Dashboard</a>
                    <a href="expenses.php">💸  Expenses</a>
                    <a href="allowances.php">💰  Allowance</a>
                    <a href="budgets.php">⚖️  Budget</a>
                    <a href="reports.php" class="active">📊  Reports</a>
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
                    <h1>Financial Reports</h1>
                    <p>Overview of your income and spending</p>
                </div>
            </div>
            <div class="cards">
                <div class="card">
                    <h4>Total Income</h4>
                    <h2>₱<?=number_format($totalIncome,2)?></h2>
                </div>
                <div class="card">
                    <h4>Total Spent</h4>
                    <h2>₱<?=number_format($totalSpent,2)?></h2>
                </div>
                <div class="card">
                    <h4>Net Savings</h4>
                    <h2>₱<?=number_format($netSavings,2)?></h2>
                </div>
            </div>
            <div class="main-grid">
                <div class="box">
                    <h3>Daily Spending (Last 7 Days)</h3>
                    <canvas id="dailyChart" class="chart-small"></canvas>
                </div>
                <div class="box">
                    <h3>Spending Breakdown</h3>
                    <canvas id="categoryChart" class="chart-small"></canvas>
                </div>
            </div>
                <div class="box" style="margin-top:20px">
                    <h3>Category Analysis</h3>
                    <table>
                        <tr>
                            <th>Category</th>
                            <th>Total Spent</th>
                            <th>% of Total</th>
                        </tr>

                        <?php foreach($categories as $i=>$cat):

                        $percent = $totalSpent>0 ? round(($categoryTotals[$i]/$totalSpent)*100,1) : 0;
                        ?>

                        <tr>
                            <td><?=htmlspecialchars($cat)?></td>
                            <td>₱<?=number_format($categoryTotals[$i],2)?></td>
                            <td><?=$percent?>%</td>
                        </tr>

                        <?php endforeach; ?>
                    </table>
                </div>
        </div>
        </div>
        <script>
            new Chart(document.getElementById('dailyChart'),{
                type:'bar',
                data:{
                labels:<?=json_encode($dailyLabels)?>,
                datasets:[{
                label:'Daily Spending',
                data:<?=json_encode($dailyTotals)?>,
                backgroundColor:'#2563EB'
                }]
                },
                options:{
                responsive:true,
                maintainAspectRatio:false
                }
            });

            new Chart(document.getElementById('categoryChart'),{
                type:'doughnut',
                data:{
                labels:<?=json_encode($categories)?>,
                datasets:[{
                data:<?=json_encode($categoryTotals)?>,
                backgroundColor:<?=json_encode($categoryColors)?>
                }]
                },
                options:{
                responsive:true,
                maintainAspectRatio:false
                }
            });

            if(localStorage.getItem("darkmode") === null) {
                localStorage.setItem("darkmode","enabled");
            }

            if(localStorage.getItem("darkmode") === "enabled") {
                document.body.classList.add("dark-mode");
            }
        </script>
    </body>
</html>