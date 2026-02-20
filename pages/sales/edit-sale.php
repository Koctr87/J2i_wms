<?php
/**
 * J2i Warehouse Management System
 * Edit Sale Page
 */
require_once __DIR__ . '/../../config/config.php';

$pageTitle = __('edit_sale');
require_once __DIR__ . '/../../includes/header.php';

try {
    $db = getDB();
    $saleId = $_GET['id'] ?? 0;

    if (!$saleId) {
        throw new Exception("Sale ID missing");
    }

    // Fetch sale
    $stmt = $db->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();

    if (!$sale) {
        throw new Exception("Sale not found");
    }

    // Fetch items
    $stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$saleId]);
    $dbItems = $stmt->fetchAll();

    // Prepare items for JS
    $jsItems = [];
    foreach ($dbItems as $item) {
        try {
            if (!empty($item['device_id'])) {
                $query = "SELECT d.*, p.name as product_name, b.name as brand_name, m.size as memory, c.name_en as color 
                          FROM devices d 
                          JOIN products p ON d.product_id = p.id 
                          JOIN brands b ON p.brand_id = b.id 
                          LEFT JOIN memory_options m ON d.memory_id = m.id 
                          LEFT JOIN color_options c ON d.color_id = c.id
                          WHERE d.id = " . intval($item['device_id']);

                $res = $db->query($query);
                if (!$res)
                    continue;

                $dev = $res->fetch();
                if (!$dev)
                    continue; // Device deleted?

                $name = trim(($dev['brand_name'] ?? '') . ' ' . ($dev['product_name'] ?? '') . ' ' . ($dev['memory'] ?? '') . ' ' . ($dev['color'] ?? ''));
                $maxQty = ($dev['quantity_available'] ?? 0) + $item['quantity'];
                $purchasePrice = $dev['purchase_price'] ?? 0;
                $deliveryCost = $dev['delivery_cost'] ?? 0;
                $purchaseCurrency = $dev['purchase_currency'] ?? 'CZK';
                $deliveryCurrency = $dev['delivery_currency'] ?? 'CZK';
            } elseif (!empty($item['accessory_id'])) {
                $query = "SELECT a.*, t.name_en as type_name FROM accessories a JOIN accessory_types t ON a.type_id = t.id WHERE a.id = " . intval($item['accessory_id']);
                $res = $db->query($query);
                if (!$res)
                    continue;
                $acc = $res->fetch();
                if (!$acc)
                    continue;

                $name = ($acc['type_name'] ?? '') . ': ' . ($acc['name'] ?? '');
                $maxQty = ($acc['quantity_available'] ?? 0) + $item['quantity'];
                $purchasePrice = $acc['purchase_price'] ?? 0;
                $deliveryCost = $acc['delivery_cost'] ?? 0;
                $purchaseCurrency = $acc['purchase_currency'] ?? 'CZK';
                $deliveryCurrency = $acc['delivery_currency'] ?? 'CZK';

                // Rule: Retail accessories are 0 price (included in set)
                if ($sale['type'] === 'retail') {
                    $item['unit_price'] = 0;
                }
            } else {
                continue;
            }

            $jsItems[] = [
                'device_id' => $item['device_id'],
                'accessory_id' => $item['accessory_id'],
                'name' => $name,
                'quantity' => (int) $item['quantity'],
                'max_quantity' => (int) $maxQty,
                'unit_price' => (float) $item['unit_price'],
                'sale_currency' => $item['sale_currency'] ?? 'CZK',
                'purchase_price' => (float) $purchasePrice,
                'delivery_cost' => (float) $deliveryCost,
                'purchase_currency' => $purchaseCurrency,
                'delivery_currency' => $deliveryCurrency,
                'item_delivery_cost' => (float) ($item['item_delivery_cost'] ?? 0),
                'item_delivery_currency' => $item['item_delivery_currency'] ?? 'CZK',
                'vat_mode' => $item['vat_mode'],
                'vat_amount' => (float) $item['vat_amount'],
                'margin' => 0,
                'currency_rate' => 1
            ];
        } catch (Exception $e) {
            // Log error but continue
            error_log("Error processing item " . $item['id'] . ": " . $e->getMessage());
        }
    }

    // Get clients
    $clients = $db->query("SELECT id, company_name, ico FROM clients WHERE is_active = 1 OR id = {$sale['client_id']} ORDER BY company_name")->fetchAll();

    // Get platforms
    $platforms = $db->query("SELECT * FROM sales_platforms ORDER BY name")->fetchAll();

} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
            </svg>
            Edit Sale #<?= $saleId ?>
        </h3>
    </div>
    <div class="card-body">
        <form id="saleForm">
            <input type="hidden" name="sale_id" value="<?= $saleId ?>">

            <!-- Client & Date -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">👤 <?= __('client') ?></h4>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label required"><?= __('select_client') ?></label>
                        <select name="client_id" id="clientSelect" class="form-control" required>
                            <option value="">-- <?= __('select_client') ?> --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= $client['id'] == $sale['client_id'] ? 'selected' : '' ?>>
                                    <?= e($client['company_name']) ?> (<?= e($client['ico'] ?? 'N/A') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><?= __('sale_date') ?></label>
                        <input type="date" name="sale_date" id="saleDate" class="form-control"
                            value="<?= $sale['sale_date'] ?>" required>
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label class="form-label"><?= __('sales_platform') ?></label>
                        <select name="platform_id" id="platformSelect" class="form-control" onchange="updateTotals()">
                            <option value="">-- No Platform --</option>
                            <?php foreach ($platforms as $p): ?>
                                <option value="<?= $p['id'] ?>" data-commission="<?= $p['commission_percentage'] ?>"
                                    <?= ($sale['platform_id'] == $p['id']) ? 'selected' : '' ?>>
                                    <?= e($p['name']) ?> (<?= (float) $p['commission_percentage'] ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= __('invoice_out') ?></label>
                        <input type="text" name="invoice_number" class="form-control"
                            value="<?= e($sale['invoice_number'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Attached Invoice (File)</label>
                        <input type="file" id="saleAttachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <?php if ($sale['attachment_path']): ?>
                            <small class="text-muted">Current: <a href="<?= APP_URL . $sale['attachment_path'] ?>"
                                    target="_blank">View Invoice</a></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Currency Rates -->
            <div
                style="margin-bottom: 2rem; background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg);">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">💱 <?= __('currency_rate') ?></h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('rate_eur') ?></label>
                        <input type="number" name="eur_rate" id="eurRate" class="form-control"
                            value="<?= isset($sale['currency_rate_eur']) ? number_format($sale['currency_rate_eur'], 4, '.', '') : '25.0000' ?>"
                            step="0.0001" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('rate_usd') ?></label>
                        <input type="number" name="usd_rate" id="usdRate" class="form-control"
                            value="<?= isset($sale['currency_rate_usd']) ? number_format($sale['currency_rate_usd'], 4, '.', '') : '23.0000' ?>"
                            step="0.0001" readonly>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="refreshRates()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10" />
                                <polyline points="1 20 1 14 7 14" />
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                            </svg> Update Rate
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sale Items -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">📦 Items</h4>
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
                        <tbody id="itemsBody"></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8">
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="button" class="btn btn-secondary" onclick="openDeviceSelector()">
                                            <?= __('add') ?> <?= __('devices') ?>
                                        </button>
                                        <button type="button" class="btn btn-outline" onclick="openAccessorySelector()">
                                            <?= __('add') ?> <?= __('accessories') ?>
                                        </button>
                                    </div>
                                </td>
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
                            <input type="number" id="saleDeliveryCost" class="form-control"
                                value="<?= $sale['sale_delivery_cost'] ?? 0 ?>" min="0" step="0.01"
                                oninput="updateTotals()" style="flex: 1;">
                            <select id="saleDeliveryCurrency" class="form-control" style="width: 90px;"
                                onchange="updateTotals()">
                                <option value="CZK" <?= ($sale['sale_delivery_currency'] ?? 'CZK') === 'CZK' ? 'selected' : '' ?>>CZK</option>
                                <option value="EUR" <?= ($sale['sale_delivery_currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                                <option value="USD" <?= ($sale['sale_delivery_currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
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
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1rem;">
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;"><?= __('subtotal') ?></div>
                        <div style="font-size: 1.25rem; font-weight: 700;" id="subtotalDisplay">0 Kč</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;">🚚 Delivery</div>
                        <div style="font-size: 1.25rem; font-weight: 700;" id="deliveryDisplay">0 Kč</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;">📦 Item Deliv. (Exp)</div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: #ff9999;"
                            id="itemDeliveryTotalDisplay">0 Kč</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;">💸 <?= __('commission') ?></div>
                        <div style="font-size: 1.25rem; font-weight: 700; color: #ffcc00;" id="commissionDisplay">0 Kč
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;"><?= __('vat_amount') ?></div>
                        <div style="font-size: 1.25rem; font-weight: 700;" id="vatDisplay">0 Kč</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; opacity: 0.8;"><?= __('total') ?></div>
                        <div style="font-size: 1.5rem; font-weight: 700;" id="totalDisplay">0 Kč</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 0.5rem; border-radius: 8px;">
                        <div style="font-size: 0.875rem; font-weight: 600;">Net Profit</div>
                        <div style="font-size: 1.5rem; font-weight: 900; color: #4ade80;" id="profitDisplay">0 Kč</div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: space-between; margin-top: 2rem;">
                <button type="button" class="btn btn-danger" onclick="deleteSale(<?= $saleId ?>)"
                    style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                    </svg>
                    Delete
                </button>
                <div style="display: flex; gap: 1rem;">
                    <a href="history.php" class="btn btn-secondary"><?= __('cancel') ?></a>
                    <button type="submit" class="btn btn-primary btn-lg"><?= __('save') ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modals Same -->
<div class="modal-overlay" id="deviceModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title"><?= __('select_device') ?></h3><button type="button" class="modal-close"
                onclick="closeModal('deviceModal')">&times;</button>
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

<div class="modal-overlay" id="accessoryModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><?= __('accessories') ?></h3><button type="button" class="modal-close"
                onclick="closeModal('accessoryModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="accessoryList" style="max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>
</div>

<script>
    let saleItems = <?= json_encode($jsItems) ?>;

    async function refreshRates() {
        const date = document.getElementById('saleDate').value;
        try {
            const eurResponse = await fetch('../../api/ajax-handlers.php?action=get_cnb_rate&currency=EUR&date=' + date);
            const eurData = await eurResponse.json();
            if (eurData.success) document.getElementById('eurRate').value = eurData.rate.toFixed(4);

            const usdResponse = await fetch('../../api/ajax-handlers.php?action=get_cnb_rate&currency=USD&date=' + date);
            const usdData = await usdResponse.json();
            if (usdData.success) document.getElementById('usdRate').value = usdData.rate.toFixed(4);

            recalculateAll();
        } catch (e) { showToast('Error updating rates', 'error'); }
    }

    let currentDevicePage = 1;
    let totalDevicePages = 1;

    async function loadBrandsForFilter() {
        const select = document.getElementById('deviceBrandFilter');
        if (select.options.length > 1) return;
        try {
            const response = await fetch('../../api/ajax-handlers.php?action=get_brands');
            const brands = await response.json();
            brands.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id; opt.textContent = b.name;
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
                action: 'get_devices', brand_id: brandId, model: model, imei: imei, page: page, limit: 15
            });
            const response = await fetch('../../api/ajax-handlers.php?' + params.toString());
            const data = await response.json();
            const devices = data.devices || [];
            totalDevicePages = data.total_pages || 1;

            if (devices.length === 0) { list.innerHTML = '<div class="empty-state"><p>No data</p></div>'; return; }

            list.innerHTML = devices.map(d => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border-bottom: 1px solid #ddd; cursor: pointer;" 
                     onclick="addDevice(${JSON.stringify(d).replace(/"/g, '&quot;')})">
                    <div>
                        <strong>${d.brand_name} ${d.product_name}</strong>
                        <span class="badge badge-gray">${d.memory || 'N/A'}</span><br>
                        <small class="text-muted">${d.imei ? `IMEI: ${d.imei} | ` : ''}<?= __('available') ?>: ${d.quantity_available}</small>
                    </div>
                    <div>${formatCurrency(d.retail_price || 0)}</div>
                </div>`).join('');

            pagination.style.display = 'flex';
            document.getElementById('devicePageInfo').textContent = `<?= __('page') ?> ${page} / ${totalDevicePages}`;
            document.getElementById('prevDevicePage').disabled = page <= 1;
            document.getElementById('nextDevicePage').disabled = page >= totalDevicePages;
        } catch (e) { }
    }

    function changeDevicePage(delta) {
        const newPage = currentDevicePage + delta;
        if (newPage >= 1 && newPage <= totalDevicePages) loadDevices(newPage);
    }
    const debounceDeviceFilter = debounce(() => loadDevices(1), 400);

    function openDeviceSelector() {
        openModal('deviceModal');
        loadBrandsForFilter();
        loadDevices(1);
    }

    async function loadAccessories() {
        const list = document.getElementById('accessoryList');
        try {
            const response = await fetch('../../api/ajax-handlers.php?action=get_accessories');
            const data = await response.json();
            list.innerHTML = data.map(a => `
                <div style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #ddd;" onclick="addAccessory(${JSON.stringify(a).replace(/"/g, '&quot;')})">
                    ${a.name} - ${formatCurrency(a.selling_price)}
                </div>`).join('');
        } catch (e) { }
    }

    function openAccessorySelector() { openModal('accessoryModal'); loadAccessories(); }

    function addDevice(device) {
        if (saleItems.find(i => i.device_id === device.id)) return;

        const eurRate = parseFloat(document.getElementById('eurRate').value);
        const usdRate = parseFloat(document.getElementById('usdRate').value);
        const rate = device.purchase_currency === 'EUR' ? eurRate : (device.purchase_currency === 'USD' ? usdRate : 1);

        const item = {
            device_id: device.id, accessory_id: null,
            name: `${device.brand_name} ${device.product_name} ${device.memory || ''}`.trim(),
            quantity: 1, max_quantity: device.quantity_available,
            unit_price: device.retail_price || 0,
            sale_currency: 'CZK',
            purchase_price: device.purchase_price,
            delivery_cost: device.delivery_cost || 0,
            purchase_currency: device.purchase_currency,
            currency_rate: rate, vat_mode: device.vat_mode,
            item_delivery_cost: 0, // New field
            item_delivery_currency: 'CZK', // New field
            margin: 0, vat_amount: 0
        };
        calculateItemVAT(item);
        saleItems.push(item);
        renderItems();
        closeModal('deviceModal');
    }

    function addAccessory(acc) {
        const saleType = '<?= $sale['type'] ?>';
        const eurRate = parseFloat(document.getElementById('eurRate').value) || 25;
        const usdRate = parseFloat(document.getElementById('usdRate').value) || 23;
        const purchaseRate = acc.purchase_currency === 'EUR' ? eurRate : (acc.purchase_currency === 'USD' ? usdRate : 1);

        const item = {
            device_id: null,
            accessory_id: acc.id,
            name: acc.name,
            quantity: 1,
            max_quantity: acc.quantity_available,
            unit_price: saleType === 'retail' ? 0 : (acc.selling_price || 0),
            sale_currency: 'CZK',
            purchase_price: acc.purchase_price || 0,
            delivery_cost: acc.delivery_cost || 0,
            purchase_currency: acc.purchase_currency || 'CZK',
            delivery_currency: acc.delivery_currency || 'CZK',
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

        // Platform Commission
        let commissionAmt = 0;
        const platformSelect = document.getElementById('platformSelect');
        if (platformSelect && platformSelect.value) {
            const commissionPct = parseFloat(platformSelect.options[platformSelect.selectedIndex].dataset.commission) || 0;
            commissionAmt = sellingPriceInCZK * (commissionPct / 100);
        }
        item.platform_commission = commissionAmt;

        if (item.vat_mode === 'marginal') {
            const vatBase = sellingPriceInCZK - totalPurchaseCostAllCZK;
            item.vat_amount = vatBase > 0 ? vatBase * (21 / 121) : 0;
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

    function renderItems() {
        const tbody = document.getElementById('itemsBody');
        if (saleItems.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No items</td></tr>';
            updateTotals();
            return;
        }
        tbody.innerHTML = saleItems.map((item, idx) => `
            <tr>
                <td>${item.name}</td>
                <td><input type="number" class="form-control" style="width: 80px" value="${item.quantity}" min="1" max="${item.max_quantity}" onchange="updateItemQuantity(${idx}, this.value)"></td>
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
                <td>${item.vat_mode}</td>
                <td class="${item.margin >= 0 ? '' : 'text-danger'}">${item.margin.toFixed(2)}</td>
                <td>${item.vat_amount.toFixed(2)}</td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${idx})">&times;</button></td>
            </tr>
        `).join('');
        updateTotals();
    }

    function updateItemQuantity(idx, val) { saleItems[idx].quantity = parseInt(val); recalculateAll(); }
    function updateItemPrice(idx, val) { saleItems[idx].unit_price = parseFloat(val); recalculateAll(); }
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
    function removeItem(idx) { saleItems.splice(idx, 1); renderItems(); }

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

        const subtotal = saleItems.reduce((s, i) => s + (i.selling_czk || i.unit_price * i.quantity), 0);
        const vat = saleItems.reduce((s, i) => s + i.vat_amount, 0);

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
        const totalQty = saleItems.reduce((s, i) => s + i.quantity, 0);
        const perItem = totalQty > 0 ? deliveryCZK / totalQty : 0;
        document.getElementById('deliveryPerItemDisplay').textContent = formatCurrency(perItem);

        const total = subtotal + deliveryCZK;

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

    document.getElementById('saleForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData();
        formData.append('action', 'update_sale');
        formData.append('sale_id', document.querySelector('[name="sale_id"]').value);
        formData.append('client_id', document.getElementById('clientSelect').value);
        formData.append('platform_id', document.getElementById('platformSelect').value);
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
            const resp = await fetch('../../api/ajax-handlers.php', { method: 'POST', body: formData });
            const res = await resp.json();
            if (res.success) {
                showToast('Sale updated!', 'success');
                setTimeout(() => { window.location.href = 'history.php'; }, 1000);
            } else {
                showToast(res.message || 'Error', 'error');
            }
        } catch (e) {
            showToast('Error saving', 'error');
        }
    });

    recalculateAll();

    async function deleteSale(saleId) {
        if (!confirm('Are you sure you want to delete this sale? This action cannot be undone. Stock quantities will be restored.')) {
            return;
        }

        try {
            const resp = await fetch('../../api/ajax-handlers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ action: 'delete_sale', sale_id: saleId })
            });
            const res = await resp.json();
            if (res.success) {
                window.location.href = 'history.php';
            } else {
                alert(res.message || 'Error deleting sale');
            }
        } catch (e) {
            alert('Network error');
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>