<?php
/**
 * View: Hardened Financial Reports Dashboard
 * Location: views/reports.php
 * Version: 4.3.1 (Excel Fix - Unique ID)
 */
if (!current_user_can_admin()) { 
    echo '<div class="bg-red-50 p-10 rounded-3xl text-center font-bold text-red-600 uppercase tracking-widest border-2 border-red-100">Access Denied</div>'; 
    return; 
}
?>

<div class="animate-fade-in pb-20">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-6">
        <div>
            <h2 class="text-3xl font-black text-slate-800 uppercase italic tracking-tighter leading-none">Financial Reports</h2>
            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-2">Unified Transaction Breakdown (Manual & Wizard)</p>
        </div>
    </div>

    <!-- CONTROLS -->
    <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-slate-200 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
            <div class="md:col-span-1">
                <label class="lbl">Report Type</label>
                <select id="rep_type" class="inp" onchange="toggleReportFilters()">
                    <option value="daily">Daily Report</option>
                    <option value="monthly">Monthly Report</option>
                </select>
            </div>
            
            <div id="filter_daily" class="md:col-span-2">
                <label class="lbl">Select Date</label>
                <input type="date" id="rep_date" class="inp" value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div id="filter_monthly" class="md:col-span-2 hidden">
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="lbl">Month</label>
                        <select id="rep_month" class="inp">
                            <?php for($m=1; $m<=12; $m++) echo "<option value='$m' ".($m==date('m')?'selected':'').">".date('F', mktime(0,0,0,$m,1))."</option>"; ?>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="lbl">Year</label>
                        <select id="rep_year" class="inp">
                            <?php for($y=date('Y'); $y>=2023; $y--) echo "<option value='$y' ".($y==date('Y')?'selected':'').">$y</option>"; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="md:col-span-2">
                <label class="lbl">Filter Document Type</label>
                <select id="rep_cat" class="inp">
                    <option value="">All Transactions</option>
                    <option value="Official Receipt">Official Receipt</option>
                    <option value="Payment Voucher">Payment Voucher</option>
                    <option value="Invoice">Invoice</option>
                </select>
            </div>

            <div class="md:col-span-1">
                <button onclick="loadReport()" class="w-full bg-slate-900 text-white py-3 rounded-xl font-black uppercase text-xs hover:bg-blue-600 transition shadow-lg">Load Data</button>
            </div>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-emerald-500 text-white p-6 rounded-[2rem] shadow-lg shadow-emerald-100">
            <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Money Received (In)</p>
            <p class="text-3xl font-black mt-2">MYR <span id="sum_in">0.00</span></p>
        </div>
        <div class="bg-red-500 text-white p-6 rounded-[2rem] shadow-lg shadow-red-100">
            <p class="text-[10px] font-black uppercase tracking-widest opacity-80">Money Paid (Out)</p>
            <p class="text-3xl font-black mt-2">MYR <span id="sum_out">0.00</span></p>
        </div>
        <div class="bg-white p-6 rounded-[2rem] border border-slate-200 shadow-sm flex flex-col justify-center items-center gap-2">
    <button id="btn-export-final-excel" class="w-full bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white py-3 rounded-xl font-black uppercase text-[10px] flex items-center justify-center gap-2 transition border border-emerald-100 shadow-sm">
        <span>📊</span> Export Excel
    </button>
   <button id="btn-export-final-pdf" class="w-full bg-red-50 text-red-600 hover:bg-red-600 hover:text-white py-3 rounded-xl font-black uppercase text-[10px] flex items-center justify-center gap-2 transition border border-red-100 shadow-sm">
    <span>📄</span> DOWNLOAD PDF REPORT
</button>
</div>
    </div>

    <!-- RESULT TABLE -->
    <div class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-200 overflow-hidden min-h-[300px]">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-900 text-white text-[10px] uppercase font-black tracking-widest">
                    <tr>
                        <th class="p-6">Date</th>
                        <th class="p-6">Type</th>
                        <th class="p-6">Client / Ref No</th>
                        <th class="p-6">Worker Category</th>
                        <th class="p-6 text-right">Amount (MYR)</th>
                    </tr>
                </thead>
                <tbody id="report_body" class="divide-y divide-slate-100 text-xs font-bold text-slate-600">
                    <tr><td colspan="5" class="p-20 text-center text-slate-300 italic">Select filters and click Load Data.</td></tr>
                </tbody>
            </table>
        </div>
        <div id="report-pagination"></div>
    </div>
</div>

<script>
// Use one specific global variable for this page
window.finalReportData = [];

function toggleReportFilters() {
    var type = document.getElementById('rep_type').value;
    if(type === 'daily') {
        document.getElementById('filter_daily').classList.remove('hidden');
        document.getElementById('filter_monthly').classList.add('hidden');
    } else {
        document.getElementById('filter_daily').classList.add('hidden');
        document.getElementById('filter_monthly').classList.remove('hidden');
    }
}

function loadReport() {
    var type = $('#rep_type').val();
    var payload = {
        action: 'get_report',
        csrf_token: CSRF_TOKEN,
        type: type,
        category: $('#rep_cat').val()
    };

    if(type === 'daily') payload.date = $('#rep_date').val();
    else { payload.month = $('#rep_month').val(); payload.year = $('#rep_year').val(); }

    Swal.fire({ title: 'Loading...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    $.post(API_URL, payload, function(res) {
        Swal.close();
        if(res.success) {
            window.finalReportData = res.data;

            var totalIn = 0;
            var totalOut = 0;
            var allRows = [];

            if(res.data && res.data.length > 0) {
                res.data.sort(function(a, b) {
                    var aIsOut = (a.doc_type === 'Payment Voucher') ? 1 : 0;
                    var bIsOut = (b.doc_type === 'Payment Voucher') ? 1 : 0;
                    if (aIsOut !== bIsOut) return aIsOut - bIsOut;
                    return new Date(a.pdate) - new Date(b.pdate);
                });

                res.data.forEach(function(row) {
                    var amt = parseFloat(row.amount) || 0;
                    var isOut = (row.doc_type === 'Payment Voucher');
                    if(isOut) totalOut += amt; else totalIn += amt;

                    var dateDisplay = row.pdate ? row.pdate.split('-').reverse().join('/') : '-';
                    var badgeClass = isOut ? 'bg-red-50 text-red-600 border-red-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100';

                    allRows.push(`<tr class="hover:bg-slate-50 transition border-b border-slate-50">
                        <td class="p-6 font-bold text-slate-500">${dateDisplay}</td>
                        <td class="p-6"><span class="${badgeClass} px-3 py-1 rounded-lg border uppercase text-[9px] font-black tracking-widest">${row.doc_type}</span></td>
                        <td class="p-6">
                            <div class="text-slate-900 font-black text-sm">${row.client_name}</div>
                            <div class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">${row.doc_no}</div>
                        </td>
                        <td class="p-6"><div class="text-[10px] font-black text-slate-400 uppercase">${row.worker_cat || '-'}</div></td>
                        <td class="p-6 text-right font-black ${isOut ? 'text-red-600' : 'text-slate-800'}">
                            ${isOut ? '-' : ''}${amt.toLocaleString('en-US', {minimumFractionDigits: 2})}
                        </td>
                    </tr>`);
                });
            }

            $('#sum_in').text(totalIn.toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#sum_out').text(totalOut.toLocaleString('en-US', {minimumFractionDigits: 2}));

            if (allRows.length === 0) {
                $('#report_body').html('<tr><td colspan="5" class="p-20 text-center text-slate-300 italic font-bold uppercase tracking-widest">No matching records found.</td></tr>');
                $('#report-pagination').html('');
                return;
            }

            var perPage = 10;
            var totalPages = Math.ceil(allRows.length / perPage);
            var curPage = 1;

            function renderReportPage(page) {
                curPage = Math.max(1, Math.min(page, totalPages));
                var start = (curPage - 1) * perPage;
                $('#report_body').html(allRows.slice(start, start + perPage).join(''));

                var nav = '';
                if (totalPages > 1) {
                    var btnBase = 'px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition ';
                    nav = '<div class="flex items-center justify-between px-6 py-4 border-t border-slate-100">';
                    nav += '<span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Showing ' + (start+1) + '–' + Math.min(curPage*perPage, allRows.length) + ' of ' + allRows.length + '</span>';
                    nav += '<div class="flex gap-1">';
                    nav += '<button class="' + btnBase + (curPage===1?'bg-slate-50 text-slate-300 cursor-not-allowed':'bg-slate-100 text-slate-600 hover:bg-slate-200') + '" onclick="window.__rpg(' + (curPage-1) + ')" ' + (curPage===1?'disabled':'') + '>&lsaquo;</button>';
                    var s = Math.max(1, curPage-2), e = Math.min(totalPages, curPage+2);
                    for (var p = s; p <= e; p++) {
                        nav += '<button class="' + btnBase + (p===curPage?'bg-blue-600 text-white':'bg-slate-100 text-slate-600 hover:bg-slate-200') + '" onclick="window.__rpg(' + p + ')">' + p + '</button>';
                    }
                    nav += '<button class="' + btnBase + (curPage===totalPages?'bg-slate-50 text-slate-300 cursor-not-allowed':'bg-slate-100 text-slate-600 hover:bg-slate-200') + '" onclick="window.__rpg(' + (curPage+1) + ')" ' + (curPage===totalPages?'disabled':'') + '>&rsaquo;</button>';
                    nav += '</div></div>';
                }
                $('#report-pagination').html(nav);
            }

            window.__rpg = renderReportPage;
            renderReportPage(1);
        }
    }, 'json');
}

/**
 * EXPORT: Using the unique ID 'btn-report-direct-export'
 */
$(document).on('click', '#btn-export-final-excel', function(e) {
    e.preventDefault();

    var rows = [];
    var tableRows = $('#report_body tr');

    // Check if the table has any real data (ignore the "No matching records" row)
    if (tableRows.length === 0 || tableRows.find('td').length === 1) {
        Swal.fire('No Data', 'The table is empty. Please click "Load Data" first.', 'info');
        return;
    }

    // Loop through every row in the visible table
    tableRows.each(function() {
        var row = $(this);
        var cols = row.find('td');
        
        if (cols.length > 1) {
            // Clean up the text (remove extra spaces and new lines)
            var date = $(cols[0]).text().trim();
            var type = $(cols[1]).text().trim();
            var clientName = $(cols[2]).find('div:first-child').text().trim();
var referenceNo = $(cols[2]).find('div:last-child').text().trim();
var clientInfo = clientName + " - " + referenceNo.replace('Ref:', '').trim();
            var category = $(cols[3]).text().trim().replace(/\s\s+/g, ' ');
            var amountText = $(cols[4]).text().trim().replace('MYR', '').replace(',', '').trim();
            
            rows.push({
                'Date': date,
                'Type': type,
                'Client / Reference': clientInfo,
                'Category / Source': category,
                'Amount (MYR)': parseFloat(amountText)
            });
        }
    });

    // Check if we captured anything
    if (rows.length === 0) {
        Swal.fire('Export Error', 'Could not read data from the table.', 'error');
        return;
    }

    // Generate Excel File
    var filename = "Financial_Statement_" + new Date().toISOString().slice(0,10) + ".xlsx";
    
    try {
        if (typeof XLSX === 'undefined') {
            Swal.fire('Library Missing', 'Excel library (SheetJS) is not loading. Check index.php.', 'error');
            return;
        }

        var wb = XLSX.utils.book_new();
        var ws = XLSX.utils.json_to_sheet(rows);
        XLSX.utils.book_append_sheet(wb, ws, "Report");
        XLSX.writeFile(wb, filename);
        
        Swal.fire({ icon: 'success', title: 'Excel Generated', timer: 1500, showConfirmButton: false });
    } catch (err) {
        console.error("Excel Export Error:", err);
        Swal.fire('Export Failed', 'An error occurred while creating the Excel file.', 'error');
    }
});


/**
 * PDF REPORT GENERATOR
 */
/**
 * ULTIMATE PDF FIX: Reads data directly from the HTML Table
 */
$(document).on('click', '#btn-export-final-pdf', function(e) {
    e.preventDefault();

    var tableRows = $('#report_body tr');

    // 1. Check if the table has data
    if (tableRows.length === 0 || tableRows.find('td').length === 1) {
        Swal.fire('No Data', 'The table is empty. Please click "Load Data" first.', 'info');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4'); // Landscape A4

    // 2. Prepare Data and Calculate Totals from the Table
    var rows = [];
    var totalIn = 0;
    var totalOut = 0;

    tableRows.each(function() {
        var cols = $(this).find('td');
        if (cols.length > 1) {
            var date = $(cols[0]).text().trim();
            var type = $(cols[1]).text().trim();
            // Grab the name and the reference number separately from their divs
var clientName = $(cols[2]).find('div:first-child').text().trim();
var referenceNo = $(cols[2]).find('div:last-child').text().trim();

// Combine them with a newline for the PDF, removing "Ref:" if it accidentally exists
var client = clientName + "\n" + referenceNo.replace('Ref:', '').trim();
            var category = $(cols[3]).text().trim().replace(/\s\s+/g, ' ');
            var amountRaw = $(cols[4]).text().trim().replace('MYR', '').replace(/,/g, '');
            var amount = parseFloat(amountRaw) || 0;

            // Update Totals for the PDF Header
            if (type.includes('Voucher')) {
                totalOut += amount;
            } else {
                totalIn += amount;
            }

            rows.push([
                date,
                type,
                client,
                category,
                (type.includes('Voucher') ? '-' : '') + amount.toLocaleString('en-US', {minimumFractionDigits: 2})
            ]);
        }
    });

    // 3. Draw Header (AGD Style)
    doc.setFont("helvetica", "bold");
    doc.setTextColor(0, 0, 139);
    doc.setFontSize(18);
    doc.text("AGD MANAGEMENT SERVICES SDN BHD", 14, 15);
    
    doc.setFontSize(9);
    doc.setTextColor(100);
    doc.setFont("helvetica", "normal");
    doc.text("FINANCIAL TRANSACTION REPORT", 14, 22);
    doc.text("Generated on: " + new Date().toLocaleDateString('en-GB'), 14, 27);

    // 4. Draw Summary Box
    doc.setDrawColor(230);
    doc.setFillColor(245, 247, 250);
    doc.roundedRect(14, 32, 268, 12, 2, 2, 'F');
    
    doc.setFont("helvetica", "bold");
    doc.setTextColor(5, 150, 105); // Green
    doc.text("TOTAL IN: MYR " + totalIn.toLocaleString('en-US', {minimumFractionDigits: 2}), 20, 40);
    
    doc.setTextColor(220, 38, 38); // Red
    doc.text("TOTAL OUT: MYR " + totalOut.toLocaleString('en-US', {minimumFractionDigits: 2}), 150, 40);

    // 5. Generate Table
    doc.autoTable({
        head: [['DATE', 'TYPE', 'CLIENT / REF NO', 'CATEGORY / SOURCE', 'AMOUNT (MYR)']],
        body: rows,
        startY: 50,
        theme: 'grid',
        headStyles: { fillColor: [15, 23, 42], fontSize: 9, halign: 'center' },
        styles: { fontSize: 8, cellPadding: 3 },
        columnStyles: {
            4: { halign: 'right', fontStyle: 'bold' }
        },
        didParseCell: function(data) {
            // If the amount is negative (from Voucher), make the text Red
            if (data.column.index === 4 && data.cell.raw.toString().startsWith('-')) {
                data.cell.styles.textColor = [220, 38, 38];
            }
        }
    });

    // 6. Save PDF
    var fn = "AGD_Report_" + new Date().toISOString().slice(0,10) + ".pdf";
    doc.save(fn);
    Swal.fire({ icon: 'success', title: 'PDF Downloaded', timer: 1500, showConfirmButton: false });
});
</script>

<style>
    .lbl { display:block; font-size:10px; font-weight:900; text-transform:uppercase; color:#94a3b8; margin-bottom:5px; margin-left: 5px; }
    .inp { width:100%; background:#f8fafc; border:none; padding:12px; border-radius:12px; font-weight:bold; color:#334155; outline:none; transition:all 0.2s; box-shadow: inset 0 2px 4px 0 rgb(0 0 0 / 0.05); }
    .inp:focus { background:#fff; box-shadow:0 0 0 2px #3b82f6; }
</style>