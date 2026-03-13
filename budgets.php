<?php
session_start();
include("config/connection.php");
require_once __DIR__ . "/includes/logger.php";

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

/* SAVE BUDGET */
if(isset($_POST['save_budget'])){

    $category_id = $_POST['category_id'];
    $limit = $_POST['budget_limit'];

    $check = $conn->query("
    SELECT id FROM budgets
    WHERE user_id=$uid AND category_id=$category_id
    ");

    if($check->num_rows > 0){

        $conn->query("
        UPDATE budgets
        SET budget_limit='$limit'
        WHERE user_id=$uid AND category_id=$category_id
        ");

    } else {

        $conn->query("
        INSERT INTO budgets (user_id,category_id,budget_limit)
        VALUES ($uid,$category_id,'$limit')
        ");

    }

    $cat = $conn->query("SELECT category_name FROM categories WHERE id=$category_id")->fetch_assoc();

    logActivity(
        $conn,
        $uid,
        "Updated Budget Limit",
        "Category: ".$cat['category_name']." | Limit: ₱".$limit
    );

    header("Location: budgets.php");
    exit();
}

/* GET CATEGORIES */
$categories = $conn->query("
SELECT * FROM categories
WHERE user_id=$uid
");

/* GET BUDGETS */
$budgetData = [];

$b = $conn->query("SELECT * FROM budgets WHERE user_id=$uid");

while($row = $b->fetch_assoc()){
    $budgetData[$row['category_id']] = $row['budget_limit'];
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Budgets</title>
        <link rel="stylesheet" href="css/style.css">
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
                    <a href="budgets.php" class="active">⚖️  Budget</a>
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

        <div class="content budget-page">
            <h1 class="budget-title">Budget Limits</h1>
            <p class="budget-sub">Set spending limits for each category to stay on track.</p>
        <div class="budget-grid">
            <?php while($c = $categories->fetch_assoc()): ?>
            <?php
            $cid = $c['id'];
            $limit = $budgetData[$cid] ?? 0;

            $spentQuery = $conn->query("
            SELECT SUM(amount) as spent
            FROM expenses
            WHERE user_id=$uid
            AND category_id=$cid
            ");

            $row = $spentQuery->fetch_assoc();
            $spent = $row['spent'] ?? 0;

            $left = $limit - $spent;
            $percent = ($limit>0)?($spent/$limit)*100:0;
            $percent = min($percent,100);

            $letter = strtoupper(substr($c['category_name'],0,1));

            ?>
        <div class="budget-card">
            <div class="budget-top">
                <div class="budget-icon" style="background:<?= $c['color'] ?>">
                    <?= $letter ?>
                </div>
                <div class="budget-name">
                    <strong><?= $c['category_name'] ?></strong>
                    <span>Monthly Limit</span>
                </div>
                <button class="edit-btn" onclick="editBudget(<?= $cid ?>)">✏</button>
                </div>
                <h2 class="budget-amount" id="amount-<?= $cid ?>">
                    ₱<?= number_format($limit,2) ?>
                </h2>
                <form method="POST" class="budget-form" id="form-<?= $cid ?>">
                    <input type="hidden" name="category_id" value="<?= $cid ?>">
                    <input type="number" step="0.01" name="budget_limit" value="<?= $limit ?>" class="budget-input"/>
                    <button name="save_budget" class="save-btn">✔</button>
            </form>
        <div class="budget-info">
            <span>Spent: ₱<?= number_format($spent,2) ?></span>
            <?php if($left>=0): ?>
            <span>Left: ₱<?= number_format($left,2) ?></span>
            <?php else: ?>
            <span class="over">Over by ₱<?= number_format(abs($left),2) ?></span>
            <?php endif; ?>
        </div>
        <div class="progress">
            <div class="progress-bar" style="width:<?= $percent ?>%; background:<?= $c['color'] ?>"></div>
        </div>
        <?php if($left<0): ?>
        <div class="budget-warning">
            ⚠ You have exceeded your budget for this category!
        </div>
        <?php endif; ?>

        </div>
        <?php endwhile; ?>

        </div>
        </div>
        </div>
        <script>
            function editBudget(id) {

                document.getElementById("amount-"+id).style.display="none";
                document.getElementById("form-"+id).style.display="flex";

            }

            if(localStorage.getItem("darkmode") === null) {
                localStorage.setItem("darkmode","enabled");
            }

            if(localStorage.getItem("darkmode") === "enabled") {
                document.body.classList.add("dark-mode");
            }
        </script>
    </body>
</html>