<?php
session_start();
include("config/connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $email     = $_POST['email'];
    $password  = $_POST['password'];

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Email already registered.";
    } else {
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $full_name, $email, $password); 
        // Note: For production use password_hash($password, PASSWORD_DEFAULT)

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['full_name'] = $full_name;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - AllowanceSense</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <img src="images/wallet-icon.png" alt="Wallet Icon" class="icon">
            <h2>Create Account</h2>
            <p>Register to start using AllowanceSense</p>

            <form method="POST" action="">
                <label>Full Name</label>
                <input type="text" name="full_name" required>

                <label>Email Address</label>
                <input type="email" name="email" placeholder="example@gmail.com" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <button type="submit">Register</button>
            </form>

            <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

            <p>Already have an account? <a href="login.php">Sign in</a></p>
        </div>
    </div>
</body>
</html>
