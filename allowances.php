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

/* HANDLE FORM */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if(!isset($_POST['amount']) || $_POST['amount'] === ''){
        header("Location: allowances.php");
        exit();
    }

    $amount = floatval($_POST['amount']);
    $type   = $_POST['type'];

    /* MONTHLY ALLOWANCE */
   if ($type == "set") {

    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';

    if(empty($start_date) || empty($end_date) || $start_date > $end_date){
        header("Location: allowances.php");
        exit();
    }

    $closeOld = $conn->prepare("
        UPDATE allowances 
        SET end_date = CURDATE()
        WHERE user_id = ?
        AND type = 'set'
        AND end_date >= CURDATE()
    ");

    $closeOld->bind_param("i",$uid);
    $closeOld->execute();

    }

    /* ADD / LESS ALLOWANCE */
    else {

        $activeQuery = $conn->prepare("
            SELECT start_date, end_date
            FROM allowances
            WHERE user_id=? AND type='set'
            ORDER BY id DESC
            LIMIT 1
        ");

        $activeQuery->bind_param("i",$uid);
        $activeQuery->execute();
        $active = $activeQuery->get_result()->fetch_assoc();

        if(!$active){
            header("Location: allowances.php");
            exit();
        }

        $start_date = $active['start_date'];
        $end_date   = $active['end_date'];
    }

    /* INSERT RECORD */
    $stmt = $conn->prepare("
        INSERT INTO allowances (user_id, amount, start_date, end_date, type)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("idsss",$uid,$amount,$start_date,$end_date,$type);
    $stmt->execute();

    /* ACTIVITY LOG */

    if($type=="set"){
        logActivity($conn,$uid,"Updated Allowance","Set monthly allowance ₱".$amount);
    }

    if($type=="add"){
        logActivity($conn,$uid,"Allowance Adjustment","Added ₱".$amount." to allowance");
    }

    if($type=="less"){
        logActivity($conn,$uid,"Allowance Adjustment","Deducted ₱".$amount." from allowance");
    }

    header("Location: allowances.php");
    exit();
}

/* CURRENT ALLOWANCE */
$currentQuery = $conn->prepare("

SELECT
    s.amount
    + (
        SELECT IFNULL(SUM(amount),0)
        FROM allowances
        WHERE user_id = ?
        AND type='add'
        AND id > s.id
    )
    - (
        SELECT IFNULL(SUM(amount),0)
        FROM allowances
        WHERE user_id = ?
        AND type='less'
        AND id > s.id
    ) AS total_allowance,
    s.start_date,
    s.end_date

FROM allowances s

WHERE s.user_id = ?
AND s.type='set'

ORDER BY s.id DESC
LIMIT 1

");

$currentQuery->bind_param("iii",$uid,$uid,$uid);
$currentQuery->execute();
$currentAllowance = $currentQuery->get_result()->fetch_assoc();

/* HISTORY */
$historyQuery = $conn->prepare("SELECT *, DATE_FORMAT(created_at,'%M %Y') as month_group
FROM allowances
WHERE user_id=?
ORDER BY created_at DESC");
$historyQuery->bind_param("i", $uid);
$historyQuery->execute();
$historyResult = $historyQuery->get_result();

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Allowance Management</title>
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
                    <a href="allowances.php" class="active">💰  Allowance</a>
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

        <div class="content allowances-page">
            <div>
                <h1 class="page-title">Allowance Management</h1>
                <p class="page-sub">Set or update your allowance for the month</p>
            </div>
            <div class="allowance-grid">
                <div class="card large-card">
                    <h4 class="card-label">Current Allowance</h4>
                    <?php if($currentAllowance): ?>
                    <div class="date-range">
                        <?= date("m/d/Y", strtotime($currentAllowance['start_date'])) ?>
                        -
                        <?= date("m/d/Y", strtotime($currentAllowance['end_date'])) ?>
                    </div>
                    <div class="allowance-amount">
                        ₱<?= number_format($currentAllowance['total_allowance'] ?? 0,2) ?>
                    </div>
                    <?php else: ?>
                        <p>No allowance yet.</p>
                    <?php endif; ?>
                </div>
                <div class="card form-card">
                    <h4>Update Allowance</h4>
                    <form method="POST" class="modern-form">
                    <label>Amount (₱)</label>
                    <input type="number" name="amount" step="0.01" required>
                    <div class="form-group">
                        <label>Action</label>
                    <div class="action-container">
                        <select name="type" id="allowanceType" onchange="toggleDates()">
                        <option value="set">Set Monthly Allowance</option>
                        <option value="add">Add Allowance</option>
                        <option value="less">Less Allowance</option>
                        </select>
                    </div>
                    </div>
                    <div id="dateFields">
                    <label>Start Date</label>
                        <input type="date" name="start_date" id="startDate">
                    <label>End Date</label>
                        <input type="date" name="end_date" id="endDate">
                    </div>
                    <div class="form-buttons">
                        <button type="submit" class="btn primary-btn">Save</button>
                        <button type="reset" class="btn cancel-btn">Cancel</button>
                    </div>
                    </form>
                </div>
            </div>
            <div class="card history-card">
                <h4>Allowance History</h4>
                <?php 
                $currentMonth = "";

                while($row = $historyResult->fetch_assoc()) {

                if($currentMonth != $row['month_group']){
                    $currentMonth = $row['month_group'];

                    echo "<h3 class='history-month'>$currentMonth</h3>";
                }

                $type = $row['type'];

                $color = "#3b82f6";
                $sign = "";

                if($type == "set"){
                    $color = "#6366f1";
                }
                elseif($type == "add"){
                    $color = "#22c55e";
                    $sign = "+";
                }
                elseif($type == "less"){
                    $color = "#ef4444";
                    $sign = "-";
                }
                ?>
            <div class="history-item">
                <div>
                    <strong style="color:<?= $color ?>">
                        <?= ucfirst($row['type']) ?> Allowance
                    </strong>

                    <div class="history-date">
                        <?= date("M d", strtotime($row['created_at'])) ?>
                    </div>
                </div>
            <div class="history-amount" style="color:<?= $color ?>">
                <?= $sign ?>₱<?= number_format($row['amount'],2) ?>
            </div>
            </div>
            <?php } ?>
            </div>
        </div>
        <script>
            document.querySelector(".modern-form").addEventListener("submit", function(e) {

                let type = document.getElementById("allowanceType").value;
                let start = document.getElementById("startDate").value;
                let end = document.getElementById("endDate").value;

                if(type === "set"){

                    if(start && end && start > end){
                        e.preventDefault();
                        alert("Start date cannot be later than End date.");
                    }

                }
            });

            function toggleDates() {

                let type = document.getElementById("allowanceType").value;
                let dates = document.getElementById("dateFields");

                let start = document.getElementById("startDate");
                let end = document.getElementById("endDate");

                if(type === "set") {

                    dates.style.display = "block";
                    start.required = true;
                    end.required = true;

                } else {

                    dates.style.display = "none";
                    start.required = false;
                    end.required = false;

                }
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