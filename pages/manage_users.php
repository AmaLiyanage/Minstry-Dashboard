<?php
include_once __DIR__ . '/auth_admin.php';
include_once __DIR__ . '/../db.php';

$message = '';
$messageType = '';

// Handle Update & Delete Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['update_user'])) {
        $id = (int)$_POST['user_id'];
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = trim($_POST['role']);
        $status = trim($_POST['status']);

        $sql = "UPDATE users SET full_name=?, email=?, role=?, status=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssi', $full_name, $email, $role, $status, $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "User profile updated successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to update user profile.";
                $messageType = "error";
            }
            mysqli_stmt_close($stmt);
        }
        
    } elseif (isset($_POST['approve_user'])) {
        $id = (int)$_POST['user_id'];
        $sql = "UPDATE users SET status='active' WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "User approved and activated successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to approve user.";
                $messageType = "error";
            }
            mysqli_stmt_close($stmt);
        }
        
    } elseif (isset($_POST['delete_user'])) {
        $id = (int)$_POST['user_id'];
        
        // Safeguard: Prevent admin from deleting their own active session account
        if ($id === (int)$_SESSION['user_id']) {
            $message = "You cannot delete your own active administrator account.";
            $messageType = "error";
        } else {
            $sql = "DELETE FROM users WHERE id=?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "User permanently deleted from the system.";
                    $messageType = "success";
                } else {
                    $message = "Failed to delete user.";
                    $messageType = "error";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Extract all users from the database
$users = [];
$sql_users = "SELECT u.*, d.division_name, i.code as institution_code 
              FROM users u 
              LEFT JOIN divisions d ON u.division_id = d.id 
              LEFT JOIN institutions i ON d.institution_id = i.id 
              ORDER BY u.created_at DESC, u.id DESC";
$res = mysqli_query($conn, $sql_users);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $users[] = $row;
    }
}

function e($val) {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<style>
.manage-users-container {
    padding: 24px;
    max-width: 1200px;
    margin: 0 auto;
    font-family: 'Inter', sans-serif;
    animation: fadeIn 0.4s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.header-box {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
    color: white;
    padding: 32px 40px;
    border-radius: 16px;
    margin-bottom: 30px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.header-box h1 { margin: 0 0 8px 0; font-size: 26px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
.header-box p { margin: 0; color: #93c5fd; font-size: 15px; }

.alert-msg { padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 24px;
}

.user-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    display: flex;
    flex-direction: column;
    gap: 16px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.user-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}

.user-top { display: flex; justify-content: space-between; align-items: flex-start; }
.user-name { margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; }
.user-username { color: #64748b; font-size: 13px; font-weight: 600; display: block; margin-top: 2px;}

.user-badges { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; }
.badge { padding: 5px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
.badge-admin { background: #fee2e2; color: #e11d48; }
.badge-user { background: #e0f2fe; color: #0284c7; }
.badge-active { background: #dcfce7; color: #16a34a; }
.badge-pending { background: #fef9c3; color: #d97706; }
.badge-inactive { background: #f1f5f9; color: #475569; }

.user-info { font-size: 14px; color: #475569; display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
.user-info div { display: flex; align-items: center; gap: 10px; }
.user-info i { color: #94a3b8; width: 16px; text-align: center; }

.user-actions { margin-top: auto; padding-top: 16px; border-top: 1px solid #f1f5f9; display: flex; gap: 10px; }
.btn { flex: 1; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; }
.btn-edit { background: #f1f5f9; color: #334155; }
.btn-edit:hover { background: #e2e8f0; color: #0f172a; }
.btn-delete { background: #fff1f2; color: #e11d48; }
.btn-delete:hover { background: #ffe4e6; color: #be123c; }
.btn-approve { background: #dcfce7; color: #15803d; }
.btn-approve:hover { background: #bbf7d0; color: #166534; }

/* Edit Modal Styles */
.modal-backdrop {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center; z-index: 10000;
}
.modal-backdrop.show { display: flex; }
.modal-box {
    background: #fff; border-radius: 16px; width: 90%; max-width: 450px;
    padding: 32px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
}
.modal-box h3 { margin: 0 0 20px 0; font-size: 20px; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;}
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.form-group input, .form-group select { width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none; }
.form-group input:focus, .form-group select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }
.modal-footer { display: flex; gap: 12px; margin-top: 24px; }
.modal-footer .btn-submit { flex: 1; background: #2563eb; color: #fff; }
.modal-footer .btn-submit:hover { background: #1d4ed8; }

/* Custom Glassmorphic Confirmation Modal System CSS */
.custom-modal-backdrop {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(15, 23, 42, 0.3); backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px); display: flex; align-items: center;
    justify-content: center; z-index: 99999; opacity: 0; pointer-events: none;
    transition: opacity 0.25s ease;
}
.custom-modal-backdrop.modal-visible { opacity: 1; pointer-events: auto; }
.custom-confirmation-modal {
    background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px;
    width: 90%; max-width: 480px; padding: 32px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    transform: scale(0.92); transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    text-align: center;
}
.custom-modal-backdrop.modal-visible .custom-confirmation-modal { transform: scale(1); }

.modal-status-icon {
    width: 56px; height: 56px; background: #eff6ff; color: #2563eb;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 24px; margin: 0 auto 20px;
}
.custom-modal-backdrop.modal-danger .modal-status-icon { background: #fff1f2; color: #e11d48; }

.custom-confirmation-modal h3 { margin: 0 0 10px 0; color: #0f172a; font-size: 18px; font-weight: 700; }
.custom-confirmation-modal p { margin: 0 0 24px 0; color: #475569; font-size: 14px; line-height: 1.5; }

.modal-target-user-name {
    display: block; font-weight: 700; color: #1e3a8a; background: #f0f4ff;
    padding: 10px 14px; border-radius: 8px; margin-top: 12px; word-break: break-word;
}
.custom-modal-backdrop.modal-danger .modal-target-user-name { color: #9f1239; background: #fff1f2; }

.modal-action-buttons { display: flex; gap: 12px; justify-content: center; }
.modal-action-buttons button {
    min-height: 44px; padding: 0 24px; font-weight: 700; font-size: 13.5px;
    border-radius: 12px; cursor: pointer; display: inline-flex; align-items: center;
    justify-content: center; transition: all 0.15s ease; border: none;
}
.modal-btn-cancel { background: #ffffff; border: 1px solid #cbd5e1 !important; color: #475569; }
.modal-btn-cancel:hover { background: #f8fafc; border-color: #94a3b8 !important; }
.modal-btn-confirm {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); color: #ffffff; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.3);
}
.modal-btn-confirm:hover { background: linear-gradient(135deg, #020617 0%, #0f172a 100%); box-shadow: 0 6px 16px rgba(15, 23, 42, 0.45); }
.custom-modal-backdrop.modal-danger .modal-btn-confirm { background: linear-gradient(135deg, #e11d48 0%, #9f1239 100%); }
.custom-modal-backdrop.modal-danger .modal-btn-confirm:hover { background: linear-gradient(135deg, #be123c 0%, #4c0519 100%); }
</style>

<div class="manage-users-container">
    <div class="header-box">
        <h1><i class="fa-solid fa-users-gear"></i> System User Management</h1>
        <p>Control user roles, update profiles, and manage system access permissions.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert-msg <?= $messageType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="users-grid">
        <?php foreach ($users as $u): ?>
            <?php 
                $userRole = !empty($u['role']) ? strtolower(trim($u['role'])) : 'user';
                $userStatus = !empty($u['status']) ? strtolower(trim($u['status'])) : 'pending';
            ?>
            <div class="user-card">
                <div class="user-top">
                    <div>
                        <h3 class="user-name"><?= e($u['full_name']) ?></h3>
                        <span class="user-username">@<?= e($u['username']) ?></span>
                    </div>
                    <div class="user-badges">
                        <span class="badge <?= $userRole === 'admin' ? 'badge-admin' : 'badge-user' ?>"><?= ucfirst($userRole) ?></span>
                        <span class="badge badge-<?= $userStatus ?>"><?= ucfirst($userStatus) ?></span>
                    </div>
                </div>
                <div class="user-info">
                    <div><i class="fa-solid fa-envelope"></i> <?= e($u['email']) ?></div>
                    <?php if (!empty($u['division_name'])): ?>
                        <div><i class="fa-solid fa-sitemap"></i> <?= e($u['institution_code'] ?: 'Unallocated') ?> &mdash; <?= e($u['division_name']) ?></div>
                    <?php endif; ?>
                    <div><i class="fa-regular fa-clock"></i> Joined: <?= date('Y-m-d', strtotime($u['created_at'])) ?></div>
                </div>
                <div class="user-actions">
                    <?php if ($userStatus === 'pending'): ?>
                        <form method="POST" style="flex:1; margin:0; display:flex;">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" name="approve_user" class="btn btn-approve">
                                <i class="fa-solid fa-check"></i> Approve
                            </button>
                        </form>
                    <?php endif; ?>
                    <button class="btn btn-edit" onclick='openEditModal(<?= json_encode($u) ?>)'>
                        <i class="fa-solid fa-pen"></i> Edit
                    </button>
                    <form method="POST" id="deleteForm_<?= $u['id'] ?>" style="flex:1; margin:0; display:flex;">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="delete_user" value="1">
                        <button type="button" class="btn btn-delete trigger-custom-delete" data-id="<?= $u['id'] ?>" data-name="<?= e($u['full_name']) ?>">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-user-pen"></i> Edit User Profile</h3>
        <form method="POST" id="editUserForm">
            <input type="hidden" name="user_id" id="edit_user_id">
            <input type="hidden" name="update_user" value="1">
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" id="edit_full_name" required>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" id="edit_email" required>
            </div>
            
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="edit_role" required>
                    <option value="user">User</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status" required>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Glassmorphic Confirmation Modal -->
<div class="custom-modal-backdrop" id="confirmationModalBackdrop">
    <div class="custom-confirmation-modal">
        <div class="modal-status-icon">
            <i class="fa-solid fa-circle-question"></i>
        </div>
        <h3 id="modalHeadingTitle">Confirm Action</h3>
        <p id="modalBodyText">Are you sure you want to proceed?
            <span class="modal-target-user-name" id="modalTargetLabel">User Name</span>
        </p>
        <div class="modal-action-buttons">
            <button type="button" class="modal-btn-cancel" id="modalCancelButton">Cancel</button>
            <button type="button" class="modal-btn-confirm" id="modalConfirmButton">Confirm Action</button>
        </div>
    </div>
</div>

<script>
function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role || 'user';
    document.getElementById('edit_status').value = user.status || 'active';
    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

document.addEventListener('DOMContentLoaded', function () {
    const editUserForm = document.getElementById('editUserForm');
    const deleteActionTriggers = document.querySelectorAll('.trigger-custom-delete');
    
    const confirmBackdrop = document.getElementById('confirmationModalBackdrop');
    const confirmHeading = document.getElementById('modalHeadingTitle');
    const confirmBodyText = document.getElementById('modalBodyText');
    const confirmTargetLabel = document.getElementById('modalTargetLabel');
    const confirmCancelBtn = document.getElementById('modalCancelButton');
    const confirmExecuteBtn = document.getElementById('modalConfirmButton');
    const confirmIcon = confirmBackdrop.querySelector('.modal-status-icon i');

    let actionFormToSubmit = null;

    if (editUserForm && confirmBackdrop) {
        editUserForm.addEventListener('submit', function (e) {
            e.preventDefault();
            actionFormToSubmit = editUserForm;
            
            confirmBackdrop.classList.remove('modal-danger');
            confirmHeading.textContent = "Confirm Profile Update";
            confirmBodyText.childNodes[0].textContent = "Are you sure you want to commit these changes to the user profile for: ";
            confirmTargetLabel.textContent = document.getElementById('edit_full_name').value;
            
            confirmIcon.className = "fa-solid fa-circle-question";
            confirmExecuteBtn.textContent = "Confirm Update";
            
            confirmBackdrop.classList.add('modal-visible');
        });
    }

    if (deleteActionTriggers.length > 0 && confirmBackdrop) {
        deleteActionTriggers.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const userId = this.getAttribute('data-id');
                actionFormToSubmit = document.getElementById('deleteForm_' + userId);
                
                confirmBackdrop.classList.add('modal-danger');
                confirmHeading.textContent = "Confirm User Deletion";
                confirmBodyText.childNodes[0].textContent = "Warning: Are you absolutely certain you want to permanently delete the account for: ";
                confirmTargetLabel.textContent = this.getAttribute('data-name');
                
                confirmIcon.className = "fa-solid fa-triangle-exclamation";
                confirmExecuteBtn.textContent = "Permanently Delete";
                
                confirmBackdrop.classList.add('modal-visible');
            });
        });
    }

    if (confirmCancelBtn) confirmCancelBtn.addEventListener('click', () => { confirmBackdrop.classList.remove('modal-visible'); actionFormToSubmit = null; });
    if (confirmExecuteBtn) confirmExecuteBtn.addEventListener('click', () => { if (actionFormToSubmit) { confirmBackdrop.classList.remove('modal-visible'); actionFormToSubmit.submit(); } });
});
</script>