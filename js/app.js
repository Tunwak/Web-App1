/**
 * ─────────────────────────────────────────────────────────────────────────────
 * app.js — Frontend Application Logic
 * ─────────────────────────────────────────────────────────────────────────────
 * [Full-stack Dev Note]:
 * จัดการส่วนแสดงผลหลัก (Presentation & UI Interactions):
 * 1. Fetch ข้อมูลสินค้าผ่าน GET /api/products มา Render ใน <tbody>
 * 2. ค้นหา Real-time ด้วยเทคนิค Debounce ป้องกันการยิง API ถี่เกินไป
 * 3. จัดการปุ่มแก้ไข (Edit) ดึง Record เก่ามาใส่ในฟอร์มเพื่อเตรียม Update
 * 4. จัดการปุ่มลบ (Delete) พร้อม Confirm Modal ป้องกัน Human Error
 * 5. Utility Helpers: formatPrice (แปลงตัวเลขเป็นสกุลเงิน), escapeHtml (ป้องกัน XSS)
 * ─────────────────────────────────────────────────────────────────────────────
 */

const API_BASE = 'api';

/* ══════════════════════════════════════════════════════════════════════════════
   1. โหลดข้อมูลสินค้าลงตาราง (+ รองรับ Search Keyword)
   ══════════════════════════════════════════════════════════════════════════════ */
async function loadProducts(search = '') {
    const tbody        = document.getElementById('productTableBody');
    const emptyState   = document.getElementById('emptyState');
    const productCount = document.getElementById('productCount');

    // แสดง Loading State ขณะรอ API ตอบกลับ
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-4">
                <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                <span class="ms-2 text-muted">กำลังโหลดข้อมูลสินค้า...</span>
            </td>
        </tr>`;

    try {
        let url = `${API_BASE}/products`;
        if (search) url += `?search=${encodeURIComponent(search)}`;

        const response = await fetch(url);
        const result   = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'ไม่สามารถโหลดข้อมูลสินค้าได้');
        }

        const products = result.data || [];
        productCount.textContent = products.length;

        // ถ้าไม่มีข้อมูล ให้แสดง Empty State Illustration
        if (products.length === 0) {
            tbody.innerHTML = '';
            emptyState.classList.remove('d-none');
            return;
        }

        emptyState.classList.add('d-none');

        // Render ข้อมูลสินค้าลงในตาราง
        tbody.innerHTML = products.map((p) => `
            <tr>
                <td class="text-center fw-medium text-muted">#${p.id}</td>
                <td class="fw-semibold text-dark">${escapeHtml(p.productName)}</td>
                <td><span class="badge bg-light text-dark border px-2 py-1">${escapeHtml(p.categoryName || '-')}</span></td>
                <td><small class="text-secondary">${escapeHtml(p.supplierName || '-')}</small></td>
                <td><small class="text-muted">${escapeHtml(p.unit || '-')}</small></td>
                <td class="text-end fw-bold text-success">฿${formatPrice(p.price)}</td>
                <td class="text-center text-nowrap">
                    <button class="btn btn-outline-primary btn-sm btn-action me-1"
                            onclick="editProduct(${p.id})"
                            title="แก้ไขข้อมูล">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm btn-action"
                            onclick="confirmDelete(${p.id}, '${escapeHtml(p.productName).replace(/'/g, "\\'")}')"
                            title="ลบข้อมูล">
                        <i class="bi bi-trash3"></i>
                    </button>
                </td>
            </tr>
        `).join('');

    } catch (error) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-danger py-4">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    ${escapeHtml(error.message)}
                </td>
            </tr>`;
        console.error('loadProducts error:', error);
    }
}

/* ══════════════════════════════════════════════════════════════════════════════
   2. ดึงข้อมูลสินค้าเดิมมาใส่ฟอร์มเพื่อแก้ไข (Edit / Pre-fill)
   ══════════════════════════════════════════════════════════════════════════════ */
async function editProduct(id) {
    try {
        const response = await fetch(`${API_BASE}/products/${id}`);
        const result   = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'ไม่สามารถดึงข้อมูลสินค้านี้ได้');
        }

        const p = result.data;

        // นำข้อมูลที่ได้มาใส่ใน Form Controls
        document.getElementById('productId').value   = p.id;
        document.getElementById('formAction').value  = 'update';
        document.getElementById('productName').value = p.productName || '';
        document.getElementById('categoryId').value  = p.categoryId || '';
        document.getElementById('supplierId').value  = p.supplierId || '';
        document.getElementById('unit').value        = p.unit || '';
        document.getElementById('price').value       = p.price || '';

        // แสดง Banner สีเหลืองแจ้งเตือนว่ากำลังอยู่ในโหมดแก้ไข
        document.getElementById('updateStatusBanner').classList.remove('d-none');
        document.getElementById('currentProductIdDisplay').textContent = `#${p.id} (${p.productName})`;

        // เปลี่ยนข้อความและไอคอนปุ่ม Submit เป็น "อัปเดตข้อมูลสินค้า"
        document.getElementById('btnSubmitText').textContent = 'อัปเดตข้อมูลสินค้า';
        document.getElementById('btnSubmitIcon').className   = 'bi bi-pencil-square me-2';

        // เลื่อนหน้าจอไปยังฟอร์มและ Focus ที่ช่องชื่อสินค้า
        document.getElementById('formSection').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('productName').focus();

    } catch (error) {
        showToast('danger', error.message);
        console.error('editProduct error:', error);
    }
}

/* ══════════════════════════════════════════════════════════════════════════════
   3. การลบสินค้า (Delete with Confirmation Modal)
   ══════════════════════════════════════════════════════════════════════════════ */
function confirmDelete(id, name) {
    document.getElementById('deleteProductName').textContent = name;
    document.getElementById('deleteProductId').value         = id;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

async function deleteProduct() {
    const id  = document.getElementById('deleteProductId').value;
    const btn = document.getElementById('btnConfirmDelete');

    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>กำลังลบ...';

    try {
        const response = await fetch(`${API_BASE}/products/${id}`, {
            method: 'DELETE'
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'ไม่สามารถลบสินค้าได้');
        }

        // ปิด Modal ยืนยัน
        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();

        // แสดง Modal แจ้งเตือนสำเร็จ
        showSuccessModal('ลบข้อมูลสำเร็จ!', 'ลบข้อมูลสินค้าออกจากระบบเรียบร้อยแล้ว', `รหัสสินค้า: <span class="badge bg-danger fs-6">#${id}</span>`);

        // โหลดข้อมูลในตารางใหม่ตามคำค้นปัจจุบัน
        loadProducts(document.getElementById('searchInput').value);

    } catch (error) {
        showToast('danger', error.message);
        console.error('deleteProduct error:', error);
    } finally {
        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-trash3 me-1"></i>ยืนยันลบ';
    }
}

/* ══════════════════════════════════════════════════════════════════════════════
   4. ระบบค้นหา Real-time แบบมี Debounce (350ms)
   ══════════════════════════════════════════════════════════════════════════════ */
function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    let debounceTimer;

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            loadProducts(this.value.trim());
        }, 350);
    });
}

/* ══════════════════════════════════════════════════════════════════════════════
   5. Modal & Toast Notifications
   ══════════════════════════════════════════════════════════════════════════════ */
function showSuccessModal(title, message, detailsHtml) {
    document.getElementById('successModalLabel').textContent   = title;
    document.getElementById('successMessageText').textContent = message;
    document.getElementById('insertedDetails').innerHTML      = detailsHtml;

    const modal = new bootstrap.Modal(document.getElementById('successModal'));
    modal.show();
}

function showToast(type, message) {
    const container  = document.getElementById('toastContainer');
    const toastId    = 'toast-' + Date.now();
    const iconClass  = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
    const bgClass    = type === 'success' ? 'text-bg-success' : 'text-bg-danger';

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center ${bgClass} border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center">
                    <i class="bi ${iconClass} fs-5 me-2"></i>
                    <span>${escapeHtml(message)}</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = document.getElementById(toastId);
    const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
    bsToast.show();

    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

/* ══════════════════════════════════════════════════════════════════════════════
   6. Data Formatters & Security Sanitizer
   ══════════════════════════════════════════════════════════════════════════════ */
function formatPrice(price) {
    if (price === null || price === undefined || isNaN(price)) return '0.00';
    return Number(price).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

/* ══════════════════════════════════════════════════════════════════════════════
   7. Application Initialization
   ══════════════════════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    setupSearch();

    // ปุ่มยกเลิกการแก้ไข
    const btnCancel = document.getElementById('btnCancelEdit');
    if (btnCancel) {
        btnCancel.addEventListener('click', () => {
            if (typeof resetForm === 'function') resetForm();
        });
    }
});
