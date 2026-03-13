<?php
include("config/connection.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows == 1){

        $token = bin2hex(random_bytes(50));
        $expire = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $update = $conn->prepare("UPDATE users SET reset_token=?, token_expire=? WHERE email=?");
        $update->bind_param("sss",$token,$expire,$email);
        $update->execute();

        $reset_link = "http://localhost/AllowanceSense/reset_password.php?token=".$token;

        $message = "Reset link generated (demo): <br><a href='$reset_link'>$reset_link</a>";

    }else{
        $error = "No account found.";
    }

}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Forgot Password</title>
        <link rel="stylesheet" href="css/login.css">
    </head>
    <body>
        <div class="page">
            <div class="login-card">
                <h2>Forgot Password</h2>
                <p class="subtitle">Enter your email to reset password</p>
                <form method="POST">
                    <label>Email Address</label>
                    <input type="email" name="email" required>
                    <button class="signin-btn">Send Reset Link</button>
                </form>
                <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>
                <?php if(isset($message)) echo "<p>$message</p>"; ?>
            </div>
        </div>
    </body>
</html>