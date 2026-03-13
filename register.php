<?php
session_start();
include("config/connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s",$email);
    $check->execute();
    $check->store_result();

    if($check->num_rows > 0){
        $error = "Email already registered.";
    }else{

        $stmt = $conn->prepare("INSERT INTO users(full_name,email,password) VALUES(?,?,?)");
        $stmt->bind_param("sss",$full_name,$email,$password);

        if($stmt->execute()){
            header("Location: login.php");
            exit();
        }else{
            $error = "Registration failed.";
        }
    }
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Register - AllowanceSense</title>
        <link rel="stylesheet" href="css/login.css">
    </head>
    <body>
        <div class="page">=
            <div class="login-card">
                <div class="icon-circle">
                    <img src="img/logo-icon.png">
                </div>
                <h2>Create Account</h2>
                <p class="subtitle">Register to start using AllowanceSense</p>
                <form method="POST">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="example@gmail.com" required>
                    <label>Password</label>
                    <input type="password" name="password" required>
                    <button class="signin-btn">Register</button>
                </form>

                <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>

                <p class="register">
                    Already have an account?
                    <a href="login.php">Sign in</a>
                </p>
            </div>
        </div>
    </body>
</html>