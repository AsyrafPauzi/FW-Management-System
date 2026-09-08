<?php
/**
 * View: Registration Wizard
 * Location: views/wizard.php
 * Version: 5.2.0 (Full DB Sync: Gender, KWSP, EPASS, Consolidated Payments)
 */

// 1. Initialize Data Logic
$worker_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Stick to current step after save, default to 1
$current_ui_step = isset($_GET['step']) ? intval($_GET['step']) : 1;

$worker = null;
$current_stage = 1;
$additional_payments = [];

if ($worker_id > 0) {
    $worker = $db->get_worker($worker_id);
    if ($worker) {
        $current_stage = intval($worker->current_stage);
        // Fetch dynamic additional payments for the financial section
        $additional_payments = $db->get_additional_payments($worker_id);
    } else {
        $worker_id = 0;
    }
}

// Logic: Ensure navigation doesn't land on removed steps (2, 5, 6)
if (!isset($_GET['step'])) {
    $current_ui_step = ($current_stage >= 8) ? 1 : $current_stage;
}
if ($current_ui_step == 2) $current_ui_step = 3;
if ($current_ui_step == 5) $current_ui_step = 5; // Step 5 remains for Status only
if ($current_ui_step == 6) $current_ui_step = 7;

$archives = ($worker_id > 0) ? $db->get_worker_archives($worker_id) : [];
$is_admin = current_user_can_admin();
$is_staff = current_user_is_staff();
$force_edit = isset($_GET['force_edit']) && $_GET['force_edit'] == 1;

// Logic: After CIDB (stage 8), staff without edit permission stay view-only; staff with can_edit match admin on this wizard.
$is_fully_completed = ($current_stage >= 8);
$can_edit_user = current_user_can_edit();
$staff_view_only = ($is_staff && $is_fully_completed && !$force_edit && !$can_edit_user);
$wizard_unlock = $is_admin || $force_edit || ($is_fully_completed && $can_edit_user);

/**
 * Attribute Helper: Handles locking/disabling inputs
 */
function render_lock_attr($step, $curr, $wizard_unlock, $staff_view_only, $is_fully_completed) {
    if ($wizard_unlock) return 'class="fws-input w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-blue-500 transition font-bold text-slate-700 shadow-sm"';
    if ($staff_view_only) return 'disabled readonly class="fws-input w-full bg-slate-100 border-slate-200 p-4 rounded-2xl text-slate-400 cursor-not-allowed font-bold"';
    
    // Step 1 is always editable for financials unless fully completed
    if ($step == 1) return 'class="fws-input w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-blue-500 transition font-bold text-slate-700 shadow-sm"';
    
    $effective_curr = $curr;
    $can_edit_prior = !$is_fully_completed && current_user_can_edit();
    if ($step < $effective_curr && !$can_edit_prior) return 'readonly class="fws-input w-full bg-slate-100 border-slate-200 p-4 rounded-2xl text-slate-500 cursor-not-allowed font-bold pointer-events-none"';
    if ($step > $effective_curr) return 'disabled class="fws-input w-full bg-slate-50 border-none p-4 rounded-2xl text-slate-200 cursor-not-allowed font-bold shadow-none"';
    
    return 'class="fws-input w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-blue-500 transition font-bold text-slate-700 shadow-sm"';
}

/**
 * File Field Helper
 */
function render_file_field($url, $name, $step, $curr, $wizard_unlock, $staff_view_only, $is_fully_completed) {
    $html = '';
    $can_revisit_files = !$is_fully_completed && current_user_can_edit() && $step <= $curr;
    if (!$staff_view_only && ($wizard_unlock || $step == 1 || $step == $curr || $can_revisit_files)) {
        $html .= '<div class="mb-2"><input type="file" name="'.$name.'" class="block w-full text-[10px] text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer transition-all"></div>';
    }
    if ($url) {
        $html .= '<div class="mt-4 flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-100 rounded-2xl text-emerald-700 shadow-sm"><span class="text-xl">📄</span><div class="overflow-hidden"><p class="text-[10px] font-black uppercase text-emerald-800 leading-none mb-1">Evidence Uploaded</p><a href="'.e($url).'" target="_blank" class="text-xs font-bold hover:underline truncate block">'.e(basename($url)).'</a></div></div>';
    }
    return $html;
}
?>

<style>
    .hidden-step { display: none !important; }
    .fws-input:focus { outline: none; }
    select[readonly] { pointer-events: none; touch-action: none; }
    .scroll-hide::-webkit-scrollbar { display: none; }
    .scroll-hide { -ms-overflow-style: none; scrollbar-width: none; }
    .lbl { display: block; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin-bottom: 0.5rem; margin-left: 0.5rem; }
    .inp { width: 100%; background-color: #f8fafc; border: none; padding: 1rem; border-radius: 1rem; font-weight: 700; color: #334155; transition: all 0.2s; box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05); }
    .inp:focus { background-color: #fff; ring: 2px; ring-color: #3b82f6; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); outline: none; }
    .inp-lg { font-size: 1.25rem; padding: 1.25rem; }
</style>

<script>
window.activeStep = <?php echo $current_ui_step; ?>;
window.maxWorkerStage = <?php echo (int)$current_stage; ?>;
window.isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
window.wizardFullAccess = <?php echo $wizard_unlock ? 'true' : 'false'; ?>;
window.isForceEdit = <?php echo ($force_edit) ? 'true' : 'false'; ?>;
window.isFullyCompleted = <?php echo ($is_fully_completed) ? 'true' : 'false'; ?>;
window.staffSaveOnPriorSteps = <?php echo (!$is_fully_completed && $can_edit_user && !$wizard_unlock) ? 'true' : 'false'; ?>;
</script>

<div class="container mx-auto max-w-5xl py-4 md:py-6 animate-fade-in pb-32 px-2 md:px-0">
    
    <?php if ($is_fully_completed && !$force_edit): ?>
    <div class="bg-emerald-500 text-white p-5 md:p-6 rounded-2xl md:rounded-[2rem] mb-6 flex flex-col md:flex-row items-center justify-between shadow-xl border border-emerald-400 gap-4">
        <div class="flex items-center gap-4 text-center md:text-left">
            <span class="text-2xl md:text-3xl font-black italic uppercase tracking-tighter leading-none">Completed</span>
            <?php if ($staff_view_only): ?>
            <p class="text-xs md:sm font-bold opacity-90">Registration cycle finalized. Access restricted to View-Only mode.</p>
            <?php else: ?>
            <p class="text-xs md:sm font-bold opacity-90">Registration cycle finalized. You can still update this record from the wizard.</p>
            <?php endif; ?>
        </div>
        <div class="flex gap-2 w-full md:w-auto">
            <a href="?page=workers" class="flex-1 text-center bg-white/10 hover:bg-white/20 px-4 py-2 rounded-xl text-[10px] font-black uppercase transition border border-white/20">Exit</a>
            <?php if($is_admin): ?>
                <a href="?page=wizard&id=<?php echo (int)$worker_id; ?>&force_edit=1" class="flex-1 text-center bg-white text-emerald-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase transition shadow-lg">Admin Edit</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-[2rem] md:rounded-[3rem] shadow-2xl overflow-hidden border border-slate-100">
        
        <!-- Header -->
        <div class="bg-slate-900 text-white p-6 md:p-10 flex flex-col md:flex-row justify-between items-center relative">
            <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-blue-600 rounded-full opacity-10 blur-3xl"></div>
            <div class="flex items-center gap-4 md:gap-6 relative z-10 w-full md:w-auto">
                <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-600 rounded-xl md:rounded-2xl flex items-center justify-center text-xl md:text-2xl font-black italic shadow-2xl">
                    <?php echo $staff_view_only ? 'VIEW' : ($worker ? 'EDIT' : 'NEW'); ?>
                </div>
                <div class="overflow-hidden">
                    <h2 class="text-xl md:text-3xl font-black uppercase italic tracking-tighter leading-none truncate"><?php echo $worker ? e($worker->passport_number) : 'Registration'; ?></h2>
                    <p class="text-slate-400 text-[10px] md:text-sm font-bold uppercase tracking-widest mt-1 md:mt-2 truncate"><?php echo $worker ? e($worker->full_name) : 'Phase: Data Capture'; ?></p>
                </div>
            </div>
            <div class="text-center md:text-right relative z-10 mt-6 md:mt-0 w-full md:w-auto">
                <div class="text-[9px] md:text-[10px] font-black uppercase tracking-widest text-blue-400 mb-1">Stage Progression</div>
                <div class="text-xl md:text-2xl font-black italic"><?php echo ($is_fully_completed) ? '08' : min(8, $current_stage); ?> <span class="text-slate-600 text-sm md:text-base font-bold uppercase not-italic">/ 08</span></div>
            </div>
        </div>

        <!-- Progress Tabs (Step 2 and 6 Removed) -->
        <div class="bg-white border-b border-slate-100 px-6 md:px-10 py-6 md:py-8 overflow-x-auto scroll-hide">
            <div class="flex items-center min-w-max justify-between gap-4 md:gap-2">
                <?php 
                $lbls = [1=>'REG & PAY', 3=>'FOMEMA', 4=>'Insurance', 5=>'Levy Sts', 7=>'Permit', 8=>'CIDB'];
                foreach($lbls as $i => $label): 
                    $isActive = ($i == $current_ui_step); 
                    $isDone = ($i < $current_stage) || ($i == 8 && $current_stage >= 8);
                    $cClass = $isActive ? 'bg-blue-600 ring-4 md:ring-8 ring-blue-50 text-white scale-110 shadow-lg' : ($isDone ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-100' : 'bg-slate-100 text-slate-300');
                    $tClass = $isActive ? 'text-blue-600 font-black' : ($isDone ? 'text-emerald-500 font-bold' : 'text-slate-300 font-bold');
                    $onClick = ($wizard_unlock || $i <= $current_stage) ? "onclick=\"window.switchTab($i)\"" : "";
                ?>
                <div class="flex flex-col items-center group cursor-pointer" <?php echo $onClick; ?>>
                    <div id="tab-circle-<?php echo $i; ?>" class="w-8 h-8 md:w-10 md:h-10 rounded-xl md:rounded-2xl flex items-center justify-center font-black text-xs md:text-sm mb-2 md:mb-3 transition-all duration-500 <?php echo $cClass; ?>">
                        <?php echo $isDone ? '✔' : $i; ?>
                    </div>
                    <span id="tab-text-<?php echo $i; ?>" class="text-[8px] md:text-[9px] uppercase tracking-widest <?php echo $tClass; ?>"><?php echo e($label); ?></span>
                </div>
                <?php if($i < 8): ?><div class="w-8 h-1 bg-slate-100 rounded-full mx-1"></div><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <form id="fws-wizard-form" enctype="multipart/form-data" class="bg-slate-50/20" novalidate>
            <input type="hidden" name="worker_id" value="<?php echo (int)$worker_id; ?>">
            <input type="hidden" name="current_stage" value="<?php echo (int)$current_stage; ?>">
            <input type="hidden" name="balance_due" id="balance_due_hidden" value="<?php echo $worker ? $worker->balance_due : 0; ?>">
            
            <div class="p-6 md:p-12 space-y-6 md:space-y-8 max-w-4xl mx-auto min-h-[400px]">

                <!-- STEP 1: REGISTRATION & PAYMENT DETAILS -->
                <div id="step-content-1" class="fws-step-content <?php echo ($current_ui_step != 1) ? 'hidden-step' : ''; ?>">
                    <div class="bg-white p-6 md:p-10 rounded-[2rem] md:rounded-[2.5rem] shadow-sm border border-slate-100">
                        <h3 class="text-lg md:text-xl font-black text-slate-800 mb-6 md:mb-8 uppercase italic border-b pb-4">REGISTRATION & PAYMENT DETAILS</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                            
                            <div class="md:col-span-2">
                                <label class="lbl">Category *</label>
                                <select name="category" class="inp" data-required="true" <?php echo render_lock_attr(1, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>>
                                    <option value="Calling Visa" <?php echo selected($worker->category ?? '', 'Calling Visa'); ?>>Calling Visa</option>
                                    <option value="Programme" <?php echo selected($worker->category ?? '', 'Programme'); ?>>Programme</option>
                                    <option value="Tukar Majikan" <?php echo selected($worker->category ?? '', 'Tukar Majikan'); ?>>Tukar Majikan</option>
                                </select>
                            </div>

                            <div><label class="lbl">Passport No *</label><input type="text" name="passport_number" id="passport_number" value="<?php echo $worker ? e($worker->passport_number) : ''; ?>" <?php echo render_lock_attr(1, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?> data-required="true"></div>
                            <div><label class="lbl">Full Name *</label><input type="text" id="worker_full_name" name="full_name" value="<?php echo $worker ? e($worker->full_name) : ''; ?>" <?php echo render_lock_attr(1, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?> data-required="true"></div>
                            <div>
    <label class="lbl">Phone Number (Optional)</label>
    <input type="text" name="phone_number" value="<?php echo $worker ? e($worker->phone_number) : ''; ?>" <?php echo render_lock_attr(1, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?> placeholder="e.g. 60123456789">
</div>
                            <div><label class="lbl">KWSP Member No</label><input type="text" name="kwsp_no" class="inp" value="<?php echo $worker ? e($worker->kwsp_no) : ''; ?>" <?php echo render_lock_attr(1, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>></div>
                            <div><label class="lbl">Nationality</label><select name="nationality" class="inp" <?php echo render_lock_attr(1, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>>
                                <option value="Bangladesh" <?php echo selected($worker->nationality ?? '', 'Bangladesh'); ?>>Bangladesh</option>
                                <option value="Indonesia" <?php echo selected($worker->nationality ?? '', 'Indonesia'); ?>>Indonesia</option>
                                <option value="Nepal" <?php echo selected($worker->nationality ?? '', 'Nepal'); ?>>Nepal</option>
                                <option value="Myanmar" <?php echo selected($worker->nationality ?? '', 'Myanmar'); ?>>Myanmar</option>
                            </select></div>
                            
                            <!-- GENDER FIELD (Now Included) -->
                            <div><label class="lbl">Gender</label><select name="gender" class="inp" <?php echo render_lock_attr(1, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>>
                                <option value="Male" <?php echo selected($worker->gender ?? '', 'Male'); ?>>Male</option>
                                <option value="Female" <?php echo selected($worker->gender ?? '', 'Female'); ?>>Female</option>
                            </select></div>

                            <div><label class="lbl">Birth Date</label><input type="date" name="dob" class="inp" value="<?php echo $worker ? e($worker->dob) : ''; ?>" <?php echo render_lock_attr(1, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>></div>
                            <div><label class="lbl">Passport Expiry</label><input type="date" name="passport_expiry" class="inp" value="<?php echo $worker ? e($worker->passport_expiry) : ''; ?>" <?php echo render_lock_attr(1, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>></div>
                            <div class="md:col-span-2"><label class="lbl">Visa Expiry</label><input type="date" name="visa_expiry" class="inp" value="<?php echo $worker ? e($worker->visa_expiry) : ''; ?>" <?php echo render_lock_attr(1, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>></div>

                            <!-- FINANCIAL OVERVIEW -->
                            <div class="md:col-span-2 mt-10 flex justify-between items-center border-b pb-4 mb-4">
                                <h3 class="text-lg font-black text-slate-800 uppercase italic">B. Financial Overview</h3>
                                <button type="button" id="btn-gen-consolidated-or" class="bg-blue-600 text-white px-6 py-2 rounded-xl text-[10px] font-black uppercase shadow-xl hover:bg-blue-700 transition">📄 Generate Official Receipt</button>
                            </div>

                            <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100 relative">
                                <label class="lbl text-blue-600 text-xs">Total Payable Amount (MYR)</label>
                                <input type="number" step="0.01" id="total_payable" name="total_payable" class="inp-lg font-black text-slate-800 bg-transparent border-none w-full outline-none" value="<?php echo $worker ? e($worker->total_payable) : '0.00'; ?>" placeholder="0.00" <?php echo ($staff_view_only)?'readonly':''; ?>>
                            </div>

                            <div class="bg-red-50 p-6 rounded-2xl border border-red-100">
                                <label class="lbl text-red-600 text-xs">Balance Due</label>
                                <div id="display_balance" class="text-3xl font-black text-red-600"><?php echo number_format($worker->balance_due ?? 0, 2); ?></div>
                            </div>

                            <div class="md:col-span-2 mt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <label class="lbl text-lg">Payments & Receipts</label>
                                    <?php if(!$staff_view_only): ?>
                                    <button type="button" id="btn-add-payment-row" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-600 transition shadow-lg">+ Add Payment</button>
                                    <?php endif; ?>
                                </div>
                                <div id="payment_rows_container" class="space-y-3">
                                    <?php foreach($additional_payments as $ap): ?>
                                    <div class="grid grid-cols-12 gap-2 payment-row items-center bg-blue-50/30 p-3 rounded-2xl border border-blue-100">
                                        <div class="col-span-3">
                                            <input type="text" name="add_pay_desc[]" value="<?php echo e($ap->description); ?>" class="w-full text-xs font-bold bg-transparent outline-none p-2" readonly placeholder="Description">
                                        </div>
                                        <div class="col-span-2">
                                            <input type="text" name="add_pay_ref[]" value="<?php echo e($ap->ref_no); ?>" class="check-receipt-ref w-full text-xs font-bold bg-transparent outline-none p-2" readonly placeholder="Ref No">
                                        </div>
                                        <div class="col-span-2">
                                            <input type="number" name="add_pay_amount[]" value="<?php echo e($ap->amount); ?>" class="add-pay-amt w-full text-xs font-bold bg-transparent outline-none text-right p-2" readonly>
                                        </div>
                                        <div class="col-span-2">
                                            <input type="date" name="add_pay_date[]" value="<?php echo e($ap->payment_date); ?>" class="w-full text-xs bg-transparent outline-none pointer-events-none" readonly>
                                        </div>
                                        <input type="hidden" name="existing_pay_proof[]" value="<?php echo e($ap->proof_file); ?>">
                                        <input type="file" name="add_pay_proof[]" style="display:none;">
                                        <div class="col-span-3 flex justify-end gap-1">
                                            <?php if($ap->proof_file): ?>
                                                <a href="<?php echo e($ap->proof_file); ?>" target="_blank" class="text-[9px] bg-white border border-blue-200 text-blue-600 px-2 py-1 rounded-lg font-bold">Doc</a>
                                            <?php endif; ?>
                                            <button type="button" class="btn-gen-step1-or bg-emerald-500 text-white px-2 py-1 rounded-lg text-[9px] font-black uppercase">OR</button>
                                            <button type="button" class="text-red-400 font-bold px-2 hover:text-red-600 transition" onclick="$(this).closest('.payment-row').remove(); window.calculateBalance();">×</button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: FOMEMA -->
                <div id="step-content-3" class="fws-step-content <?php echo ($current_ui_step != 3) ? 'hidden-step' : ''; ?>">
                    <div class="bg-white p-6 md:p-10 rounded-[2rem] md:rounded-[2.5rem] shadow-sm border border-slate-100">
                        <h3 class="text-lg md:text-xl font-black text-slate-800 mb-6 md:mb-8 uppercase italic border-b pb-4 tracking-widest">03. FOMEMA Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                            <div><label class="lbl">Status</label>
                            <select name="fomema_status" class="inp" <?php echo render_lock_attr(3, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>>
                                <option value="">Select Status</option>
                                <option value="Pending" <?php echo selected($worker->fomema_status ?? '', 'Pending'); ?>>Pending</option>
                                <option value="In Progress" <?php echo selected($worker->fomema_status ?? '', 'In Progress'); ?>>In Progress</option>
                                <option value="Fit" <?php echo selected($worker->fomema_status ?? '', 'Fit'); ?>>Fit</option>
                                <option value="Unfit" <?php echo selected($worker->fomema_status ?? '', 'Unfit'); ?>>Unfit</option>
                            </select></div>
                            <div><label class="lbl">Expired Date</label>
                                <input type="date" name="fomema_expiry" class="inp" value="<?php echo $worker ? e($worker->fomema_expiry) : ''; ?>" <?php echo render_lock_attr(3, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>>
                            </div>
                            <div class="md:col-span-2"><label class="lbl">Clinic Code</label>
                                <input type="text" name="fomema_code" class="inp" placeholder="Optional" value="<?php echo $worker ? e($worker->fomema_code) : ''; ?>" <?php echo render_lock_attr(3, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>>
                            </div>
                            <div class="md:col-span-2 bg-slate-50 p-6 md:p-8 rounded-[2rem] border-2 border-dashed border-slate-200">
                                <label class="lbl mb-2">FOMEMA Document</label>
                                <?php echo render_file_field($worker->fomema_proof ?? '', 'fomema_proof', 3, (int)$current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: INSURANCE -->
                <div id="step-content-4" class="fws-step-content <?php echo ($current_ui_step != 4) ? 'hidden-step' : ''; ?>">
                    <div class="bg-white p-6 md:p-10 rounded-[2rem] md:rounded-[2.5rem] shadow-sm border border-slate-100">
                        <h3 class="text-lg md:text-xl font-black text-slate-800 mb-6 md:mb-8 uppercase italic border-b pb-4">04. Insurance Matrix</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                            <div><label class="lbl">Policy No</label><input type="text" name="insurance_policy" class="inp" value="<?php echo $worker ? e($worker->insurance_policy) : ''; ?>" <?php echo render_lock_attr(4, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>></div>
                            <div><label class="lbl">Provider</label><input type="text" name="insurance_provider" class="inp" value="<?php echo $worker ? e($worker->insurance_provider) : ''; ?>" <?php echo render_lock_attr(4, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>></div>
                            <div class="md:col-span-2"><label class="lbl">Expiry Date</label><input type="date" name="insurance_expiry" class="inp" value="<?php echo $worker ? e($worker->insurance_expiry) : ''; ?>" <?php echo render_lock_attr(4, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>></div>
                            <div class="md:col-span-2 bg-slate-50 p-6 md:p-8 rounded-[2rem] border-2 border-dashed border-slate-200">
                                <label class="lbl mb-2">Insurance Document (JPG, PNG or PDF)</label>
                                <?php echo render_file_field($worker->insurance_proof ?? '', 'insurance_proof', 4, (int)$current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 5: LEVY STATUS -->
                <div id="step-content-5" class="fws-step-content <?php echo ($current_ui_step != 5) ? 'hidden-step' : ''; ?>">
                    <div class="bg-white p-6 md:p-10 rounded-[2rem] md:rounded-[2.5rem] shadow-sm border border-slate-100">
                        <h3 class="text-lg md:text-xl font-black text-slate-800 uppercase italic border-b pb-4 mb-8">05. Levy Status</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                            <div class="md:col-span-2">
                                <label class="lbl">Status</label>
                                <select name="levy_status" class="inp" <?php echo render_lock_attr(5, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>>
                                    <option value="">Select Status</option>
                                    <option value="Pending Submission" <?php echo selected($worker->levy_status ?? '', 'Pending Submission'); ?>>Pending Submission</option>
                                    <option value="Pending OTP" <?php echo selected($worker->levy_status ?? '', 'Pending OTP'); ?>>Pending OTP</option>
                                    <option value="Pending Insurance" <?php echo selected($worker->levy_status ?? '', 'Pending Insurance'); ?>>Pending Insurance</option>
                                    <option value="OTP Done" <?php echo selected($worker->levy_status ?? '', 'OTP Done'); ?>>OTP Done</option>
                                    <option value="Pending Levy" <?php echo selected($worker->levy_status ?? '', 'Pending Levy'); ?>>Pending Levy</option>
                                    <option value="Levy Paid" <?php echo selected($worker->levy_status ?? '', 'Levy Paid'); ?>>Levy Paid</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 7: PERMIT -->
                <div id="step-content-7" class="fws-step-content <?php echo ($current_ui_step != 7) ? 'hidden-step' : ''; ?>">
                    <div class="bg-white p-10 rounded-[2rem] md:rounded-[2.5rem] shadow-sm border border-slate-100">
                        <h3 class="text-xl font-black text-slate-800 mb-8 uppercase italic border-b pb-4 tracking-widest">07. Permit (PLKS)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                            <div class="md:col-span-2">
                                <label class="lbl">Status</label>
                                <select name="permit_status" class="inp" <?php echo render_lock_attr(7, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>>
                                    <option value="">Select Status</option>
                                    <option value="Pending Collection" <?php echo selected($worker->permit_status ?? '', 'Pending Collection'); ?>>Pending Collection</option>
                                    <option value="Collected" <?php echo selected($worker->permit_status ?? '', 'Collected'); ?>>Collected</option>
                                </select>
                            </div>
                            <div class="md:col-span-2"><label class="lbl">Permit Sticker No *</label><input type="text" name="permit_number" class="inp" value="<?php echo $worker ? e($worker->permit_number) : ''; ?>" <?php echo render_lock_attr(7, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?> data-required="true"></div>
                            <div><label class="lbl">Issue Date *</label><input type="date" name="permit_issue" class="inp" value="<?php echo $worker ? e($worker->permit_issue) : ''; ?>" <?php echo render_lock_attr(7, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?> data-required="true"></div>
                            <div><label class="lbl">Expiry Date *</label><input type="date" name="permit_expiry" class="inp" value="<?php echo $worker ? e($worker->permit_expiry) : ''; ?>" <?php echo render_lock_attr(7, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?> data-required="true"></div>
                            <div class="md:col-span-2 bg-blue-50 p-6 rounded-3xl border-2 border-blue-100 mt-4">
    <label class="lbl mb-2 text-blue-600">Passport Copy (Full Page) *</label>
    <?php echo render_file_field($worker->passport_copy_proof ?? '', 'passport_copy_proof', 7, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>
</div>
                            <div class="md:col-span-2 bg-red-50 p-6 rounded-3xl border-2 border-red-100">
                                <label class="lbl mb-2 text-red-600">EPASS Worker Document (Mandatory) *</label>
                                <?php echo render_file_field($worker->epass_worker_proof ?? '', 'epass_worker_proof', 7, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 8: CIDB -->
                <div id="step-content-8" class="fws-step-content <?php echo ($current_ui_step != 8) ? 'hidden-step' : ''; ?>">
                    <div class="bg-white p-10 rounded-[2rem] md:rounded-[2.5rem] shadow-sm border border-slate-100">
                        <h3 class="text-xl font-black text-slate-800 mb-8 uppercase italic border-b pb-4 tracking-widest">08. CIDB Matrix</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                            <div><label class="lbl">Status *</label><select name="cidb_status" class="inp" <?php echo render_lock_attr(8, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>><option value="Pending" <?php echo selected($worker->cidb_status??'','Pending');?>>Pending</option><option value="Done" <?php echo selected($worker->cidb_status??'','Done');?>>Done</option></select></div>
                            <div><label class="lbl">Category *</label><select name="cidb_category" class="inp" <?php echo render_lock_attr(8, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>><option value="General Worker" <?php echo selected($worker->cidb_category??'','General Worker');?>>General Worker</option><option value="Skill Worker" <?php echo selected($worker->cidb_category??'','Skill Worker');?>>Skill Worker</option></select></div>
                            <div class="md:col-span-2"><label class="lbl">Expiry Date *</label><input type="date" name="cidb_expiry" class="inp" value="<?php echo $worker ? e($worker->cidb_expiry) : ''; ?>" <?php echo render_lock_attr(8, $current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?> data-required="true"></div>
                            <div class="md:col-span-2 bg-slate-50 p-6 md:p-8 rounded-[2rem] border-2 border-dashed border-slate-200">
                                <label class="lbl mb-2">Upload Latest CIDB Proof</label>
                                <?php echo render_file_field($worker->cidb_proof ?? '', 'cidb_proof', 8, (int)$current_stage, $wizard_unlock, $staff_view_only, $is_fully_completed); ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="p-6 md:p-10 bg-white border-t border-slate-100 flex flex-col md:flex-row justify-between items-center sticky bottom-0 z-50 shadow-2xl gap-6">
                <div class="flex gap-2 w-full md:w-auto">
                   <a href="?page=workers" class="flex-1 text-center px-6 md:px-8 py-4 border-2 border-slate-100 rounded-2xl font-black uppercase text-[10px] tracking-widest text-slate-400 hover:bg-slate-50 transition">Exit</a>
                   <button type="button" id="fws-btn-prev" onclick="window.prevTab()" style="display: none;" class="flex-1 px-6 md:px-8 py-4 bg-slate-100 text-slate-600 rounded-2xl font-black uppercase text-[10px] tracking-widest transition hover:bg-slate-200 shadow-sm">Previous</button>
                </div>
                
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div id="fws-action-group" class="flex gap-2 w-full md:w-auto" style="display: flex;">
                        <button type="button" class="fws-btn-save flex-1 md:flex-none bg-white border-2 border-slate-900 text-slate-900 px-6 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-slate-900 hover:text-white transition shadow-lg" data-advance="false">Draft</button>
                        <button type="button" class="fws-btn-save flex-2 md:flex-none bg-blue-600 text-white px-8 md:px-10 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-xl hover:bg-blue-700 transition transform hover:-translate-y-1" data-advance="true"><?php echo (int)$current_stage < 8 ? 'Confirm & Next' : 'Submit & Finish'; ?></button>
                    </div>
                    <div id="fws-nav-group" class="flex flex-col md:flex-row gap-2 w-full md:w-auto items-center" style="display: none;">
                        <div class="bg-slate-900 text-white px-4 py-3 rounded-2xl text-[9px] font-black uppercase tracking-widest border border-slate-800 flex items-center gap-2 mr-4"><span>🔒</span> Locked</div>
                        <button type="button" id="fws-btn-next-tab" onclick="window.nextTab()" class="w-full md:w-auto px-10 py-4 bg-blue-600 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest shadow-xl transition transform hover:-translate-y-1">Next Tab →</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- ARCHIVE SECTION (Synced with V5 Data) -->
    <?php if(!empty($archives)): ?>
    <div class="max-w-5xl mx-auto mt-12 bg-white rounded-[2rem] shadow-2xl border border-slate-200 p-6 md:p-10 animate-fade-in">
        <h3 class="text-xl md:text-2xl font-black uppercase italic tracking-tighter mb-2 text-slate-800 tracking-widest">📜 Renewal Archive History</h3>
        <p class="text-[11px] text-slate-500 font-bold mb-8 leading-relaxed">Records from each <strong>Renew</strong> action on the worker directory. Filling the wizard again does not remove these — only use <strong>Remove</strong> if renewal was started by mistake.</p>
        <div class="space-y-6">
            <?php foreach($archives as $a): 
                $archive_snapshot = json_decode($a->archive_data);
                $d = $archive_snapshot->worker_details ?? $archive_snapshot; 
                $adds = $archive_snapshot->additional_payments ?? [];
            ?>
            <div class="bg-slate-50 p-6 rounded-3xl border border-slate-200 shadow-inner">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-[10px] font-black uppercase text-blue-500 tracking-widest">Cycle Archived: <?php echo format_date_my($a->archive_date); ?></span>
                    <span class="bg-slate-900 text-white text-[8px] font-bold px-3 py-1 rounded-full uppercase">Category: <?php echo e($d->category ?? 'N/A'); ?></span>
                    <?php if ($can_edit_user): ?>
                    <button type="button"
                        class="btn-remove-worker-archive text-[9px] font-black uppercase text-red-600 hover:text-red-800 border border-red-200 hover:border-red-400 px-3 py-1 rounded-lg transition cursor-pointer"
                        data-archive-id="<?php echo (int)$a->id; ?>"
                        data-worker-id="<?php echo (int)$worker_id; ?>">Remove</button>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div><span class="text-slate-400 block text-[9px] uppercase font-bold">Passport Exp</span><p class="text-xs font-black text-slate-700"><?php echo format_date_my($d->passport_expiry ?? ''); ?></p></div>
                    <div><span class="text-slate-400 block text-[9px] uppercase font-bold">Permit Exp</span><p class="text-xs font-black text-slate-700"><?php echo format_date_my($d->permit_expiry ?? ''); ?></p></div>
                    <div><span class="text-slate-400 block text-[9px] uppercase font-bold">Total Payable</span><p class="text-xs font-black text-slate-700">MYR <?php echo number_format((float)($d->total_payable ?? 0), 2); ?></p></div>
                    <div><span class="text-slate-400 block text-[9px] uppercase font-bold">Final Balance</span><p class="text-xs font-black text-red-600">MYR <?php echo number_format((float)($d->balance_due ?? 0), 2); ?></p></div>
                </div>

                <!-- Show Archived Payments Breakdown -->
                <?php if(!empty($adds)): ?>
                <div class="border-t border-slate-200 pt-4">
                    <p class="text-[9px] font-black uppercase text-slate-400 mb-2">Cycle Payments:</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach($adds as $pay): ?>
                            <span class="bg-white border border-slate-200 px-3 py-1 rounded-lg text-[10px] font-bold text-slate-600">
                                <?php echo e($pay->description); ?>: <span class="text-slate-900">MYR <?php echo number_format($pay->amount, 2); ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    window.addEventListener('load', function() {
        if(typeof window.switchTab === 'function') {
            window.switchTab(window.activeStep);
        }
    });
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-remove-worker-archive');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var archiveId = parseInt(btn.getAttribute('data-archive-id'), 10);
        var workerId = parseInt(btn.getAttribute('data-worker-id'), 10);
        if (!archiveId || !workerId) return;
        if (typeof window.deleteWorkerArchive === 'function') {
            window.deleteWorkerArchive(archiveId, workerId);
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Scripts outdated', text: 'Please hard-refresh the page (Ctrl+F5 or Cmd+Shift+R) and try again.' });
        } else {
            alert('Please refresh the page and try again.');
        }
    });
</script>