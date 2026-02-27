<?php
/**
 * View: Hardened User/Staff Management
 * Location: views/users.php
 * Version: 4.0.0 (Account Name Update)
 */

// 1. Check Permissions (Admin Only)
if (!current_user_can_admin()) { 
    echo '<div class="bg-red-50 p-10 rounded-3xl text-center font-bold text-red-600 uppercase tracking-widest border-2 border-red-100">Access Denied</div>'; 
    return; 
}

// 2. Fetch Users
$users = $db->get_users();
?>

<div class="container mx-auto max-w-5xl px-2 md:px-0 animate-fade-in pb-20">
    <!-- Header Area -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 md:mb-10 gap-6">
        <div>
            <h2 class="text-3xl md:text-4xl font-black text-slate-800 uppercase italic tracking-tighter leading-none">System Staff</h2>
            <p class="text-slate-400 text-xs md:text-sm font-bold uppercase tracking-widest mt-2">Manage administrative access & receipt issuers</p>
        </div>
        <button onclick="openUserModal()" class="w-full md:w-auto bg-slate-900 text-white px-8 py-4 rounded-2xl font-black uppercase tracking-widest text-[10px] md:text-xs hover:bg-blue-600 hover:shadow-xl hover:shadow-blue-200 transition-all transform hover:-translate-y-1 active:scale-95">
            + Create New Account
        </button>
    </div>

    <!-- User Table Container -->
    <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl border border-slate-200 overflow-hidden shadow-slate-200/50">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-900 text-white text-[10px] uppercase font-black tracking-widest">
                    <tr>
                        <th class="p-5 md:p-8">Identification</th>
                        <th class="p-5 md:p-8">Account Name (Issuer)</th>
                        <th class="p-5 md:p-8 text-center">Access Level</th>
                        <th class="p-5 md:p-8 text-right">Operations</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php foreach($users as $u): ?>
                    <tr class="hover:bg-slate-50/80 transition-all group">
                        <!-- User Identity -->
                        <td class="p-5 md:p-8">
                            <div class="flex items-center gap-3 md:gap-4">
                                <div class="hidden xs:flex w-10 h-10 rounded-xl bg-slate-100 items-center justify-center font-black text-slate-400 border border-slate-200 group-hover:bg-blue-50 group-hover:text-blue-500 group-hover:border-blue-100 transition-colors">
                                    <?php echo e(strtoupper(substr($u->username, 0, 1))); ?>
                                </div>
                                <div>
                                    <div class="font-black text-slate-800 tracking-tight text-base md:text-lg"><?php echo e($u->username); ?></div>
                                    <div class="text-[9px] md:text-[10px] text-slate-400 font-bold uppercase tracking-widest">Joined: <?php echo date('d M Y', strtotime($u->created_at)); ?></div>
                                </div>
                            </div>
                        </td>

                        <!-- Account Name (New Column) -->
                        <td class="p-5 md:p-8">
                            <div class="font-bold text-slate-700 text-sm"><?php echo e($u->account_name ?? '-'); ?></div>
                        </td>

                        <!-- Role Badge -->
                        <td class="p-5 md:p-8 text-center">
                            <span class="px-3 md:px-4 py-1.5 rounded-full text-[9px] md:text-[10px] font-black uppercase tracking-widest border <?php echo $u->role=='admin'?'bg-purple-50 text-purple-600 border-purple-100':'bg-blue-50 text-blue-600 border-blue-100'; ?>">
                                <?php echo e($u->role); ?>
                            </span>
                        </td>

                        <!-- Actions -->
                        <td class="p-5 md:p-8 text-right">
                            <div class="flex justify-end gap-2 md:gap-3">
                                <button onclick='editUser(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8'); ?>)' class="bg-slate-50 text-slate-600 px-3 md:px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95">
                                    Edit
                                </button>
                                <?php if($u->id != $_SESSION['user_id']): ?>
                                <button onclick="deleteUser(<?php echo (int)$u->id; ?>)" class="bg-red-50 text-red-400 px-3 md:px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-red-500 hover:text-white transition-all shadow-sm active:scale-95">
                                    Del
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
/**
 * User Modal Logic
 */
function openUserModal(isEdit = false) {
    Swal.fire({
        title: isEdit ? 'Update Staff' : 'New Staff',
        html: `
            <div class="text-left space-y-4 p-2">
                <input id="swal-id" type="hidden" value="0">
                <div><label class="lbl">Username</label><input id="swal-user" class="inp" placeholder="e.g. johndoe"></div>
                <div><label class="lbl">Account Name (Issuer)</label><input id="swal-name" class="inp" placeholder="e.g. John Doe"></div>
                <div><label class="lbl">Password</label><input id="swal-pass" type="password" class="inp" placeholder="${isEdit ? 'Leave blank to keep' : 'Required'}"></div>
                <div><label class="lbl">Role</label><select id="swal-role" class="inp"><option value="staff">Staff</option><option value="admin">Admin</option></select></div>
                
                <div class="flex gap-4 mt-4 pt-4 border-t border-slate-100">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="swal-edit" class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500">
                        <span class="text-xs font-bold uppercase text-slate-600">Allow Edit</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="swal-delete" class="w-5 h-5 rounded text-red-600 focus:ring-red-500">
                        <span class="text-xs font-bold uppercase text-slate-600">Allow Delete</span>
                    </label>
                </div>
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Save Configuration',
        preConfirm: () => {
            const u = document.getElementById('swal-user').value.trim();
            const an = document.getElementById('swal-name').value.trim();
            const p = document.getElementById('swal-pass').value;
            const r = document.getElementById('swal-role').value;
            const id = document.getElementById('swal-id').value;
            const edit = document.getElementById('swal-edit').checked;
            const del = document.getElementById('swal-delete').checked;
            
            if (!u || !an) { Swal.showValidationMessage('Username and Account Name required'); return false; }
            if (!isEdit && !p) { Swal.showValidationMessage('Password required'); return false; }
            
            return { id: id, username: u, account_name: an, password: p, role: r, can_edit: edit, can_delete: del }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api.php', { action: 'save_user', csrf_token: CSRF_TOKEN, ...result.value }, function(res) {
                if(res.success) { Swal.fire('Saved', 'User updated.', 'success').then(() => location.reload()); }
                else { Swal.fire('Error', 'Save failed.', 'error'); }
            }, 'json');
        }
    });
}

function editUser(user) {
    openUserModal(true);
    setTimeout(() => {
        document.getElementById('swal-id').value = user.id;
        document.getElementById('swal-user').value = user.username;
        document.getElementById('swal-name').value = user.account_name || '';
        document.getElementById('swal-role').value = user.role;
        // Check checkboxes based on DB value (1 or 0)
        document.getElementById('swal-edit').checked = (user.can_edit == 1);
        document.getElementById('swal-delete').checked = (user.can_delete == 1);
    }, 50);
}

/**
 * Trigger User Deletion
 */
function deleteUser(id) {
    Swal.fire({
        title: 'Remove Staff?',
        text: "The user will lose system access immediately.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete Account',
        customClass: {
            popup: 'rounded-[2.5rem]',
            confirmButton: 'bg-red-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-xs tracking-widest mr-2',
            cancelButton: 'bg-slate-100 text-slate-400 px-8 py-4 rounded-2xl font-black uppercase text-xs tracking-widest'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('api.php', { action: 'delete_user', id: id, csrf_token: CSRF_TOKEN }, function(res) {
                if(res.success) location.reload();
                else Swal.fire('Error', 'Could not delete user. You cannot delete yourself.', 'error');
            }, 'json');
        }
    });
}
</script>