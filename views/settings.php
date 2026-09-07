<?php
/**
 * View: Hardened Global Branding Settings
 * Location: views/settings.php
 * Version: 3.0.0 (Security Hardened)
 */

// 1. Permission Check (Admin Only)
if (!current_user_can_admin()) { 
    echo '<div class="bg-red-50 p-10 rounded-3xl text-center font-bold text-red-600 uppercase tracking-widest border-2 border-red-100">Access Denied</div>'; 
    return; 
}

// 2. Load Current Settings
$current_name = $db->get_setting('company_name', 'FW System');
$current_logo = $db->get_setting('company_logo', '');
?>

<div class="container mx-auto max-w-2xl px-2 md:px-0 animate-fade-in pb-20">
    <!-- Header Area -->
    <div class="mb-8 md:mb-10 text-center md:text-left">
        <h2 class="text-3xl md:text-4xl font-black text-slate-800 uppercase italic tracking-tighter leading-none">Branding</h2>
        <p class="text-slate-400 text-xs md:text-sm font-bold uppercase tracking-widest mt-2">Manage System Identity</p>
    </div>

    <!-- Settings Card -->
    <div class="bg-white rounded-[2.5rem] md:rounded-[3rem] shadow-2xl border border-slate-200 p-6 md:p-10 shadow-slate-200/50">
        <form id="form-branding" enctype="multipart/form-data" class="space-y-6 md:space-y-8">
            <!-- Security & Routing -->
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
            
            <!-- Company Name -->
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-2 tracking-widest ml-2 group-focus-within:text-blue-500 transition-colors">Display Name</label>
                <input type="text" name="c_name" value="<?php echo e($current_name); ?>" 
                    class="w-full bg-slate-50 border-2 border-slate-100 p-4 rounded-2xl focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none transition-all font-bold text-slate-700 shadow-inner"
                    placeholder="Enter system name">
            </div>

            <!-- Logo Upload -->
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-500 mb-2 tracking-widest ml-2 group-focus-within:text-blue-500 transition-colors">Update Logo</label>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                    <input type="file" name="c_logo_file" accept="image/*" 
                        class="flex-1 bg-slate-50 border-2 border-slate-100 p-3 rounded-2xl text-[10px] md:text-xs font-black uppercase tracking-widest file:hidden cursor-pointer hover:border-blue-200 transition-colors">
                </div>
                
                <!-- Logo Preview Box -->
                <div class="mt-6 p-6 md:p-8 bg-slate-900 rounded-[2rem] md:rounded-3xl flex items-center justify-center border border-slate-800 shadow-inner relative overflow-hidden">
                    <!-- Decorative background for preview -->
                    <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#4f46e5 0.5px, transparent 0.5px); background-size: 10px 10px;"></div>
                    
                    <div class="text-center relative z-10">
                        <p class="text-[8px] font-black text-slate-500 uppercase mb-4 tracking-widest">Current Active Logo</p>
                        <div class="min-h-[64px] flex items-center justify-center">
                            <?php if($current_logo): ?>
                                <img src="<?php echo e($current_logo); ?>?t=<?php echo time(); ?>" class="h-12 md:h-16 w-auto object-contain mx-auto drop-shadow-2xl">
                            <?php else: ?>
                                <div class="w-12 h-12 md:w-16 md:h-16 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center text-white font-black italic mx-auto shadow-xl">FW</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <p class="text-[9px] text-slate-400 mt-4 px-2 italic font-bold uppercase text-center md:text-left">
                    💡 Recommended: PNG or SVG with transparent background.
                </p>
            </div>

            <!-- Action Button -->
            <div class="pt-2">
                <button type="submit" class="w-full bg-slate-900 text-white p-5 rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-blue-600 transition-all shadow-xl shadow-slate-200 transform hover:-translate-y-1 active:scale-95">
                    Save System Branding
                </button>
            </div>
        </form>
        
        <!-- Add this below the Save Branding Button in settings.php -->
<div class="mt-10 pt-10 border-t border-slate-100">
    <h3 class="text-sm font-black text-slate-800 uppercase italic mb-4">System Maintenance</h3>
    <div class="bg-slate-50 p-6 rounded-3xl border border-slate-200 space-y-4">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <p class="text-xs font-bold text-slate-700">Database Backup</p>
                <p class="text-[10px] text-slate-400 uppercase font-bold">Download a full SQL copy of all data and process history.</p>
            </div>
            <button type="button" onclick="downloadSystemBackup()" class="bg-white border-2 border-slate-900 text-slate-900 px-6 py-2 rounded-xl font-black uppercase text-[10px] hover:bg-slate-900 hover:text-white transition">Download .SQL</button>
        </div>
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-4 border-t border-slate-200">
            <div>
                <p class="text-xs font-bold text-slate-700">Save Backup on Server</p>
                <p class="text-[10px] text-slate-400 uppercase font-bold">Writes SQL + uploads zip to <code class="normal-case">/backups</code> (14-day retention). Cron: <code class="normal-case">php cli/backup.php</code></p>
            </div>
            <button type="button" onclick="runServerBackup()" class="bg-slate-900 text-white px-6 py-2 rounded-xl font-black uppercase text-[10px] hover:bg-blue-600 transition">Run Backup</button>
        </div>
        <div class="pt-4 border-t border-slate-200">
            <p class="text-xs font-bold text-slate-700 mb-1">Health Check</p>
            <p class="text-[10px] text-slate-400 uppercase font-bold mb-3">Public monitor URL: <code class="normal-case">/health.php</code></p>
            <button type="button" onclick="checkSystemHealth()" class="bg-white border-2 border-slate-300 text-slate-700 px-6 py-2 rounded-xl font-black uppercase text-[10px] hover:border-blue-500 hover:text-blue-600 transition">Check Now</button>
            <pre id="health-result" class="mt-3 hidden text-[10px] bg-white border border-slate-200 rounded-2xl p-4 overflow-auto text-slate-600"></pre>
        </div>
    </div>
</div>
    </div>
</div>

<script>

function downloadSystemBackup() {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'api.php';
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'action';
    input.value = 'download_backup';
    var csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf_token';
    csrf.value = CSRF_TOKEN;
    form.appendChild(input);
    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
}

function runServerBackup() {
    Swal.fire({
        title: 'Running Backup...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); },
        customClass: { popup: 'rounded-[2.5rem]' }
    });
    $.ajax({
        url: API_URL,
        type: 'POST',
        dataType: 'json',
        data: { action: 'run_backup', csrf_token: CSRF_TOKEN, keep_days: 14 },
        success: function(res) {
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Backup Saved',
                    text: res.data.sql_file + (res.data.uploads_zip ? ' + ' + res.data.uploads_zip : ''),
                    customClass: { popup: 'rounded-[2.5rem]' }
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Backup Failed', text: res.data || 'Unknown error', customClass: { popup: 'rounded-[2.5rem]' } });
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'Backup Failed', text: 'Network or server error.', customClass: { popup: 'rounded-[2.5rem]' } });
        }
    });
}

function checkSystemHealth() {
    $.ajax({
        url: API_URL,
        type: 'POST',
        dataType: 'json',
        data: { action: 'get_health', csrf_token: CSRF_TOKEN },
        success: function(res) {
            var el = document.getElementById('health-result');
            el.classList.remove('hidden');
            el.textContent = JSON.stringify(res.data || res, null, 2);
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'Health check failed', customClass: { popup: 'rounded-[2.5rem]' } });
        }
    });
}


$(document).ready(function() {
    $('#form-branding').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        
        Swal.fire({ 
            title: 'Applying Brand...', 
            text: 'Updating your portal identity',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading() },
            customClass: {
                popup: 'rounded-[2.5rem]'
            }
        });

        $.ajax({
            url: API_URL,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if(res.success) {
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'Identity Saved', 
                        text: 'Branding has been updated successfully.',
                        timer: 2000,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'rounded-[2.5rem]'
                        }
                    }).then(() => {
                        window.location.reload(); 
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Save Failed',
                        text: res.data || 'An error occurred while saving.',
                        confirmButtonColor: '#0f172a',
                        customClass: {
                            popup: 'rounded-[2.5rem]',
                            confirmButton: 'rounded-xl px-6 py-3 font-bold uppercase text-xs tracking-widest'
                        }
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Could not connect to the server. Check your connection.',
                    confirmButtonColor: '#0f172a',
                    customClass: {
                        popup: 'rounded-[2.5rem]',
                        confirmButton: 'rounded-xl px-6 py-3 font-bold uppercase text-xs tracking-widest'
                    }
                });
            }
        });
    });
});
</script>