<?php
/**
 * J2i Warehouse Management System
 * Accessories Page
 */
require_once __DIR__ . '/../../config/config.php';
$pageTitle = __('accessories');
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Get accessory types
$types = $db->query("SELECT * FROM accessory_types")->fetchAll();

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

// Count total
$total = $db->query("SELECT COUNT(*) FROM accessories")->fetchColumn();
$pagination = getPagination($total, $page, $perPage);

// Get accessories
$lang = $current_lang ?? 'cs';
$sql = "SELECT a.*, t.name_" . $lang . " as type_name,
d.id as device_id, p.name as product_name, b.name as brand_name
FROM accessories a
JOIN accessory_types t ON a.type_id = t.id
LEFT JOIN devices d ON a.device_id = d.id
LEFT JOIN products p ON d.product_id = p.id
LEFT JOIN brands b ON p.brand_id = b.id
ORDER BY a.created_at DESC
LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
$accessories = $db->query($sql)->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("
INSERT INTO accessories (type_id, name, purchase_date, quantity, quantity_available,
purchase_price, purchase_currency, selling_price, repair_comment,
device_id, created_by)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

        $quantity = (int) $_POST['quantity'];

        $stmt->execute([
            $_POST['type_id'],
            $_POST['name'],
            $_POST['purchase_date'],
            $quantity,
            $quantity,
            (float) $_POST['purchase_price'],
            $_POST['purchase_currency'] ?? 'CZK',
            $_POST['selling_price'] ? (float) $_POST['selling_price'] : null,
            $_POST['repair_comment'] ?: null,
            $_POST['device_id'] ?: null,
            $_SESSION['user_id']
        ]);

        setFlashMessage('success', __('success_save'));
        redirect('accessories.php');

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                <line x1="6" y1="1" x2="6" y2="4" />
                <line x1="10" y1="1" x2="10" y2="4" />
                <line x1="14" y1="1" x2="14" y2="4" />
            </svg>
            <?= __('accessories') ?>
            <span class="badge badge-primary" style="margin-left: 0.5rem;">
                <?= $total ?>
            </span>
        </h3>
        <button class="btn btn-primary" onclick="openModal('addAccessoryModal')">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <?= __('add_accessory') ?>
        </button>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($accessories)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                    <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                </svg>
                <h3>
                    <?= __('no_data') ?>
                </h3>
                <p>
                    <?= __('add_accessory') ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>
                                <?= __('date') ?>
                            </th>
                            <th>
                                <?= __('accessory_type') ?>
                            </th>
                            <th>
                                <?= __('notes') ?>
                            </th>
                            <th>
                                <?= __('quantity') ?>
                            </th>
                            <th>
                                <?= __('purchase_price') ?>
                            </th>
                            <th>
                                <?= __('price') ?>
                            </th>
                            <th>
                                <?= __('linked_device') ?>
                            </th>
                            <th>
                                <?= __('status') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accessories as $acc): ?>
                            <tr>
                                <td>
                                    <?= formatDate($acc['purchase_date']) ?>
                                </td>
                                <td><span class="badge badge-info">
                                        <?= e($acc['type_name']) ?>
                                    </span></td>
                                <td>
                                    <?= e($acc['name']) ?>
                                    <?php if ($acc['repair_comment']): ?>
                                        <br><small class="text-muted">
                                            <?= e($acc['repair_comment']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $acc['quantity_available'] ?> /
                                    <?= $acc['quantity'] ?>
                                </td>
                                <td>
                                    <?= formatCurrency($acc['purchase_price'], $acc['purchase_currency']) ?>
                                </td>
                                <td>
                                    <?= $acc['selling_price'] ? formatCurrency($acc['selling_price']) : '-' ?>
                                </td>
                                <td>
                                    <?php if ($acc['device_id']): ?>
                                        <a href="devices.php?id=<?= $acc['device_id'] ?>">
                                            <?= e($acc['brand_name']) ?>
                                            <?= e($acc['product_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span
                                        class="badge badge-<?= $acc['status'] === 'in_stock' ? 'success' : ($acc['status'] === 'sold' ? 'primary' : 'gray') ?>">
                                        <?= __($acc['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Accessory Modal -->
<div class="modal-overlay" id="addAccessoryModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('add_accessory') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('addAccessoryModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('accessory_type') ?>
                        </label>
                        <select name="type_id" id="accessoryType" class="form-control" required>
                            <?php foreach ($types as $type): ?>
                                <option value="<?= $type['id'] ?>">
                                    <?= e(getLocalizedField($type, 'name')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('purchase_date') ?>
                        </label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label required">
                        <?= __('notes') ?>
                    </label>
                    <input type="text" name="name" class="form-control"
                        placeholder="USB-C Cable 1m, iPhone Box 15 Pro..." required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('quantity') ?>
                        </label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('purchase_price') ?>
                        </label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="number" name="purchase_price" class="form-control" step="0.01" min="0" required
                                style="flex: 1;">
                            <select name="purchase_currency" class="form-control" style="width: 80px;">
                                <option value="CZK">CZK</option>
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('price') ?> (CZK)
                        </label>
                        <input type="number" name="selling_price" class="form-control" step="0.01" min="0">
                    </div>
                </div>

                <div class="form-group" id="repairCommentGroup" style="display: none;">
                    <label class="form-label">
                        <?= __('repair_comment') ?>
                    </label>
                    <textarea name="repair_comment" class="form-control" rows="2"
                        placeholder="<?= __('repair_comment') ?>..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <?= __('linked_device') ?>
                    </label>
                    <select name="device_id" class="form-control">
                        <option value="">--
                            <?= __('none') ?> --
                        </option>
                    </select>
                    <small class="form-text">Опционально, если аксессуар связан с устройством</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addAccessoryModal')">
                    <?= __('cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Show repair comment field when type is "Repair"
    document.getElementById('accessoryType').addEventListener('change', function () {
        const repairGroup = document.getElementById('repairCommentGroup');
        // Type ID 6 is "Repair" based on our schema
        if (this.value == '6') {
            repairGroup.style.display = 'block';
        } else {
            repairGroup.style.display = 'none';
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>