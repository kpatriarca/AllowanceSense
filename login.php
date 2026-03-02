<?php
session_start();
include("config/connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        if ($password === $row['password']) { // replace with password_verify() later
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['full_name'] = $row['full_name'];
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
    <div class="login-container">
        <div class="login-box">
            <img src="img/logo-icon.png" alt="Wallet Icon" class="icon">
            <h2>Welcome</h2>
            <p>Sign in to AllowanceSense</p>

            <form method="POST" action="">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="example@gmail.com" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <div class="options">
                    <label><input type="checkbox" name="remember"> Remember me</label>
                    <a href="#">Forgot password?</a>
                </div>

                <button type="submit">Sign In</button>
            </form>

            <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

            <p>Don't have an account? <a href="register.php">Register now</a></p>
        </div>
    </div>
</body>
</html>
