<?php
/**
 * View: Hardened System Audit Logs
 * Location: views/logs.php
 * Version: 3.0.0 (Security Hardened)
 */

// 1. Check Permissions
if (!current_user_can_admin()) {
    echo '<div class="bg-red-50 p-10 rounded-3xl text-center font-bold text-red-600 uppercase tracking-widest border-2 border-red-100">Access Denied</div>';
    return;
}

// 2. Pagination & Limit Logic
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($limit == -1) ? 0 : ($paged - 1) * $limit;

// 3. Fetch Data from DB
// Calls get_logs (also purges logs older than 90 days)
$logs = $db->get_logs($limit, $offset);
$total_items = (int)$db->get_total_logs();
$total_pages = ($limit == -1) ? 1 : ceil($total_items / $limit);

/**
 * Helper for Pagination URL Generation
 */
function get_log_page_url($new_paged, $new_limit) {
    return "?page=logs&paged=" . (int)$new_paged . "&limit=" . (int)$new_limit;
}
?>

<!-- STYLE OVERRIDES FOR VISIBILITY -->
<style>
    h3.font-bold.text-md.flex.items-center.gap-2 { color: white !important; }
    h2.text-2xl.font-bold { color: white !important; }
    h3.font-bold.text-lg.flex.items-center.gap-2 { color: white !important; }
    
    /* Custom Scrollbar for the responsive table */
    .custom-scrollbar::-webkit-scrollbar { height: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>

<div class="container mx-auto max-w-6xl animate-fade-in pb-20">
    
    <!-- HEADER BAR -->
    <div class="bg-slate-900 p-6 md:p-10 rounded-t-[2.5rem] shadow-xl border border-slate-800">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <span class="text-white text-xl">📜</span>
                </div>
                <div>
                    <h2 class="text-2xl font-bold uppercase italic tracking-tighter leading-none text-white">Audit Logs</h2>
                    <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest mt-1">Immutable system activity trace</p>
                </div>
            </div>

            <!-- ROW LIMIT SELECTOR -->
            <div class="flex items-center gap-3 bg-slate-800 p-2 rounded-2xl border border-slate-700 w-full md:w-auto">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-2">Show Records</label>
                <select onchange="location.href='?page=logs&limit=' + this.value" class="bg-slate-900 text-white text-xs font-bold border-none rounded-xl focus:ring-0 cursor-pointer p-2">
                    <option value="10" <?php selected($limit, 10); ?>>10 Rows</option>
                    <option value="20" <?php selected($limit, 20); ?>>20 Rows</option>
                    <option value="50" <?php selected($limit, 50); ?>>50 Rows</option>
                    <option value="100" <?php selected($limit, 100); ?>>100 Rows</option>
                    <option value="-1" <?php selected($limit, -1); ?>>All Records</option>
                </select>
            </div>
        </div>
    </div>

    <!-- TABLE AREA -->
    <div class="bg-white rounded-b-[2.5rem] shadow-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 text-[10px] uppercase font-black tracking-widest text-slate-400 border-b border-slate-100">
                    <tr>
                        <th class="p-6">Timeline</th>
                        <th class="p-6">User Entity</th>
                        <th class="p-6">Operation</th>
                        <th class="p-6">Activity Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="4" class="p-20 text-center text-slate-300 font-bold uppercase tracking-widest italic">No activity logs captured in current cycle</td>
                        </tr>
                    <?php else: foreach($logs as $l): 
                        // Style badges based on action type
                        $badge_style = 'bg-slate-100 text-slate-600';
                        if($l->action == 'CREATE') $badge_style = 'bg-emerald-50 text-emerald-600 border-emerald-100';
                        if($l->action == 'DELETE') $badge_style = 'bg-red-50 text-red-600 border-red-100';
                        if($l->action == 'UPDATE') $badge_style = 'bg-blue-50 text-blue-600 border-blue-100';
                        if($l->action == 'INVOICE') $badge_style = 'bg-purple-50 text-purple-600 border-purple-100';
                        if($l->action == 'SETTINGS') $badge_style = 'bg-orange-50 text-orange-600 border-orange-100';
                        if($l->action == 'SECURITY') $badge_style = 'bg-slate-900 text-white border-slate-800';
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="p-6">
                            <div class="text-xs font-black text-slate-700 font-mono tracking-tighter">
                                <?php echo date('d M Y', strtotime($l->timestamp)); ?>
                            </div>
                            <div class="text-[10px] text-slate-400 font-bold"><?php echo date('h:i:s A', strtotime($l->timestamp)); ?></div>
                        </td>
                        <td class="p-6">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-[10px] font-black text-slate-400 uppercase border border-slate-200">
                                    <?php echo e(substr($l->user_name, 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-800"><?php echo e($l->user_name); ?></div>
                                    <div class="text-[9px] font-black text-blue-500 uppercase tracking-tighter"><?php echo e($l->user_role); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="p-6">
                            <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border <?php echo $badge_style; ?>">
                                <?php echo e($l->action); ?>
                            </span>
                        </td>
                        <td class="p-6">
                            <div class="text-xs font-bold text-slate-600 leading-relaxed max-w-md">
                                <?php echo e($l->details); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION FOOTER -->
        <?php if ($limit != -1 && $total_pages > 1): ?>
        <div class="bg-slate-50 p-6 border-t border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                Displaying <?php echo (int)($offset + 1); ?> to <?php echo (int)min($offset + $limit, $total_items); ?> of <?php echo (int)$total_items; ?> entries
            </div>
            
            <div class="flex items-center gap-1">
                <!-- PREV -->
                <?php if ($paged > 1): ?>
                    <a href="<?php echo get_log_page_url($paged - 1, $limit); ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-blue-600 hover:text-white transition shadow-sm">«</a>
                <?php endif; ?>

                <!-- PAGE NUMBERS -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $paged): ?>
                        <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-600 text-white font-black text-xs shadow-lg shadow-blue-500/30"><?php echo $i; ?></span>
                    <?php elseif ($i == 1 || $i == $total_pages || ($i >= $paged - 1 && $i <= $paged + 1)): ?>
                        <a href="<?php echo get_log_page_url($i, $limit); ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-100 transition shadow-sm"><?php echo $i; ?></a>
                    <?php elseif ($i == $paged - 2 || $i == $paged + 2): ?>
                        <span class="w-8 h-8 flex items-center justify-center text-slate-300 font-bold">...</span>
                    <?php endif; ?>
                <?php endfor; ?>

                <!-- NEXT -->
                <?php if ($paged < $total_pages): ?>
                    <a href="<?php echo get_log_page_url($paged + 1, $limit); ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-blue-600 hover:text-white transition shadow-sm">»</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>