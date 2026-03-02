<?php
session_start();
require 'config/connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

$search = isset($_GET['search']) ? $_GET['search'] : "";
$filter_category = isset($_GET['category']) ? $_GET['category'] : "";

/* =============================
   PAGINATION SETTINGS
============================= */
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$start = ($page - 1) * $limit;

/* =============================
   FETCH USER CATEGORIES
============================= */
$catQuery = $conn->prepare("SELECT id, category_name FROM categories WHERE user_id=?");
$catQuery->bind_param("i", $uid);
$catQuery->execute();
$catResult = $catQuery->get_result();

/* =============================
   HANDLE ADD EXPENSE
============================= */
if (isset($_POST['add_expense'])) {

    $desc = $_POST['description'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $category_id = $_POST['category_id'];

    $receiptName = NULL;

    if(!empty($_FILES['receipt']['name'])){
        $uploadDir = "uploads/";

        if(!is_dir($uploadDir)){
            mkdir($uploadDir, 0777, true);
        }

        $receiptName = time() . "_" . basename($_FILES['receipt']['name']);
        move_uploaded_file($_FILES['receipt']['tmp_name'], $uploadDir . $receiptName);
    }

    $stmt = $conn->prepare("
        INSERT INTO expenses(user_id, category_id, description, amount, expense_date, receipt)
        VALUES(?,?,?,?,?,?)
    ");
    $stmt->bind_param("iissss", $uid, $category_id, $desc, $amount, $date, $receiptName);
    $stmt->execute();

    header("Location: view_expenses.php");
    exit();
}

/* =============================
   HANDLE DELETE
============================= */
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $del = $conn->prepare("DELETE FROM expenses WHERE id=? AND user_id=?");
    $del->bind_param("ii", $id, $uid);
    $del->execute();
    header("Location: view_expenses.php");
    exit();
}

/* =============================
   SEARCH + FILTER QUERY
============================= */
$sql = "
SELECT e.*, c.category_name
FROM expenses e
LEFT JOIN categories c ON e.category_id = c.id
WHERE e.user_id = ?
";

$params = [$uid];
$types = "i";

if (!empty($search)) {
    $sql .= " AND e.description LIKE CONCAT('%', ?, '%')";
    $params[] = $search;
    $types .= "s";
}

if (!empty($filter_category)) {
    $sql .= " AND e.category_id = ?";
    $params[] = $filter_category;
    $types .= "i";
}

$sql .= " ORDER BY e.expense_date DESC LIMIT ?, ?";
$params[] = $start;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* =============================
   COUNT FOR PAGINATION
============================= */
$countSql = "SELECT COUNT(*) as total FROM expenses WHERE user_id=?";
$countParams = [$uid];
$countTypes = "i";

if (!empty($search)) {
    $countSql .= " AND description LIKE CONCAT('%', ?, '%')";
    $countParams[] = $search;
    $countTypes .= "s";
}

if (!empty($filter_category)) {
    $countSql .= " AND category_id=?";
    $countParams[] = $filter_category;
    $countTypes .= "i";
}

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$totalResult = $countStmt->get_result()->fetch_assoc();
$totalPages = ceil($totalResult['total'] / $limit);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Expenses - AllowanceSense</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="dashboard">

<div class="sidebar">
    <h2>AllowanceSense</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="expenses.php" class="active">Expenses</a>
    <a href="allowances.php">Allowance</a>
    <a href="budgets.php">Budget</a>
    <a href="reports.php">Reports</a>
    <a href="categories.php">Categories</a>
    <a href="activity_log.php">Activity Log</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php">Logout</a>
</div>

<div class="content">

<div class="page-header">
    <div>
        <h1>Expenses</h1>
        <p>Manage and track your spending</p>
    </div>
    <button class="btn primary" onclick="toggleForm()">+ Add Expense</button>
</div>

<!-- ADD EXPENSE FORM -->
<div class="expense-form" id="expenseForm" style="display:none;">
<form method="POST" enctype="multipart/form-data">

<div class="form-grid">

<div class="form-group">
<label>Description</label>
<input type="text" name="description" required>
</div>

<div class="form-group">
<label>Amount (₱)</label>
<input type="number" step="0.01" name="amount" required>
</div>

<div class="form-group">
<label>Category</label>
<select name="category_id" required>
<option value="">Select Category</option>
<?php
$catQuery->execute();
$catResult = $catQuery->get_result();
while($cat = $catResult->fetch_assoc()):
?>
<option value="<?= $cat['id'] ?>">
<?= htmlspecialchars($cat['category_name']) ?>
</option>
<?php endwhile; ?>
</select>
</div>

<div class="form-group">
<label>Date</label>
<input type="date" name="date" required>
</div>

<div class="form-group">
<label>Upload Receipt</label>
<input type="file" name="receipt" id="receiptInput" accept="image/*">
<img id="receiptPreview" style="display:none; margin-top:10px; width:120px; border-radius:8px;">
</div>

</div>

<div class="form-actions">
<button type="button" class="btn cancel" onclick="toggleForm()">Cancel</button>
<button name="add_expense" class="btn primary">Save Expense</button>
</div>

</form>
</div>

<!-- TABLE -->
<div class="expense-table box">

<div class="table-top">
<form method="GET" id="filterForm">

<input type="text"
name="search"
placeholder="Search description..."
value="<?= htmlspecialchars($search) ?>"
class="search-input">

<select name="category" class="filter-select">
<option value="">All Categories</option>

<?php
$catQuery2 = $conn->prepare("SELECT id, category_name FROM categories WHERE user_id=?");
$catQuery2->bind_param("i", $uid);
$catQuery2->execute();
$catResult2 = $catQuery2->get_result();
while($cat = $catResult2->fetch_assoc()):
?>
<option value="<?= $cat['id'] ?>"
<?= ($filter_category == $cat['id']) ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['category_name']) ?>
</option>
<?php endwhile; ?>
</select>

</form>
</div>

<table>
<thead>
<tr>
<th>Date</th>
<th>Description</th>
<th>Category</th>
<th>Amount</th>
<th>Receipt</th>
<th>Action</th>
</tr>
</thead>

<tbody>
<?php if($result->num_rows > 0): ?>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
<td><?= $row['expense_date'] ?></td>
<td><?= htmlspecialchars($row['description']) ?></td>
<td><span class="category-pill"><?= htmlspecialchars($row['category_name']) ?></span></td>
<td class="amount">₱<?= number_format($row['amount'],2) ?></td>
<td>
<?php if($row['receipt']): ?>
<a href="uploads/<?= $row['receipt'] ?>" target="_blank">View</a>
<?php else: ?>
—
<?php endif; ?>
</td>
<td>
<a href="?delete=<?= $row['id'] ?>"
class="delete-btn"
onclick="return confirm('Delete this expense?')">
Delete
</a>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="6">No expenses found.</td>
</tr>
<?php endif; ?>
</tbody>
</table>

<!-- PAGINATION -->
<div class="pagination">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $filter_category ?>"
class="page-btn <?= ($page==$i)?'active':'' ?>">
<?= $i ?>
</a>
<?php endfor; ?>
</div>

</div>
</div>

<script>
function toggleForm(){
    var form = document.getElementById("expenseForm");
    form.style.display = form.style.display === "none" ? "block" : "none";
}

/* RECEIPT PREVIEW */
document.getElementById("receiptInput").addEventListener("change", function(e){
    const file = e.target.files[0];
    const preview = document.getElementById("receiptPreview");

    if(file){
        preview.src = URL.createObjectURL(file);
        preview.style.display = "block";
    }
});

/* ===========================
   🔎 LIVE AUTO SEARCH
=========================== */

const searchInput = document.querySelector("input[name='search']");
const categorySelect = document.querySelector("select[name='category']");
const filterForm = document.getElementById("filterForm");

let typingTimer;
const doneTypingInterval = 400; // milliseconds delay

// Search while typing (with small delay)
searchInput.addEventListener("keyup", function () {
    clearTimeout(typingTimer);
    typingTimer = setTimeout(function () {
        filterForm.submit();
    }, doneTypingInterval);
});

// Filter instantly when category changes
categorySelect.addEventListener("change", function () {
    filterForm.submit();
});
</script>

</body>
</html>