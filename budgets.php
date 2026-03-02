<?php
session_start();
include("config/connection.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// Fetch budgets with category names
$budgetQuery = $conn->query("SELECT b.id, b.budget_limit, c.category_name,
                                    (SELECT SUM(amount) FROM expenses e WHERE e.category_id = b.category_id AND e.user_id=$uid) AS spent
                             FROM budgets b
                             JOIN categories c ON b.category_id = c.id
                             WHERE b.user_id=$uid");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Budgets - AllowanceSense</title>
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
        .progress-bar {
            width: 100%;
            background: #eee;
            border-radius: 5px;
            overflow: hidden;
        }
        .progress-fill {
            height: 20px;
            background: #22C55E;
            text-align: center;
            color: #fff;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>AllowanceSense</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="expenses.php">Expenses</a>
        <a href="allowances.php">Allowance</a>
        <a href="budgets.php" class="active">Budget</a>
        <a href="reports.php">Reports</a>
        <a href="categories.php">Categories</a>
        <a href="activity_log.php">Activity Log</a>
        <a href="settings.php">Settings</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="container">
        <h2>Budget Management</h2>

        <table>
            <tr>
                <th>Category</th>
                <th>Spent</th>
                <th>Limit</th>
                <th>Status</th>
            </tr>
            <?php while($row = $budgetQuery->fetch_assoc()): 
                $spent = $row['spent'] ?? 0;
                $limit = $row['budget_limit'];
                $percent = $limit > 0 ? round(($spent / $limit) * 100) : 0;
            ?>
            <tr>
                <td><?= htmlspecialchars($row['category_name']); ?></td>
                <td>₱<?= number_format($spent, 2); ?></td>
                <td>₱<?= number_format($limit, 2); ?></td>
                <td>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $percent ?>%">
                            <?= $percent ?>%
                        </div>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>

        <h3>Add / Update Budget</h3>
        <form method="POST" action="set_budget.php">
            <label>Category</label>
            <select name="category_id" required>
                <?php
                $categories = $conn->query("SELECT * FROM categories WHERE user_id=$uid");
                while($cat = $categories->fetch_assoc()):
                ?>
                    <option value="<?= $cat['id']; ?>"><?= htmlspecialchars($cat['category_name']); ?></option>
                <?php endwhile; ?>
            </select>

            <label>Budget Limit</label>
            <input type="number" name="budget_limit" step="0.01" required>

            <button type="submit">Save Budget</button>
        </form>
    </div>
</body>
</html>
