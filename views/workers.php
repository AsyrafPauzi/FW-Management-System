<?php
/**
 * View: Worker Directory (Final Fix)
 * Location: views/workers.php
 * Version: 4.2.5 (High-Contrast Renewal & Expiry Fix)
 */

// 1. Handle Filters
$search = sanitize_text_field($_POST['s'] ?? '');
$cat = sanitize_text_field($_POST['cat'] ?? '');
$start = sanitize_text_field($_POST['start_date'] ?? '');
$end = sanitize_text_field($_POST['end_date'] ?? '');

// 2. Fetch Workers (Order by Expiry ASC handled in functions.php)
$workers = $db->get_all_workers($search, $cat, $start, $end);
$is_admin = current_user_can_admin();

// Stage Mappings
$stages = [1=>'Identity', 2=>'Reg Pay', 3=>'FOMEMA', 4=>'Insurance', 5=>'Levy Pay', 7=>'Permit', 8=>'CIDB', 9=>'Completed'];
?>

<div class="animate-fade-in pb-20">
    <!-- HEADER AREA -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-6">
        <div>
            <h2 class="text-3xl md:text-4xl font-black text-slate-800 uppercase italic tracking-tighter leading-none">Worker Directory</h2>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-2">Prioritizing Soonest Expiry</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
            <!-- COMPACT EXPORT TOOLS -->
            <div class="flex bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm h-11">
                <button id="btn-export-excel" class="px-4 border-r border-slate-100 hover:bg-slate-50 transition flex items-center gap-2" title="Export Excel">
                    <span class="text-base">📊</span><span class="text-[10px] font-black text-green-600">EXCEL</span>
                </button>
                <button id="btn-export-pdf" class="px-4 border-r border-slate-100 hover:bg-slate-50 transition flex items-center gap-2" title="Export PDF">
                    <span class="text-base">📄</span><span class="text-[10px] font-black text-red-600">PDF</span>
                </button>
                <button id="btn-export-csv" class="px-4 hover:bg-slate-50 transition flex items-center gap-2" title="Export CSV">
                    <span class="text-base">📝</span><span class="text-[10px] font-black text-blue-600">CSV</span>
                </button>
            </div>
            
            <a href="?page=wizard" class="h-11 bg-slate-900 text-white px-6 rounded-xl font-black uppercase text-[10px] tracking-widest flex items-center shadow-lg hover:bg-blue-600 transition transform active:scale-95">
                + New Worker
            </a>
            <div class="flex items-center gap-2">
    <!-- DOWNLOAD TEMPLATE BUTTON -->
    <button onclick="downloadImportTemplate()" class="h-11 bg-slate-100 text-slate-500 px-4 rounded-xl font-black uppercase text-[10px] hover:bg-slate-200 transition">
        ⬇ Template
    </button>

    <!-- BULK IMPORT BUTTON -->
    <button onclick="triggerImport()" class="h-11 bg-emerald-50 text-emerald-600 px-4 rounded-xl font-black uppercase text-[10px] border border-emerald-100 hover:bg-emerald-600 hover:text-white transition">
        ↑ Bulk Import
    </button>
</div>
<!-- Hidden file input -->
<input type="file" id="import_excel_file" class="hidden" accept=".xlsx, .xls">


        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-200 mb-10">
        <form method="POST" class="grid grid-cols-12 gap-4 items-end">
            <div class="col-span-12 lg:col-span-4">
                <label class="lbl">Search registry</label>
                <input type="text" name="s" id="filter_search" value="<?php echo e($search); ?>" class="inp h-11" placeholder="Passport or Name...">
            </div>
            <div class="col-span-12 md:col-span-6 lg:col-span-2">
                <label class="lbl">Category</label>
                <select name="cat" id="filter_category" class="inp h-11">
                    <option value="">All Categories</option>
                    <option value="Calling Visa" <?php selected($cat, 'Calling Visa'); ?>>Calling Visa</option>
                    <option value="Programme" <?php selected($cat, 'Programme'); ?>>Programme</option>
                    <option value="Tukar Majikan" <?php selected($cat, 'Tukar Majikan'); ?>>Tukar Majikan</option>
                </select>
            </div>
            <div class="col-span-12 md:col-span-6 lg:col-span-4">
                <label class="lbl">Expiry Range</label>
                <div class="flex gap-2">
                    <!-- Expiry Dates -->
<input type="date" name="start_date" id="filter_start" value="<?php echo e($start); ?>" class="inp h-11 text-xs flex-1">
<input type="date" name="end_date" id="filter_end" value="<?php echo e($end); ?>" class="inp h-11 text-xs flex-1">
                </div>
            </div>
            <div class="col-span-12 lg:col-span-2">
                <button type="submit" class="w-full bg-blue-600 text-white h-11 rounded-xl font-black uppercase text-xs hover:bg-blue-700 transition shadow-lg">Filter</button>
            </div>
        </form>
    </div>

    <!-- TABLE -->
    <div class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="fws-table w-full text-left border-collapse min-w-[1000px]">
                <thead class="bg-slate-900 text-white text-[10px] uppercase font-black tracking-widest">
                    <tr>
                        <th class="p-6">Worker Profile</th>
                        <th class="p-6">Category</th>
                        <th class="p-6">Stage</th>
                        <th class="p-6">Permit Expiry</th>
                        <th class="p-6 text-right">Balance Due</th>
                        <th class="p-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="workers-tbody" class="divide-y divide-slate-100">
                    <?php if (empty($workers)): ?>
                        <tr><td colspan="6" class="p-20 text-center text-slate-300 font-bold uppercase italic">No worker records found.</td></tr>
                    <?php else: foreach ($workers as $w): 
                        $stage_val = intval($w->current_stage);
                        $stage_name = ($stage_val >= 8) ? 'Completed' : ($stages[$stage_val] ?? 'Processing');
                        $stage_color = ($stage_val >= 8) ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-blue-50 text-blue-600 border-blue-100';

                        $exp = $w->permit_expiry;
                        $exp_display = format_date_my($exp);
                        $exp_class = 'text-slate-400';
                        $show_renewal = false;
                        
                        // RENEWAL & EXPIRY LOGIC
                        if($exp && $exp != '0000-00-00' && $exp != '1970-01-01') {
                            $expiry_time = strtotime($exp);
                            $now = time();
                            $days_diff = floor(($expiry_time - $now) / (60 * 60 * 24));

                            if($days_diff < 0) {
                                $exp_class = 'text-red-600 font-black animate-pulse';
                                $show_renewal = true;
                            } elseif($days_diff <= 90) { // 3 months or less
                                $exp_class = 'text-orange-500 font-black';
                                $show_renewal = true;
                            } else {
                                $exp_class = 'text-emerald-600 font-black';
                            }
                        }
                    ?>
                        <tr class="hover:bg-slate-50 transition-all group">
                            <td class="p-6">
                                <a href="?page=wizard&id=<?php echo (int)$w->id; ?>" class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 rounded-lg -m-1 p-1">
                                    <div class="font-black text-slate-800 text-sm uppercase group-hover:text-blue-600 transition hover:text-blue-600"><?php echo e($w->passport_number); ?></div>
                                    <div class="text-slate-400 text-[9px] font-black uppercase group-hover:text-slate-600"><?php echo e($w->full_name); ?></div>
                                </a>
                            </td>
                            <td class="p-6">
                                <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-[9px] font-black border border-slate-200 uppercase"><?php echo e($w->category ?? 'General'); ?></span>
                            </td>
                            <td class="p-6">
                                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase border <?php echo $stage_color; ?>">
                                    <?php echo e($stage_name); ?>
                                </span>
                            </td>
                            <td class="p-6 <?php echo $exp_class; ?> text-sm">
                                <?php echo $exp_display; ?>
                            </td>
                            <td class="p-6 text-right">
                                <?php if($w->balance_due > 0): ?>
                                    <div class="text-red-600 font-black">MYR <?php echo number_format((float)$w->balance_due, 2); ?></div>
                                <?php else: ?>
                                    <div class="text-emerald-500 font-black uppercase text-[10px]">Fully Paid</div>
                                <?php endif; ?>
                            </td>
                            <td class="p-6 text-right">
                                <div class="flex justify-end gap-2 items-center">
                                    <?php if($show_renewal): ?>
                                        <button onclick="renewWorker(<?php echo (int)$w->id; ?>, '<?php echo addslashes(e($w->full_name)); ?>')" 
                                                class="bg-orange-500 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-md hover:bg-orange-600 transition flex items-center gap-1">
                                            <span>🔄</span> Renew
                                        </button>
                                    <?php endif; ?>

                                   <!-- MANAGE/EDIT BUTTON -->
        <!-- Admin always can, Staff checks 'can_edit' permission -->
        <?php if($is_admin || (isset($_SESSION['can_edit']) && $_SESSION['can_edit'] == 1)): ?>
        <a href="?page=wizard&id=<?php echo (int)$w->id; ?>" class="bg-blue-50 text-blue-600 px-4 py-2 rounded-xl text-[10px] font-black hover:bg-blue-600 hover:text-white transition shadow-sm">
            Manage
        </a>
        <?php endif; ?>
        
        <!-- DELETE BUTTON -->
        <!-- Only show if Admin OR Staff has 'can_delete' permission -->
        <?php if($is_admin || (isset($_SESSION['can_delete']) && $_SESSION['can_delete'] == 1)): ?>
        <button class="fws-delete-worker text-slate-300 hover:text-red-600 transition transform hover:scale-125 ml-2" 
                data-id="<?php echo (int)$w->id; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </button>
        <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div id="workers-pagination"></div>
    </div>
</div>

<style>
    .lbl { display:block; font-size:9px; font-weight:900; text-transform:uppercase; color:#94a3b8; margin-bottom:5px; margin-left: 5px; letter-spacing: 0.05em; }
    .inp { width:100%; background:#f8fafc; border:none; padding:10px 16px; border-radius:12px; font-weight:bold; color:#334155; outline:none; transition:all 0.2s; box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05); }
    .inp:focus { background:#fff; box-shadow:0 0 0 2px #3b82f6; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setupPagination({ id: 'workers', tbodyId: 'workers-tbody', navId: 'workers-pagination', perPage: 10 });
});
</script>