<?php

session_start();
include_once __DIR__ . '/../db.php';

$message = '';
$messageType = 'error';

if (isset($_GET['registered']) && $_GET['registered'] === 'true') {
    $message = "Registration successful! Please wait for an administrator to approve your account before logging in.";
    $messageType = 'success';
}

if(isset($_POST['login'])){

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "
        SELECT u.*, d.division_name, i.code as institution_code, i.institution_name 
        FROM users u
        LEFT JOIN divisions d ON u.division_id = d.id
        LEFT JOIN institutions i ON d.institution_id = i.id
        WHERE u.email='$email'
        LIMIT 1
    ";

    $result = mysqli_query($conn,$sql);

    if(mysqli_num_rows($result) > 0){

        $user = mysqli_fetch_assoc($result);

        if(password_verify(
            $password,
            $user['password']
        )){
                if (isset($user['status']) && strtolower((string)$user['status']) === 'pending') {
                    $message = "Your account is pending administrator approval.";
                } elseif (isset($user['status']) && strtolower((string)$user['status']) === 'inactive') {
                    $message = "Your account is currently inactive.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['division_id'] = $user['division_id'];
                    $_SESSION['division_name'] = $user['division_name'];
                    $_SESSION['institution_code'] = $user['institution_code'] ?: $user['institution_name'];

                    header("Location: ../index.php");
                    exit;
                }
            } else {
                $message = "Invalid Login";
        }
        } else {
            $message = "Invalid Login";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
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
    <h2>Login</h2>

    <?php if ($message): ?>
        <p style="color: <?= $messageType === 'success' ? '#15803d' : 'red' ?>; background: <?= $messageType === 'success' ? '#dcfce7' : 'transparent' ?>; padding: <?= $messageType === 'success' ? '10px' : '0' ?>; border-radius: 6px; font-size: 14px; font-weight: 600;"><?= $message ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>

    <div class="footer-text">
        If not signed up, then <a href="signup.php">Create Account</a>
    </div>
</div>

</body>
</html>