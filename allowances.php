<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

/* ======================
   HANDLE FORM
====================== */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $amount     = floatval($_POST['amount']);
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];

    if ($start_date > $end_date) {
        die("Start date cannot be later than end date.");
    }

    // Insert NEW record (keeps history)
    $stmt = $conn->prepare("INSERT INTO allowances (user_id, amount, start_date, end_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("idss", $uid, $amount, $start_date, $end_date);
    $stmt->execute();

    header("Location: allowances.php");
    exit();
}

/* ======================
   CURRENT ALLOWANCE
====================== */
$currentQuery = $conn->prepare("SELECT * FROM allowances WHERE user_id=? ORDER BY id DESC LIMIT 1");
$currentQuery->bind_param("i", $uid);
$currentQuery->execute();
$currentAllowance = $currentQuery->get_result()->fetch_assoc();

/* ======================
   HISTORY
====================== */
$historyQuery = $conn->prepare("SELECT * FROM allowances WHERE user_id=? ORDER BY id DESC");
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
<div class="sidebar">
        <h2>AllowanceSense</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="expenses.php">Expenses</a>
        <a href="allowances.php" class="active">Allowance</a>
        <a href="budgets.php">Budget</a>
        <a href="reports.php">Reports</a>
        <a href="categories.php">Categories</a>
        <a href="activity_log.php">Activity Log</a>
        <a href="settings.php">Settings</a>
        <a href="logout.php">Logout</a>
    </div>
<div class="content allowances-page">

<h2 class="page-title">Allowance Management</h2>

<!-- TOP GRID -->
<div class="allowance-grid">

    <!-- CURRENT ALLOWANCE -->
    <div class="card large-card">
        <h4 class="card-label">Current Allowance</h4>

        <?php if($currentAllowance): ?>
            <div class="date-range">
                <?= date("m/d/Y", strtotime($currentAllowance['start_date'])) ?>
                -
                <?= date("m/d/Y", strtotime($currentAllowance['end_date'])) ?>
            </div>

            <div class="allowance-amount">
                ₱<?= number_format($currentAllowance['amount'],2) ?>
            </div>
        <?php else: ?>
            <p>No allowance yet.</p>
        <?php endif; ?>
    </div>

    <!-- UPDATE FORM -->
    <div class="card form-card">
        <h4>Update Allowance</h4>

        <form method="POST" class="modern-form">

            <label>Amount (₱)</label>
            <input type="number" name="amount" step="0.01" required>

            <label>Start Date</label>
            <input type="date" name="start_date" required>

            <label>End Date</label>
            <input type="date" name="end_date" required>

            <div class="form-buttons">
                <button type="submit" class="btn primary-btn">Save</button>
                <button type="reset" class="btn cancel-btn">Cancel</button>
            </div>
        </form>
    </div>

</div>

<!-- HISTORY -->
<div class="card history-card">
    <h4>Allowance History</h4>

    <?php while($row = $historyResult->fetch_assoc()): ?>
        <div class="history-item">
            <div>
                <strong>
                    <?= date("F Y", strtotime($row['start_date'])) ?>
                </strong>
                <div class="history-date">
                    <?= date("M d", strtotime($row['start_date'])) ?>
                    -
                    <?= date("M d", strtotime($row['end_date'])) ?>
                </div>
            </div>

            <div class="history-amount">
                ₱<?= number_format($row['amount'],2) ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>

</div>

</body>
</html>