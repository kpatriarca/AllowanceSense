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

/* GET USER */
$userQuery = $conn->prepare("SELECT full_name,email,created_at,profile_pic FROM users WHERE id=?");
$userQuery->bind_param("i",$uid);
$userQuery->execute();
$user = $userQuery->get_result()->fetch_assoc();

/* DELETE ACCOUNT */
if(isset($_POST['delete_account'])){

logActivity($conn,$uid,"Account Deleted","User deleted their account");

$conn->query("
DELETE e FROM expenses e
JOIN categories c ON e.category_id = c.id
WHERE c.user_id = $uid
");

$conn->query("DELETE FROM expenses WHERE user_id=$uid");
$conn->query("DELETE FROM budgets WHERE user_id=$uid");
$conn->query("DELETE FROM allowances WHERE user_id=$uid");
$conn->query("DELETE FROM activity_log WHERE user_id=$uid");
$conn->query("DELETE FROM categories WHERE user_id=$uid");

/* Delete User */
$stmt=$conn->prepare("DELETE FROM users WHERE id=?");
$stmt->bind_param("i",$uid);
$stmt->execute();

/* Destroy Session */
session_destroy();

header("Location: register.php?account_deleted=1");
exit();

}

/* REVOKE SESSION */
if(isset($_POST['revoke_session'])){

logActivity($conn,$uid,"Session Revoked","User revoked active session");

/* Destroy Session */

session_destroy();

header("Location: login.php?session_revoked=1");
exit();

}

/* UPDATE SETTINGS */
if($_SERVER["REQUEST_METHOD"]=="POST" && !isset($_POST['delete_account']) && !isset($_POST['revoke_session'])){

$full_name=$_POST['full_name'];
$email=$_POST['email'];
$password=$_POST['password'];

$profile_pic = $user['profile_pic'];

/* HANDLE IMAGE UPLOAD */
if(!empty($_FILES['profile_pic']['name'])){

$targetDir = "uploads/";
$fileName = time() . "_" . basename($_FILES["profile_pic"]["name"]);
$targetFile = $targetDir . $fileName;

$allowed = ['jpg','jpeg','png'];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if(in_array($ext,$allowed)){

if(move_uploaded_file($_FILES["profile_pic"]["tmp_name"],$targetFile)){
$profile_pic = $targetFile;
}

}

}

/* PASSWORD UPDATE */
if(!empty($password)){

$hashed=password_hash($password,PASSWORD_DEFAULT);

$stmt=$conn->prepare("
UPDATE users 
SET full_name=?,email=?,password=?,profile_pic=? 
WHERE id=?
");

$stmt->bind_param("ssssi",$full_name,$email,$hashed,$profile_pic,$uid);

logActivity($conn,$uid,"Password Changed","User updated password");

}else{

$stmt=$conn->prepare("
UPDATE users 
SET full_name=?,email=?,profile_pic=? 
WHERE id=?
");

$stmt->bind_param("sssi",$full_name,$email,$profile_pic,$uid);

}

if($stmt->execute()){

$_SESSION['full_name']=$full_name;

logActivity(
$conn,
$uid,
"Account Settings Updated",
"Name: $full_name | Email: $email"
);

$success="Settings updated successfully.";

}

}

/* PROFILE AVATAR */
$initial=strtoupper(substr($user['full_name'],0,1));

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Settings - AllowanceSense</title>
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
                    <a href="activity_log.php">📃  Activity Log</a>
                    <a href="settings.php" class="active">⚙️  Settings</a>
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

        <div class="content settings-page">
            <h1 class="page-title">Settings</h1>
            <?php if(isset($success)){ ?>
            <p style="color:green"><?= $success ?></p>
            <?php } ?>
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>Profile Information</h3>
                    <div class="profile-row">
                        <div class="profile-avatar">
                            <?php if(!empty($user['profile_pic'])){ ?>
                            <img src="<?= $user['profile_pic'] ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                            <?php }else{ ?>
                            <?= $initial ?>
                            <?php } ?>
                        </div>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="settings-form">
                        <label>Upload Profile Picture</label>
                        <input type="file" name="profile_pic" accept="image/*">
                        <label>Full Name</label>
                        <input type="text" name="full_name"
                        value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        <label>Email</label>
                        <input type="email" name="email"
                        value="<?= htmlspecialchars($user['email']) ?>" required>
                        <label>New Password</label>
                        <input type="password" name="password"
                        placeholder="Type your new password">
                        <button class="btn primary">Save Changes</button>
                    </form>
                </div>
                <div class="settings-side">
                    <div class="settings-card">
                        <h3>Account</h3>
                        <div class="account-info">
                            <div>
                                <span>Member Since</span>
                                <strong>
                                <?= date("F d, Y",strtotime($user['created_at'])) ?>
                                </strong>
                            </div>
                        </div>
                        <button class="delete-account" onclick="openDeleteModal()">
                            Delete Account
                        </button>
                    </div>
                    <div class="settings-card">
                        <h3>Active Session</h3>
                        <div class="session-box">
                            <strong>Chrome on Windows</strong>
                            <span>
                                Active now
                            </span>
                            <button class="revoke-btn" onclick="openRevokeModal()">
                                Revoke
                            </button>

                        </div>
                    </div>
                    <div class="settings-card">
                        <h3>Appearance</h3>
                        <div class="session-box">
                        <strong>Dark Mode</strong>
                        <label class="switch">
                            <input type="checkbox" id="darkToggle">
                            <span class="slider"></span>
                        </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
        <div id="deleteModal" class="settings-modal">
            <div class="settings-modal-box">
                <h3>Delete Account</h3>
                <p>This action cannot be undone.</p>
                <div class="settings-modal-buttons">
                <form method="POST">
                    <button type="submit" name="delete_account" class="settings-confirm-delete">
                        Delete
                    </button>
                </form>
                <button onclick="closeDeleteModal()" class="settings-cancel">
                Cancel
                </button>
                </div>
            </div>
        </div>
        <div id="revokeModal" class="settings-modal">
            <div class="settings-modal-box">
                <h3>Revoke Session</h3>
                <p>You will be logged out of this session.</p>
                <div class="settings-modal-buttons">
                <form method="POST">
                    <button type="submit" name="revoke_session" class="settings-confirm-revoke">
                        Revoke
                    </button>
                </form>
                <button onclick="closeRevokeModal()" class="settings-cancel">
                    Cancel
                </button>
                </div>
            </div>
        </div>
        <script>
            function openDeleteModal() {
                document.getElementById("deleteModal").style.display="flex";
            }

            function closeDeleteModal() {
                document.getElementById("deleteModal").style.display="none";
            }

            function openRevokeModal() {
                document.getElementById("revokeModal").style.display="flex";
            }

            function closeRevokeModal() {
                document.getElementById("revokeModal").style.display="none";
            }

            const toggle = document.getElementById("darkToggle");

            if(localStorage.getItem("darkmode") === null) {
                localStorage.setItem("darkmode","enabled");
            }

            if(localStorage.getItem("darkmode") === "enabled") {
                document.body.classList.add("dark-mode");
                toggle.checked = true;
            }else{
                toggle.checked = false;
            }

            toggle.addEventListener("change", function(){

            if(this.checked) {
                document.body.classList.add("dark-mode");
                localStorage.setItem("darkmode","enabled");
            }else{
                document.body.classList.remove("dark-mode");
                localStorage.setItem("darkmode","disabled");
            }
            });
        </script>
    </body>
</html>