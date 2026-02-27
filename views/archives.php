<?php
/**
 * View: Global Archive Master Ledger (Hardened & Updated)
 * Location: views/archives.php
 * Version: 5.2.0 (Full Wizard Data Sync: Gender, KWSP, CIDB & Expiries)
 */

if (!current_user_can_admin()) { 
    echo '<div class="bg-red-50 p-10 rounded-3xl text-center font-bold text-red-600 uppercase tracking-widest border-2 border-red-100">Access Denied</div>'; 
    return; 
}

// 1. Handle Filters
$search = sanitize_text_field($_POST['s'] ?? '');
$start_date = sanitize_text_field($_POST['start_date'] ?? '');
$end_date = sanitize_text_field($_POST['end_date'] ?? '');

$where = ["1=1"];
$params = [];
if ($search) {
    $where[] = "(passport_number LIKE ? OR full_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($start_date && $end_date) {
    $where[] = "DATE(archive_date) BETWEEN ? AND ?";
    $params[] = $start_date; $params[] = $end_date;
}

$sql = "SELECT * FROM worker_archives WHERE " . implode(' AND ', $where) . " ORDER BY archive_date DESC";
$stmt = $db->pdo->prepare($sql);
$stmt->execute($params);
$all_archives = $stmt->fetchAll();
?>

<div class="animate-fade-in pb-20">
    <!-- Header Area -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-6">
        <div>
            <h2 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter leading-none">History Master Ledger</h2>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-2">Historical Snapshots of All Process Cycles</p>
        </div>
        <div class="flex gap-3 w-full lg:w-auto">
            <button id="btn-export-archive-excel" class="flex-1 lg:flex-none bg-emerald-600 text-white px-6 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl hover:bg-emerald-700 transition transform active:scale-95">📊 Excel</button>
            <button id="btn-export-archive-pdf" class="flex-1 lg:flex-none bg-red-600 text-white px-6 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl hover:bg-red-700 transition transform active:scale-95">📄 PDF</button>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="bg-white p-6 md:p-8 rounded-[2.5rem] shadow-sm border border-slate-200 mb-10">
        <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
            <div class="md:col-span-2">
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 ml-2">Search Passport / Name</label>
                <input type="text" name="s" id="arch_search" value="<?php echo e($search); ?>" placeholder="Enter details..." class="w-full bg-slate-50 border-none rounded-2xl px-5 py-4 font-bold text-slate-700 shadow-inner outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 ml-2">From Date</label>
                <input type="date" name="start_date" id="arch_start" value="<?php echo e($start_date); ?>" class="w-full bg-slate-50 border-none rounded-2xl px-5 py-4 font-bold text-slate-700 shadow-inner outline-none">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 ml-2">To Date</label>
                <input type="date" name="end_date" id="arch_end" value="<?php echo e($end_date); ?>" class="w-full bg-slate-50 border-none rounded-2xl px-5 py-4 font-bold text-slate-700 shadow-inner outline-none">
            </div>
            <div class="md:col-span-4 flex justify-end gap-2 border-t pt-6">
                <a href="?page=archives" class="bg-slate-100 text-slate-400 px-6 py-4 rounded-2xl font-bold uppercase text-[10px] hover:bg-slate-200 transition">Reset</a>
                <button type="submit" class="bg-slate-900 text-white px-10 py-4 rounded-2xl font-black uppercase text-xs tracking-widest shadow-lg hover:bg-blue-600 transition">Filter Archives</button>
            </div>
        </form>
    </div>

    <!-- MASTER TABLE -->
    <div class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <!-- Increased min-width to accommodate ALL 28+ columns -->
            <table class="w-full text-left border-collapse min-w-[4200px]">
                <thead>
                    <tr class="bg-slate-900 text-white text-[9px] uppercase font-black tracking-widest text-center">
                        <th class="p-4 border-r border-slate-700" colspan="2">System Info</th>
                        <th class="p-4 border-r border-slate-700" colspan="9">Identity & Biodata</th>
                        <th class="p-4 border-r border-slate-700" colspan="2">Financials</th>
                        <th class="p-4 border-r border-slate-700" colspan="3">FOMEMA Status</th>
                        <th class="p-4 border-r border-slate-700" colspan="3">Insurance Details</th>
                        <th class="p-4 border-r border-slate-700" colspan="2">Levy Status</th>
                        <th class="p-4 border-r border-slate-700" colspan="4">Permit (PLKS)</th>
                        <th class="p-4" colspan="3">CIDB Details</th>
                        <th class="p-4" colspan="4">Documents</th>
                    </tr>
                    <tr class="bg-slate-800 text-slate-300 text-[8px] uppercase font-black tracking-tighter">
                        <!-- System -->
                        <th class="p-4">Archived On</th><th class="p-4 border-r">Worker ID</th>
                        <!-- Identity -->
                        <th class="p-4">Category</th><th class="p-4">Passport No</th><th class="p-4">Passport Exp</th><th class="p-4">Full Name</th><th class="p-4">KWSP No</th><th class="p-4">Origin</th><th class="p-4">Gender</th><th class="p-4 border-r">DOB</th><th class="p-4">Phone No</th>
                        
                        <!-- Financials -->
                        <th class="p-4">Total Payable</th><th class="p-4 border-r">Final Balance</th>
                        <!-- FOM -->
                        <th class="p-4">Status</th><th class="p-4">Clinic Code</th><th class="p-4 border-r">Expiry</th>
                        <!-- INS -->
                        <th class="p-4">Policy No</th><th class="p-4">Provider</th><th class="p-4 border-r">Expiry</th>
                        <!-- LEV -->
                        <th class="p-4">Status</th><th class="p-4 border-r">Reference No</th>
                        <!-- PER -->
                        <th class="p-4">Status</th><th class="p-4">Sticker No</th><th class="p-4">Issue Date</th><th class="p-4 border-r">Expiry Date</th>
                        <!-- CIDB -->
                        <th class="p-4">Status</th><th class="p-4">Category</th><th class="p-4">Expiry Date</th>
                        
                        <th class="p-4">Documents Proof</th><
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-[11px] font-bold text-slate-600">
                    <?php if (empty($all_archives)): ?>
                        <tr><td colspan="28" class="p-20 text-center text-slate-300 font-bold uppercase italic">No archived records found.</td></tr>
                    <?php else: foreach ($all_archives as $a): 
                        $snapshot = json_decode($a->archive_data);
                        // FIXED: Detection of nested (V4/V5) vs flat data (V3)
                        $d = isset($snapshot->worker_details) ? $snapshot->worker_details : $snapshot;
                    ?>
                    <tr class="hover:bg-blue-50/50 transition-colors">
                        <!-- System Info -->
                        <td class="p-4 whitespace-nowrap"><?php echo date('d/m/Y H:i', strtotime($a->archive_date)); ?></td>
                        <td class="p-4 border-r">#<?php echo (int)$a->worker_id; ?></td>
                        
                        <!-- Identity & Biodata -->
                        <td class="p-4 uppercase"><?php echo e($d->category ?? '-'); ?></td>
                        <td class="p-4 font-black text-blue-600"><?php echo e($a->passport_number); ?></td>
                        <td class="p-4"><?php echo format_date_my($d->passport_expiry ?? ''); ?></td>
                        <td class="p-4 text-slate-900"><?php echo e($a->full_name); ?></td>
                        <td class="p-4"><?php echo e($d->kwsp_no ?? '-'); ?></td>
                        <td class="p-4"><?php echo e($d->nationality ?? '-'); ?></td>
                        <td class="p-4"><?php echo e($d->gender ?? '-'); ?></td>
                        <td class="p-4 border-r whitespace-nowrap"><?php echo format_date_my($d->dob ?? ''); ?></td>
                        <td class="p-4"><?php echo e($d->phone_number ?? '-'); ?></td>
                        <!-- Financials -->
                        <td class="p-4 text-slate-900">MYR <?php echo number_format((float)($d->total_payable ?? 0), 2); ?></td>
                        <td class="p-4 border-r text-red-600">MYR <?php echo number_format((float)($d->balance_due ?? 0), 2); ?></td>
                        
                        <!-- FOMEMA -->
                        <td class="p-4"><span class="px-2 py-0.5 rounded-full text-[9px] <?php echo (($d->fomema_status ?? '') =='Fit')?'bg-emerald-100 text-emerald-700':'bg-orange-100 text-orange-700'; ?>"><?php echo e($d->fomema_status ?? 'Pending'); ?></span></td>
                        <td class="p-4"><?php echo e($d->fomema_code ?? '-'); ?></td>
                        <td class="p-4 border-r"><?php echo format_date_my($d->fomema_expiry ?? ($d->fomema_date ?? '')); ?></td>
                        
                        <!-- Insurance -->
                        <td class="p-4"><?php echo e($d->insurance_policy ?? '-'); ?></td>
                        <td class="p-4"><?php echo e($d->insurance_provider ?? '-'); ?></td>
                        <td class="p-4 border-r"><?php echo format_date_my($d->insurance_expiry ?? ''); ?></td>

                        <!-- Levy Status -->
                        <td class="p-4 uppercase text-[9px]"><?php echo e($d->levy_status ?? '-'); ?></td>
                        <td class="p-4 border-r"><?php echo e($d->levy_reference ?? '-'); ?></td>
                        
                        <!-- Permit (PLKS) -->
                        <td class="p-4 uppercase text-[9px]"><?php echo e($d->permit_status ?? '-'); ?></td>
                        <td class="p-4 text-slate-900"><?php echo e($d->permit_number ?? '-'); ?></td>
                        <td class="p-4"><?php echo format_date_my($d->permit_issue ?? ''); ?></td>
                        <td class="p-4 border-r text-red-600 font-bold"><?php echo format_date_my($d->permit_expiry ?? ''); ?></td>
                        
                        <!-- CIDB -->
                        <td class="p-4 text-slate-700 uppercase"><?php echo e($d->cidb_status ?? '-'); ?></td>
                        <td class="p-4 text-slate-700 uppercase"><?php echo e($d->cidb_category ?? '-'); ?></td>
                        <td class="p-4"><?php echo format_date_my($d->cidb_expiry ?? ''); ?></td>
                        <td class="p-4">
    <div class="flex flex-wrap gap-1">
        <?php if(!empty($d->fomema_proof)): ?>
            <a href="<?php echo e($d->fomema_proof); ?>" target="_blank" class="px-2 py-1 bg-slate-100 text-[8px] rounded font-bold uppercase">Fomema</a>
        <?php endif; ?>
        <?php if(!empty($d->cidb_proof)): ?>
            <a href="<?php echo e($d->cidb_proof); ?>" target="_blank" class="px-2 py-1 bg-slate-100 text-[8px] rounded font-bold uppercase">CIDB</a>
        <?php endif; ?>
        <?php if(!empty($d->epass_worker_proof)): ?>
            <a href="<?php echo e($d->epass_worker_proof); ?>" target="_blank" class="px-2 py-1 bg-slate-100 text-[8px] rounded font-bold uppercase">E-Pass</a>
        <?php endif; ?>
        <?php if(!empty($d->passport_copy_proof)): ?>
            <a href="<?php echo e($d->passport_copy_proof); ?>" target="_blank" class="px-2 py-1 bg-blue-100 text-blue-700 text-[8px] rounded font-bold uppercase">Passport</a>
        <?php endif; ?>
    </div>
</td>
                        
                        
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    /**
     * Unified Export Function for Archives
     */
    function exportArchive(type) {
        var s = $('#arch_search').val();
        var sd = $('#arch_start').val();
        var ed = $('#arch_end').val();

        Swal.fire({ 
            title: 'Generating Report...', 
            text: 'Extracting all proces fields...', 
            allowOutsideClick: false, 
            didOpen: () => Swal.showLoading() 
        });

        $.ajax({
            url: API_URL,
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'export_archives', 
                csrf_token: CSRF_TOKEN,
                search: s, start_date: sd, end_date: ed
            },
            success: function(res) {
                Swal.close();
                if(res.success && res.data.length > 0) {
                    var d = res.data; 
                    var fn = "Full_Archive_Master_Ledger_" + new Date().toISOString().slice(0,10);
                    
                    if(type === 'excel') {
                        if (typeof XLSX === 'undefined') {
                            Swal.fire('Error', 'Excel library missing.', 'error');
                            return;
                        }
                        var wb = XLSX.utils.book_new(); 
                        var ws = XLSX.utils.json_to_sheet(d);
                        XLSX.utils.book_append_sheet(wb, ws, "Master_Ledger"); 
                        XLSX.writeFile(wb, fn + ".xlsx");
                    } else {
                        const { jsPDF } = window.jspdf;
                        // Use a custom very wide page format to fit all columns
                        const doc = new jsPDF('l', 'mm', [600, 300]); 
                        doc.setFontSize(14);
                        doc.text("WORKER ARCHIVE MASTER LEDGER (FULL DATA SYNC)", 14, 15);
                        doc.autoTable({ 
                            head: [Object.keys(d[0])], 
                            body: d.map(o => Object.values(o)), 
                            startY: 25, 
                            styles: { fontSize: 6, cellPadding: 1 }, 
                            theme: 'grid' 
                        });
                        doc.save(fn + ".pdf");
                    }
                } else {
                    Swal.fire('No Data', 'No records found for these filters.', 'info');
                }
            },
            error: function() {
                Swal.fire('Error', 'Could not communicate with the server.', 'error');
            }
        });
    }

    $('#btn-export-archive-excel').on('click', function(e) { e.preventDefault(); exportArchive('excel'); });
    $('#btn-export-archive-pdf').on('click', function(e) { e.preventDefault(); exportArchive('pdf'); });
});
</script>