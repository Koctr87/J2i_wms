<?php
/**
 * J2i Warehouse Management System
 * New Bulk Stock Entry (Purchase Intake)
 */
require_once __DIR__ . '/../../config/config.php';

$db = getDB();

// Get data for dropdowns
$suppliers = $db->query("SELECT id, company_name FROM suppliers WHERE is_active = 1 ORDER BY company_name")->fetchAll();
$brands = $db->query("SELECT id, name FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();
$memories = $db->query("SELECT id, size FROM memory_options ORDER BY sort_order")->fetchAll();
$colors = $db->query("SELECT id, name_en, name_cs, name_ru FROM color_options ORDER BY sort_order, name_en")->fetchAll();
$categories = $db->query("SELECT id, name_en, name_cs, name_ru FROM categories WHERE is_active = 1")->fetchAll();

$pageTitle = __('add_purchase');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path
                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
            </svg>
            <?= __('add_purchase') ?>
        </h3>
    </div>
    <div class="card-body">
        <form id="purchaseForm">
            <!-- Step 1: General Info -->
            <div
                style="background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1.5rem; color: var(--gray-600);">📋 <?= __('general_info') ?></h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required"><?= __('purchase_date') ?></label>
                        <input type="date" name="purchase_date" id="purchaseDate" class="form-control"
                            value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><?= __('supplier') ?></label>
                        <select name="supplier_id" id="supplierId" class="form-control" required>
                            <option value="">-- <?= __('select_supplier') ?> --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= __('invoice_in') ?></label>
                        <input type="text" name="invoice_number" id="invoiceNumber" class="form-control"
                            placeholder="INV-2024-X">
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><?= __('status') ?> (New/Used)</label>
                        <select name="condition" id="condition" class="form-control" required>
                            <option value="used">Used (Б/У)</option>
                            <option value="new">New (Новое)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><?= __('vat_mode') ?></label>
                        <select name="vat_mode" id="vatMode" class="form-control" required>
                            <option value="marginal"><?= __('vat_marginal') ?></option>
                            <option value="reverse"><?= __('vat_reverse') ?></option>
                            <option value="no"><?= __('vat_no') ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __('attachment_url') ?> (PDF/JPG/PNG)</label>
                    <input type="file" name="attachment_file" id="attachmentFile" class="form-control"
                        accept=".pdf,.jpg,.jpeg,.png">
                    <small style="color: var(--gray-500); display: block; margin-top: 5px;">
                        Max size: 10MB. Allowed: PDF, JPG, PNG.
                    </small>
                </div>
            </div>

            <!-- Step 2: Items Table -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">📦 <?= __('items') ?></h4>
                <div class="table-container" style="overflow-x: auto;">
                    <table class="table" style="min-width: 1200px;" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 150px;"><?= __('brand') ?></th>
                                <th style="width: 200px;"><?= __('model') ?></th>
                                <th style="width: 100px;"><?= __('memory') ?></th>
                                <th style="width: 120px;"><?= __('color') ?></th>
                                <th style="width: 80px;"><?= __('grade') ?></th>
                                <th style="width: 120px;"><?= __('qty') ?></th>
                                <th style="width: 180px;"><?= __('purchase_price') ?></th>
                                <th style="width: 100px;"><?= __('delivery_cost') ?></th>
                                <th style="width: 150px;"><?= __('imei') ?></th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <!-- Rows will be added dynamically -->
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-secondary" style="margin-top: 1rem;" onclick="addRow()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    <?= __('add_row') ?>
                </button>

                <!-- Totals Summary -->
                <div id="totalsSummary"
                    style="margin-top: 1.5rem; padding: 1rem 1.5rem; background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border: 1px solid #bae6fd; border-radius: 10px; display: flex; justify-content: flex-end; gap: 3rem; align-items: center;">
                    <div style="text-align: right;">
                        <div
                            style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">
                            <?= __('total') ?> <?= __('purchase_price') ?>
                        </div>
                        <div id="totalPrice" style="font-size: 1.4rem; font-weight: 700; color: #0369a1;">0.00</div>
                    </div>
                    <div style="width: 1px; height: 40px; background: #93c5fd;"></div>
                    <div style="text-align: right;">
                        <div
                            style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">
                            <?= __('total') ?> <?= __('delivery_cost') ?>
                        </div>
                        <div id="totalDelivery" style="font-size: 1.4rem; font-weight: 700; color: #0369a1;">0.00</div>
                    </div>
                    <div style="width: 1px; height: 40px; background: #93c5fd;"></div>
                    <div style="text-align: right;">
                        <div
                            style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">
                            <?= __('total') ?>
                        </div>
                        <div id="grandTotal" style="font-size: 1.6rem; font-weight: 800; color: #0c4a6e;">0.00</div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Notes -->
            <div class="form-group">
                <label class="form-label"><?= __('notes') ?> (Common for all items)</label>
                <textarea name="notes" id="notes" class="form-control" rows="4"></textarea>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <a href="devices.php" class="btn btn-secondary"><?= __('cancel') ?></a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                    </svg>
                    <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Template for Modal Brand Selection -->
<div class="modal-overlay" id="addProductModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add New Product</h3>
            <button type="button" class="modal-close" onclick="closeModal('addProductModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Product Name</label>
                <input type="text" id="newProductName" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Category</label>
                <select id="newProductCategory" class="form-control">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e(getLocalizedField($cat, 'name')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="saveProductBtn">Save Product</button>
        </div>
    </div>
</div>

<script>
    const brands = <?= json_encode($brands) ?>;
    const memories = <?= json_encode($memories) ?>;
    const colors = <?= json_encode($colors) ?>;
    let rowCount = 0;
    let activeRowForProduct = null;

    function addRow() {
        rowCount++;
        const row = document.createElement('tr');
        row.id = `row-${rowCount}`;
        row.innerHTML = `
            <td>
                <select class="form-control brand-select" onchange="loadProducts(this, ${rowCount})">
                    <option value="">-- Brand --</option>
                    ${brands.map(b => `<option value="${b.id}">${b.name}</option>`).join('')}
                </select>
            </td>
            <td>
                <select class="form-control product-select" id="product-${rowCount}" onchange="handleNewProduct(this, ${rowCount})">
                    <option value="">-- Model --</option>
                </select>
            </td>
            <td>
                <select class="form-control">
                    <option value="">-- Mem --</option>
                    ${memories.map(m => `<option value="${m.id}">${m.size}</option>`).join('')}
                </select>
            </td>
            <td>
                <select class="form-control">
                    <option value="">-- Color --</option>
                    ${colors.map(c => `<option value="${c.id}">${c.name_en}</option>`).join('')}
                </select>
            </td>
            <td>
                <select class="form-control">
                    <option value="A">Grade A</option>
                    <option value="B">Grade B</option>
                    <option value="C">Grade C</option>
                    <option value="Q">Grade Q</option>
                </select>
            </td>
            <td>
                <input type="number" class="form-control qty-input" value="1" min="1" onchange="toggleIMEI(${rowCount})" oninput="updateTotals()">
            </td>
            <td>
                <div style="display: flex; gap: 0.25rem;">
                    <input type="number" class="form-control price-input" step="0.01" style="flex: 1;" placeholder="0.00" oninput="updateTotals()">
                    <select class="form-control" style="width: 70px;">
                        <option value="EUR">EUR</option>
                        <option value="USD">USD</option>
                        <option value="CZK">CZK</option>
                    </select>
                </div>
            </td>
            <td>
                <input type="number" class="form-control delivery-input" step="0.01" value="0" oninput="updateTotals()">
            </td>
            <td>
                <input type="text" class="form-control imei-input" placeholder="IMEI / Batch">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeRow(${rowCount})">&times;</button>
            </td>
        `;
        document.getElementById('itemsBody').appendChild(row);
        toggleIMEI(rowCount);
        updateTotals();
    }

    function removeRow(id) {
        document.getElementById(`row-${id}`).remove();
        updateTotals();
    }

    function updateTotals() {
        let totalPrice = 0;
        let totalDelivery = 0;

        document.querySelectorAll('#itemsBody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
            const price = parseFloat(row.querySelector('.price-input')?.value) || 0;
            const delivery = parseFloat(row.querySelector('.delivery-input')?.value) || 0;

            totalPrice += price * qty;
            totalDelivery += delivery * qty;
        });

        document.getElementById('totalPrice').textContent = totalPrice.toFixed(2);
        document.getElementById('totalDelivery').textContent = totalDelivery.toFixed(2);
        document.getElementById('grandTotal').textContent = (totalPrice + totalDelivery).toFixed(2);
    }

    async function loadProducts(brandSelect, id) {
        const brandId = brandSelect.value;
        const productSelect = document.getElementById(`product-${id}`);
        productSelect.innerHTML = '<option value="">Loading...</option>';

        if (!brandId) return;

        const response = await fetch(`<?= APP_URL ?>/api/ajax-handlers.php?action=get_products&brand_id=${brandId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        productSelect.innerHTML = '<option value="">-- Model --</option>';
        data.forEach(p => {
            productSelect.innerHTML += `<option value="${p.id}">${p.name}</option>`;
        });
        productSelect.innerHTML += '<option value="new" style="font-weight:bold; color:var(--primary);">+ New Product...</option>';
    }

    function handleNewProduct(select, id) {
        if (select.value === 'new') {
            activeRowForProduct = id;
            openModal('addProductModal');
            select.value = '';
        }
    }

    document.getElementById('saveProductBtn').onclick = async () => {
        const name = document.getElementById('newProductName').value;
        const catId = document.getElementById('newProductCategory').value;
        const row = document.getElementById(`row-${activeRowForProduct}`);
        const brandId = row.querySelector('.brand-select').value;

        if (!brandId) {
            showToast('Please select a brand first', 'error');
            return;
        }
        if (!name) return;

        try {
            const response = await fetch('<?= APP_URL ?>/api/ajax-handlers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'create_product',
                    name: name,
                    brand_id: brandId,
                    category_id: catId
                })
            });
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                showToast('Server error creating product', 'error');
                return;
            }

            if (data.success) {
                const productSelect = document.getElementById(`product-${activeRowForProduct}`);
                const opt = document.createElement('option');
                opt.value = data.id;
                opt.textContent = name;
                opt.selected = true;
                productSelect.insertBefore(opt, productSelect.lastChild);
                closeModal('addProductModal');
                document.getElementById('newProductName').value = '';
                showToast('Product created successfully!', 'success');
            } else {
                showToast(data.message || 'Error creating product', 'error');
            }
        } catch (err) {
            console.error('Submit error:', err);
            showToast('Network error', 'error');
        }
    };

    function toggleIMEI(id) {
        const row = document.getElementById(`row-${id}`);
        const qty = parseInt(row.querySelector('.qty-input').value) || 0;
        const imeiInput = row.querySelector('.imei-input');

        if (qty > 1) {
            imeiInput.readOnly = true;
            imeiInput.style.backgroundColor = '#f3f4f6';
            if (!imeiInput.value.startsWith('BATCH-')) {
                const batchId = 'BATCH-' + Math.random().toString(36).substr(2, 6).toUpperCase();
                imeiInput.value = batchId;
            }
        } else {
            imeiInput.readOnly = false;
            imeiInput.style.backgroundColor = '';
            if (imeiInput.value.startsWith('BATCH-')) imeiInput.value = '';
        }
    }

    document.getElementById('purchaseForm').onsubmit = async (e) => {
        e.preventDefault();

        const supplierId = document.getElementById('supplierId').value;
        if (!supplierId) {
            showToast('Please select a supplier', 'error');
            return;
        }

        const items = [];
        let itemsValid = true;

        document.querySelectorAll('#itemsBody tr').forEach(row => {
            const selects = row.querySelectorAll('select');
            const inputs = row.querySelectorAll('input');

            const productId = selects[1].value;
            if (!productId) {
                itemsValid = false;
                row.querySelector('.product-select').style.borderColor = 'red';
            } else {
                row.querySelector('.product-select').style.borderColor = '';
            }
            const memoryId = selects[2].value;
            const colorId = selects[3].value;
            const grading = selects[4].value;
            const currency = selects[5].value;

            const quantity = row.querySelector('.qty-input').value;
            const purchasePrice = row.querySelector('.price-input').value;
            const deliveryCost = row.querySelector('.delivery-input').value;
            const imei = row.querySelector('.imei-input').value;

            items.push({
                product_id: productId,
                memory_id: memoryId,
                color_id: colorId,
                grading: grading,
                quantity: quantity,
                purchase_price: purchasePrice,
                currency: currency,
                delivery_cost: deliveryCost,
                delivery_currency: currency, // Reuse the same currency for delivery
                imei: imei
            });
        });

        if (!itemsValid || items.length === 0) {
            showToast('Please select products for all rows', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'create_purchase');
        formData.append('supplier_id', supplierId);
        formData.append('purchase_date', document.getElementById('purchaseDate').value);
        formData.append('invoice_number', document.getElementById('invoiceNumber').value);
        formData.append('condition', document.getElementById('condition').value);
        formData.append('vat_mode', document.getElementById('vatMode').value);
        formData.append('notes', document.getElementById('notes').value);
        formData.append('items_json', JSON.stringify(items));

        const fileInput = document.getElementById('attachmentFile');
        if (fileInput && fileInput.files.length > 0) {
            formData.append('attachment_file', fileInput.files[0]);
        }

        try {
            const response = await fetch('<?= APP_URL ?>/api/ajax-handlers.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const text = await response.text();
            console.log('Server response:', text);
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('JSON Parse Error:', e, 'Raw Text:', text);
                showToast('Server error: Invalid response format', 'error');
                return;
            }

            if (result.success) {
                showToast('Purchase created successfully!', 'success');
                setTimeout(() => window.location.href = 'devices.php', 1000);
            } else {
                showToast(result.message || 'Error saving purchase', 'error');
            }
        } catch (err) {
            console.error('Submit error:', err);
            showToast('Network error or server unavailable', 'error');
        }
    };

    // Initial first row
    addRow();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>