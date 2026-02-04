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
    $statusFilter = $_GET['status'] ?? 'in_stock';
    $gradingFilter = $_GET['grading'] ?? '';

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

    if ($statusFilter) {
        $where[] = "d.status = ?";
        $params[] = $statusFilter;
    }

    if ($gradingFilter) {
        $where[] = "d.grading = ?";
        $params[] = $gradingFilter;
    }

    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

    // Count total
    $countSql = "SELECT COUNT(*) FROM devices d 
                 JOIN products p ON d.product_id = p.id 
                 JOIN brands b ON p.brand_id = b.id 
                 $whereClause";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    $pagination = getPagination($total, $page, $perPage);

    // Get devices
    $lang = $current_lang ?? 'cs'; // fallback
    $sql = "SELECT d.*, p.name as product_name, b.name as brand_name,
                   m.size as memory, c.name_" . $lang . " as color,
                   cat.name_" . $lang . " as category
            FROM devices d
            JOIN products p ON d.product_id = p.id
            JOIN brands b ON p.brand_id = b.id
            JOIN categories cat ON p.category_id = cat.id
            LEFT JOIN memory_options m ON d.memory_id = m.id
            LEFT JOIN color_options c ON d.color_id = c.id
            $whereClause
            ORDER BY d.created_at DESC
            LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $devices = $stmt->fetchAll();

    // Get brands for filter
    $brands = $db->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();

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
    <div style="padding: 1rem 1.5rem; background: var(--gray-50); border-bottom: 1px solid var(--gray-200);">
        <form method="GET" class="form-row" style="align-items: flex-end; gap: 1rem;">
            <div class="form-group" style="margin-bottom: 0; flex: 1;">
                <input type="text" name="search" class="form-control" placeholder="<?= __('search') ?>..."
                    value="<?= e($search) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <select name="brand" class="form-control">
                    <option value="">
                        <?= __('all') ?>
                        <?= __('brands') ?>
                    </option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand['id'] ?>" <?= $brandFilter == $brand['id'] ? 'selected' : '' ?>>
                            <?= e($brand['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <select name="status" class="form-control">
                    <option value="in_stock" <?= $statusFilter === 'in_stock' ? 'selected' : '' ?>>
                        <?= __('in_stock') ?>
                    </option>
                    <option value="sold" <?= $statusFilter === 'sold' ? 'selected' : '' ?>>
                        <?= __('sold') ?>
                    </option>
                    <option value="reserved" <?= $statusFilter === 'reserved' ? 'selected' : '' ?>>
                        <?= __('reserved') ?>
                    </option>
                    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>
                        <?= __('all') ?>
                    </option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <select name="grading" class="form-control">
                    <option value="">Grading: All</option>
                    <option value="A" <?= $gradingFilter === 'A' ? 'selected' : '' ?>>Grade A</option>
                    <option value="B" <?= $gradingFilter === 'B' ? 'selected' : '' ?>>Grade B</option>
                    <option value="C" <?= $gradingFilter === 'C' ? 'selected' : '' ?>>Grade C</option>
                    <option value="Q" <?= $gradingFilter === 'Q' ? 'selected' : '' ?>>Grade Q</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">
                <?= __('filter') ?>
            </button>
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
                            <th>
                                <?= __('date') ?>
                            </th>
                            <th>
                                <?= __('brand') ?> /
                                <?= __('model') ?>
                            </th>
                            <th>
                                <?= __('memory') ?>
                            </th>
                            <th>Grading</th>
                            <th>
                                <?= __('color') ?>
                            </th>
                            <th>
                                <?= __('quantity') ?>
                            </th>
                            <th>
                                <?= __('purchase_price') ?>
                            </th>
                            <th>
                                <?= __('retail_price') ?>
                            </th>
                            <th>
                                <?= __('vat_mode') ?>
                            </th>
                            <th>
                                <?= __('status') ?>
                            </th>
                            <th>
                                <?= __('actions') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $device): ?>
                            <tr>
                                <td>
                                    <?= formatDate($device['purchase_date']) ?>
                                </td>
                                <td>
                                    <strong>
                                        <?= e($device['brand_name']) ?>
                                    </strong><br>
                                    <span class="text-muted">
                                        <?= e($device['product_name']) ?>
                                    </span>
                                    <?php if ($device['imei']): ?>
                                        <br><small class="text-muted">IMEI:
                                            <?= e($device['imei']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-gray">
                                        <?= e($device['memory'] ?? 'N/A') ?>
                                    </span></td>
                                <td>
                                    <span class="badge badge-gray"><?= e($device['grading'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <?= e($device['color'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= $device['quantity_available'] ?> /
                                    <?= $device['quantity'] ?>
                                </td>
                                <td>
                                    <?= formatCurrency($device['purchase_price'], $device['purchase_currency']) ?>
                                </td>
                                <td>
                                    <?= $device['retail_price'] ? formatCurrency($device['retail_price']) : '-' ?>
                                </td>
                                <td>
                                    <span
                                        class="badge badge-<?= $device['vat_mode'] === 'marginal' ? 'warning' : ($device['vat_mode'] === 'reverse' ? 'info' : 'gray') ?>">
                                        <?= __('vat_' . $device['vat_mode']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="badge badge-<?= $device['status'] === 'in_stock' ? 'success' : ($device['status'] === 'sold' ? 'primary' : 'warning') ?>">
                                        <?= __($device['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.25rem;">
                                        <a href="edit-device.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-outline"
                                            title="<?= __('edit') ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="card-footer" style="display: flex; justify-content: center; gap: 0.5rem;">
                    <?php if ($pagination['has_prev']): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&brand=<?= $brandFilter ?>&status=<?= $statusFilter ?>"
                            class="btn btn-sm btn-secondary">←</a>
                    <?php endif; ?>

                    <span style="padding: 0.375rem 0.875rem; color: var(--gray-600);">
                        <?= $page ?> /
                        <?= $pagination['total_pages'] ?>
                    </span>

                    <?php if ($pagination['has_next']): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&brand=<?= $brandFilter ?>&status=<?= $statusFilter ?>"
                            class="btn btn-sm btn-secondary">→</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>