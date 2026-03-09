<?php
session_start();
include("config/connection.php");
require_once __DIR__ . "/includes/logger.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

/* GET USER */
$userQuery = $conn->prepare("SELECT full_name,email,created_at,profile_pic FROM users WHERE id=?");
$userQuery->bind_param("i",$uid);
$userQuery->execute();
$user = $userQuery->get_result()->fetch_assoc();

/* UPDATE SETTINGS */
if($_SERVER["REQUEST_METHOD"]=="POST"){

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

<!-- SIDEBAR -->

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


<!-- CONTENT -->

<div class="content settings-page">

<h1 class="page-title">Settings</h1>

<?php if(isset($success)){ ?>
<p style="color:green"><?= $success ?></p>
<?php } ?>


<div class="settings-grid">


<!-- PROFILE -->

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



<!-- RIGHT COLUMN -->

<div class="settings-side">


<!-- ACCOUNT -->

<div class="settings-card">

<h3>Account</h3>

<div class="account-info">

<div>
<span>Role</span>
<strong>Student</strong>
</div>

<div>
<span>Member Since</span>
<strong>
<?= date("F d, Y",strtotime($user['created_at'])) ?>
</strong>
</div>

</div>

<button class="delete-account">
Delete Account
</button>

</div>


<!-- ACTIVE SESSION -->

<div class="settings-card">

<h3>Active Session</h3>

<div class="session-box">

<strong>Chrome on Windows</strong>

<span>
Active now
</span>

<button class="revoke-btn">
Revoke
</button>

</div>

</div>

</div>

</div>

</div>
</div>

</body>
</html>