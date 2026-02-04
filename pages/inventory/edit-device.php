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
$stmt = $db->prepare("SELECT d.*, p.brand_id FROM devices d JOIN products p ON d.product_id = p.id WHERE d.id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch();

if (!$device) {
    die("Device not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required = ['product_id', 'purchase_date', 'quantity', 'purchase_price', 'vat_mode'];
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
                grading = ?, 
                purchase_date = ?, 
                quantity = ?, 
                quantity_available = ?,
                invoice_in = ?, 
                invoice_out = ?, 
                purchase_price = ?, 
                purchase_currency = ?, 
                delivery_cost = ?,
                wholesale_price = ?, 
                retail_price = ?, 
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
            $_POST['grading'] ?? 'A',
            $_POST['purchase_date'],
            $quantity,
            $quantityAvailable,
            $_POST['invoice_in'] ?: null,
            $_POST['invoice_out'] ?: null,
            (float) $_POST['purchase_price'],
            $_POST['purchase_currency'] ?? 'EUR',
            (float) ($_POST['delivery_cost'] ?? 0),
            $_POST['wholesale_price'] ? (float) $_POST['wholesale_price'] : null,
            $_POST['retail_price'] ? (float) $_POST['retail_price'] : null,
            $_POST['imei'] ?: null,
            $_POST['vat_mode'],
            $_POST['notes'] ?: null,
            $status,
            $id
        ]);

        logActivity('device_updated', 'device', $id);

        setFlashMessage('success', __('success_save'));
        redirect('devices.php');

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get data for dropdowns
$brands = $db->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1")->fetchAll();
$memories = $db->query("SELECT * FROM memory_options ORDER BY sort_order")->fetchAll();
$colors = $db->query("SELECT * FROM color_options")->fetchAll();

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
                        <input type="number" name="quantity" class="form-control" value="<?= e($device['quantity']) ?>" min="1" required>
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

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('invoice_out') ?>
                        </label>
                        <input type="text" name="invoice_out" class="form-control" value="<?= e($device['invoice_out']) ?>" placeholder="OUT-2024-001">
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">💰
                    <?= __('price') ?>
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

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('wholesale_price') ?> (CZK)
                        </label>
                        <input type="number" name="wholesale_price" class="form-control" step="0.01" min="0" value="<?= e($device['wholesale_price']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('retail_price') ?> (CZK)
                        </label>
                        <input type="number" name="retail_price" class="form-control" step="0.01" min="0" value="<?= e($device['retail_price']) ?>">
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
                        <input type="text" name="imei" class="form-control" value="<?= e($device['imei']) ?>" maxlength="20">
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

            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
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
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
