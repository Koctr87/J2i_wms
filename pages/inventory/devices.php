<?php
/**
 * J2i Warehouse Management System
 * Devices List Page
 */
require_once __DIR__ . '/../../config/config.php';

try {
    $db = getDB();

    // Language switch handler
    if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGUAGES)) {
        $_SESSION['language'] = $_GET['lang'];
        redirect('devices.php');
    }

    // Pagination
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;

    // Filters
    $search = $_GET['search'] ?? '';
    $brandFilter = $_GET['brand'] ?? '';
    $modelFilter = $_GET['model'] ?? '';
    $memoryFilter = $_GET['memory'] ?? '';
    $colorFilter = $_GET['color'] ?? '';
    $statusFilter = $_GET['status'] ?? 'in_stock';
    $gradingFilter = $_GET['grading'] ?? '';
    $conditionFilter = $_GET['condition'] ?? '';
    $supplierFilter = $_GET['supplier'] ?? '';

    // Build query
    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(p.name LIKE ? OR b.name LIKE ? OR d.imei LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($brandFilter) {
        $where[] = "b.id = ?";
        $params[] = $brandFilter;
    }

    if ($modelFilter) {
        $where[] = "p.id = ?";
        $params[] = $modelFilter;
    }

    if ($memoryFilter) {
        $where[] = "d.memory_id = ?";
        $params[] = $memoryFilter;
    }

    if ($colorFilter) {
        $where[] = "d.color_id = ?";
        $params[] = $colorFilter;
    }

    if ($statusFilter) {
        if ($statusFilter === 'repaired') {
            $where[] = "d.repair_cost > 0";
        } else {
            $where[] = "d.status = ?";
            $params[] = $statusFilter;
        }
    }

    if ($gradingFilter) {
        $where[] = "d.grading = ?";
        $params[] = $gradingFilter;
    }

    if ($conditionFilter) {
        $where[] = "d.condition = ?";
        $params[] = $conditionFilter;
    }

    if ($supplierFilter) {
        $where[] = "pur.supplier_id = ?";
        $params[] = $supplierFilter;
    }

    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

    // Count total groups
    $countSql = "SELECT COUNT(*) FROM (
                    SELECT 1 FROM devices d 
                    JOIN products p ON d.product_id = p.id 
                    JOIN brands b ON p.brand_id = b.id 
                    LEFT JOIN purchases pur ON d.purchase_id = pur.id
                    $whereClause
                    GROUP BY d.product_id, d.memory_id, d.color_id, d.condition, d.grading, d.status, d.vat_mode
                 ) as sub";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    $pagination = getPagination($total, $page, $perPage);

    // Get device groups
    $lang = $current_lang ?? 'cs'; // fallback
    $sql = "SELECT d.product_id, d.memory_id, d.color_id, d.condition, d.grading, d.status, d.vat_mode,
                   p.name as product_name, b.name as brand_name,
                   m.size as memory, c.name_" . $lang . " as color,
                   cat.name_" . $lang . " as category,
                   COUNT(d.id) as total_qty,
                   SUM(d.quantity_available) as avail_qty,
                   MIN(COALESCE(d.purchase_price_czk, 0) + COALESCE(d.delivery_cost_czk, 0) + COALESCE(d.repair_cost_czk, 0)) as min_price,
                   MAX(COALESCE(d.purchase_price_czk, 0) + COALESCE(d.delivery_cost_czk, 0) + COALESCE(d.repair_cost_czk, 0)) as max_price,
                   MAX(d.created_at) as last_created,
                   MAX(pur.invoice_number) as last_invoice,
                   MAX(sup.company_name) as supplier_name, -- Just show one supplier or 'Mixed' logic
                   GROUP_CONCAT(DISTINCT sup.company_name SEPARATOR ', ') as suppliers_list
            FROM devices d
            JOIN products p ON d.product_id = p.id
            JOIN brands b ON p.brand_id = b.id
            JOIN categories cat ON p.category_id = cat.id
            LEFT JOIN purchases pur ON d.purchase_id = pur.id
            LEFT JOIN suppliers sup ON pur.supplier_id = sup.id
            LEFT JOIN memory_options m ON d.memory_id = m.id
            LEFT JOIN color_options c ON d.color_id = c.id
            $whereClause
            GROUP BY d.product_id, d.memory_id, d.color_id, d.condition, d.grading, d.status, d.vat_mode
            ORDER BY last_created DESC
            LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $devices = $stmt->fetchAll();

    // Get data for filters
    $brands = $db->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();
    $memories = $db->query("SELECT * FROM memory_options ORDER BY sort_order")->fetchAll();
    $colors = $db->query("SELECT * FROM color_options ORDER BY sort_order, name_en")->fetchAll();
    $suppliers = $db->query("SELECT id, company_name FROM suppliers ORDER BY company_name")->fetchAll();

    // Get products for the selected brand (for model filter)
    $filterProducts = [];
    if ($brandFilter) {
        $pStmt = $db->prepare("SELECT id, name FROM products WHERE brand_id = ? AND is_active = 1 ORDER BY name");
        $pStmt->execute([$brandFilter]);
        $filterProducts = $pStmt->fetchAll();
    }

} catch (Throwable $e) {
    die("<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>");
}

$pageTitle = __('devices');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
                <line x1="12" y1="18" x2="12.01" y2="18" />
            </svg>
            <?= __('device_list') ?>
            <span class="badge badge-primary" style="margin-left: 0.5rem;">
                <?= $total ?>
            </span>
        </h3>
        <a href="add-device.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <?= __('add_device') ?>
        </a>
    </div>

    <!-- Filters -->
    <div style="padding: 1.25rem 1.5rem; background: var(--gray-50); border-bottom: 1px solid var(--gray-200);">
        <form method="GET" id="filterForm">
            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; align-items: flex-end;">
                <!-- Search (Spans 2 columns if possible) -->
                <div class="form-group" style="margin-bottom: 0; grid-column: span 2;">
                    <label class="form-label text-muted small"
                        style="margin-bottom: 0.25rem; font-weight: 600;"><?= __('search') ?></label>
                    <div style="position: relative;">
                        <span
                            style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--gray-400);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                        </span>
                        <input type="text" name="search" class="form-control" style="padding-left: 2.25rem;"
                            placeholder="<?= __('search') ?> IMEI, Model..." value="<?= e($search) ?>">
                    </div>
                </div>

                <!-- Brand -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label text-muted small"
                        style="margin-bottom: 0.25rem; font-weight: 600;"><?= __('brand') ?></label>
                    <select name="brand" id="filterBrand" class="form-control"
                        onchange="onBrandFilterChange(this.value)">
                        <option value=""><?= __('all') ?> <?= __('brands') ?></option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= $brand['id'] ?>" <?= $brandFilter == $brand['id'] ? 'selected' : '' ?>>
                                <?= e($brand['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Model -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label text-muted small"
                        style="margin-bottom: 0.25rem; font-weight: 600;"><?= __('model') ?></label>
                    <select name="model" id="filterModel" class="form-control">
                        <option value=""><?= __('all') ?> <?= __('model') ?></option>
                        <?php foreach ($filterProducts as $fp): ?>
                            <option value="<?= $fp['id'] ?>" <?= $modelFilter == $fp['id'] ? 'selected' : '' ?>>
                                <?= e($fp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Supplier -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label text-muted small"
                        style="margin-bottom: 0.25rem; font-weight: 600;"><?= __('supplier') ?></label>
                    <select name="supplier" class="form-control">
                        <option value=""><?= __('all') ?></option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= $supplierFilter == $sup['id'] ? 'selected' : '' ?>>
                                <?= e($sup['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Memory -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label text-muted small"
                        style="margin-bottom: 0.25rem; font-weight: 600;"><?= __('memory') ?></label>
                    <select name="memory" class="form-control">
                        <option value=""><?= __('all') ?></option>
                        <?php foreach ($memories as $mem): ?>
                            <option value="<?= $mem['id'] ?>" <?= $memoryFilter == $mem['id'] ? 'selected' : '' ?>>
                                <?= e($mem['size']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Color -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label text-muted small"
                        style="margin-bottom: 0.25rem; font-weight: 600;"><?= __('color') ?></label>
                    <select name="color" class="form-control">
                        <option value=""><?= __('all') ?></option>
                        <?php foreach ($colors as $clr): ?>
                            <option value="<?= $clr['id'] ?>" <?= $colorFilter == $clr['id'] ? 'selected' : '' ?>>
                                <?= e(getLocalizedField($clr, 'name')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Condition -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label text-muted small"
                        style="margin-bottom: 0.25rem; font-weight: 600;"><?= __('condition') ?></label>
                    <select name="condition" class="form-control">
                        <option value=""><?= __('all') ?></option>
                        <option value="new" <?= $conditionFilter === 'new' ? 'selected' : '' ?>><?= __('condition_new') ?>
                        </option>
                        <option value="used" <?= $conditionFilter === 'used' ? 'selected' : '' ?>>
                            <?= __('condition_used') ?>
                        </option>
                    </select>
                </div>

                <!-- Status -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label text-muted small"
                        style="margin-bottom: 0.25rem; font-weight: 600;"><?= __('status') ?></label>
                    <select name="status" class="form-control">
                        <option value="in_stock" <?= $statusFilter === 'in_stock' ? 'selected' : '' ?>>
                            <?= __('in_stock') ?>
                        </option>
                        <option value="sold" <?= $statusFilter === 'sold' ? 'selected' : '' ?>><?= __('sold') ?></option>
                        <option value="reserved" <?= $statusFilter === 'reserved' ? 'selected' : '' ?>>
                            <?= __('reserved') ?>
                        </option>
                        <option value="returned" <?= $statusFilter === 'returned' ? 'selected' : '' ?>>RETURNED</option>
                        <option value="repaired" <?= $statusFilter === 'repaired' ? 'selected' : '' ?>>Repair Cost</option>
                        <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>><?= __('all') ?></option>
                    </select>
                </div>

                <!-- Grading -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label text-muted small"
                        style="margin-bottom: 0.25rem; font-weight: 600;"><?= __('grading') ?></label>
                    <select name="grading" class="form-control">
                        <option value=""><?= __('all') ?></option>
                        <option value="A" <?= $gradingFilter === 'A' ? 'selected' : '' ?>>Grade A</option>
                        <option value="B" <?= $gradingFilter === 'B' ? 'selected' : '' ?>>Grade B</option>
                        <option value="C" <?= $gradingFilter === 'C' ? 'selected' : '' ?>>Grade C</option>
                        <option value="Q" <?= $gradingFilter === 'Q' ? 'selected' : '' ?>>Grade Q</option>
                    </select>
                </div>

                <!-- Buttons -->
                <div style="display: flex; gap: 0.5rem; min-width: 120px;">
                    <button type="submit" class="btn btn-secondary" style="flex: 1;">
                        <?= __('filter') ?>
                    </button>
                    <a href="devices.php" class="btn btn-outline" title="Reset"
                        style="width: 42px; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <polyline points="1 4 1 10 7 10" />
                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" />
                        </svg>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($devices)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
                    <line x1="12" y1="18" x2="12.01" y2="18" />
                </svg>
                <h3>
                    <?= __('no_data') ?>
                </h3>
                <p><a href="add-device.php">
                        <?= __('add_device') ?>
                    </a></p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="40"></th> <!-- Expand icon -->
                            <th><?= __('brand') ?> / <?= __('model') ?></th>
                            <th><?= __('specs') ?></th> <!-- Memory/Color -->
                            <th><?= __('condition') ?></th>
                            <th><?= __('grade') ?></th>
                            <th><?= __('quantity') ?></th>
                            <th><?= __('price_range') ?></th> <!-- Purchase Price -->
                            <th><?= __('status') ?></th>
                            <th><?= __('suppliers') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $group): ?>
                            <?php
                            // Unique ID for row expansion
                            $rowId = md5(implode('_', [
                                $group['product_id'],
                                $group['memory_id'],
                                $group['color_id'],
                                $group['condition'],
                                $group['grading'],
                                $group['status'],
                                $group['vat_mode']
                            ]));
                            $jsonParams = htmlspecialchars(json_encode([
                                'product_id' => $group['product_id'],
                                'memory_id' => $group['memory_id'],
                                'color_id' => $group['color_id'],
                                'condition' => $group['condition'],
                                'grading' => $group['grading'],
                                'status' => $group['status'],
                                'vat_mode' => $group['vat_mode']
                            ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr style="cursor: pointer;" onclick="toggleGroup('<?= $rowId ?>', <?= $jsonParams ?>, this)">
                                <td class="text-center text-muted">
                                    <svg id="icon-<?= $rowId ?>" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        style="transition: transform 0.2s;">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </td>
                                <td>
                                    <strong><?= e($group['brand_name']) ?></strong><br>
                                    <span class="text-muted"><?= e($group['product_name']) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-gray"><?= e($group['memory'] ?? '-') ?></span>
                                    <span class="text-muted ml-1"><?= e($group['color'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $group['condition'] === 'new' ? 'success' : 'gray' ?>">
                                        <?= strtoupper($group['condition']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-gray"><?= e($group['grading'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <strong><?= $group['avail_qty'] ?></strong> / <?= $group['total_qty'] ?>
                                </td>
                                <td>
                                    <?php if ($group['min_price'] == $group['max_price']): ?>
                                        <?= formatCurrency($group['min_price'], 'CZK') ?>
                                    <?php else: ?>
                                        <small><?= formatCurrency($group['min_price'], 'CZK') ?> -
                                            <?= formatCurrency($group['max_price'], 'CZK') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span
                                        class="badge badge-<?= $group['status'] === 'in_stock' ? 'success' : ($group['status'] === 'sold' ? 'primary' : 'warning') ?>">
                                        <?= __($group['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted" title="<?= e($group['suppliers_list']) ?>">
                                        <?= e(mb_strimwidth($group['suppliers_list'], 0, 20, '...')) ?>
                                    </small>
                                </td>
                            </tr>
                            <!-- Hidden Row for Details -->
                            <tr id="details-<?= $rowId ?>" style="display: none; background: #f8f9fa;">
                                <td colspan="9" style="padding: 0;">
                                    <div id="content-<?= $rowId ?>" style="padding: 1rem;">
                                        <div class="spinner"></div> <?= __('loading') ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <?php
                $paginationParams = http_build_query(array_filter([
                    'search' => $search,
                    'brand' => $brandFilter,
                    'model' => $modelFilter,
                    'memory' => $memoryFilter,
                    'color' => $colorFilter,
                    'condition' => $conditionFilter,
                    'status' => $statusFilter,
                    'grading' => $gradingFilter,
                    'supplier' => $supplierFilter,
                ]));
                ?>
                <div class="card-footer" style="display: flex; justify-content: center; gap: 0.5rem;">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="?page=<?= $page - 1 ?>&<?= $paginationParams ?>" class="btn btn-sm btn-secondary">←</a>
                    <?php endif; ?>

                    <span style="padding: 0.375rem 0.875rem; color: var(--gray-600);">
                        <?= $page ?> /
                        <?= $pagination['total_pages'] ?>
                    </span>

                    <?php if ($pagination['has_next']): ?>
                        <a href="?page=<?= $page + 1 ?>&<?= $paginationParams ?>" class="btn btn-sm btn-secondary">→</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Device Details Modal -->
<div class="modal-overlay" id="deviceDetailsModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3><?= __('info') ?></h3>
            <button type="button" class="modal-close" onclick="closeModal('deviceDetailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="deviceDetailsBody">
            <div class="text-center py-5">
                <div class="spinner"></div> <?= __('loading') ?>
            </div>
        </div>
        <div class="modal-footer" id="deviceDetailsFooter">
            <button type="button" class="btn btn-secondary"
                onclick="closeModal('deviceDetailsModal')"><?= __('back') ?></button>
        </div>
    </div>
</div>

<!-- Add Repair Cost Modal -->
<div class="modal-overlay" id="repairModal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Add Repair Cost</h3>
            <button type="button" class="modal-close" onclick="closeModal('repairModal')">&times;</button>
        </div>
        <form id="repairForm" onsubmit="submitRepair(event)">
            <div class="modal-body">
                <input type="hidden" id="repair_device_id" name="device_id" value="">
                <div class="form-group">
                    <label>Repair Cost</label>
                    <input type="number" step="0.01" class="form-control" name="repair_cost" id="repair_cost" required>
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select class="form-control" name="repair_currency" id="repair_currency" required>
                        <option value="CZK">CZK</option>
                        <option value="EUR">EUR</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    onclick="closeModal('repairModal')"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn-primary">Save Repair</button>
            </div>
        </form>
    </div>
</div>

<!-- RMA / Reklamace Modal -->
<div class="modal-overlay" id="rmaModal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Reklamace (Return to Supplier)</h3>
            <button type="button" class="modal-close" onclick="closeModal('rmaModal')">&times;</button>
        </div>
        <form id="rmaForm" onsubmit="submitRma(event)" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" id="rma_device_id" name="device_id" value="">
                <div class="alert alert-warning">
                    This will mark the device as "returned" and remove it from your active stock.
                </div>
                <div class="form-group">
                    <label>Credit Note Document (PDF/Image)</label>
                    <input type="file" class="form-control" name="credit_note" id="credit_note"
                        accept=".pdf,.png,.jpg,.jpeg">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    onclick="closeModal('rmaModal')"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn-danger">Confirm Reklamace</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showRepairModal(deviceId) {
        closeModal('deviceDetailsModal');
        document.getElementById('repair_device_id').value = deviceId;
        document.getElementById('repair_cost').value = '';
        document.getElementById('repairModal').classList.add('active');
    }

    function submitRepair(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        data.append('action', 'add_repair_cost');

        fetch('<?= APP_URL ?>/api/ajax-handlers.php', {
            method: 'POST',
            body: data
        }).then(res => res.json()).then(res => {
            if (res.success) {
                location.reload();
            } else {
                alert(res.message);
            }
        });
    }

    function showRmaModal(deviceId) {
        closeModal('deviceDetailsModal');
        document.getElementById('rma_device_id').value = deviceId;
        document.getElementById('credit_note').value = '';
        document.getElementById('rmaModal').classList.add('active');
    }

    function submitRma(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        data.append('action', 'return_device_rma');

        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = 'Submitting...';

        fetch('<?= APP_URL ?>/api/ajax-handlers.php', {
            method: 'POST',
            body: data
        }).then(res => res.json()).then(res => {
            if (res.success) {
                location.reload();
            } else {
                alert(res.message);
                btn.disabled = false;
                btn.innerHTML = 'Confirm Reklamace';
            }
        });
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function showDeviceDetails(id) {
        document.getElementById('deviceDetailsModal').classList.add('active');
        const body = document.getElementById('deviceDetailsBody');
        const footer = document.getElementById('deviceDetailsFooter');
        body.innerHTML = '<div style="text-align:center; padding:2rem;"><div class="spinner"></div> <?= __('loading') ?></div>';

        // Reset footer
        footer.innerHTML = `<button type="button" class="btn btn-secondary" onclick="closeModal('deviceDetailsModal')"><?= __('back') ?></button>`;

        fetch('<?= APP_URL ?>/api/ajax-handlers.php?action=get_device_details&id=' + id, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const d = res.data;
                    let html = `
                        <h4 style="border-bottom:1px solid #eee; padding-bottom:0.5rem; margin-bottom:1rem;"><?= __('device_list') ?></h4>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <div>
                                <label class="text-muted small"><?= __('brand') ?> / <?= __('model') ?></label>
                                <div class="font-weight-bold">${d.brand_name} ${d.product_name}</div>
                            </div>
                            <div>
                                <label class="text-muted small">IMEI</label>
                                <div>${d.imei || '-'}</div>
                            </div>
                            <div>
                                <label class="text-muted small"><?= __('memory') ?> / <?= __('color') ?></label>
                                <div>${d.memory || '-'} / ${d.color || '-'}</div>
                            </div>
                            <div>
                                <label class="text-muted small"><?= __('grade') ?> / <?= __('condition') ?></label>
                                <div>${d.grading || '-'} / ${d.condition ? d.condition.toUpperCase() : '-'}</div>
                            </div>
                        </div>
                        
                        <h4 style="border-bottom:1px solid #eee; padding-bottom:0.5rem; margin-bottom:1rem;"><?= __('purchase') ?></h4>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <div>
                                <label class="text-muted small"><?= __('supplier') ?></label>
                                <div>${d.supplier_name || '-'}</div>
                            </div>
                            <div>
                                <label class="text-muted small"><?= __('purchase_date') ?></label>
                                <div>${d.purchase_created || d.purchase_date}</div>
                            </div>
                            <div>
                                <label class="text-muted small"><?= __('invoice_in') ?></label>
                                <div>${d.invoice_in || d.purchase_invoice || '-'}</div>
                            </div>
                             <div>
                                <label class="text-muted small"><?= __('purchase_price') ?></label>
                                <div>${parseFloat(d.purchase_price).toFixed(2)} ${d.purchase_currency} 
                                    <span style="font-size: 0.8rem; color: var(--gray-500);">(${parseFloat(d.purchase_price_czk).toFixed(2)} CZK)</span>
                                </div>
                            </div>
                        </div>
                    `;

                    if (parseFloat(d.repair_cost) > 0) {
                        html += `
                        <div style="background:#fff3cd; color:#856404; padding:0.75rem 1rem; border-radius:6px; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong style="display:block;">Repair Cost Applied:</strong>
                                ${parseFloat(d.repair_cost).toFixed(2)} ${d.repair_currency} <span style="font-size: 0.8rem; opacity: 0.8;">(${parseFloat(d.repair_cost_czk).toFixed(2)} CZK)</span>
                            </div>
                        </div>
                        `;
                    }
                    if (d.status === 'returned') {
                        html += `
                        <div style="background:#f8d7da; color:#721c24; padding:0.75rem 1rem; border-radius:6px; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong style="display:block;">RMA / Returned to Supplier</strong>
                            </div>
                            ` + (d.credit_note_file ? `<a href="<?= APP_URL ?>${d.credit_note_file}" target="_blank" class="btn btn-sm" style="background:white; color:#721c24; border:1px solid #f5c6cb;">View Credit Note</a>` : '') + `
                        </div>
                        `;
                    }

                    if (d.status === 'in_stock') {
                        footer.innerHTML = `
                            <button type="button" class="btn btn-secondary" onclick="closeModal('deviceDetailsModal')"><?= __('back') ?></button>
                            <div style="flex-grow: 1;"></div>
                            <button type="button" class="btn btn-warning" onclick="showRepairModal(${d.id})">💰 Add Repair Cost</button>
                            <button type="button" class="btn btn-danger" onclick="showRmaModal(${d.id})">🔄 Reklamace</button>
                        `;
                    }

                    if (d.related_items && d.related_items.length > 0) {
                        html += `
                            <h4 style="border-bottom:1px solid #eee; padding-bottom:0.5rem; margin-bottom:1rem;">Invoice Items</h4>
                            <div style="background:#fff; border:1px solid #eee; border-radius:6px; overflow:hidden; margin-bottom:1.5rem;">
                                <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                                    <thead>
                                        <tr style="background:#f9fafb; border-bottom:1px solid #eee;">
                                            <th style="padding:0.5rem 1rem; text-align:left; color:#6b7280; font-weight:500;">Product</th>
                                            <th style="padding:0.5rem 1rem; text-align:left; color:#6b7280; font-weight:500;">Spec</th>
                                            <th style="padding:0.5rem 1rem; text-align:right; color:#6b7280; font-weight:500;">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        d.related_items.forEach(item => {
                            html += `
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:0.5rem 1rem;">${item.brand_name} ${item.product_name}</td>
                                    <td style="padding:0.5rem 1rem; color:#6b7280;">${item.memory || '-'} / ${item.color_en || '-'}</td>
                                    <td style="padding:0.5rem 1rem; text-align:right; font-weight:600;">${item.quantity}</td>
                                </tr>
                            `;
                        });
                        html += `</tbody></table></div>`;
                    }

                    if (d.attachment_url) {
                        html += `
                            <div style="background:#f9fafb; padding:1rem; border-radius:8px; text-align:center; margin-top:1rem;">
                                <a href="<?= APP_URL ?>${d.attachment_url}" target="_blank" class="btn btn-primary" onclick="event.stopPropagation()">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:5px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    Download Attachment
                                </a>
                            </div>
                        `;
                    } else {
                        html += `<div class="text-muted text-center small" style="margin-top:1rem;">No attachment available</div>`;
                    }

                    body.innerHTML = html;
                } else {
                    body.innerHTML = '<div class="text-danger">Error loading details</div>';
                }
            })
            .catch(err => {
                console.error(err);
                body.innerHTML = '<div class="text-danger">Network error</div>';
            });
    }

    // Dynamic model loading when brand filter changes
    function onBrandFilterChange(brandId) {
        // ... (existing code, implied preserved or I should include it? I'll include it)
        const modelSelect = document.getElementById('filterModel');
        modelSelect.innerHTML = '<option value=""><?= __('all') ?> <?= __('model') ?></option>';

        if (!brandId) return;

        fetch('<?= APP_URL ?>/api/ajax-handlers.php?action=get_products&brand_id=' + brandId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json())
            .then(data => {
                data.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    modelSelect.appendChild(opt);
                });
            })
            .catch(err => console.error('Error loading models:', err));
    }

    function toggleGroup(rowId, params, trElement) {
        const detailsRow = document.getElementById('details-' + rowId);
        const icon = document.getElementById('icon-' + rowId);
        const contentDiv = document.getElementById('content-' + rowId);

        if (detailsRow.style.display === 'none') {
            detailsRow.style.display = 'table-row';
            icon.style.transform = 'rotate(180deg)';

            // Fetch if empty or loading
            if (!contentDiv.dataset.loaded) {
                const query = new URLSearchParams(params).toString();

                fetch('<?= APP_URL ?>/api/ajax-handlers.php?action=get_device_group_details&' + query, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            let html = `
                            <table class="table table-sm table-bordered" style="background: white; width: 100%;">
                                <thead>
                                    <tr style="background: #eee;">
                                        <th>ID</th>
                                        <th>IMEI</th>
                                        <th>Purchase Date</th>
                                        <th>Invoice</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                            data.forEach(d => {
                                let invoiceHtml = d.invoice_number || d.invoice_in || '-';
                                if (d.attachment_url) {
                                    // Use APP_URL if needed, or relative path. attachment_url usually starts with /uploads
                                    // But check if it already has relative path
                                    invoiceHtml += ` <a href="<?= APP_URL ?>${d.attachment_url}" target="_blank" title="View Invoice" class="text-primary ml-1" onclick="event.stopPropagation()">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    </a>`;
                                }

                                html += `
                                <tr>
                                    <td>#${d.id}</td>
                                    <td style="font-family:monospace; font-weight:bold;">${d.imei || '-'}</td>
                                    <td>${d.purchase_date}</td>
                                    <td>${invoiceHtml}</td>
                                    <td>
                                        ${parseFloat(d.purchase_price).toFixed(2)} ${d.purchase_currency}
                                        <br><span class="small text-muted">${parseFloat(d.purchase_price_czk).toFixed(2)} CZK</span>
                                        ${parseFloat(d.repair_cost) > 0 ? `<br><span class="small text-warning" title="Repair Cost">🔧 +${parseFloat(d.repair_cost).toFixed(2)} ${d.repair_currency}</span>` : ''}
                                    </td>
                                    <td>
                                        <span class="badge badge-${d.status === 'in_stock' ? 'success' : (d.status === 'returned' ? 'danger' : (d.status === 'sold' ? 'primary' : 'warning'))}">
                                            ${d.status.toUpperCase()}
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-xs btn-outline" onclick="showDeviceDetails(${d.id})">View/Action</button>
                                        <a href="edit-device.php?id=${d.id}" class="btn btn-xs btn-outline">Edit</a>
                                    </td>
                                </tr>
                            `;
                            });
                            html += `</tbody></table>`;
                            contentDiv.innerHTML = html;
                            contentDiv.dataset.loaded = "true";
                        } else {
                            contentDiv.innerHTML = '<div class="text-muted p-3">No items found in this group.</div>';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        contentDiv.innerHTML = '<div class="text-danger p-3">Failed to load details.</div>';
                    });
            }
        } else {
            detailsRow.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>