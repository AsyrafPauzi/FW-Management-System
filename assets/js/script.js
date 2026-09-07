/**
 * FW Management System - Standalone Core Logic
 * Location: assets/js/script.js
 * Version: 5.0.0 (Security Hardened, UX Improved)
 */

// =======================================================
// 1. GLOBAL AJAX SECURITY SETUP
// =======================================================
var API_URL = 'api.php';
// Read CSRF token from meta tag (secure - not exposed in JS source)
var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')
    ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    : '';
function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : CSRF_TOKEN;
}
if (typeof CURRENT_USER_ACCOUNT_NAME === 'undefined') { var CURRENT_USER_ACCOUNT_NAME = 'Authorized User'; }

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

$.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
});

// =======================================================
// 2. SHARED TABLE PAGINATION UTILITY
// =======================================================
function setupPagination(config) {
    var tbody = document.getElementById(config.tbodyId);
    var nav = document.getElementById(config.navId);
    if (!tbody) return;

    var per = config.perPage || 10;
    var rows = Array.from(tbody.rows);
    var total = rows.length;
    var pages = Math.max(1, Math.ceil(total / per));
    var cur = 1;

    function goTo(p) {
        cur = Math.max(1, Math.min(p, pages));
        rows.forEach(function(r, i) {
            r.style.display = (i >= (cur-1)*per && i < cur*per) ? '' : 'none';
        });
        if (nav) renderNav();
    }

    function renderNav() {
        if (pages <= 1) { nav.innerHTML = ''; return; }
        var btnBase = 'px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition ';
        var h = '<div class="flex items-center justify-between px-6 py-4 border-t border-slate-100">';
        h += '<span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Showing ' + ((cur-1)*per+1) + '&ndash;' + Math.min(cur*per, total) + ' of ' + total + '</span>';
        h += '<div class="flex gap-1">';
        h += '<button class="' + btnBase + (cur===1?'bg-slate-50 text-slate-300 cursor-not-allowed':'bg-slate-100 text-slate-600 hover:bg-slate-200') + '" onclick="window[\'__pg_' + config.id + '\'](' + (cur-1) + ')" ' + (cur===1?'disabled':'') + '>&#8249;</button>';
        var s = Math.max(1, cur-2), e = Math.min(pages, cur+2);
        for (var p = s; p <= e; p++) {
            h += '<button class="' + btnBase + (p===cur?'bg-blue-600 text-white':'bg-slate-100 text-slate-600 hover:bg-slate-200') + '" onclick="window[\'__pg_' + config.id + '\'](' + p + ')">' + p + '</button>';
        }
        h += '<button class="' + btnBase + (cur===pages?'bg-slate-50 text-slate-300 cursor-not-allowed':'bg-slate-100 text-slate-600 hover:bg-slate-200') + '" onclick="window[\'__pg_' + config.id + '\'](' + (cur+1) + ')" ' + (cur===pages?'disabled':'') + '>&#8250;</button>';
        h += '</div></div>';
        nav.innerHTML = h;
    }

    window['__pg_' + config.id] = goTo;
    goTo(1);
}



window.triggerImport = function() {
    $('#import_excel_file').click();
};

$(document).on('change', '#import_excel_file', function(e) {
    var file = e.target.files[0];
    if (!file) return;

    var reader = new FileReader();
    reader.onload = function(e) {
        var data = new Uint8Array(e.target.result);
        var workbook = XLSX.read(data, {type: 'array'});
        var sheet = workbook.Sheets[workbook.SheetNames[0]];
        var rows = XLSX.utils.sheet_to_json(sheet);

        if(rows.length === 0) { Swal.fire('Error', 'Excel file is empty.', 'error'); return; }

        Swal.fire({
            title: 'Importing ' + rows.length + ' Workers',
            text: 'Processing records, please wait...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        // Send rows to API
        $.ajax({
            url: API_URL,
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'bulk_import_workers', 
                workers: JSON.stringify(rows), 
                csrf_token: CSRF_TOKEN 
            },
            success: function(res) {
                if(res.success) {
                    Swal.fire('Import Success', res.data + ' workers added.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Partial Success', res.data, 'warning');
                }
            }
        });
    };
    reader.readAsArrayBuffer(file);
});


window.triggerImport = function() {
    $('#import_excel_file').click();
};

$(document).on('change', '#import_excel_file', function(e) {
    var file = e.target.files[0];
    if (!file) return;

    var reader = new FileReader();
    reader.onload = function(e) {
        var data = new Uint8Array(e.target.result);
        var workbook = XLSX.read(data, {type: 'array'});
        var sheet = workbook.Sheets[workbook.SheetNames[0]];
        var rows = XLSX.utils.sheet_to_json(sheet);

        if(rows.length === 0) { Swal.fire('Error', 'Excel file is empty.', 'error'); return; }

        Swal.fire({
            title: 'Importing ' + rows.length + ' Workers',
            text: 'Processing records, please wait...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        // Send rows to API
        $.ajax({
            url: API_URL,
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'bulk_import_workers', 
                workers: JSON.stringify(rows), 
                csrf_token: CSRF_TOKEN 
            },
            success: function(res) {
                if(res.success) {
                    Swal.fire('Import Success', res.data + ' workers added.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Partial Success', res.data, 'warning');
                }
            }
        });
    };
    reader.readAsArrayBuffer(file);
});


/**
 * GENERATE EXCEL IMPORT TEMPLATE
 * Version: 5.2.0 (Includes all identity & initial financial fields)
 */
window.downloadImportTemplate = function() {
    // 1. Define the headers and sample data
    var templateData = [
        {
            'Passport': 'A12345678',
            'Name': 'JOHN DOE BIN ABDULLAH',
            'Category': 'Calling Visa',
            'KWSP': '123456789',
            'Nationality': 'Indonesia',
            'Gender': 'Male',
            'BirthDate': '1990-01-01',
            'PassportExpiry': '2030-12-31',
            'TotalPayable': '10500.50'
        },
        {
            'Passport': 'B98765432',
            'Name': 'SITI AMINAH BINTI ALI',
            'Category': 'Programme',
            'KWSP': '987654321',
            'Nationality': 'Bangladesh',
            'Gender': 'Female',
            'BirthDate': '1995-05-20',
            'PassportExpiry': '2029-06-15',
            'TotalPayable': '8500.00'
        }
    ];

    // 2. Create workbook and sheet
    var wb = XLSX.utils.book_new();
    var ws = XLSX.utils.json_to_sheet(templateData);

    // 3. Add instructions for the user
    XLSX.utils.book_append_sheet(wb, ws, "Worker_Import_Template");

    // 4. Download file
    XLSX.writeFile(wb, "FWMS_Bulk_Import_Template.xlsx");
    
    Swal.fire({
        icon: 'info',
        title: 'Template Downloaded',
        text: 'Please follow the format in the sample rows. Category must be: Calling Visa, Programme, or Tukar Majikan.',
        confirmButtonColor: '#0f172a'
    });
};


// =======================================================
// 2. GLOBAL WINDOW FUNCTIONS (Scope: Universal)
// =======================================================

/**
 * Generate New Payment Row in Step 1 (With File Upload & Gen OR)
 */
window.addPaymentRow = function() {
    var html = `
    <div class="grid grid-cols-12 gap-2 payment-row items-center bg-slate-50 p-3 rounded-2xl border border-slate-200 animate-fade-in mb-3">
        <!-- 1. Description -->
        <div class="col-span-3">
            <input type="text" name="add_pay_desc[]" placeholder="Description (e.g. Medical)" 
                class="w-full text-xs font-bold bg-white border border-slate-100 p-2 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- 2. Receipt / Ref No -->
        <div class="col-span-2">
            <input type="text" name="add_pay_ref[]" placeholder="Ref No *" 
                class="check-receipt-ref w-full text-xs font-bold bg-white border border-slate-100 p-2 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- 3. Amount -->
        <div class="col-span-2">
            <input type="number" step="0.01" name="add_pay_amount[]" placeholder="0.00" 
                class="add-pay-amt w-full text-xs font-bold bg-white border border-slate-100 p-2 rounded-lg outline-none text-right">
        </div>

        <!-- 4. Date -->
        <div class="col-span-2">
            <input type="date" name="add_pay_date[]" 
                class="w-full text-xs bg-white border border-slate-100 p-2 rounded-lg outline-none">
        </div>

        <!-- 5. File Upload -->
        <div class="col-span-2">
            <input type="file" name="add_pay_proof[]" 
                class="w-full text-[9px] file:mr-1 file:py-1 file:px-2 file:rounded-full file:border-0 file:text-[9px] file:font-black file:bg-blue-50 file:text-blue-700">
        </div>

        <!-- 6. Actions -->
        <div class="col-span-1 flex justify-end gap-1">
            <button type="button" class="btn-gen-step1-or bg-emerald-500 text-white px-2 py-1 rounded-lg text-[8px] font-black uppercase shadow-sm">OR</button>
            <button type="button" class="text-red-400 font-bold px-1 hover:text-red-600 transition" 
                onclick="$(this).closest('.payment-row').remove(); window.calculateBalance();">×</button>
        </div>
    </div>`;
    $('#payment_rows_container').append(html);
};

/**
 * Financials: Recalculate Balance Due
 */
window.calculateBalance = function() {
    var total = parseFloat($('#total_payable').val()) || 0;
    var paid = 0;
    // Sum standard payments (Reg & Levy)
    $('.pay-amt').each(function() { paid += parseFloat($(this).val()) || 0; });
    // Sum dynamic row payments
    $('.add-pay-amt').each(function() { paid += parseFloat($(this).val()) || 0; });

    var balance = total - paid;
    $('#display_balance').text(balance.toLocaleString('en-US', {minimumFractionDigits: 2}));
    $('#balance_due_hidden').val(balance.toFixed(2));
};

/**
 * Global function to add a row to the Invoice Generator
 */
window.addInvoiceRow = function(d = '', q = 1, p = 0.00) {
    var h = `<div class="grid grid-cols-12 gap-4 items-center inv-row bg-white p-3 rounded-2xl border border-gray-100 mb-3 shadow-sm animate-fade-in">
        <div class="col-span-6">
            <input type="text" class="inv-desc w-full bg-slate-50 p-3 rounded-xl font-bold border-none outline-none focus:ring-2 focus:ring-blue-500" value="${d}" placeholder="Item description...">
        </div>
        <div class="col-span-2 text-center">
            <input type="number" class="inv-qty w-full bg-slate-50 p-3 rounded-xl text-center border-none font-bold" value="${q}">
        </div>
        <div class="col-span-3">
            <input type="number" step="0.01" class="inv-price w-full bg-slate-50 p-3 rounded-xl text-right border-none font-bold" value="${p}">
        </div>
        <div class="col-span-1 text-center">
            <button type="button" class="btn-remove text-red-400 text-2xl hover:text-red-600 transition" onclick="$(this).closest('.inv-row').remove(); window.updateInvoiceTotal();">×</button>
        </div>
    </div>`;
    $('#invoice-items-list').append(h);
    window.updateInvoiceTotal();
};

/**
 * Global function to calculate the total for manual invoices
 */
window.updateInvoiceTotal = function() {
    var t = 0; 
    $('.inv-row').each(function() { 
        var qty = parseFloat($(this).find('.inv-qty').val()) || 0;
        var price = parseFloat($(this).find('.inv-price').val()) || 0;
        t += (qty * price); 
    });
    $('#inv_total_display').text(t.toLocaleString('en-US', {minimumFractionDigits: 2}));
};

/**
 * EXPORT ENGINE (FIX for Excel, PDF, CSV)
 */
window.executeExport = function(type) {
    var s   = $('#filter_search').val() || $('input[name="s"]').val() || '';
    var cat = $('#filter_category').val() || $('select[name="cat"]').val() || '';
    var sd  = $('#filter_start').val() || $('input[name="start_date"]').val() || '';
    var ed  = $('#filter_end').val() || $('input[name="end_date"]').val() || '';

    Swal.fire({ title: 'Exporting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    $.ajax({
        url: API_URL,
        type: 'POST',
        dataType: 'json',
        data: { 
            action: 'export_data', 
            search: s, 
            category: cat,
            start_date: sd,
            end_date: ed,
            csrf_token: CSRF_TOKEN 
        },
        success: function(res) {
            Swal.close();
            if(res.success && res.data.length > 0) {
                var d = res.data; 
                var fn = "Worker_List_" + new Date().toISOString().slice(0,10);
                if(type === 'excel') {
                    var wb = XLSX.utils.book_new(); 
                    var ws = XLSX.utils.json_to_sheet(d);
                    XLSX.utils.book_append_sheet(wb, ws, "Workers"); 
                    XLSX.writeFile(wb, fn + ".xlsx");
                } else if(type === 'csv') {
                    var csv = [Object.keys(d[0]).join(",")];
                    d.forEach(r => csv.push(Object.values(r).map(v => '"'+v+'"').join(",")));
                    var link = document.createElement("a");
                    link.href = URL.createObjectURL(new Blob([csv.join("\n")], { type: 'text/csv' }));
                    link.download = fn + ".csv"; link.click();
                } else {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF('l');
                    doc.text("WORKER DIRECTORY REPORT", 14, 15);
                    doc.autoTable({ head: [Object.keys(d[0])], body: d.map(o => Object.values(o)), startY: 25, styles: {fontSize: 8} });
                    doc.save(fn + ".pdf");
                }
            } else {
                Swal.fire('No Data', res.data || 'No records found matching filters.', 'info');
            }
        },
        error: function() {
            Swal.fire('Error', 'Export failed. Check server.', 'error');
        }
    });
};

/**
 * Global Renewal Logic
 */
window.renewWorker = function(id, name) {
    Swal.fire({
        title: 'Initiate Renewal cycle?',
        text: "Archive current data for " + name + " and reset for a new permit cycle. The old cycle will stay under Renewal Archive History on the wizard (use Remove there only if this was a mistake).",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Start Renewal',
        customClass: {
            popup: 'rounded-[2rem] p-8',
            confirmButton: 'bg-orange-500 text-white px-8 py-3 rounded-xl font-black uppercase text-xs shadow-xl',
            cancelButton: 'bg-slate-100 text-slate-400 px-8 py-3 rounded-xl font-black uppercase text-xs ml-2'
        },
        buttonsStyling: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ 
                title: 'Archiving...', 
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading() } 
            });
            
            // USE $.ajax instead of $.post for better security control
            $.ajax({
                url: API_URL,
                type: 'POST',
                dataType: 'json',
                data: { 
                    action: 'archive_worker', 
                    id: id, 
                    csrf_token: CSRF_TOKEN // EXPLICIT TOKEN
                },
                success: function(res) {
                    if(res.success) {
                        Swal.fire({ 
                            icon: 'success', 
                            title: 'Renewal Initiated', 
                            text: 'Redirecting to wizard...',
                            timer: 2000, 
                            showConfirmButton: false 
                        }).then(() => {
                            window.location.href = '?page=wizard&id=' + id;
                        });
                    } else {
                        Swal.fire('Error', res.data || 'Archive failed.', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Network Error', 'Could not reach server.', 'error');
                }
            });
        }
    });
};

/**
 * Remove a mistaken renewal archive entry (wizard footer section).
 */
window.deleteWorkerArchive = function(archiveId, workerId) {
    if (typeof Swal === 'undefined') {
        alert('Dialog library not loaded. Please refresh the page.');
        return;
    }
    Swal.fire({
        title: 'Remove this archive?',
        text: 'Only use if Renew was clicked by mistake. This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove',
        cancelButtonText: 'Cancel',
        customClass: {
            popup: 'rounded-[2rem] p-8',
            confirmButton: 'bg-red-600 text-white px-8 py-3 rounded-xl font-black uppercase text-xs shadow-xl',
            cancelButton: 'bg-slate-100 text-slate-400 px-8 py-3 rounded-xl font-black uppercase text-xs ml-2'
        },
        buttonsStyling: false,
        heightAuto: false,
        didOpen: function() {
            var popup = Swal.getPopup();
            if (popup) popup.style.zIndex = '20000';
        }
    }).then(function(result) {
        if (!result || !result.isConfirmed) return;
        Swal.fire({ title: 'Removing...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
        $.ajax({
            url: API_URL,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete_worker_archive',
                archive_id: archiveId,
                worker_id: workerId,
                csrf_token: getCsrfToken()
            },
            success: function(res) {
                if (res && res.success) {
                    Swal.fire({ icon: 'success', title: 'Archive removed', timer: 1200, showConfirmButton: false })
                        .then(function() { window.location.reload(); });
                } else {
                    var msg = (res && res.data) ? (typeof res.data === 'string' ? res.data : 'Could not remove archive.') : 'Could not remove archive.';
                    Swal.fire('Error', msg, 'error');
                }
            },
            error: function(xhr) {
                var msg = 'Could not reach server.';
                try {
                    var j = JSON.parse(xhr.responseText);
                    if (j && j.data) msg = j.data;
                } catch (e) {}
                Swal.fire('Error', msg, 'error');
            }
        });
    });
};

/**
 * Navigation: Switch Wizard Tabs
 */
window.switchTab = function(step) {
    if (step == 6) step = 7;
    var wizardFullAccess = (window.wizardFullAccess === true || window.wizardFullAccess === 'true');
    var isForceEditMode = (window.isForceEdit === true || window.isForceEdit === 'true');
    var isFullDone = (window.isFullyCompleted === true || window.isFullyCompleted === 'true');
    var maxReach = (wizardFullAccess || isFullDone) ? 8 : window.maxWorkerStage;
    
    if (step > maxReach) return;

    window.activeStep = step;
    $('.fws-step-content').addClass('hidden-step');
    var target = document.getElementById('step-content-' + step);
    if(target) target.classList.remove('hidden-step');
    $('#fws-btn-prev').toggle(step > 1);

    if (wizardFullAccess || isForceEditMode) { $('#fws-action-group').show(); $('#fws-nav-group').hide(); }
    else {
        var staffSavePrior = (window.staffSaveOnPriorSteps === true || window.staffSaveOnPriorSteps === 'true');
        var isPastStep = (step < window.maxWorkerStage || isFullDone);
        if (isPastStep && !staffSavePrior) { $('#fws-action-group').hide(); $('#fws-nav-group').show(); }
        else { $('#fws-action-group').show(); $('#fws-nav-group').hide(); }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

window.nextTab = function() { var n = window.activeStep + 1; if(n == 6) n = 7; if(n <= 8) window.switchTab(n); };
window.prevTab = function() { var p = window.activeStep - 1; if(p == 6) p = 5; if(p >= 1) window.switchTab(p); };

// =======================================================
// 3. HELPER: NUMBER TO WORDS
// =======================================================
window.convertNumberToWords = function(amount) {
    var words = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
    var tens = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];

    function fetchWords(n) {
        if (n < 20) return words[n];
        if (n < 100) return tens[Math.floor(n / 10)] + (n % 10 !== 0 ? " " + words[n % 10] : "");
        if (n < 1000) return words[Math.floor(n / 100)] + " HUNDRED" + (n % 100 !== 0 ? " " + fetchWords(n % 100) : "");
        return fetchWords(Math.floor(n / 1000)) + " THOUSAND" + (n % 1000 !== 0 ? " " + fetchWords(n % 1000) : "");
    }

    var val = parseFloat(amount).toFixed(2);
    var parts = val.split('.');
    var ringgit = parseInt(parts[0]);
    var sen = parseInt(parts[1]);

    var res = (ringgit === 0) ? "ZERO RINGGIT" : fetchWords(ringgit) + " RINGGIT";
    if (sen > 0) res += " AND " + fetchWords(sen) + " CENTS";
    
    return res + " ONLY";
};

// =======================================================
// 4. PDF GENERATOR (AGD FIXED LAYOUT)
// =======================================================
window.generateAGDReceipt = function(data) {
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a5' });

        // 1. Logo (Fixed Aspect Ratio)
        var logoImg = document.querySelector('aside img');
        if(logoImg && logoImg.src) {
            try { doc.addImage(logoImg, 'PNG', 10, 8, 30, 20); } catch(e) {}
        }

        // 2. Header
        doc.setFont("helvetica", "bold"); doc.setTextColor(0, 0, 139); doc.setFontSize(16);
        doc.text("AGD MANAGEMENT SERVICES SDN BHD", 45, 15);
        doc.setFont("helvetica", "normal"); doc.setTextColor(0); doc.setFontSize(9);
        doc.text("Suite 05-01, 5th Floor Wisma TKS, 3rd Mile, Jalan Sultan Azlan Shah, 51200 KL", 45, 20);
        doc.text("Tel: 03-40505006  Email: asiaglobal2011@gmail.com", 45, 25);

        // 3. Title & Doc Info (Aligned Y=45)
        var headerY = 45;
        doc.setFont("helvetica", "bold"); doc.setFontSize(14);
        doc.text(data.type.toUpperCase(), 105, headerY, {align: 'center'});
        var tw = doc.getTextWidth(data.type.toUpperCase());
        doc.line(105 - (tw/2), headerY + 1.5, 105 + (tw/2), headerY + 1.5);

        doc.setFontSize(9); doc.setFont("helvetica", "normal");
        doc.text("No   : " + data.docNo, 150, headerY);
        doc.text("Date : " + data.date.split('-').reverse().join('/'), 150, headerY + 5);

        // 4. Body Content
        var startY = 65;
        var label = "Received From"; // Default for Official Receipt
if (data.type === 'Payment Voucher') {
    label = "Pay To";
} else if (data.type === 'Refund Receipt') {
    label = "Refunded To";
} else if (data.type === 'Invoice') {
    label = "Bill To";
}
        doc.text(label, 15, startY); doc.text(":", 45, startY);
        doc.setFont("helvetica", "bold"); doc.text(data.client.toUpperCase(), 50, startY);
        doc.line(50, startY+1, 195, startY+1);

        startY += 10;
        doc.setFont("helvetica", "normal"); doc.text("The sum of (RM)", 15, startY); doc.text(":", 45, startY);
        doc.setFont("helvetica", "bold"); doc.setFontSize(8.5);
        doc.text(window.convertNumberToWords(data.amount), 50, startY);
        doc.line(50, startY+1, 195, startY+1);

        startY += 10;
        doc.setFontSize(10); doc.setFont("helvetica", "normal"); doc.text("Description", 15, startY); doc.text(":", 45, startY);
        doc.setFont("helvetica", "bold");
        
        // --- THE KEY FIX FOR NEW LINES ---
        // This regex finds " (REF:" and replaces it with a real newline "\n(REF:"
        var formattedDesc = data.desc.replace(/ \(REF:/gi, "\n(REF:").replace(/\\n/g, "\n").replace(/nn/g, "\n\n");
        var descLines = doc.splitTextToSize(formattedDesc.toUpperCase(), 140);
        doc.text(descLines, 50, startY);

        // 5. Footer (Tight Gap)
        var footerY = startY + (descLines.length * 5) + 12;
        if(footerY < 115) footerY = 115; 

        doc.setFontSize(14);
        doc.text("RM :  " + parseFloat(data.amount).toLocaleString('en-US', {minimumFractionDigits: 2}), 15, footerY);
        doc.line(15, footerY+1, 60, footerY+1); doc.line(15, footerY+2, 60, footerY+2);

        var signX = 135;
        doc.setFontSize(10); doc.setFont("helvetica", "normal");
        doc.text("Issued by,", signX, footerY - 5); 
        doc.setFont("helvetica", "bold"); 
        doc.text((data.issuer || CURRENT_USER_ACCOUNT_NAME).toUpperCase(), signX, footerY + 7); 
        doc.line(signX, footerY + 9, 195, footerY + 9); 

        doc.save(data.docNo + ".pdf");
    } catch(err) { console.error(err); }
};

function completePDF(doc, data) {
    // --- 2. HEADER DETAILS ---
    doc.setFont("helvetica", "bold"); 
    doc.setTextColor(0, 0, 139); 
    doc.setFontSize(16);
    doc.text("AGD MANAGEMENT SERVICES SDN BHD", 40, 15);
    
    doc.setFont("helvetica", "normal"); 
    doc.setTextColor(0, 0, 0); 
    doc.setFontSize(9);
    doc.text("Suite 05-01, 5th Floor Wisma TKS, 3rd Mile, Jalan Sultan Azlan Shah,", 40, 20);
    doc.text("51200 Kuala Lumpur", 40, 24);
    doc.text("Tel: 03-40505006  Email: asiaglobal2011@gmail.com", 40, 28);

    // --- 3. TITLE (CENTERED) & DOC INFO (SAME LINE) ---
    var headerY = 45; 

    // Center Title
    var title = data.type.toUpperCase();
    doc.setFont("helvetica", "bold");
    doc.setFontSize(14);
    doc.text(title, 105, headerY, {align: 'center'});
    
    // Underline Title
    var textWidth = doc.getTextWidth(title);
    doc.setLineWidth(0.5);
    doc.line(105 - (textWidth/2), headerY + 1.5, 105 + (textWidth/2), headerY + 1.5);

    // Doc Info (Right Side, Same Line)
    doc.setFontSize(10);
    doc.setFont("helvetica", "normal");
    doc.text("No   :  " + data.docNo, 145, headerY);
    var displayDate = data.date.split('-').reverse().join('/');
    doc.text("Date :  " + displayDate, 145, headerY + 5);

    // --- 4. BODY CONTENT ---
    var startY = 65;
    var lineGap = 11;

    // Row 1: Name
    var label = "Received From"; // Default for Official Receipt
if (data.type === 'Payment Voucher') {
    label = "Pay To";
} else if (data.type === 'Refund Receipt') {
    label = "Refunded To";
} else if (data.type === 'Invoice') {
    label = "Bill To";
}
    doc.text(label, 15, startY);
    doc.text(":", 45, startY);
    doc.setFont("helvetica", "bold");
    doc.text(data.client.toUpperCase(), 50, startY);
    doc.setLineWidth(0.1);
    doc.line(50, startY+1, 195, startY+1);

    // Row 2: Sum of
    startY += lineGap;
    doc.setFont("helvetica", "normal");
    doc.text("The sum of (RM)", 15, startY);
    doc.text(":", 45, startY);
    doc.setFont("helvetica", "bold");
    doc.setFontSize(9);
    doc.text(window.convertNumberToWords(data.amount), 50, startY);
    doc.line(50, startY+1, 195, startY+1);

    // Row 3: Description
    startY += 10;
        doc.setFontSize(10);
        doc.setFont("helvetica", "normal");
        doc.text("Description", 15, startY);
        doc.text(":", 45, startY);
        doc.setFont("helvetica", "bold");
    
    // THE FIX: Convert literal "n" or "\\n" back into real breaks, and remove corrupted "N"
        var cleanDesc = data.desc.replace(/\\n/g, "\n").replace(/\nN/g, "\n").replace(/nn/g, "\n\n");
        
        var descLines = doc.splitTextToSize(cleanDesc.toUpperCase(), 140);
        doc.text(descLines, 50, startY);

    

    // Amount Box
    doc.setFontSize(14);
    doc.setFont("helvetica", "bold");
    doc.text("RM :   " + parseFloat(data.amount).toLocaleString('en-US', {minimumFractionDigits: 2}), 15, footerY);
    doc.setLineWidth(0.5);
    doc.line(15, footerY+1.5, 60, footerY+1.5);
    doc.line(15, footerY+2.5, 60, footerY+2.5);

    // Signature Area
    var signX = 135;
    doc.setFontSize(10);
    doc.setFont("helvetica", "normal");
    doc.text("Issued by,", signX, footerY - 5); 
    
    // Name sits ON the line
    var issuerName = data.issuer || CURRENT_USER_ACCOUNT_NAME;
    doc.setFont("helvetica", "bold");
    doc.text(issuerName.toUpperCase(), signX, footerY + 8); 

    // The Line
    doc.setLineWidth(0.1);
    doc.line(signX, footerY + 10, 195, footerY + 10); 

    doc.save(data.docNo + ".pdf");
}

// =======================================================
// 5. REPORTS DASHBOARD LOGIC
// =======================================================
window.toggleReportFilters = function() {
    var type = $('#rep_type').val();
    $('#filter_daily').toggleClass('hidden', type !== 'daily');
    $('#filter_monthly').toggleClass('hidden', type !== 'monthly');
};

// =======================================================
    // 3. REPORTS DASHBOARD (Sorted: Money In first, then Money Out)
    // =======================================================
    window.loadReport = function() {
        var type = $('#rep_type').val();
        var payload = { action: 'get_report', csrf_token: CSRF_TOKEN, type: type, category: $('#rep_cat').val() };
        if(type === 'daily') payload.date = $('#rep_date').val();
        else { payload.month = $('#rep_month').val(); payload.year = $('#rep_year').val(); }

        Swal.fire({ title: 'Loading...', didOpen: () => Swal.showLoading() });
        $.post(API_URL, payload, function(res) {
            Swal.close();
            if(res.success) {
                var html = ''; var totalIn = 0; var totalOut = 0;
                
                // SORTING LOGIC: 
                // 1. "Official Receipt" & "Invoice" (Money In) come FIRST
                // 2. "Payment Voucher" (Money Out) comes SECOND
                // 3. Within those groups, sort by Date
                res.data.sort(function(a, b) {
                    var aIsOut = (a.doc_type === 'Payment Voucher') ? 1 : 0;
                    var bIsOut = (b.doc_type === 'Payment Voucher') ? 1 : 0;
                    
                    if (aIsOut !== bIsOut) return aIsOut - bIsOut; // 0 (In) comes before 1 (Out)
                    return new Date(a.pdate) - new Date(b.pdate); // Then sort chronologically
                });

                res.data.forEach(function(row) {
                    var amt = parseFloat(row.amount) || 0;
                    var isOut = (row.doc_type === 'Payment Voucher');
                    if(isOut) totalOut += amt; else totalIn += amt;
                    
                    var badgeClass = isOut ? 'bg-red-50 text-red-600 border-red-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100';
                    var dateDisplay = row.pdate ? row.pdate.split('-').reverse().join('/') : '-';

                    html += `<tr class="hover:bg-slate-50 transition border-b border-slate-50">
                        <td class="p-6 font-bold text-slate-500">${escapeHtml(dateDisplay)}</td>
                        <td class="p-6"><span class="${badgeClass} px-3 py-1 rounded-lg border uppercase text-[9px] font-black tracking-widest">${escapeHtml(row.doc_type)}</span></td>
                        <td class="p-6"><div class="text-slate-900 font-black">${escapeHtml(row.client_name)}</div><div class="text-[9px] text-slate-400 uppercase">Ref: ${escapeHtml(row.doc_no)}</div></td>
                        <td class="p-6"><div class="text-[10px] font-black text-slate-400 uppercase">${escapeHtml(row.worker_cat || '-')}</div><div class="text-[8px] text-slate-300 italic">${escapeHtml(row.source)}</div></td>
                        <td class="p-6 text-right font-black ${isOut?'text-red-600':'text-slate-800'}">${isOut?'-':''}${amt.toLocaleString('en-US',{minimumFractionDigits:2})}</td></tr>`;
                });
                $('#report_body').html(html || '<tr><td colspan="5" class="p-20 text-center text-slate-300 italic font-bold uppercase tracking-widest">No matching records found.</td></tr>');
                $('#sum_in').text(totalIn.toLocaleString('en-US',{minimumFractionDigits:2})); 
                $('#sum_out').text(totalOut.toLocaleString('en-US',{minimumFractionDigits:2})); 
                $('#sum_count').text(res.data.length);
            }
        }, 'json');
    };

// =======================================================
    // 5. WIZARD VALIDATION (Added Step 7 EPASS Check)
    // =======================================================
    function validateStepData() {
        var isValid = true;
        var currentTab = $('.fws-step-content:not(.hidden-step)'); 
        var stepId = currentTab.attr('id');

        // 1. Standard Inputs
        currentTab.find('[data-required="true"]').each(function() {
            var input = $(this);
            if (input.prop('disabled') || input.prop('readonly')) return;
            if (!input.val()) {
                isValid = false;
                input.addClass('border-red-500 ring-2 ring-red-100');
            } else {
                input.removeClass('border-red-500 ring-2 ring-red-100');
            }
        });

        // 2. File Validation Helper
        function validateFile(fieldName) {
            var fileInput = currentTab.find('input[name="' + fieldName + '"]');
            if (fileInput.length === 0 || fileInput.prop('disabled')) return true;
            // Check if new file selected OR existing link present
            var hasNew = fileInput.val() !== '';
            var hasOld = currentTab.find('a[href*="uploads"]').filter(function() {
                return $(this).attr('href').includes(fieldName.replace('_proof','')); // Fuzzy match based on context or just check sibling
            }).length > 0;
            
            // Simpler check: Look for the specific link structure if possible, 
            // or just check if the "Uploaded" text exists near the input
            if(currentTab.find('input[name="'+fieldName+'"]').parent().next().find('a').length > 0) hasOld = true;

            if (!hasNew && !hasOld) {
                fileInput.addClass('border-red-500 ring-2 ring-red-100'); 
                return false;
            } else {
                fileInput.removeClass('border-red-500 ring-2 ring-red-100'); 
                return true;
            }
        }

        // 3. Step-Specific Rules
        if (stepId === 'step-content-2') { if (!validateFile('payment1_proof')) isValid = false; }
        if (stepId === 'step-content-5') { if (!validateFile('payment2_proof')) isValid = false; }
        
        // NEW: Step 7 EPASS Mandatory
        if (stepId === 'step-content-7') { 
            if (!validateFile('epass_worker_proof')) {
                isValid = false;
                // Highlight the specific area
                $('input[name="epass_worker_proof"]').closest('div').addClass('border-red-500');
            }
        }

        if (!isValid) Swal.fire({ icon: 'warning', title: 'Missing Data', text: 'Please fill all required fields and upload mandatory documents.' });
        return isValid;
    }

// =======================================================
// 6. JQUERY EVENT LISTENERS (DOCUMENT READY)
// =======================================================
jQuery(document).ready(function($) {
    
    
    
    function parseInvoiceRowJson(el) {
        var raw = $(el).attr('data-json');
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (err) {
            console.error('Invalid invoice row JSON:', err);
            return null;
        }
    }

    // =======================================================
// FIX: INVOICE EDIT HANDLER (DATA POPULATION FIX)
// =======================================================
$(document).on('click', '.fws-edit-inv', function(e) {
    e.preventDefault();
    
    var d = parseInvoiceRowJson(this);
    if (!d) {
        Swal.fire('Error', 'Could not load document data. Please refresh and try again.', 'error');
        return;
    }

    // SECURITY CHECK: Block staff from editing OR
    if (USER_ROLE === 'staff' && d.type === 'Official Receipt') {
        Swal.fire({
            icon: 'error',
            title: 'Access Denied',
            text: 'Staff members are not permitted to edit Official Receipts once generated.',
            confirmButtonColor: '#ef4444'
        });
        return;
    }

    // 2. Populate Header Fields
    $('#inv_id').val(d.id);
    $('#inv_type').val(d.type);
    $('#inv_no').val(d.doc_no);
    $('#inv_client').val(d.client);
    $('#inv_date').val(d.date);

    // Update the "Bill To / Pay To" label based on the type
    var label = "Bill To";
    if (d.type === 'Official Receipt') label = "Received From";
    else if (d.type === 'Payment Voucher') label = "Pay To";
    else if (d.type === 'Refund Receipt') label = "Refunded To"; // ADD THIS
    $('#dynamic_recipient_label').text(label);

    // 3. Clear existing dynamic rows in the form
    $('#invoice-items-list').empty();

    // 4. Parse and Populate Line Items
    try {
        // If it's a string, parse it. If jQuery already parsed it, use it.
        var items = (typeof d.items_json === 'string') ? JSON.parse(d.items_json) : d.items_json;

        items.forEach(function(item) {
            // Repair line breaks that might be corrupted as 'n' or 'nn'
            var cleanDesc = item.desc.replace(/nn/g, "\n\n").replace(/n\(/g, "\n(");
            
            // Call the global function to add the row
            window.addInvoiceRow(cleanDesc, item.qty, item.price);
        });
    } catch (err) {
        console.error("Error parsing invoice items:", err);
    }

    // 5. Smooth scroll back to the top form
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

    // Fill Pay To / Bill To from a history recipient name
    $(document).on('click', '.fws-fill-client', function(e) {
        e.preventDefault();
        var name = $(this).data('client');
        if (!name) return;
        $('#inv_client').val(name).trigger('focus');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // A. Add Payment Button
    $(document).on('click', '#btn-add-payment-row', function(e) {
        e.preventDefault();
        window.addPaymentRow();
    });
    
    // B. Invoice Gen Add Row
    $(document).on('click', '#btn-add-item', function(e) {
        e.preventDefault();
        window.addInvoiceRow();
    });
    
    // C. Export Listeners
    $('#btn-export-excel').on('click', function(e) { e.preventDefault(); window.executeExport('excel'); });
    $('#btn-export-pdf').on('click', function(e) { e.preventDefault(); window.executeExport('pdf'); });
    $('#btn-export-csv').on('click', function(e) { e.preventDefault(); window.executeExport('csv'); });

    // D. Receipt Check (Local + Global)
    $(document).on('change', '.receipt-val, .check-receipt-ref', function() {
        var input = $(this);
        var receipt = input.val().trim();
        if (receipt === '') return;

        // Local Check
        var count = 0;
        $('.receipt-val, .check-receipt-ref').each(function() {
            if ($(this).val().trim().toLowerCase() === receipt.toLowerCase()) count++;
        });

        if (count > 1) {
            Swal.fire({ icon: 'error', title: 'Duplicate Detected', text: 'Receipt number used elsewhere on this page.' });
            input.val('').addClass('border-red-500').focus();
            return;
        }

        // Global Check
        $.post(API_URL, { action: 'check_receipt', receipt: receipt, exclude_id: $('input[name="worker_id"]').val(), csrf_token: CSRF_TOKEN }, function(res) {
            if (res.exists) {
                Swal.fire({ icon: 'error', title: 'Receipt Already Used', text: res.message });
                input.val('').addClass('border-red-500').focus();
            } else { input.removeClass('border-red-500').addClass('border-emerald-500'); }
        }, 'json');
    });

    // --- AMEND: CONSOLIDATED OR (ONE LINE FORMAT) ---
   // --- AMEND: CONSOLIDATED OR (MULTI-LINE FORMAT) ---
    $(document).on('click', '#btn-gen-consolidated-or', function(e) {
        e.preventDefault();
        var client = $('#worker_full_name').val() || 'Client Name';
        var totalAmount = 0; var descriptions = [];

        // 1. Registration
        var rAmt = parseFloat($('#payment1_amount').val()) || 0;
        if(rAmt > 0) { 
            totalAmount += rAmt; 
            // \n forces the (REF: ...) to the next line
            descriptions.push("REGISTRATION PAYMENT\n(REF: " + ($('#payment1_receipt').val() || '-') + ")"); 
        }

        // 2. Levy
        var lAmt = parseFloat($('#payment2_amount').val()) || 0;
        if(lAmt > 0) { 
            totalAmount += lAmt; 
            descriptions.push("LEVY PAYMENT\n(REF: " + ($('#payment2_receipt').val() || '-') + ")"); 
        }

        // 3. Dynamic Rows
        $('.payment-row').each(function() {
            var aAmt = parseFloat($(this).find('.add-pay-amt').val()) || parseFloat($(this).find('input[type="number"]').val()) || 0;
            var aDesc = $(this).find('input[name*="desc"]').val() || $(this).find('input[readonly]').val();
            var aRef = $(this).find('input[name*="ref"]').val() || '';
            
            if(aAmt > 0 && aDesc) { 
                totalAmount += aAmt; 
                descriptions.push(aDesc.toUpperCase() + "\n(REF: " + (aRef || '-') + ")"); 
            }
        });

        if(totalAmount <= 0) return;

        var docNo = "OR-" + Date.now().toString().slice(-6);
        var date = new Date().toISOString().slice(0,10);

        $.ajax({
            url: API_URL, type: 'POST', dataType: 'json',
            data: { 
                action: 'save_invoice', type: 'Official Receipt', doc_no: docNo, date: date, client: client, 
                // We join different payments with TWO new lines for better spacing
                items_json: JSON.stringify([{ desc: descriptions.join("\n\n"), qty: 1, price: totalAmount }]), 
                amount: totalAmount, csrf_token: CSRF_TOKEN 
            },
            success: function(res) { 
                if(res.success) { 
                    window.generateAGDReceipt({ type: "Official Receipt", docNo: docNo, date: date, client: client, desc: descriptions.join("\n\n"), amount: totalAmount }); 
                } 
            }
        });
    });
    
    
    // F. Individual Row OR
    // --- AMEND: INDIVIDUAL ROW (MULTI-LINE FORMAT) ---
    $(document).on('click', '.btn-gen-step1-or, .btn-gen-static-or', function(e) {
        e.preventDefault();
        var row = $(this).closest('.payment-row');
        
        var desc = "";
        if($(this).hasClass('btn-gen-static-or')) {
            // Re-format the static data-desc to include a newline
            desc = $(this).data('desc').replace(" (REF:", "\n(REF:"); 
        } else {
            var dVal = row.find('input[name*="desc"]').val() || row.find('input[readonly]').val();
            var rVal = row.find('input[name*="ref"]').val() || '';
            desc = dVal.toUpperCase() + "\n(REF: " + (rVal || '-') + ")";
        }

        var amt = row.find('.add-pay-amt').val() || $(this).data('amt');
        var date = row.find('input[name*="date"]').val() || $(this).data('date') || new Date().toISOString().slice(0,10);

        window.generateAGDReceipt({ 
            type: "Official Receipt", docNo: "OR-" + Date.now().toString().slice(-6), 
            date: date, client: $('#worker_full_name').val(), desc: desc, amount: amt 
        });
    });

    // G. Live Math
    $(document).on('input', '#total_payable, .pay-amt, .add-pay-amt, .inv-qty, .inv-price', function() {
        if(typeof window.calculateBalance === 'function') window.calculateBalance();
        if(typeof window.updateInvoiceTotal === 'function') window.updateInvoiceTotal();
    });

    // H. Save Worker
    $('.fws-btn-save').on('click', function(e) {
        e.preventDefault();
        var formData = new FormData($('#fws-wizard-form')[0]);
        formData.append('action', 'save_worker');
        formData.append('csrf_token', CSRF_TOKEN);
        if($(this).data('advance') === true) formData.append('advance_stage', 'true');
        Swal.fire({ title: 'Syncing...', didOpen: () => Swal.showLoading() });
        $.ajax({
            url: API_URL, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
            success: function(res) { if(res.success) window.location.href = '?page=wizard&id=' + res.data.id; else Swal.fire('Error', res.data, 'error'); }
        });
    });
    
    

    // I. Delete Actions
    $(document).on('click', '.fws-delete-worker', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        Swal.fire({ title: 'Delete?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444' }).then(r => {
            if (r.isConfirmed) $.post(API_URL, { action: 'delete_worker', id: id, csrf_token: CSRF_TOKEN }, () => location.reload());
        });
    });

    $(document).on('click', '.fws-del-inv', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        Swal.fire({ title: 'Delete?', icon: 'warning', showCancelButton: true }).then(r => { if (r.isConfirmed) $.post(API_URL, { action: 'delete_invoice', id: id, csrf_token: CSRF_TOKEN }, () => location.reload()); });
    });

    // J. Manual Save PDF
    $('#fws-save-gen-pdf').on('click', function(e) {
        e.preventDefault();
        var type = $('#inv_type').val();
        var client = $('#inv_client').val().trim();
        var docNo = $('#inv_no').val();
        var date = $('#inv_date').val();
        var invId = parseInt($('#inv_id').val(), 10) || 0;
        var items = []; var total = 0;
        $('.inv-row').each(function() { 
            var d = $(this).find('.inv-desc').val(); var q = $(this).find('.inv-qty').val(); var p = $(this).find('.inv-price').val();
            if(d && q && p) { items.push({desc:d, qty:q, price:p}); total += (q*p); }
        });
        if (!client) { Swal.fire('Error', 'Please enter a recipient name in Pay To / Bill To.', 'warning'); return; }
        if(items.length === 0) { Swal.fire('Error', 'Please add at least one line item.', 'warning'); return; }
        $.ajax({
            url: API_URL, type: 'POST', dataType: 'json',
            data: { action: 'save_invoice', id: invId, type: type, doc_no: docNo, date: date, client: client, items_json: JSON.stringify(items), amount: total, csrf_token: CSRF_TOKEN },
            success: function(res) {
                if(res.success) { window.generateAGDReceipt({ type: type, docNo: docNo, date: date, client: client, desc: items.map(i=>i.desc).join(", "), amount: total }); location.reload(); }
            }
        });
    });
    
    
      $(document).on('click', '.btn-remove-row', function(e) {
        e.preventDefault();
        var row = $(this).closest('.payment-row');
        
        // Visual removal
        row.fadeOut(300, function() {
            $(this).remove();
            // Recalculate the red balance at the top
            window.calculateBalance();
        });
    });
    
    // --- FIX: INDIVIDUAL ROW REF NO & FORMATTING ---
    // --- AMEND: INDIVIDUAL ROW (ONE LINE FORMAT) ---
    /**
 * Listener for Static Step 1 Receipt Buttons (Reg & Levy)
 */

    // K. Invoice Numbering Logic
   $(document).on('change', '#inv_type', function() {
    var type = $(this).val(); 
    var prefix = "INV";
    
    if (type === 'Official Receipt') {
        prefix = "OR";
    } else if (type === 'Payment Voucher') {
        prefix = "PV";
    } else if (type === 'Refund Receipt') { // ADD THIS
        prefix = "RR";
    }
    
    var d = new Date(); 
    var ds = d.getFullYear() + String(d.getMonth()+1).padStart(2,'0') + String(d.getDate()).padStart(2,'0');
    $('#inv_no').val(prefix + "-" + ds + "-" + Math.floor(100 + Math.random() * 900));
    
    // Also update the label dynamically
    var label = "Bill To";
    if (type === 'Official Receipt') label = "Received From";
    else if (type === 'Payment Voucher') label = "Pay To";
    else if (type === 'Refund Receipt') label = "Refunded To"; // ADD THIS
    $('#dynamic_recipient_label').text(label);
});

// =======================================================
// FIX: HISTORY DOWNLOAD (RESTORING LINE BREAKS)
// =======================================================
$(document).on('click', '.fws-download-inv', function(e) { 
    e.preventDefault();
    var d = parseInvoiceRowJson(this);
    if (!d) {
        Swal.fire('Error', 'Could not load document data. Please refresh and try again.', 'error');
        return;
    }
    var items = (typeof d.items_json === 'string') ? JSON.parse(d.items_json) : d.items_json;
    
    // Join descriptions using real newlines
    var combinedDesc = items.map(function(i) {
        return i.desc; 
    }).join("\n\n");
    
    window.generateAGDReceipt({ 
        type: d.type, 
        docNo: d.doc_no, 
        date: d.date, 
        client: d.client, 
        desc: combinedDesc, 
        amount: d.amount, 
        issuer: d.issuer 
    });
});

});