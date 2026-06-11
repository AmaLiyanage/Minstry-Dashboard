<?php

include_once __DIR__ . '/auth.php';
include_once __DIR__ . '/../db.php';

$user_id = $_SESSION['user_id'];

$message = '';
$messageType = '';

if(isset($_POST['change_password'])){

    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];

    $sql = "
        SELECT password
        FROM users
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($conn,$sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $user = mysqli_fetch_assoc($result);

    if($user && password_verify($current_password, $user['password'])){

        $hashed =
        password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );

        $update = "
            UPDATE users
            SET password = ?
            WHERE id = ?
        ";

        $stmt2 = mysqli_prepare(
            $conn,
            $update
        );

        mysqli_stmt_bind_param(
            $stmt2,
            "si",
            $hashed,
            $user_id
        );

        mysqli_stmt_execute($stmt2);

        $message =
        "Password changed successfully.";
        $messageType = 'success';

    }else{

        $message =
        "Current password is incorrect.";
        $messageType = 'error';
    }
}

?>
<style>
.auth-box {
    max-width: 500px;
    margin: 40px auto;
    background: #fff;
    padding: 32px 40px;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
}
.auth-box h2 {
    margin-top: 0;
    color: #0f172a;
    font-size: 24px;
    margin-bottom: 24px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #475569;
    font-size: 14px;
}
.form-group input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 15px;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
    color: #1e293b;
}
.form-group input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
}
.btn-submit {
    width: 100%;
    background: #2563eb;
    color: #fff;
    border: none;
    padding: 14px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-top: 10px;
}
.btn-submit:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}
.alert-msg {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
.alert-success {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.alert-error {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 24px;
    color: #64748b;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: color 0.2s;
}
.back-link:hover {
    color: #0f172a;
}
</style>

<div class="auth-box">
    <h2><i class="fa-solid fa-lock"></i> Change Password</h2>

    <?php if($message): ?>
        <div class="alert-msg <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" placeholder="Enter current password" required>
        </div>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" placeholder="Enter new password" required>
        </div>
        <button type="submit" name="change_password" class="btn-submit">
            Update Password
        </button>
    </form>

    <a href="index.php?page=profile" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Profile
    </a>
</div>