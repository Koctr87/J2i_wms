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

    // Get sale type
    $saleType = $_GET['type'] ?? 'wholesale';
    if (!in_array($saleType, ['wholesale', 'retail']))
        $saleType = 'wholesale';

    // Get clients for dropdown
    $clients = $db->query("SELECT id, company_name, ico FROM clients WHERE is_active = 1 AND type = '$saleType' ORDER BY company_name")->fetchAll();

    // Get platforms if retail
    $platforms = [];
    if ($saleType === 'retail') {
        $platforms = $db->query("SELECT * FROM sales_platforms WHERE is_active = 1 ORDER BY name")->fetchAll();
    }

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
            <?= __('new_sale') ?> (<?= __($saleType) ?>)
        </h3>
        <input type="hidden" name="sale_type" id="saleType" value="<?= $saleType ?>">
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

                    <?php if ($saleType === 'retail'): ?>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label required"><?= __('sales_platform') ?></label>
                            <select name="platform_id" id="platformSelect" class="form-control" onchange="updateTotals()"
                                required>
                                <option value="">-- <?= __('sales_platform') ?> --</option>
                                <?php foreach ($platforms as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-commission="<?= $p['commission_percentage'] ?>">
                                        <?= e($p['name']) ?> (<?= (float) $p['commission_percentage'] ?>%)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

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

                    <div class="form-group">
                        <label class="form-label">Attached Invoice (File)</label>
                        <input type="file" id="saleAttachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
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
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Delivery (Exp)</th>
                                <th>VAT Mode</th>
                                <th>Margin</th>
                                <th>VAT</th>
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
                                <?php if ($saleType === 'retail'): ?>
                                    <td colspan="1">
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                            <button type="button" class="btn btn-sm btn-outline"
                                                style="font-size: 0.75rem; padding: 2px 6px;"
                                                onclick="addQuickAccessory('transport_box')">📦 Трансп.</button>
                                            <button type="button" class="btn btn-sm btn-outline"
                                                style="font-size: 0.75rem; padding: 2px 6px;"
                                                onclick="addQuickAccessory('packaging_box')">🎁 Упак.</button>
                                            <button type="button" class="btn btn-sm btn-outline"
                                                style="font-size: 0.75rem; padding: 2px 6px;"
                                                onclick="addQuickAccessory('charging_cable')">🔌 Кабель</button>
                                            <button type="button" class="btn btn-sm btn-outline"
                                                style="font-size: 0.75rem; padding: 2px 6px;"
                                                onclick="addQuickAccessory('charging_brick')">🔌 Блок</button>
                                            <button type="button" class="btn btn-sm btn-outline"
                                                style="font-size: 0.75rem; padding: 2px 6px;"
                                                onclick="addQuickAccessory('sim_tool')">📍 Скрепка</button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Sale Delivery Cost -->
            <div
                style="margin-bottom: 2rem; background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg);">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">🚚 <?= __('delivery_cost') ?></h4>
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label"><?= __('delivery_cost') ?></label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="number" id="saleDeliveryCost" class="form-control" value="0" min="0"
                                step="0.01" oninput="updateTotals()" style="flex: 1;">
                            <select id="saleDeliveryCurrency" class="form-control" style="width: 90px;"
                                onchange="updateTotals()">
                                <option value="CZK">CZK</option>
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label"><?= __('delivery_cost') ?> per item (CZK)</label>
                        <div id="deliveryPerItemDisplay"
                            style="font-size: 1.1rem; font-weight: 600; color: var(--gray-600); padding-top: 0.5rem;">
                            0.00 Kč</div>
                    </div>
                </div>
            </div>

            <div
                style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); padding: 1.5rem; border-radius: var(--radius-lg); color: white;">
                <div
                    style="display: grid; grid-template-columns: repeat(<?= $saleType === 'retail' ? 7 : 6 ?>, 1fr); gap: 1rem;">
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;"><?= __('subtotal') ?></div>
                        <div style="font-size: 1.25rem; font-weight: 700;" id="subtotalDisplay">0 Kč</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;">🚚 Delivery</div>
                        <div style="font-size: 1.25rem; font-weight: 700;" id="deliveryDisplay">0 Kč</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;">📦 Item Deliv.</div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: #ff9999;"
                            id="itemDeliveryTotalDisplay">0 Kč</div>
                    </div>
                    <?php if ($saleType === 'retail'): ?>
                        <div>
                            <div style="font-size: 0.875rem; opacity: 0.8;">💸 <?= __('commission') ?></div>
                            <div style="font-size: 1.25rem; font-weight: 700; color: #ffcc00;" id="commissionDisplay">0 Kč
                            </div>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;"><?= __('vat_amount') ?> (21%)</div>
                        <div style="font-size: 1.25rem; font-weight: 700;" id="vatDisplay">0 Kč</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;"><?= __('total') ?></div>
                        <div style="font-size: 1.5rem; font-weight: 900;" id="totalDisplay">0 Kč</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 0.5rem; border-radius: 8px;">
                        <div style="font-size: 0.875rem; font-weight: 600;">Net Profit</div>
                        <div style="font-size: 1.5rem; font-weight: 900; color: #4ade80;" id="profitDisplay">0 Kč</div>
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
            <div style="display: grid; grid-template-columns: 1fr 1.5fr 1fr; gap: 0.5rem; margin-bottom: 1rem;">
                <select id="deviceBrandFilter" class="form-control" onchange="loadDevices(1)">
                    <option value="">-- All Brands --</option>
                </select>
                <input type="text" class="form-control" id="deviceModelFilter" placeholder="Model..."
                    oninput="debounceDeviceFilter()">
                <input type="text" class="form-control" id="deviceImeiFilter" placeholder="IMEI..."
                    oninput="debounceDeviceFilter()">
            </div>
            <div id="deviceList" style="max-height: 400px; overflow-y: auto;">
                <div class="loading">
                    <div class="spinner"></div>
                </div>
            </div>
            <div id="devicePagination"
                style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; border-top: 1px solid var(--gray-200); padding-top: 0.5rem; display: none;">
                <button type="button" class="btn btn-sm btn-outline" onclick="changeDevicePage(-1)"
                    id="prevDevicePage">←</button>
                <span id="devicePageInfo" style="font-size: 0.875rem; font-weight: 600; color: var(--gray-600);">Page 1
                    / 1</span>
                <button type="button" class="btn btn-sm btn-outline" onclick="changeDevicePage(1)"
                    id="nextDevicePage">→</button>
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

    let currentDevicePage = 1;
    let totalDevicePages = 1;

    // Load brands for filter
    async function loadBrandsForFilter() {
        const select = document.getElementById('deviceBrandFilter');
        if (select.options.length > 1) return; // Already loaded
        try {
            const response = await fetch('../../api/ajax-handlers.php?action=get_brands');
            const brands = await response.json();
            brands.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = b.name;
                select.appendChild(opt);
            });
        } catch (e) { }
    }

    async function loadDevices(page = 1) {
        currentDevicePage = page;
        const brandId = document.getElementById('deviceBrandFilter').value;
        const model = document.getElementById('deviceModelFilter').value;
        const imei = document.getElementById('deviceImeiFilter').value;
        const list = document.getElementById('deviceList');
        const pagination = document.getElementById('devicePagination');

        list.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        pagination.style.display = 'none';

        try {
            const params = new URLSearchParams({
                action: 'get_devices',
                brand_id: brandId,
                model: model,
                imei: imei,
                page: page,
                limit: 15
            });
            const response = await fetch('../../api/ajax-handlers.php?' + params.toString());
            const data = await response.json();
            const devices = data.devices || [];
            totalDevicePages = data.total_pages || 1;

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
                    ${d.condition === 'used' ? `<span class="badge badge-outline" style="border: 1px solid #ddd;">Gr. ${d.grading || 'A'}</span>` : ''}
                    <br>
                    <small class="text-muted">${d.imei ? `IMEI: ${d.imei} | ` : ''}<?= __('available') ?>: ${d.quantity_available}</small>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: 600;">${formatCurrency(d.retail_price || 0)}</div>
                    <span class="badge badge-${d.vat_mode === 'marginal' ? 'warning' : (d.vat_mode === 'reverse' ? 'info' : 'gray')}">${d.vat_mode}</span>
                </div>
            </div>
        `).join('');

            // Update pagination
            pagination.style.display = 'flex';
            document.getElementById('devicePageInfo').textContent = `<?= __('page') ?> ${page} / ${totalDevicePages}`;
            document.getElementById('prevDevicePage').disabled = page <= 1;
            document.getElementById('nextDevicePage').disabled = page >= totalDevicePages;
        } catch (e) {
            list.innerHTML = '<div class="alert alert-error">Ошибка загрузки</div>';
        }
    }

    function changeDevicePage(delta) {
        const newPage = currentDevicePage + delta;
        if (newPage >= 1 && newPage <= totalDevicePages) {
            loadDevices(newPage);
        }
    }

    const debounceDeviceFilter = debounce(() => loadDevices(1), 400);

    // Open device selector
    async function openDeviceSelector() {
        openModal('deviceModal');
        loadBrandsForFilter();
        loadDevices(1);
    }

    // Add device to sale
    async function addDevice(device) {
        const exists = saleItems.find(i => i.device_id === device.id);
        if (exists) {
            showToast('Устройство уже добавлено', 'warning');
            return;
        }

        const eurRate = parseFloat(document.getElementById('eurRate').value);
        const usdRate = parseFloat(document.getElementById('usdRate').value);
        const purchaseRate = device.purchase_currency === 'EUR' ? eurRate : (device.purchase_currency === 'USD' ? usdRate : 1);

        // Try to fetch master price
        let suggestedPrice = device.retail_price || 0;
        try {
            const masterPriceResponse = await fetch(`../../api/ajax-handlers.php?action=get_master_price&product_id=${device.product_id}&memory_id=${device.memory_id}&condition=${device.condition}&vat_mode=${device.vat_mode}&grade=${device.grading || 'A'}`);
            const masterPriceData = await masterPriceResponse.json();

            if (masterPriceData.success) {
                const mp = masterPriceData.price;
                const mpRate = mp.currency === 'EUR' ? eurRate : (mp.currency === 'USD' ? usdRate : 1);
                // Default to retail, but we could allow switching
                suggestedPrice = mp.retail_price * mpRate;
                showToast(`Загружена цена из прайса: ${suggestedPrice.toFixed(0)} Kč`, 'info');
            }
        } catch (e) {
            console.error('Error fetching master price', e);
        }

        const item = {
            device_id: device.id,
            accessory_id: null,
            name: `${device.brand_name} ${device.product_name} ${device.memory || ''} (${device.condition === 'new' ? 'New' : 'Used'})`.trim(),
            quantity: 1,
            max_quantity: device.quantity_available,
            unit_price: Math.round(suggestedPrice),
            sale_currency: 'CZK',
            purchase_price: device.purchase_price,
            delivery_cost: device.delivery_cost || 0,
            purchase_currency: device.purchase_currency,
            delivery_currency: device.delivery_currency || 'CZK',
            item_delivery_cost: 0,
            item_delivery_currency: 'CZK',
            currency_rate: purchaseRate,
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
        const eurRate = parseFloat(document.getElementById('eurRate').value) || 25;
        const usdRate = parseFloat(document.getElementById('usdRate').value) || 23;

        // Convert purchase costs to CZK
        const deliveryRate = item.delivery_currency === 'EUR' ? eurRate : (item.delivery_currency === 'USD' ? usdRate : 1);
        const purchasePriceCZK = item.purchase_price * item.currency_rate;
        const purchaseDeliveryCZK = item.delivery_cost * deliveryRate;
        const totalPurchaseCostItemCZK = purchasePriceCZK + purchaseDeliveryCZK;

        // Convert item-level delivery (expense) to CZK
        const itemDelCurrency = item.item_delivery_currency || 'CZK';
        const itemDelRate = itemDelCurrency === 'EUR' ? eurRate : (itemDelCurrency === 'USD' ? usdRate : 1);
        const itemDeliveryExpenseCZK = (item.item_delivery_cost || 0) * itemDelRate;

        // Convert selling price to CZK
        const saleCurrency = item.sale_currency || 'CZK';
        let saleRate = 1;
        if (saleCurrency === 'EUR') saleRate = eurRate;
        else if (saleCurrency === 'USD') saleRate = usdRate;

        const sellingPriceInCZK = item.unit_price * item.quantity * saleRate;
        const totalPurchaseCostAllCZK = totalPurchaseCostItemCZK * item.quantity;
        const totalItemDeliveryAllCZK = itemDeliveryExpenseCZK * item.quantity;

        // Platform Commission (Retail only)
        let commissionAmt = 0;
        const platformSelect = document.getElementById('platformSelect');
        if (platformSelect && platformSelect.value) {
            const commissionPct = parseFloat(platformSelect.options[platformSelect.selectedIndex].dataset.commission) || 0;
            commissionAmt = sellingPriceInCZK * (commissionPct / 100);
        }
        item.platform_commission = commissionAmt;

        if (item.vat_mode === 'marginal') {
            // VAT is only on (Sale - Purchase)
            const vatBase = sellingPriceInCZK - totalPurchaseCostAllCZK;
            item.vat_amount = vatBase > 0 ? vatBase * (21 / 121) : 0;
            // Margin = Sale - Purchase - Outbound Delivery - VAT - Commission
            item.margin = sellingPriceInCZK - totalPurchaseCostAllCZK - totalItemDeliveryAllCZK - item.vat_amount - commissionAmt;
        } else {
            item.margin = sellingPriceInCZK - totalPurchaseCostAllCZK - totalItemDeliveryAllCZK - commissionAmt;
            item.vat_amount = 0;
        }

        // Store CZK equivalent for totals
        item.selling_czk = sellingPriceInCZK;

        item.margin = Math.round(item.margin * 100) / 100;
        item.vat_amount = Math.round(item.vat_amount * 100) / 100;
    }

    // Render items table
    function renderItems() {
        const tbody = document.getElementById('itemsBody');

        if (saleItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Добавьте товары</td></tr>';
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
                <div style="display: flex; gap: 0.25rem; align-items: center;">
                    <input type="number" class="form-control" style="width: 100px;" value="${item.unit_price}" min="0" step="0.01"
                           onchange="updateItemPrice(${idx}, this.value)">
                    <select class="form-control" style="width: 75px; font-size: 0.8rem;" onchange="updateItemCurrency(${idx}, this.value)">
                        <option value="CZK" ${(item.sale_currency || 'CZK') === 'CZK' ? 'selected' : ''}>CZK</option>
                        <option value="EUR" ${item.sale_currency === 'EUR' ? 'selected' : ''}>EUR</option>
                        <option value="USD" ${item.sale_currency === 'USD' ? 'selected' : ''}>USD</option>
                    </select>
                </div>
            </td>
            <td>
                <div style="display: flex; gap: 0.25rem; align-items: center;">
                    <input type="number" class="form-control" style="width: 80px;" value="${item.item_delivery_cost || 0}" min="0" step="0.01"
                           onchange="updateItemDeliveryCost(${idx}, this.value)">
                    <select class="form-control" style="width: 70px; font-size: 0.75rem;" onchange="updateItemDeliveryCurrency(${idx}, this.value)">
                        <option value="CZK" ${(item.item_delivery_currency || 'CZK') === 'CZK' ? 'selected' : ''}>CZK</option>
                        <option value="EUR" ${item.item_delivery_currency === 'EUR' ? 'selected' : ''}>EUR</option>
                        <option value="USD" ${item.item_delivery_currency === 'USD' ? 'selected' : ''}>USD</option>
                    </select>
                </div>
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

    function updateItemCurrency(idx, currency) {
        saleItems[idx].sale_currency = currency;
        calculateItemVAT(saleItems[idx]);
        renderItems();
    }

    function updateItemDeliveryCost(idx, cost) {
        saleItems[idx].item_delivery_cost = parseFloat(cost) || 0;
        calculateItemVAT(saleItems[idx]);
        renderItems();
    }

    function updateItemDeliveryCurrency(idx, currency) {
        saleItems[idx].item_delivery_currency = currency;
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
        const eurRate = parseFloat(document.getElementById('eurRate').value) || 25;
        const usdRate = parseFloat(document.getElementById('usdRate').value) || 23;

        const subtotal = saleItems.reduce((sum, item) => sum + (item.selling_czk || item.unit_price * item.quantity), 0);
        const vat = saleItems.reduce((sum, item) => sum + item.vat_amount, 0);

        // Commission
        const totalCommission = saleItems.reduce((sum, item) => sum + (item.platform_commission || 0), 0);

        // Delivery cost in CZK
        const deliveryCost = parseFloat(document.getElementById('saleDeliveryCost').value) || 0;
        const deliveryCurrency = document.getElementById('saleDeliveryCurrency').value;
        let deliveryRate = 1;
        if (deliveryCurrency === 'EUR') deliveryRate = eurRate;
        else if (deliveryCurrency === 'USD') deliveryRate = usdRate;
        const deliveryCZK = deliveryCost * deliveryRate;

        // Delivery per item
        const totalQty = saleItems.reduce((sum, item) => sum + item.quantity, 0);
        const perItem = totalQty > 0 ? deliveryCZK / totalQty : 0;
        document.getElementById('deliveryPerItemDisplay').textContent = formatCurrency(perItem);

        const total = subtotal + deliveryCZK;

        // Item-level delivery (Expense)
        const itemDeliveryTotal = saleItems.reduce((sum, item) => {
            const delCurrency = item.item_delivery_currency || 'CZK';
            const delRate = delCurrency === 'EUR' ? eurRate : (delCurrency === 'USD' ? usdRate : 1);
            return sum + (parseFloat(item.item_delivery_cost) || 0) * item.quantity * delRate;
        }, 0);

        // Net Profit (Sum of margins minus shipping to client)
        const totalProfit = saleItems.reduce((sum, item) => sum + item.margin, 0) - deliveryCZK;

        document.getElementById('subtotalDisplay').textContent = formatCurrency(subtotal);
        document.getElementById('deliveryDisplay').textContent = formatCurrency(deliveryCZK);
        document.getElementById('itemDeliveryTotalDisplay').textContent = formatCurrency(itemDeliveryTotal);
        if (document.getElementById('commissionDisplay')) {
            document.getElementById('commissionDisplay').textContent = formatCurrency(totalCommission);
        }
        document.getElementById('vatDisplay').textContent = formatCurrency(vat);
        document.getElementById('totalDisplay').textContent = formatCurrency(total);

        if (document.getElementById('profitDisplay')) {
            document.getElementById('profitDisplay').textContent = formatCurrency(totalProfit);
            document.getElementById('profitDisplay').style.color = totalProfit >= 0 ? 'var(--success)' : 'var(--danger)';
        }
    }

    async function addQuickAccessory(type) {
        try {
            const response = await fetch(`<?= APP_URL ?>/api/ajax-handlers.php?action=get_accessories_by_type&type_key=${type}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (data.success && data.accessory) {
                addAccessory(data.accessory);
                showToast(`Added ${type.replace('_', ' ')}`, 'success');
            } else {
                showToast(`No available ${type.replace('_', ' ')} found`, 'warning');
            }
        } catch (err) {
            console.error(err);
            showToast('Error adding accessory', 'error');
        }
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
        const saleType = '<?= $saleType ?>';
        const eurRate = parseFloat(document.getElementById('eurRate').value) || 25;
        const usdRate = parseFloat(document.getElementById('usdRate').value) || 23;

        const purchaseRate = accessory.purchase_currency === 'EUR' ? eurRate : (accessory.purchase_currency === 'USD' ? usdRate : 1);

        const item = {
            device_id: null,
            accessory_id: accessory.id,
            name: accessory.name,
            quantity: 1,
            max_quantity: accessory.quantity_available,
            unit_price: saleType === 'retail' ? 0 : (accessory.selling_price || 0),
            sale_currency: 'CZK',
            purchase_price: accessory.purchase_price || 0,
            delivery_cost: accessory.delivery_cost || 0,
            purchase_currency: accessory.purchase_currency || 'CZK',
            delivery_currency: accessory.delivery_currency || 'CZK',
            currency_rate: purchaseRate,
            vat_mode: 'no',
            item_delivery_cost: 0,
            item_delivery_currency: 'CZK',
            margin: 0,
            vat_amount: 0
        };

        calculateItemVAT(item);
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

        const formData = new FormData();
        formData.append('action', 'create_sale');
        formData.append('client_id', clientId);
        formData.append('sale_date', document.getElementById('saleDate').value);
        formData.append('invoice_number', document.querySelector('[name="invoice_number"]').value);
        formData.append('eur_rate', document.getElementById('eurRate').value);
        formData.append('usd_rate', document.getElementById('usdRate').value);
        formData.append('sale_delivery_cost', document.getElementById('saleDeliveryCost').value || 0);
        formData.append('sale_delivery_currency', document.getElementById('saleDeliveryCurrency').value);

        const attachment = document.getElementById('saleAttachment').files[0];
        if (attachment) {
            formData.append('attachment', attachment);
        }

        const items = saleItems.map(item => ({
            device_id: item.device_id,
            accessory_id: item.accessory_id,
            quantity: item.quantity,
            unit_price: item.unit_price,
            sale_currency: item.sale_currency || 'CZK',
            vat_mode: item.vat_mode,
            vat_amount: item.vat_amount,
            item_delivery_cost: item.item_delivery_cost || 0,
            item_delivery_currency: item.item_delivery_currency || 'CZK'
        }));
        formData.append('items', JSON.stringify(items));

        try {
            const response = await fetch('../../api/ajax-handlers.php', {
                method: 'POST',
                body: formData
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