<?php
/**
 * View: Hardened Invoice Generator & History
 * Location: views/invoice.php
 * Version: 5.0.0 (Dynamic Labels & Account Name Sync)
 */

// 1. Fetch Data using the Standalone DB class
$invoices = $db->pdo->query("SELECT * FROM invoices ORDER BY created_at DESC LIMIT 50")->fetchAll();
$is_admin = current_user_can_admin();

// Distinct recipient names for Pay To / Bill To suggestions
$client_names = [];
foreach ($invoices as $inv) {
    $name = trim($inv->client_name ?? '');
    if ($name !== '') {
        $client_names[$name] = true;
    }
}
$client_names = array_keys($client_names);
sort($client_names, SORT_NATURAL | SORT_FLAG_CASE);
?>

<div class="container mx-auto px-2 md:px-0 animate-fade-in pb-20">
    
    <!-- GENERATOR SECTION -->
    <div class="max-w-5xl mx-auto bg-white rounded-[2rem] md:rounded-[3rem] shadow-2xl border border-gray-200 overflow-hidden mb-10">
        
        <!-- Header -->
        <div class="bg-slate-900 text-white p-6 md:p-10 flex flex-col sm:flex-row justify-between items-center gap-6 relative">
            <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-blue-600 rounded-full opacity-10 blur-3xl"></div>
            <div class="relative z-10 text-center sm:text-left">
                <h2 class="text-2xl md:text-3xl font-black uppercase italic tracking-tighter leading-none">Document Generator</h2>
                <p class="text-slate-400 text-xs md:text-sm font-bold uppercase tracking-widest mt-2">Generate or Edit Invoices & Vouchers</p>
            </div>
            <div class="flex flex-wrap justify-center gap-3 relative z-10 w-full sm:w-auto">
                <button type="button" onclick="location.reload()" class="flex-1 sm:flex-none bg-slate-700 hover:bg-slate-600 text-white px-4 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest transition shadow-lg">
                    Reset
                </button>
                <button id="fws-save-gen-pdf" class="flex-1 sm:flex-none bg-blue-600 hover:bg-blue-50 text-white px-6 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest shadow-xl transition flex items-center justify-center gap-2">
                    <span>📥</span> Save & PDF
                </button>
            </div>
        </div>

        <div class="p-6 md:p-10">
            <form id="fws-invoice-form">
                <!-- HIDDEN ID FOR EDITING -->
                <input type="hidden" id="inv_id" value="0">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 mb-8">
                    <!-- Column 1 -->
                    <div class="space-y-4 md:space-y-6">
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest ml-2">Document Type</label>
                            <select id="inv_type" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-blue-500 transition font-bold text-slate-700 shadow-sm outline-none">
                                <option value="Invoice">Invoice</option>
                                <option value="Payment Voucher">Payment Voucher</option>
                                <option value="Official Receipt">Official Receipt</option>
                                <option value="Refund Receipt">Refund Receipt</option>
                            </select>
                        </div>
                        <div>
                            <!-- UPDATED: Added ID to label for dynamic switching in script.js -->
                            <label id="dynamic_recipient_label" class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest ml-2">Bill To / Pay To</label>
                            <input type="text" id="inv_client" list="inv_client_suggestions" autocomplete="off" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-blue-500 transition font-bold text-slate-700 shadow-sm outline-none" placeholder="Enter name or company">
                            <datalist id="inv_client_suggestions">
                                <?php foreach ($client_names as $client_name): ?>
                                    <option value="<?php echo e($client_name); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <!-- Column 2 -->
                    <div class="space-y-4 md:space-y-6">
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest ml-2">Document Number</label>
                            <input type="text" id="inv_no" value="INV-<?php echo date('Ymd') . '-' . rand(100,999); ?>" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-blue-500 transition font-bold text-slate-700 shadow-sm outline-none font-mono uppercase">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-2 tracking-widest ml-2">Document Date</label>
                            <input type="date" id="inv_date" value="<?php echo date('Y-m-d'); ?>" class="w-full bg-slate-50 border-none p-4 rounded-2xl focus:ring-2 focus:ring-blue-500 transition font-bold text-slate-700 shadow-sm outline-none">
                        </div>
                    </div>
                </div>

                <!-- Line Items Container -->
                <div class="bg-slate-50 p-4 md:p-8 rounded-[2rem] border border-slate-200">
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 border-b border-slate-200 pb-4 gap-4">
                        <h3 class="font-black text-slate-800 uppercase tracking-tighter italic text-xl">Item Details</h3>
                        <button type="button" id="btn-add-item" class="w-full sm:w-auto text-[10px] bg-slate-900 hover:bg-blue-600 text-white px-6 py-2 rounded-xl font-black uppercase tracking-widest transition shadow-lg">
                            + Add Line Item
                        </button>
                    </div>

                    <!-- Labels Header (Hidden on Mobile) -->
                    <div class="grid grid-cols-12 gap-4 mb-4 px-4 text-[10px] uppercase font-black text-slate-400 tracking-widest hidden md:grid">
                        <div class="col-span-6">Description</div>
                        <div class="col-span-2 text-center">Qty</div>
                        <div class="col-span-3 text-right">Unit Price (MYR)</div>
                        <div class="col-span-1"></div>
                    </div>

                    <!-- Dynamic Rows Area -->
                    <div id="invoice-items-list" class="space-y-4">
                        <!-- Rows injected by script.js -->
                    </div>

                    <div class="mt-8 text-center md:text-right border-t border-slate-200 pt-6">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest mr-2 block md:inline mb-1 md:mb-0">Total Amount Payable</span>
                        <span class="text-3xl md:text-4xl font-black text-slate-900 tracking-tighter italic">MYR <span id="inv_total_display">0.00</span></span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- HISTORY TABLE -->
    <div class="max-w-5xl mx-auto bg-white rounded-[2.5rem] shadow-2xl border border-slate-200 overflow-hidden shadow-slate-200/50">
        <div class="bg-slate-800 text-white p-6 flex flex-col sm:flex-row justify-between items-center gap-4">
            <h3 class="font-black text-lg uppercase italic tracking-tighter flex items-center gap-2">
                <span>🕒</span> Generated History
            </h3>
            <?php if(!$is_admin): ?>
                <span class="text-[9px] font-black bg-slate-700 px-3 py-1 rounded-full uppercase tracking-widest border border-slate-600">Staff View (Read Only)</span>
            <?php endif; ?>
        </div>
        
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 text-[10px] uppercase text-slate-500 font-black tracking-widest">
                    <tr>
                        <th class="p-6 border-b">Document Date</th>
                        <th class="p-6 border-b">Doc No</th>
                        <th class="p-6 border-b text-center">Type</th>
                        <th class="p-6 border-b">Recipient</th>
                        <th class="p-6 border-b text-right">Total Amount</th>
                        <th class="p-6 border-b text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if(empty($invoices)): ?>
                        <tr><td colspan="6" class="p-12 text-center text-slate-300 font-bold uppercase tracking-widest">No documents generated yet.</td></tr>
                    <?php else: foreach($invoices as $inv): 
                        $date_display = !empty($inv->invoice_date) ? $inv->invoice_date : $inv->created_at;
                        
                        // Updated JSON data to include the issuer (created_by) for the new PDF signature block
                        $json_data = htmlspecialchars(json_encode([
                            'id' => (int)$inv->id,
                            'type' => $inv->type,
                            'doc_no' => $inv->doc_no,
                            'client' => $inv->client_name,
                            'items_json' => $inv->description,
                            'amount' => (float)$inv->amount,
                            'date' => date('Y-m-d', strtotime($date_display)),
                            'issuer' => $inv->created_by 
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="p-6 text-slate-600 font-bold text-sm whitespace-nowrap"><?php echo date('d M Y', strtotime($date_display)); ?></td>
                        <td class="p-6 font-mono font-black text-blue-600 text-sm whitespace-nowrap"><?php echo e($inv->doc_no); ?></td>
                        <td class="p-6 text-center">
                            <?php 
                                $type_class = 'bg-blue-50 text-blue-700 border-blue-100';
                                if($inv->type == 'Official Receipt') $type_class = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                                if($inv->type == 'Payment Voucher') $type_class = 'bg-red-50 text-red-700 border-red-100';
                                if($inv->type == 'Refund Receipt') $type_class = 'bg-orange-50 text-orange-700 border-orange-100';
                            ?>
                            <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase border <?php echo $type_class; ?>">
                                <?php echo e($inv->type); ?>
                            </span>
                        </td>
                        <td class="p-6 font-bold text-slate-700 min-w-[150px]">
                            <?php if (!empty($inv->client_name)): ?>
                                <button type="button" class="fws-fill-client text-left hover:text-blue-600 transition-colors" data-client="<?php echo e($inv->client_name); ?>" title="Use this name in Pay To / Bill To">
                                    <?php echo e($inv->client_name); ?>
                                </button>
                            <?php else: ?>
                                <span class="text-slate-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-6 text-right font-black text-slate-800 text-sm whitespace-nowrap">MYR <?php echo number_format($inv->amount, 2); ?></td>
                        <td class="p-6 text-right">
                            <div class="flex justify-end gap-2">
                                <button class="fws-download-inv w-10 h-10 flex items-center justify-center bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-xl hover:bg-emerald-500 hover:text-white transition-all shadow-sm" data-json="<?php echo $json_data; ?>" title="Download PDF">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                </button>
                                <?php 
        // Logic: Admin can edit anything. Staff can only edit if type is NOT Official Receipt.
        $user_can_edit_this = ($is_admin || $inv->type !== 'Official Receipt'); 
        
        if($user_can_edit_this): 
    ?>
        <button class="fws-edit-inv w-10 h-10 flex items-center justify-center bg-blue-50 text-blue-600 border border-blue-100 rounded-xl hover:bg-blue-600 hover:text-white transition-all shadow-sm" 
            data-json="<?php echo $json_data; ?>" title="Edit Record">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
        </button>
    <?php endif; ?>
                                <?php if($is_admin): ?>
                                    <button class="fws-del-inv w-10 h-10 flex items-center justify-center bg-red-50 text-red-500 border border-red-100 rounded-xl hover:bg-red-500 hover:text-white transition-all shadow-sm" data-id="<?php echo (int)$inv->id; ?>" title="Delete History">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
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