<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// Fetch current user info
$userQuery = $conn->prepare("SELECT full_name, email FROM users WHERE id=?");
$userQuery->bind_param("i", $uid);
$userQuery->execute();
$userResult = $userQuery->get_result();
$user = $userResult->fetch_assoc();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $email     = $_POST['email'];
    $password  = $_POST['password'];

    if (!empty($password)) {
        // Update with password
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, password=? WHERE id=?");
        $stmt->bind_param("sssi", $full_name, $email, $hashed, $uid);
    } else {
        // Update without password
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=? WHERE id=?");
        $stmt->bind_param("ssi", $full_name, $email, $uid);
    }

    if ($stmt->execute()) {
        $_SESSION['full_name'] = $full_name; // update session
        $success = "Settings updated successfully!";
    } else {
        $error = "Failed to update settings.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Settings - AllowanceSense</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="sidebar">
        <h2>AllowanceSense</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="expenses.php">Expenses</a>
        <a href="allowances.php">Allowance</a>
        <a href="budgets.php">Budget</a>
        <a href="reports.php">Reports</a>
        <a href="categories.php">Categories</a>
        <a href="activity_log.php">Activity Log</a>
        <a href="settings.php" class="active">Settings</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="container">
        <h2>Account Settings</h2>

        <?php if(isset($success)) echo "<p style='color:green;'>$success</p>"; ?>
        <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

        <form method="POST" action="">
            <label>Full Name</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']); ?>" required>

            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>

            <label>New Password (leave blank to keep current)</label>
            <input type="password" name="password">

            <button type="submit">Save Changes</button>
        </form>
    </div>
</body>
</html>
