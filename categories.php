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

$colors = [
"#ef4444","#f97316","#f59e0b","#10b981","#06b6d4",
"#3b82f6","#6366f1","#8b5cf6","#ec4899","#14b8a6"
];

/* ADD CATEGORY */
if(isset($_POST['add_category'])){

$category_name = trim($_POST['category_name']);
$user_id = $_SESSION['user_id'];

function randomColor(){
return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}

$color = randomColor();
$icon = "tag";

$stmt = $conn->prepare("INSERT INTO categories (user_id, category_name, color, icon) VALUES (?,?,?,?)");
$stmt->bind_param("isss",$user_id,$category_name,$color,$icon);
$stmt->execute();

logActivity(
    $conn,
    $user_id,
    "Added Category",
    "Category: $category_name"
);

header("Location: categories.php");
exit();

}

/* UPDATE CATEGORY */
if(isset($_POST['update_category'])){

$id = $_POST['id'];
$name = mysqli_real_escape_string($conn,$_POST['category_name']);
$color = $_POST['color'];

$conn->query("
UPDATE categories
SET category_name='$name', color='$color'
WHERE id=$id AND user_id=$uid
");

logActivity(
    $conn,
    $uid,
    "Updated Category",
    "Category: $name"
);

header("Location: categories.php");
exit();
}

/* DELETE */
if(isset($_GET['delete'])){
$id=$_GET['delete'];

$get = $conn->query("SELECT category_name FROM categories WHERE id=$id AND user_id=$uid");
$cat = $get->fetch_assoc();

$conn->query("
DELETE FROM categories
WHERE id=$id AND user_id=$uid
");

logActivity(
    $conn,
    $uid,
    "Deleted Category",
    "Category: ".$cat['category_name']
);

header("Location: categories.php");
exit();
}

/* FETCH */
$categories = $conn->query  ("
    SELECT c.*,
    IFNULL(SUM(e.amount),0) as total_spent
    FROM categories c
    LEFT JOIN expenses e ON c.id=e.category_id
    WHERE c.user_id=$uid
    GROUP BY c.id
    ORDER BY c.id DESC
");

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Categories</title>
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
                    <a href="categories.php" class="active">🏷️  Categories</a>
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
                    <h1>Categories</h1>
                    <p>Manage your expense categories.</p>
                </div>
                <button class="btn primary" onclick="toggleForm()">
                +  Add Category
                </button>
            </div>
            <div class="category-form" id="catForm">
                <form method="POST">
                    <input type="text" name="category_name" placeholder="Category name" required>
                    <button class="btn primary" name="add_category">
                    Add
                    </button>
                    <button type="button" class="btn cancel" onclick="toggleForm()">
                    Cancel
                    </button>
                </form>
            </div>
            <div class="category-grid">
            <?php while($cat=$categories->fetch_assoc()): ?>
            <div class="category-card">
                <div class="category-top">
                    <div class="category-icon" style="background:<?= $cat['color'] ?>">
                    <?= strtoupper(substr($cat['category_name'],0,1)) ?>
                    </div>
                </div>
                <h3><?= htmlspecialchars($cat['category_name']) ?></h3>
                <div class="category-spent">
                    Total Spent: ₱ <?= number_format($cat['total_spent'],2) ?>
                </div>
                <div class="category-actions">
                    <a href="#" class="edit-link" onclick="openEdit(
                    '<?= $cat['id'] ?>',
                    '<?= htmlspecialchars($cat['category_name']) ?>',
                    '<?= $cat['color'] ?>'
                    )">
                    Edit
                    </a>
                    <a href="#"
                    class="delete-link"
                    onclick="openDeletePopup(<?= $cat['id'] ?>)">
                    Delete
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
    
            </div>

        </div>
        </div>
        <div id="editPopup" class="popup">
            <div class="popup-content">
                <h3>Edit Category</h3>
                <form method="POST">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="text" name="category_name" id="edit_name" required>
                    <label>Color</label>
                    <input type="color" name="color" id="edit_color">
                <div class="popup-buttons">
                    <button class="btn primary" name="update_category">
                    Save
                    </button>
                    <button type="button" class="btn cancel" onclick="closePopup()">
                    Cancel
                    </button>
                </div>
                </form>
            </div>
        </div>
        <div id="deletePopup" class="popup">
            <div class="popup-content">
                <h3>Delete Category</h3>
                <p>Are you sure you want to delete this category?</p>
                <div class="popup-buttons">
                    <a id="confirmDeleteBtn" class="btn danger">
                    Delete
                    </a>
                    <button class="btn cancel" onclick="closeDeletePopup()">
                    Cancel
                    </button>
                </div>
            </div>
        </div>
        <script>
            function toggleForm() {

                let form=document.getElementById("catForm");

                if(form.style.display==="block"){
                form.style.display="none";
                }else{
                form.style.display="block";
                }
            }

            function openEdit(id,name,color) {

                document.getElementById("editPopup").style.display="flex";

                document.getElementById("edit_id").value=id;
                document.getElementById("edit_name").value=name;
                document.getElementById("edit_color").value=color;

            }

            function closePopup() {

                document.getElementById("editPopup").style.display="none";

            }

            if(localStorage.getItem("darkmode") === null){
                localStorage.setItem("darkmode","enabled");
            }

            if(localStorage.getItem("darkmode") === "enabled"){
                document.body.classList.add("dark-mode");
            }

            function openDeletePopup(id) {

                document.getElementById("deletePopup").style.display="flex";
                document.getElementById("confirmDeleteBtn").href = "?delete=" + id;

            }

            function closeDeletePopup() {

                document.getElementById("deletePopup").style.display="none";

            }
        </script>
    </body>
</html>