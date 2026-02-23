<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// Handle new category submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['category_name'])) {
    $category_name = trim($_POST['category_name']);

    if (!empty($category_name)) {
        $stmt = $conn->prepare("INSERT INTO categories (user_id, category_name) VALUES (?, ?)");
        $stmt->bind_param("is", $uid, $category_name);
        $stmt->execute();
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM categories WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $delete_id, $uid);
    $stmt->execute();
}

// Fetch all categories
$categories = $conn->query("SELECT id, category_name FROM categories WHERE user_id=$uid ORDER BY category_name ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Categories - AllowanceSense</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #2563EB;
            color: #fff;
        }
        .delete-link {
            color: red;
            text-decoration: none;
        }
    </style>
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
        <h2>Manage Categories</h2>

        <!-- Add Category Form -->
        <form method="POST" action="">
            <label>New Category</label>
            <input type="text" name="category_name" required>
            <button type="submit">Add Category</button>
        </form>

        <!-- Categories Table -->
        <h3>Your Categories</h3>
        <table>
            <tr>
                <th>Category Name</th>
                <th>Action</th>
            </tr>
            <?php while($row = $categories->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['category_name']); ?></td>
                <td><a href="categories.php?delete=<?= $row['id']; ?>" class="delete-link">Delete</a></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
