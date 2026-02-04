<?php
/**
 * J2i Warehouse Management System
 * New Sale Page
 */
try {
    $pageTitle = 'Nový Prodej'; // Manual string to avoid gettext issues if not loaded
    require_once __DIR__ . '/../../includes/header.php';

    // Helper for translations if not exists
    if (!function_exists('__')) {
        function __($k)
        {
            return $k;
        }
    }

    $db = getDB();

    // Get clients for dropdown
    $clients = $db->query("SELECT id, company_name, ico FROM clients WHERE is_active = 1 ORDER BY company_name")->fetchAll();

    // Get current currency rates
    try {
        $eurRate = getCNBRate('EUR') ?? 25.00;
        $usdRate = getCNBRate('USD') ?? 23.00;
    } catch (Throwable $e) {
        $eurRate = 25.00;
        $usdRate = 23.00;
    }

} catch (Throwable $e) {
    die('<div class="alert alert-danger">System Error: ' . $e->getMessage() . '</div>');
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23" />
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
            </svg>
            <?= __('new_sale') ?>
        </h3>
    </div>
    <div class="card-body">
        <form id="saleForm">
            <!-- Client & Date -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">👤
                    <?= __('client') ?>
                </h4>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label required">
                            <?= __('select_client') ?>
                        </label>
                        <select name="client_id" id="clientSelect" class="form-control" required>
                            <option value="">--
                                <?= __('select_client') ?> --
                            </option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>">
                                    <?= e($client['company_name']) ?> (
                                    <?= e($client['ico'] ?? 'N/A') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('sale_date') ?>
                        </label>
                        <input type="date" name="sale_date" id="saleDate" class="form-control"
                            value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('invoice_out') ?>
                        </label>
                        <input type="text" name="invoice_number" class="form-control" placeholder="FAK-2024-001">
                    </div>
                </div>
            </div>

            <!-- Currency Rates -->
            <div
                style="margin-bottom: 2rem; background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg);">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">💱
                    <?= __('currency_rate') ?> (ČNB)
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('rate_eur') ?> (EUR → CZK)
                        </label>
                        <input type="number" name="eur_rate" id="eurRate" class="form-control"
                            value="<?= number_format($eurRate, 4, '.', '') ?>" step="0.0001" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('rate_usd') ?> (USD → CZK)
                        </label>
                        <input type="number" name="usd_rate" id="usdRate" class="form-control"
                            value="<?= number_format($usdRate, 4, '.', '') ?>" step="0.0001" readonly>
                    </div>

                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="refreshRates()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10" />
                                <polyline points="1 20 1 14 7 14" />
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                            </svg>
                            Обновить
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sale Items -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">📦
                    <?= __('devices') ?> &
                    <?= __('accessories') ?>
                </h4>

                <div class="table-container">
                    <table class="table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Товар</th>
                                <th>
                                    <?= __('quantity') ?>
                                </th>
                                <th>
                                    <?= __('price') ?> (CZK)
                                </th>
                                <th>
                                    <?= __('vat_mode') ?>
                                </th>
                                <th>
                                    <?= __('margin') ?>
                                </th>
                                <th>
                                    <?= __('vat_amount') ?>
                                </th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <!-- Items will be added here -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7">
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="button" class="btn btn-secondary" onclick="openDeviceSelector()">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="5" x2="12" y2="19" />
                                                <line x1="5" y1="12" x2="19" y2="12" />
                                            </svg>
                                            <?= __('add') ?>
                                            <?= __('devices') ?>
                                        </button>
                                        <button type="button" class="btn btn-outline" onclick="openAccessorySelector()">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="5" x2="12" y2="19" />
                                                <line x1="5" y1="12" x2="19" y2="12" />
                                            </svg>
                                            <?= __('add') ?>
                                            <?= __('accessories') ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Totals -->
            <div
                style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); padding: 1.5rem; border-radius: var(--radius-lg); color: white;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;">
                            <?= __('subtotal') ?>
                        </div>
                        <div style="font-size: 1.5rem; font-weight: 700;" id="subtotalDisplay">0 Kč</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;">
                            <?= __('vat_amount') ?> (21%)
                        </div>
                        <div style="font-size: 1.5rem; font-weight: 700;" id="vatDisplay">0 Kč</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;">
                            <?= __('total') ?>
                        </div>
                        <div style="font-size: 2rem; font-weight: 700;" id="totalDisplay">0 Kč</div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <a href="history.php" class="btn btn-secondary">
                    <?= __('cancel') ?>
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                        <polyline points="17 21 17 13 7 13 7 21" />
                        <polyline points="7 3 7 8 15 8" />
                    </svg>
                    <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Device Selector Modal -->
<div class="modal-overlay" id="deviceModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('select_device') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('deviceModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="text" class="form-control" id="deviceSearch" placeholder="<?= __('search') ?>..."
                style="margin-bottom: 1rem;">
            <div id="deviceList" style="max-height: 400px; overflow-y: auto;">
                <div class="loading">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Accessory Selector Modal -->
<div class="modal-overlay" id="accessoryModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('accessories') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('accessoryModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="accessoryList" style="max-height: 400px; overflow-y: auto;">
                <div class="loading">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let saleItems = [];

    // Refresh currency rates
    async function refreshRates() {
        const date = document.getElementById('saleDate').value;

        try {
            const eurResponse = await fetch('../../api/ajax-handlers.php?action=get_cnb_rate&currency=EUR&date=' + date);
            const eurData = await eurResponse.json();
            if (eurData.success) {
                document.getElementById('eurRate').value = eurData.rate.toFixed(4);
            }

            const usdResponse = await fetch('../../api/ajax-handlers.php?action=get_cnb_rate&currency=USD&date=' + date);
            const usdData = await usdResponse.json();
            if (usdData.success) {
                document.getElementById('usdRate').value = usdData.rate.toFixed(4);
            }

            showToast('Курс обновлён', 'success');
            recalculateAll();
        } catch (e) {
            showToast('Ошибка обновления курса', 'error');
        }
    }

    // Update rates when date changes
    document.getElementById('saleDate').addEventListener('change', refreshRates);

    // Open device selector
    async function openDeviceSelector() {
        openModal('deviceModal');
        loadDevices();
    }

    async function loadDevices(search = '') {
        const list = document.getElementById('deviceList');
        list.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        try {
            const response = await fetch('../../api/ajax-handlers.php?action=get_devices&search=' + encodeURIComponent(search));
            const devices = await response.json();

            if (devices.length === 0) {
                list.innerHTML = '<div class="empty-state"><p><?= __('no_data') ?></p></div>';
                return;
            }

            list.innerHTML = devices.map(d => `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border-bottom: 1px solid var(--gray-200); cursor: pointer;" 
                 onclick="addDevice(${JSON.stringify(d).replace(/"/g, '&quot;')})">
                <div>
                    <strong>${d.brand_name} ${d.product_name}</strong>
                    <span class="badge badge-gray">${d.memory || 'N/A'}</span>
                    ${d.color ? `<span class="text-muted">${d.color}</span>` : ''}
                    <br>
                    <small class="text-muted">Доступно: ${d.quantity_available} | Закупка: ${d.purchase_price} ${d.purchase_currency}</small>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: 600;">${formatCurrency(d.retail_price || 0)}</div>
                    <span class="badge badge-${d.vat_mode === 'marginal' ? 'warning' : (d.vat_mode === 'reverse' ? 'info' : 'gray')}">${d.vat_mode}</span>
                </div>
            </div>
        `).join('');
        } catch (e) {
            list.innerHTML = '<div class="alert alert-error">Ошибка загрузки</div>';
        }
    }

    // Search devices with debounce
    document.getElementById('deviceSearch').addEventListener('input', debounce(function () {
        loadDevices(this.value);
    }, 300));

    // Add device to sale
    function addDevice(device) {
        const exists = saleItems.find(i => i.device_id === device.id);
        if (exists) {
            showToast('Устройство уже добавлено', 'warning');
            return;
        }

        const eurRate = parseFloat(document.getElementById('eurRate').value);
        const usdRate = parseFloat(document.getElementById('usdRate').value);
        const rate = device.purchase_currency === 'EUR' ? eurRate : (device.purchase_currency === 'USD' ? usdRate : 1);

        const item = {
            device_id: device.id,
            accessory_id: null,
            name: `${device.brand_name} ${device.product_name} ${device.memory || ''} ${device.color || ''}`.trim(),
            quantity: 1,
            max_quantity: device.quantity_available,
            unit_price: device.retail_price || 0,
            purchase_price: device.purchase_price,
            delivery_cost: device.delivery_cost || 0,
            purchase_currency: device.purchase_currency,
            currency_rate: rate,
            vat_mode: device.vat_mode,
            margin: 0,
            vat_amount: 0
        };

        calculateItemVAT(item);
        saleItems.push(item);
        renderItems();
        closeModal('deviceModal');
    }

    // Calculate VAT for item
    function calculateItemVAT(item) {
        const purchaseInCZK = ((item.purchase_price || 0) + (item.delivery_cost || 0)) * item.currency_rate;
        const sellingPrice = item.unit_price * item.quantity;

        if (item.vat_mode === 'marginal') {
            item.margin = sellingPrice - purchaseInCZK;
            item.vat_amount = item.margin * (21 / 121);
        } else if (item.vat_mode === 'reverse') {
            item.margin = sellingPrice - purchaseInCZK;
            item.vat_amount = sellingPrice * 0.21;
        } else {
            item.margin = sellingPrice - purchaseInCZK;
            item.vat_amount = 0;
        }

        item.margin = Math.round(item.margin * 100) / 100;
        item.vat_amount = Math.round(item.vat_amount * 100) / 100;
    }

    // Render items table
    function renderItems() {
        const tbody = document.getElementById('itemsBody');

        if (saleItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Добавьте товары</td></tr>';
            updateTotals();
            return;
        }

        tbody.innerHTML = saleItems.map((item, idx) => `
        <tr>
            <td>
                <strong>${item.name}</strong>
                ${item.device_id ? '<span class="badge badge-primary">Device</span>' : '<span class="badge badge-info">Accessory</span>'}
            </td>
            <td>
                <input type="number" class="form-control" style="width: 80px;" value="${item.quantity}" min="1" max="${item.max_quantity}"
                       onchange="updateItemQuantity(${idx}, this.value)">
            </td>
            <td>
                <input type="number" class="form-control" style="width: 120px;" value="${item.unit_price}" min="0" step="0.01"
                       onchange="updateItemPrice(${idx}, this.value)">
            </td>
            <td>
                <span class="badge badge-${item.vat_mode === 'marginal' ? 'warning' : (item.vat_mode === 'reverse' ? 'info' : 'gray')}">
                    ${item.vat_mode === 'marginal' ? 'Marginal' : (item.vat_mode === 'reverse' ? 'Reverse' : 'No VAT')}
                </span>
            </td>
            <td class="${item.margin >= 0 ? 'text-success' : 'text-danger'}">${formatCurrency(item.margin)}</td>
            <td>${formatCurrency(item.vat_amount)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${idx})">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
            </td>
        </tr>
    `).join('');

        updateTotals();
    }

    function updateItemQuantity(idx, qty) {
        saleItems[idx].quantity = parseInt(qty) || 1;
        calculateItemVAT(saleItems[idx]);
        renderItems();
    }

    function updateItemPrice(idx, price) {
        saleItems[idx].unit_price = parseFloat(price) || 0;
        calculateItemVAT(saleItems[idx]);
        renderItems();
    }

    function removeItem(idx) {
        saleItems.splice(idx, 1);
        renderItems();
    }

    function recalculateAll() {
        const eurRate = parseFloat(document.getElementById('eurRate').value);
        const usdRate = parseFloat(document.getElementById('usdRate').value);

        saleItems.forEach(item => {
            if (item.purchase_currency === 'EUR') item.currency_rate = eurRate;
            else if (item.purchase_currency === 'USD') item.currency_rate = usdRate;
            else item.currency_rate = 1;

            calculateItemVAT(item);
        });

        renderItems();
    }

    function updateTotals() {
        const subtotal = saleItems.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        const vat = saleItems.reduce((sum, item) => sum + item.vat_amount, 0);
        const total = subtotal;

        document.getElementById('subtotalDisplay').textContent = formatCurrency(subtotal);
        document.getElementById('vatDisplay').textContent = formatCurrency(vat);
        document.getElementById('totalDisplay').textContent = formatCurrency(total);
    }

    // Open accessory selector
    async function openAccessorySelector() {
        openModal('accessoryModal');

        const list = document.getElementById('accessoryList');
        list.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        try {
            const response = await fetch('../../api/ajax-handlers.php?action=get_accessories');
            const accessories = await response.json();

            if (accessories.length === 0) {
                list.innerHTML = '<div class="empty-state"><p><?= __('no_data') ?></p></div>';
                return;
            }

            list.innerHTML = accessories.map(a => `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border-bottom: 1px solid var(--gray-200); cursor: pointer;"
                 onclick="addAccessory(${JSON.stringify(a).replace(/"/g, '&quot;')})">
                <div>
                    <span class="badge badge-info">${a.type_name}</span>
                    <strong>${a.name}</strong>
                    <br><small class="text-muted">Доступно: ${a.quantity_available}</small>
                </div>
                <div style="font-weight: 600;">${formatCurrency(a.selling_price || 0)}</div>
            </div>
        `).join('');
        } catch (e) {
            list.innerHTML = '<div class="alert alert-error">Ошибка загрузки</div>';
        }
    }

    function addAccessory(accessory) {
        const item = {
            device_id: null,
            accessory_id: accessory.id,
            name: `${accessory.type_name}: ${accessory.name}`,
            quantity: 1,
            max_quantity: accessory.quantity_available,
            unit_price: accessory.selling_price || 0,
            purchase_price: 0,
            delivery_cost: 0,
            purchase_currency: 'CZK',
            currency_rate: 1,
            vat_mode: 'no',
            margin: accessory.selling_price || 0,
            vat_amount: 0
        };

        saleItems.push(item);
        renderItems();
        closeModal('accessoryModal');
    }

    // Submit form
    document.getElementById('saleForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const clientId = document.getElementById('clientSelect').value;
        if (!clientId) {
            showToast('Выберите клиента', 'error');
            return;
        }

        if (saleItems.length === 0) {
            showToast('Добавьте товары', 'error');
            return;
        }

        const data = {
            action: 'create_sale',
            client_id: clientId,
            sale_date: document.getElementById('saleDate').value,
            invoice_number: document.querySelector('[name="invoice_number"]').value,
            eur_rate: parseFloat(document.getElementById('eurRate').value),
            usd_rate: parseFloat(document.getElementById('usdRate').value),
            items: saleItems.map(item => ({
                device_id: item.device_id,
                accessory_id: item.accessory_id,
                quantity: item.quantity,
                unit_price: item.unit_price,
                vat_mode: item.vat_mode,
                vat_amount: item.vat_amount
            }))
        };

        try {
            const response = await fetch('../../api/ajax-handlers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                showToast('Продажа создана!', 'success');
                setTimeout(() => {
                    window.location.href = 'history.php';
                }, 1000);
            } else {
                showToast(result.message || 'Ошибка', 'error');
            }
        } catch (e) {
            showToast('Ошибка сохранения', 'error');
        }
    });

    // Initialize
    renderItems();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>