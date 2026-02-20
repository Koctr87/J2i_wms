<?php
/**
 * J2i Warehouse Management System
 * Edit Device Page
 */
require_once __DIR__ . '/../../config/config.php';

$db = getDB();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('devices.php');
}

// Fetch device data
$stmt = $db->prepare("SELECT d.*, p.brand_id, p.name as product_name FROM devices d JOIN products p ON d.product_id = p.id WHERE d.id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch();

if (!$device) {
    die("Device not found.");
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_device'])) {
    try {
        // Check if device is referenced in sale_items
        $checkSales = $db->prepare("SELECT COUNT(*) FROM sale_items WHERE device_id = ?");
        $checkSales->execute([$id]);
        if ($checkSales->fetchColumn() > 0) {
            throw new Exception('Cannot delete: device is referenced in sales. Change status to "sold" instead.');
        }

        $db->prepare("DELETE FROM devices WHERE id = ?")->execute([$id]);
        logActivity('device_deleted', 'device', $id);
        setFlashMessage('success', __('success_delete') ?: 'Device deleted successfully');
        redirect('devices.php');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle form submission (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_device'])) {
    try {
        $db->beginTransaction();

        $isSplitMode = isset($_POST['split_mode']) && $_POST['split_mode'] == '1';

        if ($isSplitMode) {
            $splitItems = json_decode($_POST['split_items_json'] ?? '[]', true);
            if (empty($splitItems)) {
                throw new Exception("No items provided in split mode.");
            }

            // Identify the "Locked" group (sold/reserved units that stay in the original record)
            $soldCount = (int)$device['quantity'] - (int)$device['quantity_available'];
            
            // The original record will be updated to represent the "Locked" group + potentially the FIRST editable unit
            $firstItem = $splitItems[0];
            
            // Actually, to be safe: 
            // 1. If there are sold units ($soldCount > 0), the original record MUST keep them.
            // 2. The editable rows from the UI are all NEW individual records, UNLESS we can reuse the original.
            
            // Let's refine:
            // Original Record ($id) will become a record for the $soldCount units.
            // If $soldCount is 0, then Original Record ($id) becomes the first individual unit from $splitItems.
            
            if ($soldCount > 0) {
                // Keep original record as the "Sold/Reserved" batch
                $stmt = $db->prepare("UPDATE devices SET quantity = ?, quantity_available = 0 WHERE id = ?");
                $stmt->execute([$soldCount, $id]);
                
                // Now insert ALL items from splitItems as new records
                $insertStmt = $db->prepare("
                    INSERT INTO devices (
                        product_id, memory_id, color_id, `condition`, grading, 
                        purchase_date, quantity, quantity_available, invoice_in, 
                        purchase_price, purchase_currency, delivery_cost, imei, 
                        vat_mode, notes, status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, 'in_stock', ?)
                ");

                foreach ($splitItems as $item) {
                    $insertStmt->execute([
                        $device['product_id'],
                        $item['memory_id'] ?: null,
                        $item['color_id'] ?: null,
                        $device['condition'],
                        $item['grading'] ?: 'A',
                        $device['purchase_date'],
                        $device['invoice_in'],
                        (float)$item['purchase_price'],
                        $device['purchase_currency'],
                        (float)$item['delivery_cost'],
                        $item['imei'] ?: null,
                        $device['vat_mode'],
                        $item['notes'] ?: $device['notes'],
                        $_SESSION['user_id'] ?? null
                    ]);
                }
            } else {
                // No sold units. First item updates original record. Others are new.
                $updateStmt = $db->prepare("
                    UPDATE devices SET 
                        memory_id = ?, color_id = ?, grading = ?, 
                        quantity = 1, quantity_available = 1, 
                        purchase_price = ?, delivery_cost = ?, imei = ?, 
                        notes = ?, status = 'in_stock'
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $firstItem['memory_id'] ?: null,
                    $firstItem['color_id'] ?: null,
                    $firstItem['grading'] ?: 'A',
                    (float)$firstItem['purchase_price'],
                    (float)$firstItem['delivery_cost'],
                    $firstItem['imei'] ?: null,
                    $firstItem['notes'] ?: $device['notes'],
                    $id
                ]);

                $insertStmt = $db->prepare("
                    INSERT INTO devices (
                        product_id, memory_id, color_id, `condition`, grading, 
                        purchase_date, quantity, quantity_available, invoice_in, 
                        purchase_price, purchase_currency, delivery_cost, imei, 
                        vat_mode, notes, status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, 'in_stock', ?)
                ");

                for ($i = 1; $i < count($splitItems); $i++) {
                    $item = $splitItems[$i];
                    $insertStmt->execute([
                        $device['product_id'],
                        $item['memory_id'] ?: null,
                        $item['color_id'] ?: null,
                        $device['condition'],
                        $item['grading'] ?: 'A',
                        $device['purchase_date'],
                        $device['invoice_in'],
                        (float)$item['purchase_price'],
                        $device['purchase_currency'],
                        (float)$item['delivery_cost'],
                        $item['imei'] ?: null,
                        $device['vat_mode'],
                        $item['notes'] ?: $device['notes'],
                        $_SESSION['user_id'] ?? null
                    ]);
                }
            }
        } else {
            // Standard update logic
            $required = ['product_id', 'condition', 'purchase_date', 'quantity', 'purchase_price', 'vat_mode'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception(__('error_required'));
                }
            }

            $stmt = $db->prepare("
                UPDATE devices SET 
                    product_id = ?, 
                    memory_id = ?, 
                    color_id = ?, 
                    `condition` = ?,
                    grading = ?, 
                    purchase_date = ?, 
                    quantity = ?, 
                    quantity_available = ?,
                    invoice_in = ?, 
                    purchase_price = ?, 
                    purchase_currency = ?, 
                    delivery_cost = ?,
                    imei = ?, 
                    vat_mode = ?, 
                    notes = ?,
                    status = ?
                WHERE id = ?
            ");

            $quantity = (int) $_POST['quantity'];
            $quantityAvailable = (int) $_POST['quantity_available'];
            $status = $_POST['status'] ?? 'in_stock';

            $stmt->execute([
                $_POST['product_id'],
                $_POST['memory_id'] ?: null,
                $_POST['color_id'] ?: null,
                $_POST['condition'],
                $_POST['grading'] ?? 'A',
                $_POST['purchase_date'],
                $quantity,
                $quantityAvailable,
                $_POST['invoice_in'] ?: null,
                (float) $_POST['purchase_price'],
                $_POST['purchase_currency'] ?? 'EUR',
                (float) ($_POST['delivery_cost'] ?? 0),
                $_POST['imei'] ?: null,
                $_POST['vat_mode'],
                $_POST['notes'] ?: null,
                $status,
                $id
            ]);
        }

        $db->commit();
        logActivity('device_updated', 'device', $id);
        setFlashMessage('success', __('success_save'));
        redirect('devices.php');

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// Get data for dropdowns
$brands = $db->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1")->fetchAll();
$memories = $db->query("SELECT * FROM memory_options ORDER BY sort_order")->fetchAll();
$colors = $db->query("SELECT * FROM color_options ORDER BY sort_order, name_en")->fetchAll();

// Get products for the current brand
$products = [];
if ($device['brand_id']) {
    $stmt = $db->prepare("SELECT id, name FROM products WHERE brand_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$device['brand_id']]);
    $products = $stmt->fetchAll();
}

$pageTitle = __('edit') . ' ' . __('device');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
            </svg>
            <?= __('edit') ?> <?= __('device') ?> #<?= $id ?>
        </h3>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="deviceForm">
            <!-- Basic Info -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">📱
                    <?= __('model') ?>
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">
                            Состояние
                        </label>
                        <select name="condition" class="form-control" required>
                            <option value="used" <?= $device['condition'] == 'used' ? 'selected' : '' ?>>Used (Б/У)</option>
                            <option value="new" <?= $device['condition'] == 'new' ? 'selected' : '' ?>>New (Новое)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('brand') ?>
                        </label>
                        <select name="brand_id" id="brandSelect" class="form-control" required>
                            <option value="">-- <?= __('brand') ?> --</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>" <?= $brand['id'] == $device['brand_id'] ? 'selected' : '' ?>>
                                    <?= e($brand['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('model') ?>
                        </label>
                        <select name="product_id" id="productSelect" class="form-control" required>
                            <option value="">-- <?= __('model') ?> --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>" <?= $product['id'] == $device['product_id'] ? 'selected' : '' ?>>
                                    <?= e($product['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('memory') ?>
                        </label>
                        <select name="memory_id" class="form-control">
                            <option value="">-- <?= __('memory') ?> --</option>
                            <?php foreach ($memories as $mem): ?>
                                <option value="<?= $mem['id'] ?>" <?= $mem['id'] == $device['memory_id'] ? 'selected' : '' ?>>
                                    <?= e($mem['size']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('color') ?>
                        </label>
                        <select name="color_id" class="form-control">
                            <option value="">-- <?= __('color') ?> --</option>
                            <?php foreach ($colors as $color): ?>
                                <option value="<?= $color['id'] ?>" <?= $color['id'] == $device['color_id'] ? 'selected' : '' ?>>
                                    <?= e(getLocalizedField($color, 'name')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Purchase Info -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">📦
                    <?= __('purchase_date') ?>
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('purchase_date') ?>
                        </label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= e($device['purchase_date']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('quantity') ?> (Total)
                        </label>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="number" name="quantity" id="quantityInput" class="form-control" value="<?= e($device['quantity']) ?>" min="1" required style="flex: 1;">
                            <?php if ($device['quantity'] > 1): ?>
                                <button type="button" id="splitBtn" class="btn btn-secondary btn-sm" onclick="toggleSplitMode()">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 21L3 13m0 0l8-8m-8 8h18" stroke-dasharray="2 2"/>
                                        <path d="M13 3l8 8m0 0l-8 8m8-8H3" />
                                    </svg>
                                    Разделить (Split)
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('quantity_available') ?>
                        </label>
                        <input type="number" name="quantity_available" class="form-control" value="<?= e($device['quantity_available']) ?>" min="0" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Status
                        </label>
                        <select name="status" class="form-control">
                            <option value="in_stock" <?= $device['status'] == 'in_stock' ? 'selected' : '' ?>><?= __('in_stock') ?></option>
                            <option value="sold" <?= $device['status'] == 'sold' ? 'selected' : '' ?>><?= __('sold') ?></option>
                            <option value="reserved" <?= $device['status'] == 'reserved' ? 'selected' : '' ?>><?= __('reserved') ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('invoice_in') ?>
                        </label>
                        <input type="text" name="invoice_in" class="form-control" value="<?= e($device['invoice_in']) ?>" placeholder="INV-2024-001">
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">💰
                    <?= __('price') ?> (Закупка)
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('purchase_price') ?>
                        </label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="number" name="purchase_price" class="form-control" step="0.01" min="0" value="<?= e($device['purchase_price']) ?>" required style="flex: 1;">
                            <select name="purchase_currency" class="form-control" style="width: 100px;">
                                <option value="EUR" <?= $device['purchase_currency'] == 'EUR' ? 'selected' : '' ?>>EUR €</option>
                                <option value="USD" <?= $device['purchase_currency'] == 'USD' ? 'selected' : '' ?>>USD $</option>
                                <option value="CZK" <?= $device['purchase_currency'] == 'CZK' ? 'selected' : '' ?>>CZK Kč</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('delivery_cost') ?>
                        </label>
                        <input type="number" name="delivery_cost" class="form-control" step="0.01" min="0" value="<?= e($device['delivery_cost']) ?>">
                    </div>
                </div>
            </div>

            <!-- Additional -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">📋
                    <?= __('notes') ?>
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('imei') ?>
                        </label>
                        <input type="text" name="imei" id="imeiInput" class="form-control" value="<?= e($device['imei']) ?>" maxlength="20">
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('vat_mode') ?>
                        </label>
                        <select name="vat_mode" class="form-control" required>
                            <option value="marginal" <?= $device['vat_mode'] == 'marginal' ? 'selected' : '' ?>>
                                <?= __('vat_marginal') ?>
                            </option>
                            <option value="reverse" <?= $device['vat_mode'] == 'reverse' ? 'selected' : '' ?>>
                                <?= __('vat_reverse') ?>
                            </option>
                            <option value="no" <?= $device['vat_mode'] == 'no' ? 'selected' : '' ?>>
                                <?= __('vat_no') ?>
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            Grading
                        </label>
                        <select name="grading" class="form-control" required>
                            <option value="A" <?= $device['grading'] == 'A' ? 'selected' : '' ?>>Grade A</option>
                            <option value="B" <?= $device['grading'] == 'B' ? 'selected' : '' ?>>Grade B</option>
                            <option value="C" <?= $device['grading'] == 'C' ? 'selected' : '' ?>>Grade C</option>
                            <option value="Q" <?= $device['grading'] == 'Q' ? 'selected' : '' ?>>Grade Q</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <?= __('notes') ?>
                    </label>
                    <textarea name="notes" class="form-control" rows="3"><?= e($device['notes']) ?></textarea>
                </div>
            </div>

            <!-- Split Section (Hidden by default) -->
            <div id="splitSection" style="display: none; margin-bottom: 2rem; background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg); border: 2px dashed var(--primary-light);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h4 style="margin: 0; color: var(--primary);">🧩 Разделение на единицы (Individual Items)</h4>
                    <button type="button" class="btn btn-outline btn-sm" onclick="cancelSplitMode()">Отмена</button>
                </div>
                
                <input type="hidden" name="split_mode" id="splitModeInput" value="0">
                <input type="hidden" name="split_items_json" id="splitItemsJson" value="">

                <div class="table-container">
                    <table class="table" style="background: white;">
                        <thead>
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th><?= __('imei') ?></th>
                                <th style="width: 150px;"><?= __('color') ?></th>
                                <th style="width: 120px;"><?= __('memory') ?></th>
                                <th style="width: 100px;"><?= __('grade') ?></th>
                                <th style="width: 120px;"><?= __('price') ?></th>
                                <th style="width: 100px;"><?= __('status') ?></th>
                            </tr>
                        </thead>
                        <tbody id="splitItemsBody">
                            <!-- Rows generated by JS -->
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 1rem; color: var(--gray-500); font-size: 0.9rem;">
                    💡 Заблокированные строки (серые) — это уже проданные или зарезервированные устройства. Они останутся в текущей записи.
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-danger" onclick="openModal('deleteDeviceModal')" style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        <line x1="10" y1="11" x2="10" y2="17" />
                        <line x1="14" y1="11" x2="14" y2="17" />
                    </svg>
                    <?= __('delete') ?>
                </button>
                <div style="display: flex; gap: 1rem;">
                    <a href="devices.php" class="btn btn-secondary">
                        <?= __('cancel') ?>
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        <?= __('save') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Load products when brand changes
    document.getElementById('brandSelect').addEventListener('change', function () {
        const brandId = this.value;
        const productSelect = document.getElementById('productSelect');

        productSelect.innerHTML = '<option value="">-- <?= __('model') ?> --</option>';

        if (!brandId) return;

        fetch('<?= APP_URL ?>/api/ajax-handlers.php?action=get_products&brand_id=' + brandId)
            .then(r => r.json())
            .then(data => {
                data.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    productSelect.appendChild(opt);
                });
            });
    });

    // Data for split mode
    const memoriesData = <?= json_encode($memories) ?>;
    const colorsData = <?= json_encode($colors) ?>;
    const deviceData = <?= json_encode($device) ?>;

    function toggleSplitMode() {
        const qty = parseInt(quantityInput.value);
        const avail = parseInt(document.querySelector('[name="quantity_available"]').value);
        const soldCount = qty - avail;

        document.getElementById('splitSection').style.display = 'block';
        document.getElementById('splitModeInput').value = '1';
        document.getElementById('splitBtn').style.display = 'none';

        const body = document.getElementById('splitItemsBody');
        body.innerHTML = '';

        for (let i = 0; i < qty; i++) {
            const isSold = i < soldCount;
            const row = document.createElement('tr');
            if (isSold) row.style.opacity = '0.6';
            
            row.innerHTML = `
                <td>${i + 1}</td>
                <td>
                    <input type="text" class="form-control imei-split" value="${isSold ? (deviceData.imei || '-') : ''}" 
                        ${isSold ? 'disabled' : ''} placeholder="Input IMEI">
                </td>
                <td>
                    <select class="form-control color-split" ${isSold ? 'disabled' : ''}>
                        <option value="">-- Color --</option>
                        ${colorsData.map(c => `<option value="${c.id}" ${c.id == deviceData.color_id ? 'selected' : ''}>${c.name_en}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <select class="form-control memory-split" ${isSold ? 'disabled' : ''}>
                        <option value="">-- Mem --</option>
                        ${memoriesData.map(m => `<option value="${m.id}" ${m.id == deviceData.memory_id ? 'selected' : ''}>${m.size}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <select class="form-control grading-split" ${isSold ? 'disabled' : ''}>
                        <option value="A" ${deviceData.grading == 'A' ? 'selected' : ''}>Grade A</option>
                        <option value="B" ${deviceData.grading == 'B' ? 'selected' : ''}>Grade B</option>
                        <option value="C" ${deviceData.grading == 'C' ? 'selected' : ''}>Grade C</option>
                        <option value="Q" ${deviceData.grading == 'Q' ? 'selected' : ''}>Grade Q</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control price-split" step="0.01" value="${deviceData.purchase_price}" ${isSold ? 'disabled' : ''}>
                </td>
                <td>
                    <span class="badge ${isSold ? 'badge-gray' : 'badge-success'}">
                        ${isSold ? 'Locked' : 'Available'}
                    </span>
                    <input type="hidden" class="is-locked" value="${isSold ? '1' : '0'}">
                </td>
            `;
            body.appendChild(row);
        }
        
        // Hide some basic info to draw focus
        document.querySelectorAll('.form-row').forEach(row => {
            if (!row.closest('#splitSection')) row.style.opacity = '0.4';
        });
    }

    function cancelSplitMode() {
        document.getElementById('splitSection').style.display = 'none';
        document.getElementById('splitModeInput').value = '0';
        document.getElementById('splitBtn').style.display = 'flex';
        document.querySelectorAll('.form-row').forEach(row => row.style.opacity = '1');
    }

    // Submit handler intercept
    document.getElementById('deviceForm').onsubmit = function(e) {
        if (document.getElementById('splitModeInput').value === '1') {
            const items = [];
            const rows = document.querySelectorAll('#splitItemsBody tr');
            rows.forEach(row => {
                if (row.querySelector('.is-locked').value === '0') {
                    items.push({
                        imei: row.querySelector('.imei-split').value,
                        color_id: row.querySelector('.color-split').value,
                        memory_id: row.querySelector('.memory-split').value,
                        grading: row.querySelector('.grading-split').value,
                        purchase_price: row.querySelector('.price-split').value,
                        delivery_cost: (parseFloat(deviceData.delivery_cost) / parseInt(quantityInput.value)).toFixed(2),
                        notes: deviceData.notes
                    });
                }
            });
            document.getElementById('splitItemsJson').value = JSON.stringify(items);
        }
        return true;
    };

    // IMEI logic based on quantity
    const quantityInput = document.getElementById('quantityInput');
    const imeiInput = document.getElementById('imeiInput');

    function updateIMEIField() {
        if (document.getElementById('splitModeInput').value === '1') return;
        const qty = parseInt(quantityInput.value) || 0;
        if (qty > 1) {
            imeiInput.readOnly = true;
            imeiInput.style.backgroundColor = '#f3f4f6';
            if (!imeiInput.value || !imeiInput.value.startsWith('BATCH-')) {
                const batchId = 'BATCH-' + Math.random().toString(36).substr(2, 6).toUpperCase() + '-' + Date.now().toString().substr(-4);
                imeiInput.value = batchId;
            }
        } else {
            imeiInput.readOnly = false;
            imeiInput.style.backgroundColor = '';
            // If it was a batch and now it's 1, let user enter real IMEI
            if (imeiInput.value.startsWith('BATCH-')) {
                imeiInput.value = '';
            }
        }
    }

    quantityInput.addEventListener('input', updateIMEIField);
    updateIMEIField(); // Initial check
</script>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteDeviceModal">
    <div class="modal" style="max-width: 450px;">
        <div class="modal-header" style="background: var(--danger); color: white;">
            <h3 style="color: white;">⚠️ <?= __('confirm_delete') ?: 'Confirm Delete' ?></h3>
            <button class="modal-close" onclick="closeModal('deleteDeviceModal')" style="color: white;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1.5rem; text-align: center;">
            <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">
                <?= __('delete_device_confirm') ?: 'Are you sure you want to delete this device?' ?>
            </p>
            <p style="color: var(--gray-500); font-size: 0.9rem;">
                <strong><?= e($device['product_name'] ?? '') ?></strong> #<?= $id ?>
            </p>
            <p style="color: var(--danger); font-size: 0.85rem;">
                <?= __('action_irreversible') ?: 'This action cannot be undone.' ?>
            </p>
        </div>
        <div class="modal-footer" style="justify-content: center; gap: 1rem;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('deleteDeviceModal')">
                <?= __('cancel') ?>
            </button>
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="delete_device" value="1">
                <button type="submit" class="btn btn-danger" style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                    </svg>
                    <?= __('delete') ?>
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
