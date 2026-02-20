<?php
/**
 * J2i Warehouse Management System
 * Invoices Report Page
 */
require_once __DIR__ . '/../../config/config.php';

$pageTitle = __('invoices');
require_once __DIR__ . '/../../includes/header.php';

try {
    $db = getDB();

    // Current Tab
    $tab = $_GET['tab'] ?? 'incoming'; // incoming | outgoing

    // Common Filters
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $brandId = $_GET['brand_id'] ?? '';

    // Incoming Filters
    $supplierId = $_GET['supplier_id'] ?? '';
    $incSearch = $_GET['inc_search'] ?? '';

    // Outgoing Filters
    $clientId = $_GET['client_id'] ?? '';
    $saleType = $_GET['sale_type'] ?? ''; // wholesale | retail
    $outSearch = $_GET['out_search'] ?? '';

    // Fetch Filter Data
    $brands = $db->query("SELECT id, name FROM brands ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);

    if ($tab === 'incoming') {
        $suppliers = $db->query("SELECT id, company_name FROM suppliers ORDER BY company_name")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Build Incoming Query
        $params = [$startDate, $endDate];
        $sql = "
            SELECT p.id, p.purchase_date, p.invoice_number, p.currency, 
                   COALESCE(SUM(d.purchase_price * d.quantity), 0) as calc_total,
                   s.company_name, p.attachment_url,
                   COUNT(d.id) as item_count
            FROM purchases p
            JOIN suppliers s ON p.supplier_id = s.id
            LEFT JOIN devices d ON d.purchase_id = p.id
            LEFT JOIN products pr ON d.product_id = pr.id
            WHERE p.purchase_date BETWEEN ? AND ?
        ";

        if ($supplierId) {
            $sql .= " AND p.supplier_id = ?";
            $params[] = $supplierId;
        }

        if ($brandId) {
            $sql .= " AND pr.brand_id = ?";
            $params[] = $brandId;
        }

        if ($incSearch) {
            $sql .= " AND (p.invoice_number LIKE ? OR s.company_name LIKE ?)";
            $params[] = "%$incSearch%";
            $params[] = "%$incSearch%";
        }

        $sql .= " GROUP BY p.id ORDER BY p.purchase_date DESC";

        // Count just for simple pagination or check
        // For simplicity, no pagination for now or limit 100
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();

    } else {
        // Outgoing
        $clients = $db->query("SELECT id, company_name FROM clients ORDER BY company_name")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Build Outgoing Query
        $params = [$startDate, $endDate];
        $sql = "
            SELECT DISTINCT s.id, s.sale_date, s.type, s.total, s.currency,
                   c.company_name, c.full_name,
                   (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as item_count
            FROM sales s
            JOIN clients c ON s.client_id = c.id
            LEFT JOIN sale_items si ON si.sale_id = s.id
            LEFT JOIN devices d ON si.device_id = d.id
            LEFT JOIN products pr ON d.product_id = pr.id
            WHERE s.sale_date BETWEEN ? AND ?
        ";

        if ($clientId) {
            $sql .= " AND s.client_id = ?";
            $params[] = $clientId;
        }

        if ($saleType) {
            $sql .= " AND s.type = ?";
            $params[] = $saleType;
        }

        if ($brandId) {
            $sql .= " AND pr.brand_id = ?";
            $params[] = $brandId;
        }

        if ($outSearch) {
            $sql .= " AND (c.company_name LIKE ? OR c.full_name LIKE ? OR s.id LIKE ?)"; // s.id as invoice ref usually
            $params[] = "%$outSearch%";
            $params[] = "%$outSearch%";
            $params[] = "%$outSearch%";
        }

        $sql .= " GROUP BY s.id ORDER BY s.sale_date DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= __('invoices') ?></h3>
    </div>

    <!-- Tabs -->
    <div class="card-body border-bottom pt-0 pb-0 px-0">
        <ul class="nav nav-tabs px-4" style="margin-bottom: 0; border-bottom: 0;">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'incoming' ? 'active' : '' ?>" href="?tab=incoming">
                    <?= __('incoming_invoices') ?> (Purchases)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'outgoing' ? 'active' : '' ?>" href="?tab=outgoing">
                    <?= __('outgoing_invoices') ?> (Sales)
                </a>
            </li>
        </ul>
    </div>

    <!-- Filters -->
    <div class="card-body bg-light">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="<?= $tab ?>">

            <!-- Date Range -->
            <div class="col-md-3">
                <label class="form-label small text-muted"><?= __('date_range') ?></label>
                <div class="input-group input-group-sm">
                    <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                    <span class="input-group-text">-</span>
                    <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                </div>
            </div>

            <!-- Brand Filter (Common) -->
            <div class="col-md-2">
                <label class="form-label small text-muted"><?= __('brand') ?></label>
                <select name="brand_id" class="form-control form-control-sm">
                    <option value=""><?= __('all') ?></option>
                    <?php foreach ($brands as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $brandId == $id ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($tab === 'incoming'): ?>
                <!-- Incoming Specific Filters -->
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('supplier') ?></label>
                    <select name="supplier_id" class="form-control form-control-sm">
                        <option value=""><?= __('all') ?></option>
                        <?php foreach ($suppliers as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $supplierId == $id ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted"><?= __('search') ?></label>
                    <input type="text" name="inc_search" class="form-control form-control-sm"
                        placeholder="Invoice #, Supplier..." value="<?= e($incSearch) ?>">
                </div>
            <?php else: ?>
                <!-- Outgoing Specific Filters -->
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('client') ?></label>
                    <select name="client_id" class="form-control form-control-sm">
                        <option value=""><?= __('all') ?></option>
                        <?php foreach ($clients as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $clientId == $id ? 'selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted"><?= __('type') ?></label>
                    <select name="sale_type" class="form-control form-control-sm">
                        <option value=""><?= __('all') ?></option>
                        <option value="wholesale" <?= $saleType === 'wholesale' ? 'selected' : '' ?>><?= __('wholesale') ?>
                        </option>
                        <option value="retail" <?= $saleType === 'retail' ? 'selected' : '' ?>><?= __('retail') ?></option>
                    </select>
                </div>
                <div class="col-md-2"> <!-- Adjusted width -->
                    <label class="form-label small text-muted"><?= __('search') ?></label>
                    <input type="text" name="out_search" class="form-control form-control-sm" placeholder="Client..."
                        value="<?= e($outSearch) ?>">
                </div>
            <?php endif; ?>

            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><?= __('filter') ?></button>
                <a href="?tab=<?= $tab ?>" class="btn btn-sm btn-outline-secondary"><?= __('reset') ?></a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th><?= __('date') ?></th>
                    <?php if ($tab === 'incoming'): ?>
                        <th><?= __('supplier') ?></th>
                        <th><?= __('invoice_number') ?></th>
                    <?php else: ?>
                        <th><?= __('client') ?></th>
                        <th><?= __('type') ?></th>
                        <th><?= __('sale_id') ?></th>
                    <?php endif; ?>
                    <th>items</th>
                    <th class="text-end"><?= __('amount') ?></th>
                    <th class="text-end"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted"><?= __('no_data') ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><?= formatDate($tab === 'incoming' ? $inv['purchase_date'] : $inv['sale_date']) ?></td>

                            <?php if ($tab === 'incoming'): ?>
                                <td><?= e($inv['company_name']) ?></td>
                                <td><?= e($inv['invoice_number']) ?></td>
                            <?php else: ?>
                                <td>
                                    <strong><?= e($inv['company_name'] ?: ($inv['full_name'] ?: 'Guest')) ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $inv['type'] === 'wholesale' ? 'info' : 'success' ?>">
                                        <?= ucfirst($inv['type']) ?>
                                    </span>
                                </td>
                                <td>#<?= $inv['id'] ?></td>
                            <?php endif; ?>

                            <td>
                                <span class="badge badge-gray"><?= $inv['item_count'] ?> items</span>
                            </td>

                            <td class="text-end">
                                <?php
                                $amount = $tab === 'incoming' ? ($inv['calc_total'] ?? 0) : $inv['total'];
                                echo formatCurrency($amount, $inv['currency']);
                                ?>
                            </td>

                            <td class="text-end">
                                <?php if ($tab === 'incoming'): ?>
                                    <?php if (!empty($inv['attachment_url'])): ?>
                                        <a href="<?= APP_URL . $inv['attachment_url'] ?>" target="_blank"
                                            class="btn btn-sm btn-outline-primary" title="Download Invoice">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                <polyline points="14 2 14 8 20 8"></polyline>
                                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                                <polyline points="10 9 9 9 8 9"></polyline>
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?= APP_URL ?>/pages/sales/view-sale.php?id=<?= $inv['id'] ?>"
                                        class="btn btn-sm btn-outline-primary" title="View Sale">
                                        View
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>