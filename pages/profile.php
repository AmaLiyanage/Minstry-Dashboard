<?php

include_once __DIR__ . '/auth.php';
include_once __DIR__ . '/../db.php';

$user_id = $_SESSION['user_id'];

$sql = "
    SELECT *
    FROM users
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$user = mysqli_fetch_assoc($result);

?>
<style>
.profile-box {
    max-width: 500px;
    margin: 40px auto;
    background: #fff;
    padding: 32px 40px;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
}
.profile-box h2 {
    margin-top: 0;
    color: #0f172a;
    font-size: 24px;
    margin-bottom: 24px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}
.profile-row {
    display: flex;
    flex-direction: column;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f1f5f9;
}
.profile-row:last-of-type {
    border-bottom: none;
    margin-bottom: 24px;
}
.profile-label {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 4px;
}
.profile-value {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}
.btn-action {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    margin-bottom: 10px;
    box-sizing: border-box;
}
.btn-edit {
    background: #2563eb;
    color: #fff;
}
.btn-edit:hover {
    background: #1d4ed8;
}
.btn-password {
    background: #10b981;
    color: #fff;
}
.btn-password:hover {
    background: #059669;
}
.btn-delete {
    background: #fff1f2;
    color: #e11d48;
    border: 1px solid #fecdd3;
}
.btn-delete:hover {
    background: #ffe4e6;
    color: #be123c;
}
</style>

<div class="profile-box">
    <h2><i class="fa-solid fa-user-circle"></i> My Profile</h2>

    <div class="profile-row">
        <div class="profile-label">Full Name</div>
        <div class="profile-value"><?= htmlspecialchars($user['full_name']) ?></div>
    </div>

    <div class="profile-row">
        <div class="profile-label">Username</div>
        <div class="profile-value"><?= htmlspecialchars($user['username']) ?></div>
    </div>

    <div class="profile-row">
        <div class="profile-label">Email Address</div>
        <div class="profile-value"><?= htmlspecialchars($user['email']) ?></div>
    </div>

    <div class="profile-row">
        <div class="profile-label">Status</div>
        <div class="profile-value">
            <span style="background: #dcfce7; color: #16a34a; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;"><?= ucfirst(htmlspecialchars($user['status'])) ?></span>
        </div>
    </div>

    <div class="profile-row">
        <div class="profile-label">Account Created</div>
        <div class="profile-value"><?= date('d M Y h:i A', strtotime($user['created_at'])) ?></div>
    </div>

    <div style="margin-top: 24px;">
        <a href="index.php?page=edit_profile" class="btn-action btn-edit">
            <i class="fa-solid fa-pen"></i> Edit Profile
        </a>
        <a href="index.php?page=change_password" class="btn-action btn-password">
            <i class="fa-solid fa-lock"></i> Change Password
        </a>
        <a href="index.php?page=delete_profile" class="btn-action btn-delete">
            <i class="fa-solid fa-trash"></i> Delete Profile
        </a>
    </div>
</div>