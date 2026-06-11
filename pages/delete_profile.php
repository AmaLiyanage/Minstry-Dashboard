<?php

include_once __DIR__ . '/auth.php';
include_once __DIR__ . '/../db.php';

$user_id = $_SESSION['user_id'];

if(isset($_POST['delete_account'])){

    $sql = "
        DELETE FROM users
        WHERE id = ?
    ";

    $stmt = mysqli_prepare(
        $conn,
        $sql
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );

    mysqli_stmt_execute($stmt);

    session_destroy();

    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

?>
<style>
.delete-profile-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    width: 100%;
    max-width: 480px;
    margin: 40px auto;
    padding: 32px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    text-align: center;
}
.modal-status-icon {
    width: 56px;
    height: 56px;
    background: #fff1f2;
    color: #e11d48;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin: 0 auto 20px;
}
.delete-profile-box h3 {
    margin: 0 0 10px 0;
    color: #0f172a;
    font-size: 20px;
    font-weight: 700;
}
.delete-profile-box p {
    margin: 0 0 24px 0;
    color: #475569;
    font-size: 14px;
    line-height: 1.5;
}
.modal-target-project-name {
    display: block;
    font-weight: 700;
    color: #9f1239;
    background: #fff1f2;
    padding: 10px 14px;
    border-radius: 8px;
    margin-top: 12px;
    word-break: break-word;
}
.modal-action-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}
.modal-action-buttons a, .modal-action-buttons button {
    min-height: 44px;
    padding: 0 24px;
    font-weight: 700;
    font-size: 13.5px;
    border-radius: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
    text-decoration: none;
}
.modal-btn-cancel {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
}
.modal-btn-cancel:hover {
    background: #f8fafc;
    border-color: #94a3b8;
}
.modal-btn-confirm {
    background: linear-gradient(135deg, #e11d48 0%, #9f1239 100%);
    color: #ffffff;
    border: none;
    box-shadow: 0 4px 14px rgba(225, 29, 72, 0.3);
}
.modal-btn-confirm:hover {
    background: linear-gradient(135deg, #be123c 0%, #4c0519 100%);
    box-shadow: 0 6px 16px rgba(190, 18, 60, 0.45);
}
</style>

<div class="delete-profile-box">
    <div class="modal-status-icon">
        <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    <h3>Confirm Profile Deletion</h3>
    <p>Are you absolutely sure you want to permanently discard your account? This action cannot be undone.
        <span class="modal-target-project-name">My User Profile</span>
    </p>
    <form method="POST">
        <div class="modal-action-buttons">
            <a href="index.php?page=profile" class="modal-btn-cancel">Cancel</a>
            <button type="submit" name="delete_account" class="modal-btn-confirm">Confirm Delete</button>
        </div>
    </form>
</div>