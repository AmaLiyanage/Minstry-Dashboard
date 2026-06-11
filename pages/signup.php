<?php
session_start();
include_once __DIR__ . '/../db.php';

$message = '';

if(isset($_POST['signup'])){

    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);

    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        $password_hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $check = mysqli_query(
            $conn,
            "SELECT id FROM users
             WHERE username='$username'
             OR email='$email'"
        );

        if(mysqli_num_rows($check) > 0){
            $message = "Username or Email already exists.";
        }else{
            mysqli_query(
                $conn,
                "INSERT INTO users
                (
                    full_name,
                    username,
                    email,
                    password,
                    status
                )
                VALUES
                (
                    '$full_name',
                    '$username',
                    '$email',
                    '$password_hash',
                    'pending'
                )"
            );

            header("Location: login.php?registered=true");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Signup</title>
<style>
    body { font-family: 'Inter', sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
    .auth-container { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
    h2 { color: #1e3a8a; margin-top: 0; }
    input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
    button { width: 100%; padding: 12px; background: #1e3a8a; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 10px; }
    button:hover { background: #1d4ed8; }
    .footer-text { margin-top: 20px; font-size: 14px; color: #475569; }
    .footer-text a { color: #2563eb; text-decoration: none; font-weight: bold; }
</style>
</head>
<body>

<div class="auth-container">
    <h2>Create Account</h2>

    <?php if ($message): ?>
        <p style="color:red;"><?= $message ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <button type="submit" name="signup">Sign Up</button>
    </form>

    <div class="footer-text">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>

</body>
</html>