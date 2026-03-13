<?php
include("config/connection.php");

if(!isset($_GET['token'])){
    die("Invalid request.");
}

$token = $_GET['token'];

$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token=? AND token_expire > NOW()");
$stmt->bind_param("s",$token);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows != 1){
    die("Invalid or expired token.");
}

$row = $result->fetch_assoc();
$user_id = $row['id'];

if($_SERVER["REQUEST_METHOD"]=="POST"){

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, token_expire=NULL WHERE id=?");
    $update->bind_param("si",$password,$user_id);
    $update->execute();

    header("Location: login.php?reset=success");
    exit();
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Reset Password</title>
        <link rel="stylesheet" href="css/login.css">
    </head>
    <body>
        <div class="page">
            <div class="login-card">
                <h2>Reset Password</h2>
                <p class="subtitle">Enter your new password</p>
                <form method="POST">
                    <label>New Password</label>
                    <input type="password" name="password" required>
                    <button class="signin-btn">Reset Password</button>
                </form>
            </div>
        </div>
    </body>
</html>