<?php
/**
 * J2i Warehouse Management System
 * Add Device Page
 */
require_once __DIR__ . '/../../config/config.php';

$db = getDB();

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
            INSERT INTO devices (
                product_id, memory_id, color_id, grading, purchase_date, quantity, quantity_available,
                invoice_in, invoice_out, purchase_price, purchase_currency, delivery_cost,
                wholesale_price, retail_price, imei, vat_mode, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $quantity = (int) $_POST['quantity'];

        $stmt->execute([
            $_POST['product_id'],
            $_POST['memory_id'] ?: null,
            $_POST['color_id'] ?: null,
            $_POST['grading'] ?? 'A',
            $_POST['purchase_date'],
            $quantity,
            $quantity,
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
            $_SESSION['user_id']
        ]);

        $deviceId = $db->lastInsertId();
        logActivity('device_created', 'device', $deviceId);

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

$pageTitle = __('add_device');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <?= __('add_device') ?>
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
                            <option value="">--
                                <?= __('brand') ?> --
                            </option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>">
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
                            <option value="">--
                                <?= __('model') ?> --
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('memory') ?>
                        </label>
                        <select name="memory_id" class="form-control">
                            <option value="">--
                                <?= __('memory') ?> --
                            </option>
                            <?php foreach ($memories as $mem): ?>
                                <option value="<?= $mem['id'] ?>">
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
                            <option value="">--
                                <?= __('color') ?> --
                            </option>
                            <?php foreach ($colors as $color): ?>
                                <option value="<?= $color['id'] ?>">
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
                        <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('quantity') ?>
                        </label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('invoice_in') ?>
                        </label>
                        <input type="text" name="invoice_in" class="form-control" placeholder="INV-2024-001">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('invoice_out') ?>
                        </label>
                        <input type="text" name="invoice_out" class="form-control" placeholder="OUT-2024-001">
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
                            <input type="number" name="purchase_price" class="form-control" step="0.01" min="0" required
                                style="flex: 1;">
                            <select name="purchase_currency" class="form-control" style="width: 100px;">
                                <option value="EUR">EUR €</option>
                                <option value="USD">USD $</option>
                                <option value="CZK">CZK Kč</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('delivery_cost') ?>
                        </label>
                        <input type="number" name="delivery_cost" class="form-control" step="0.01" min="0" value="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('wholesale_price') ?> (CZK)
                        </label>
                        <input type="number" name="wholesale_price" class="form-control" step="0.01" min="0">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('retail_price') ?> (CZK)
                        </label>
                        <input type="number" name="retail_price" class="form-control" step="0.01" min="0">
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
                        <input type="text" name="imei" class="form-control"
                            placeholder="IMEI (<?= __('quantity') ?> = 1)" maxlength="20">
                        <small class="form-text">
                            <?= __('quantity') ?> > 1 = без IMEI
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('vat_mode') ?>
                        </label>
                        <select name="vat_mode" class="form-control" required>
                            <option value="marginal">
                                <?= __('vat_marginal') ?>
                            </option>
                            <option value="reverse">
                                <?= __('vat_reverse') ?>
                            </option>
                            <option value="no">
                                <?= __('vat_no') ?>
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            Grading
                        </label>
                        <select name="grading" class="form-control" required>
                            <option value="A">Grade A</option>
                            <option value="B">Grade B</option>
                            <option value="C">Grade C</option>
                            <option value="Q">Grade Q</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <?= __('notes') ?>
                    </label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
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

<!-- Add New Product Modal -->
<div class="modal-overlay" id="addProductModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('add') ?>
                <?= __('model') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('addProductModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="newProductForm">
                <input type="hidden" name="brand_id" id="modalBrandId">
                <div class="form-group">
                    <label class="form-label required">
                        <?= __('model') ?>
                    </label>
                    <input type="text" name="product_name" id="newProductName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">
                        <?= __('categories') ?>
                    </label>
                    <select name="category_id" id="newProductCategory" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>">
                                <?= e(getLocalizedField($cat, 'name')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')">
                <?= __('cancel') ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="saveNewProduct()">
                <?= __('save') ?>
            </button>
        </div>
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

                // Add "Create new" option
                const newOpt = document.createElement('option');
                newOpt.value = 'new';
                newOpt.textContent = '+ <?= __('add') ?> <?= __('model') ?>...';
                newOpt.style.fontWeight = 'bold';
                productSelect.appendChild(newOpt);
            });
    });

    // Handle "Create new" product
    document.getElementById('productSelect').addEventListener('change', function () {
        if (this.value === 'new') {
            document.getElementById('modalBrandId').value = document.getElementById('brandSelect').value;
            openModal('addProductModal');
            this.value = '';
        }
    });

    // Save new product
    function saveNewProduct() {
        const name = document.getElementById('newProductName').value;
        const brandId = document.getElementById('modalBrandId').value;
        const categoryId = document.getElementById('newProductCategory').value;

        if (!name || !brandId || !categoryId) {
            showToast('<?= __('error_required') ?>', 'error');
            return;
        }

        fetch('<?= APP_URL ?>/api/ajax-handlers.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create_product',
                name: name,
                brand_id: brandId,
                category_id: categoryId
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeModal('addProductModal');
                    document.getElementById('newProductName').value = '';

                    // Add to select and select it
                    const productSelect = document.getElementById('productSelect');
                    const opt = document.createElement('option');
                    opt.value = data.id;
                    opt.textContent = name;
                    opt.selected = true;
                    productSelect.insertBefore(opt, productSelect.lastChild);

                    showToast('<?= __('success_save') ?>', 'success');
                } else {
                    showToast(data.message || '<?= __('error_save') ?>', 'error');
                }
            });
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>