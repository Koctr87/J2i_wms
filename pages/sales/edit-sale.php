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
                if (!$res) continue;
                
                $dev = $res->fetch();
                if (!$dev) continue; // Device deleted?
                
                $name = trim(($dev['brand_name'] ?? '') . ' ' . ($dev['product_name'] ?? '') . ' ' . ($dev['memory'] ?? '') . ' ' . ($dev['color'] ?? ''));
                $maxQty = ($dev['quantity_available'] ?? 0) + $item['quantity'];
                $purchasePrice = $dev['purchase_price'] ?? 0;
                $deliveryCost = $dev['delivery_cost'] ?? 0;
                $purchaseCurrency = $dev['purchase_currency'] ?? 'CZK';
            } elseif (!empty($item['accessory_id'])) {
                $query = "SELECT a.*, t.name_en as type_name FROM accessories a JOIN accessory_types t ON a.type_id = t.id WHERE a.id = " . intval($item['accessory_id']);
                $res = $db->query($query);
                if (!$res) continue;
                $acc = $res->fetch();
                if (!$acc) continue;

                $name = ($acc['type_name'] ?? '') . ': ' . ($acc['name'] ?? '');
                $maxQty = ($acc['quantity_available'] ?? 0) + $item['quantity'];
                $purchasePrice = 0;
                $deliveryCost = 0;
                $purchaseCurrency = 'CZK';
            } else {
                continue;
            }

            $jsItems[] = [
                'device_id' => $item['device_id'],
                'accessory_id' => $item['accessory_id'],
                'name' => $name,
                'quantity' => (int)$item['quantity'],
                'max_quantity' => (int)$maxQty,
                'unit_price' => (float)$item['unit_price'],
                'purchase_price' => (float)$purchasePrice,
                'delivery_cost' => (float)$deliveryCost,
                'purchase_currency' => $purchaseCurrency,
                'vat_mode' => $item['vat_mode'],
                'vat_amount' => (float)$item['vat_amount'],
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

} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                        <input type="date" name="sale_date" id="saleDate" class="form-control" value="<?= $sale['sale_date'] ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= __('invoice_out') ?></label>
                        <input type="text" name="invoice_number" class="form-control" value="<?= e($sale['invoice_number'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Currency Rates -->
            <div style="margin-bottom: 2rem; background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg);">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">💱 <?= __('currency_rate') ?></h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?= __('rate_eur') ?></label>
                        <input type="number" name="eur_rate" id="eurRate" class="form-control" 
                               value="<?= isset($sale['currency_rate_eur']) ? number_format($sale['currency_rate_eur'], 4, '.', '') : '25.0000' ?>" step="0.0001" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= __('rate_usd') ?></label>
                        <input type="number" name="usd_rate" id="usdRate" class="form-control" 
                               value="<?= isset($sale['currency_rate_usd']) ? number_format($sale['currency_rate_usd'], 4, '.', '') : '23.0000' ?>" step="0.0001" readonly>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="refreshRates()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                                <th style="width: 35%;">Item</th>
                                <th>Qty</th>
                                <th>Price (CZK)</th>
                                <th>VAT Mode</th>
                                <th>Margin</th>
                                <th>VAT</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody"></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7">
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

            <!-- Totals -->
            <div style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); padding: 1.5rem; border-radius: var(--radius-lg); color: white;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                    <div><div style="font-size: 0.875rem; opacity: 0.8;"><?= __('subtotal') ?></div><div style="font-size: 1.5rem; font-weight: 700;" id="subtotalDisplay">0 Kč</div></div>
                    <div><div style="font-size: 0.875rem; opacity: 0.8;"><?= __('vat_amount') ?></div><div style="font-size: 1.5rem; font-weight: 700;" id="vatDisplay">0 Kč</div></div>
                    <div><div style="font-size: 0.875rem; opacity: 0.8;"><?= __('total') ?></div><div style="font-size: 2rem; font-weight: 700;" id="totalDisplay">0 Kč</div></div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <a href="history.php" class="btn btn-secondary"><?= __('cancel') ?></a>
                <button type="submit" class="btn btn-primary btn-lg"><?= __('save') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modals Same -->
<div class="modal-overlay" id="deviceModal">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header"><h3 class="modal-title"><?= __('select_device') ?></h3><button type="button" class="modal-close" onclick="closeModal('deviceModal')">&times;</button></div>
        <div class="modal-body">
            <input type="text" class="form-control" id="deviceSearch" placeholder="<?= __('search') ?>..." style="margin-bottom: 1rem;">
            <div id="deviceList" style="max-height: 400px; overflow-y: auto;"><div class="loading"><div class="spinner"></div></div></div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="accessoryModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header"><h3 class="modal-title"><?= __('accessories') ?></h3><button type="button" class="modal-close" onclick="closeModal('accessoryModal')">&times;</button></div>
        <div class="modal-body"><div id="accessoryList" style="max-height: 400px; overflow-y: auto;"></div></div>
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

    function openDeviceSelector() { openModal('deviceModal'); loadDevices(); }
    function openAccessorySelector() { openModal('accessoryModal'); loadAccessories(); }

    async function loadDevices(search = '') {
        const list = document.getElementById('deviceList');
        list.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        try {
            const response = await fetch('../../api/ajax-handlers.php?action=get_devices&search=' + encodeURIComponent(search));
            const devices = await response.json();
            if (devices.length === 0) { list.innerHTML = '<div class="empty-state"><p>No data</p></div>'; return; }
            
            list.innerHTML = devices.map(d => `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border-bottom: 1px solid #ddd; cursor: pointer;" 
                     onclick="addDevice(${JSON.stringify(d).replace(/"/g, '&quot;')})">
                    <div><strong>${d.brand_name} ${d.product_name}</strong><br><small>${d.memory || ''}</small></div>
                    <div>${formatCurrency(d.retail_price || 0)}</div>
                </div>`).join('');
        } catch (e) {}
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
        } catch(e) {}
    }
    
    document.getElementById('deviceSearch').addEventListener('input', debounce(function() { loadDevices(this.value); }, 300));

    function addDevice(device) {
        if (saleItems.find(i => i.device_id === device.id)) return;
        
        const eurRate = parseFloat(document.getElementById('eurRate').value);
        const usdRate = parseFloat(document.getElementById('usdRate').value);
        const rate = device.purchase_currency === 'EUR' ? eurRate : (device.purchase_currency === 'USD' ? usdRate : 1);
        
        const item = {
            device_id: device.id, accessory_id: null,
            name: `${device.brand_name} ${device.product_name} ${device.memory||''}`.trim(),
            quantity: 1, max_quantity: device.quantity_available,
            unit_price: device.retail_price || 0,
            purchase_price: device.purchase_price,
            delivery_cost: device.delivery_cost || 0,
            purchase_currency: device.purchase_currency,
            currency_rate: rate, vat_mode: device.vat_mode,
            margin: 0, vat_amount: 0
        };
        calculateItemVAT(item);
        saleItems.push(item);
        renderItems();
        closeModal('deviceModal');
    }

    function addAccessory(acc) {
        const item = {
            device_id: null, accessory_id: acc.id,
            name: acc.name, quantity: 1, max_quantity: acc.quantity_available,
            unit_price: acc.selling_price || 0,
            purchase_price: 0,
            delivery_cost: 0,
            purchase_currency: 'CZK', currency_rate: 1, vat_mode: 'no',
            margin: acc.selling_price, vat_amount: 0
        };
        saleItems.push(item);
        renderItems();
        closeModal('accessoryModal');
    }

    function calculateItemVAT(item) {
        const purchaseInCZK = ((item.purchase_price||0) + (item.delivery_cost||0)) * item.currency_rate;
        const sellingPrice = item.unit_price * item.quantity;
        
        item.margin = sellingPrice - purchaseInCZK;
        
        if (item.vat_mode === 'marginal') {
            item.vat_amount = item.margin * (21 / 121);
        } else if (item.vat_mode === 'reverse') {
            item.vat_amount = sellingPrice * 0.21;
        } else {
            item.vat_amount = 0;
        }
    }

    function renderItems() {
        const tbody = document.getElementById('itemsBody');
        tbody.innerHTML = saleItems.map((item, idx) => `
            <tr>
                <td>${item.name}</td>
                <td><input type="number" class="form-control" style="width: 80px" value="${item.quantity}" min="1" max="${item.max_quantity}" onchange="updateItemQuantity(${idx}, this.value)"></td>
                <td><input type="number" class="form-control" style="width: 100px" value="${item.unit_price}" step="0.01" onchange="updateItemPrice(${idx}, this.value)"></td>
                <td>${item.vat_mode}</td>
                <td>${item.margin.toFixed(2)}</td>
                <td>${item.vat_amount.toFixed(2)}</td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${idx})">&times;</button></td>
            </tr>
        `).join('');
        updateTotals();
    }

    function updateItemQuantity(idx, val) { saleItems[idx].quantity = parseInt(val); recalculateAll(); }
    function updateItemPrice(idx, val) { saleItems[idx].unit_price = parseFloat(val); recalculateAll(); }
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
        const subtotal = saleItems.reduce((s, i) => s + (i.unit_price * i.quantity), 0);
        const vat = saleItems.reduce((s, i) => s + i.vat_amount, 0);
        document.getElementById('subtotalDisplay').textContent = formatCurrency(subtotal);
        document.getElementById('vatDisplay').textContent = formatCurrency(vat);
        document.getElementById('totalDisplay').textContent = formatCurrency(subtotal);
    }

    document.getElementById('saleForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const data = {
            action: 'update_sale',
            sale_id: document.querySelector('[name="sale_id"]').value,
            client_id: document.getElementById('clientSelect').value,
            sale_date: document.getElementById('saleDate').value,
            invoice_number: document.querySelector('[name="invoice_number"]').value,
            eur_rate: parseFloat(document.getElementById('eurRate').value),
            usd_rate: parseFloat(document.getElementById('usdRate').value),
            items: saleItems
        };

        try {
            const resp = await fetch('../../api/ajax-handlers.php', { method: 'POST', body: JSON.stringify(data) });
            const res = await resp.json();
            if (res.success) window.location.href = 'history.php';
            else alert(res.message);
        } catch (e) { alert('Error'); }
    });

    recalculateAll();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
