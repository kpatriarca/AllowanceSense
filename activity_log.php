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

$search = $_GET['search'] ?? "";
$dateFilter = $_GET['date'] ?? "";

$sql = "SELECT action, details, log_time 
        FROM activity_log 
        WHERE user_id=?";

$params = [$uid];
$types = "i";

if (!empty($search)) {
    $sql .= " AND action LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($dateFilter == "today") {
    $sql .= " AND DATE(log_time)=CURDATE()";
}
elseif ($dateFilter == "week") {
    $sql .= " AND log_time >= NOW() - INTERVAL 7 DAY";
}
elseif ($dateFilter == "month") {
    $sql .= " AND log_time >= NOW() - INTERVAL 30 DAY";
}

$sql .= " ORDER BY log_time DESC LIMIT 50";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logQuery = $stmt->get_result();

/* STATISTICS */
$totalActions = $conn->query("
SELECT COUNT(*) as total 
FROM activity_log 
WHERE user_id=$uid
")->fetch_assoc()['total'];

$expenseActions = $conn->query("
SELECT COUNT(*) as total 
FROM activity_log 
WHERE user_id=$uid AND action LIKE '%Expense%'
")->fetch_assoc()['total'];

$categoryActions = $conn->query("
SELECT COUNT(*) as total 
FROM activity_log 
WHERE user_id=$uid AND action LIKE '%Category%'
")->fetch_assoc()['total'];

?>

<!DOCTYPE html>
<html>
    <head>
    <title>Activity Log</title>
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
                    <a href="budgets.php">⚖️  Budget</a>
                    <a href="reports.php">📊  Reports</a>
                    <a href="categories.php">🏷️  Categories</a>
                    <a href="activity_log.php" class="active">📃  Activity Log</a>
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
                    <h1>Activity Log</h1>
                    <p>System audit trail of your account activity.</p>
                </div>
            </div>

            <div class="activity-stats">
                <div class="stat-box">
                    <h3><?= $totalActions ?></h3>
                    <p>Total Actions</p>
                </div>
                <div class="stat-box">
                    <h3><?= $expenseActions ?></h3>
                    <p>Expense Actions</p>
                </div>
                <div class="stat-box">
                    <h3><?= $categoryActions ?></h3>
                    <p>Category Actions</p>
                </div>
            </div>
            <form method="GET" class="activity-search">
                <input type="text" name="search" placeholder="Search activity..."
                value="<?= htmlspecialchars($search) ?>">
                <select name="date" onchange="this.form.submit()">
                    <option value="">All Time</option>
                    <option value="today"
                    <?= $dateFilter=="today" ? "selected" : "" ?>>
                    Today
                    </option>
                    <option value="week"
                    <?= $dateFilter=="week" ? "selected" : "" ?>>
                    This Week
                    </option>
                    <option value="month"
                    <?= $dateFilter=="month" ? "selected" : "" ?>>
                    This Month
                    </option>
                </select>
            </form>

            <div class="activity-box">
                <h3>System Audit Trail</h3>
                <div class="timeline">
                <?php while($row = $logQuery->fetch_assoc()): ?>
                <div class="timeline-item">
                <div class="timeline-icon">•</div>
                <div class="timeline-content">
                <div class="timeline-main">
                <?php
                    $action = strtolower($row['action']);
                    $icon = "🔵";
                    $class = "log-update";

                    if(str_contains($action,"add") || str_contains($action,"create")){
                        $icon = "🟢";
                        $class = "log-create";
                    }
                    elseif(str_contains($action,"delete")){
                        $icon = "🔴";
                        $class = "log-delete";
                    }
                    elseif(str_contains($action,"login")){
                        $icon = "🟣";
                        $class = "log-login";
                    }
                ?>

                <strong class="<?= $class ?>">
                <?= $icon ?> <?= htmlspecialchars($row['action']) ?>
                </strong>

                <?php if(!empty($row['details'])): ?>
                <span><?= htmlspecialchars($row['details']) ?></span>
                <?php endif; ?>

                </div>
                <div class="timeline-time">
                    <?= date("m/d/Y, g:i A", strtotime($row['log_time'])) ?>
                </div>
                </div>
                </div>

                <?php endwhile; ?>
                
                </div>
            </div>
        </div>
        </div>
        <script>
            if(localStorage.getItem("darkmode") === null){
            localStorage.setItem("darkmode","enabled");
            }

            if(localStorage.getItem("darkmode") === "enabled"){
                document.body.classList.add("dark-mode");
            }
        </script>
    </body>
</html>