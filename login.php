<?php
session_start();
include("config/connection.php");
require_once __DIR__ . "/includes/logger.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

$email    = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT id, full_name, password FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {

$row = $result->fetch_assoc();

if (password_verify($password, $row['password']) || $password === $row['password']) {

    if ($password === $row['password']) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $update->bind_param("si", $newHash, $row['id']);
        $update->execute();
    }

    $_SESSION['user_id'] = $row['id'];
    $_SESSION['full_name'] = $row['full_name'];

    logActivity(
        $conn,
        $row['id'],
        "User Login",
        "User logged into the system"
    );

    header("Location: dashboard.php");
    exit();

} else {

    $error = "Invalid password.";
}
} else {
$error = "No account found with that email.";

}

}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Login - AllowanceSense</title>
        <link rel="stylesheet" href="css/login.css">
    </head>
    <body>
        <div class="page">
            <div class="login-card">
                <div class="icon-circle">
                    <img src="img/logo-icon.png" alt="Wallet Icon">
                </div>
                <h2>Welcome</h2>
                <p class="subtitle">Sign in to AllowanceSense</p>
                <form method="POST" action="">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="example@gmail.com" required>
                    <label>Password</label>
                    <input type="password" name="password" required>
                <div class="options">
                    <label class="remember">
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>
                    <a class="forgot" href="forgot_password.php">Forgot password?</a>
                </div>
                <button type="submit" class="signin-btn">Sign In</button>
                </form>
                
                <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>

                <p class="register">
                    Don't have an account?
                    <a href="register.php">Register now</a>
                </p>

            </div>
        </div>
    </body>
</html>
