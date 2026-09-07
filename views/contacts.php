<?php
/**
 * View: Contacts Directory
 * Location: views/contacts.php
 * Version: 1.0.0
 */

$can_edit = current_user_can_edit();
$can_delete = current_user_can_delete();
$q = sanitize_text_field($_GET['q'] ?? '');
$contacts = $db->get_contacts($q);
?>

<div class="container mx-auto max-w-5xl px-2 md:px-0 animate-fade-in pb-20">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 md:mb-10 gap-6">
        <div>
            <h2 class="text-3xl md:text-4xl font-black text-slate-800 uppercase italic tracking-tighter leading-none">Contacts</h2>
            <p class="text-slate-400 text-xs md:text-sm font-bold uppercase tracking-widest mt-2">Manage Bill To / Pay To and business contacts</p>
        </div>
        <?php if ($can_edit): ?>
        <button type="button" onclick="openContactModal()" class="w-full md:w-auto bg-slate-900 text-white px-8 py-4 rounded-2xl font-black uppercase tracking-widest text-[10px] md:text-xs hover:bg-blue-600 hover:shadow-xl hover:shadow-blue-200 transition-all transform hover:-translate-y-1 active:scale-95">
            + Add Contact
        </button>
        <?php endif; ?>
    </div>

    <form method="GET" class="mb-6 flex flex-col sm:flex-row gap-3">
        <input type="hidden" name="page" value="contacts">
        <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="Search name, company, phone, email..."
            class="flex-1 bg-white border-2 border-slate-100 p-4 rounded-2xl font-bold text-slate-700 shadow-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-50">
        <button type="submit" class="bg-slate-100 text-slate-700 px-6 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-slate-200 transition">Search</button>
        <?php if ($q !== ''): ?>
        <a href="?page=contacts" class="bg-white border-2 border-slate-100 text-slate-400 px-6 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-slate-50 transition text-center">Clear</a>
        <?php endif; ?>
    </form>

    <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-2xl border border-slate-200 overflow-hidden shadow-slate-200/50">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-900 text-white text-[10px] uppercase font-black tracking-widest">
                    <tr>
                        <th class="p-5 md:p-8">Name</th>
                        <th class="p-5 md:p-8">Company</th>
                        <th class="p-5 md:p-8">Phone / Email</th>
                        <th class="p-5 md:p-8 text-right">Operations</th>
                    </tr>
                </thead>
                <tbody id="contacts-tbody" class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($contacts)): ?>
                    <tr>
                        <td colspan="4" class="p-10 text-center text-slate-400 font-bold uppercase text-xs tracking-widest">
                            <?php echo $q !== '' ? 'No contacts match your search.' : 'No contacts yet. Add your first contact.'; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($contacts as $c): ?>
                    <tr class="hover:bg-slate-50/80 transition-all group">
                        <td class="p-5 md:p-8">
                            <div class="font-black text-slate-800 tracking-tight text-base md:text-lg"><?php echo e($c->name); ?></div>
                            <?php if (!empty($c->address)): ?>
                            <div class="text-[10px] text-slate-400 font-bold mt-1 max-w-xs"><?php echo e($c->address); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="p-5 md:p-8">
                            <div class="font-bold text-slate-700 text-sm"><?php echo e($c->company ?: '—'); ?></div>
                        </td>
                        <td class="p-5 md:p-8">
                            <div class="text-sm font-bold text-slate-700"><?php echo e($c->phone ?: '—'); ?></div>
                            <div class="text-[10px] text-slate-400 font-bold"><?php echo e($c->email ?: ''); ?></div>
                        </td>
                        <td class="p-5 md:p-8 text-right">
                            <div class="flex justify-end gap-2 md:gap-3">
                                <?php if ($can_edit): ?>
                                <button type="button" onclick='editContact(<?php echo htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8'); ?>)' class="bg-slate-50 text-slate-600 px-3 md:px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-blue-600 hover:text-white transition-all shadow-sm active:scale-95">
                                    Edit
                                </button>
                                <?php endif; ?>
                                <?php if ($can_delete): ?>
                                <button type="button" onclick="deleteContact(<?php echo (int)$c->id; ?>)" class="bg-red-50 text-red-400 px-3 md:px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:bg-red-500 hover:text-white transition-all shadow-sm active:scale-95">
                                    Del
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div id="contacts-pagination"></div>
    </div>
</div>

<style>
.lbl { display:block; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:0.1em; color:#94a3b8; margin-bottom:6px; }
.inp { width:100%; background:#f8fafc; border:2px solid #f1f5f9; padding:12px 14px; border-radius:1rem; font-weight:700; color:#334155; outline:none; }
.inp:focus { border-color:#3b82f6; background:#fff; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelectorAll('#contacts-tbody tr').length > 1 || !document.querySelector('#contacts-tbody td[colspan]')) {
        setupPagination({ id: 'contacts', tbodyId: 'contacts-tbody', navId: 'contacts-pagination', perPage: 10 });
    }
});

function openContactModal(isEdit) {
    isEdit = !!isEdit;
    Swal.fire({
        title: isEdit ? 'Update Contact' : 'New Contact',
        html: `
            <div class="text-left space-y-3 p-2">
                <input id="swal-cid" type="hidden" value="0">
                <div><label class="lbl">Name *</label><input id="swal-cname" class="inp" placeholder="Person or company name"></div>
                <div><label class="lbl">Company</label><input id="swal-ccompany" class="inp" placeholder="Organization"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label class="lbl">Phone</label><input id="swal-cphone" class="inp" placeholder="Phone"></div>
                    <div><label class="lbl">Email</label><input id="swal-cemail" class="inp" placeholder="email@example.com"></div>
                </div>
                <div><label class="lbl">Address</label><textarea id="swal-caddress" class="inp" rows="2" placeholder="Mailing address"></textarea></div>
                <div><label class="lbl">Notes</label><textarea id="swal-cnotes" class="inp" rows="2" placeholder="Optional notes"></textarea></div>
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Save Contact',
        customClass: { popup: 'rounded-[2.5rem]' },
        preConfirm: () => {
            const name = document.getElementById('swal-cname').value.trim();
            if (!name) { Swal.showValidationMessage('Name is required'); return false; }
            return {
                id: document.getElementById('swal-cid').value,
                name: name,
                company: document.getElementById('swal-ccompany').value.trim(),
                phone: document.getElementById('swal-cphone').value.trim(),
                email: document.getElementById('swal-cemail').value.trim(),
                address: document.getElementById('swal-caddress').value.trim(),
                notes: document.getElementById('swal-cnotes').value.trim()
            };
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        $.post('api.php', { action: 'save_contact', csrf_token: CSRF_TOKEN, ...result.value }, function(res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Saved', text: 'Contact updated.', customClass: { popup: 'rounded-[2.5rem]' } })
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', res.data || 'Save failed.', 'error');
            }
        }, 'json');
    });
}

function editContact(contact) {
    openContactModal(true);
    setTimeout(() => {
        document.getElementById('swal-cid').value = contact.id;
        document.getElementById('swal-cname').value = contact.name || '';
        document.getElementById('swal-ccompany').value = contact.company || '';
        document.getElementById('swal-cphone').value = contact.phone || '';
        document.getElementById('swal-cemail').value = contact.email || '';
        document.getElementById('swal-caddress').value = contact.address || '';
        document.getElementById('swal-cnotes').value = contact.notes || '';
    }, 50);
}

function deleteContact(id) {
    Swal.fire({
        title: 'Remove Contact?',
        text: 'This contact will be removed from the directory.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        customClass: {
            popup: 'rounded-[2.5rem]',
            confirmButton: 'bg-red-600 text-white px-8 py-4 rounded-2xl font-black uppercase text-xs tracking-widest mr-2',
            cancelButton: 'bg-slate-100 text-slate-400 px-8 py-4 rounded-2xl font-black uppercase text-xs tracking-widest'
        },
        buttonsStyling: false
    }).then((result) => {
        if (!result.isConfirmed) return;
        $.post('api.php', { action: 'delete_contact', id: id, csrf_token: CSRF_TOKEN }, function(res) {
            if (res.success) location.reload();
            else Swal.fire('Error', res.data || 'Could not delete contact.', 'error');
        }, 'json');
    });
}
</script>
