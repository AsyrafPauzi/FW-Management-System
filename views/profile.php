<?php
/**
 * View: Hardened User Security Profile
 * Location: views/profile.php
 * Version: 4.0.0 (Financials & Account Name Update)
 */

// Fetch current account name and details for the logged-in user
$user_id = $_SESSION['user_id'];
$stmt = $db->pdo->prepare("SELECT username, account_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

$display_username = $current_user->username ?? 'User';
$display_account_name = $current_user->account_name ?? '';
?>

<div class="container mx-auto max-w-md px-4 py-8 animate-fade-in">
    <!-- Main Card Container -->
    <div class="bg-white p-6 md:p-10 rounded-[2.5rem] shadow-2xl border border-slate-200 shadow-slate-200/50">
        
        <!-- Header Section -->
        <div class="mb-8 md:mb-10 text-center">
            <div class="w-20 h-20 bg-blue-50 text-blue-600 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-6 shadow-inner">
                👤
            </div>
            <h2 class="text-2xl md:text-3xl font-black text-slate-800 uppercase italic tracking-tighter leading-none">
                My Profile
            </h2>
            <p class="text-slate-400 text-[10px] md:text-xs mt-3 font-bold uppercase tracking-widest leading-relaxed">
                Updating credentials for: <span class="text-blue-600"><?php echo e($display_username); ?></span>
            </p>
        </div>

        <!-- Form Section -->
        <form id="form-update-profile" class="space-y-5 md:space-y-6">
            
            <!-- Account Name Input (REQUIRED for V4.0.0 Receipts) -->
            <div class="group">
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest ml-2 group-focus-within:text-blue-500 transition-colors">
                    Display Name (Account Name)
                </label>
                <div class="relative">
                    <input type="text" id="account_name" required
                        class="w-full bg-slate-50 border-2 border-slate-100 p-4 rounded-2xl focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none transition-all font-bold text-slate-700 shadow-inner"
                        value="<?php echo e($display_account_name); ?>" placeholder="e.g. Ahmad bin Ibrahim">
                </div>
                <p class="text-[9px] text-slate-400 mt-2 ml-2 italic font-medium uppercase">This name appears as 'Issued By' on all Documents.</p>
            </div>

            <hr class="border-slate-100">

            <!-- New Password Input -->
            <div class="group">
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest ml-2 group-focus-within:text-blue-500 transition-colors">
                    New Security Password
                </label>
                <div class="relative">
                    <input type="password" id="new_pass"
                        class="w-full bg-slate-50 border-2 border-slate-100 p-4 rounded-2xl focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none transition-all font-bold text-slate-700 shadow-inner"
                        placeholder="Leave blank to keep current">
                </div>
            </div>

            <!-- Confirm Password Input -->
            <div class="group">
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest ml-2 group-focus-within:text-blue-500 transition-colors">
                    Verify New Password
                </label>
                <div class="relative">
                    <input type="password" id="confirm_pass"
                        class="w-full bg-slate-50 border-2 border-slate-100 p-4 rounded-2xl focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none transition-all font-bold text-slate-700 shadow-inner"
                        placeholder="••••••••">
                </div>
            </div>

            <!-- Information Box -->
            <div class="bg-blue-50 p-5 rounded-2xl border border-blue-100">
                <div class="flex gap-3">
                    <span class="text-lg">💡</span>
                    <p class="text-[10px] text-blue-600 font-bold leading-relaxed uppercase">
                        Passwords must be at least 6 characters long. Ensure your Account Name is correct for professional document generation.
                    </p>
                </div>
            </div>

            <!-- Action Button -->
            <div class="pt-2">
                <button type="submit"
                    class="w-full bg-slate-900 text-white p-5 rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-blue-600 transition-all shadow-xl shadow-slate-200 transform hover:-translate-y-1 active:scale-95">
                    Save Profile Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Footer Meta -->
    <div class="mt-8 text-center">
        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">
            System Identity Status: <span class="text-emerald-500">Verified Issuer</span>
        </p>
    </div>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
$(document).ready(function() {
    $('#form-update-profile').on('submit', function(e) {
        e.preventDefault();
        
        const account_name = $('#account_name').val().trim();
        const p1 = $('#new_pass').val().trim();
        const p2 = $('#confirm_pass').val().trim();

        // 1. Validations
        if (!account_name) {
            Swal.fire('Error', 'Account Name is required.', 'error');
            return;
        }

        if (p1 !== "" && p1.length < 6) {
            Swal.fire({
                icon: 'warning',
                title: 'Security Notice',
                text: 'Password is too short. Please use at least 6 characters.',
                confirmButtonColor: '#0f172a',
                customClass: { popup: 'rounded-[2.5rem]' }
            });
            return;
        }

        if (p1 !== p2) {
            Swal.fire({
                icon: 'error',
                title: 'Mismatch',
                text: 'The entered passwords do not match.',
                confirmButtonColor: '#0f172a',
                customClass: { popup: 'rounded-[2.5rem]' }
            });
            return;
        }

        // 2. Processing UI
        Swal.fire({
            title: 'Updating Profile',
            text: 'Securing your new identity...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); },
            customClass: { popup: 'rounded-[2.5rem]' }
        });

        // 3. API Request
        $.ajax({
            url: API_URL,
            type: 'POST',
            data: { 
                action: 'update_personal_profile', // Updated V4 API Action
                account_name: account_name,
                new_password: p1,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Profile Updated',
                        text: 'Your identity and credentials have been updated.',
                        showConfirmButton: false,
                        timer: 2000,
                        customClass: { popup: 'rounded-[2.5rem]' }
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: res.data || 'Could not update profile.',
                        confirmButtonColor: '#0f172a',
                        customClass: { popup: 'rounded-[2.5rem]' }
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Communication with server failed.',
                    confirmButtonColor: '#0f172a',
                    customClass: { popup: 'rounded-[2.5rem]' }
                });
            }
        });
    });
});
</script>