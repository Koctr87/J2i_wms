<?php
/**
 * J2i Warehouse Management System
 * Sales History Page
 */
require_once __DIR__ . '/../../config/config.php';

try {
    $db = getDB();

    // Pagination
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;

    // Filters
    $search = $_GET['search'] ?? '';
    $clientId = $_GET['client_id'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $typeFilter = $_GET['type'] ?? '';

    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(c.company_name LIKE ? OR s.invoice_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($clientId) {
        $where[] = "s.client_id = ?";
        $params[] = $clientId;
    }

    if ($dateFrom) {
        $where[] = "s.sale_date >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $where[] = "s.sale_date <= ?";
        $params[] = $dateTo;
    }

    if ($typeFilter) {
        $where[] = "s.type = ?";
        $params[] = $typeFilter;
    }

    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

    // Count total
    $countSql = "SELECT COUNT(*) FROM sales s JOIN clients c ON s.client_id = c.id $whereClause";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    $pagination = getPagination($total, $page, $perPage);

    // Get sales
    $sql = "SELECT s.*, c.company_name, c.ico,
                   (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count,
                   (SELECT SUM(vat_amount) FROM sale_items WHERE sale_id = s.id AND vat_mode = 'marginal') as margin_vat
            FROM sales s
            JOIN clients c ON s.client_id = c.id
            $whereClause
            ORDER BY s.sale_date DESC, s.created_at DESC
            LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();

    // Monthly totals
    $monthStart = date('Y-m-01');
    $stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM sales WHERE sale_date >= ? AND status = 'completed'");
    $stmt->execute([$monthStart]);
    $monthlyStats = $stmt->fetch();

} catch (Throwable $e) {
    die("<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>");
}

$pageTitle = __('sale_history');
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom: 1.5rem;">
    <div class="stat-card">
        <div class="stat-icon success">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
            </svg>
        </div>
        <div class="stat-content">
            <div class="stat-label">
                <?= __('monthly_sales') ?>
            </div>
            <div class="stat-value">
                <?= formatCurrency($monthlyStats['total']) ?>
            </div>
            <div class="stat-change">
                <?= $monthlyStats['count'] ?>
                <?= mb_strtolower(__('sales')) ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <?= __('sale_history') ?>
            <span class="badge badge-primary" style="margin-left: 0.5rem;">
                <?= $total ?>
            </span>
        </h3>
        <button type="button" class="btn btn-primary" onclick="openModal('saleTypeModal')">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <?= __('new_sale') ?>
        </button>
    </div>

    <!-- Filters -->
    <div style="padding: 1rem 1.5rem; background: var(--gray-50); border-bottom: 1px solid var(--gray-200);">
        <form method="GET" class="form-row" style="align-items: flex-end; gap: 1rem;">
            <div class="form-group" style="margin-bottom: 0; flex: 1;">
                <input type="text" name="search" class="form-control" placeholder="<?= __('search') ?>..."
                    value="<?= e($search) ?>">
            </div>

            <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
                <select name="client_id" class="form-control">
                    <option value=""><?= __('all') ?> <?= __('clients') ?></option>
                    <?php
                    $clients = $db->query("SELECT id, company_name FROM clients ORDER BY company_name")->fetchAll();
                    foreach ($clients as $client):
                        ?>
                        <option value="<?= $client['id'] ?>" <?= ($clientId == $client['id']) ? 'selected' : '' ?>>
                            <?= e($client['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                <select name="type" class="form-control">
                    <option value=""><?= __('all') ?> Типы</option>
                    <option value="wholesale" <?= ($typeFilter === 'wholesale') ? 'selected' : '' ?>><?= __('wholesale') ?>
                    </option>
                    <option value="retail" <?= ($typeFilter === 'retail') ? 'selected' : '' ?>><?= __('retail') ?></option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.75rem;">От</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.75rem;">До</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
            </div>
            <button type="submit" class="btn btn-secondary">
                <?= __('filter') ?>
            </button>
        </form>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($sales)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23" />
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
                <h3>
                    <?= __('no_data') ?>
                </h3>
                <p><a href="new-sale.php">
                        <?= __('new_sale') ?>
                    </a></p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= __('date') ?></th>
                            <th><?= __('invoice_out') ?></th>
                            <th><?= __('client') ?></th>
                            <th>Items</th>
                            <th><?= __('rate_eur') ?></th>
                            <th><?= __('subtotal') ?></th>
                            <th><?= __('vat_amount') ?></th>
                            <th>НДС с маржи</th>
                            <th><?= __('total') ?></th>
                            <th><?= __('status') ?></th>
                            <th class="text-right"><?= __('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr style="cursor: pointer;" onclick="showSaleDetails(<?= $sale['id'] ?>)">
                                <td><?= formatDate($sale['sale_date']) ?></td>
                                <td><?= e($sale['invoice_number'] ?? '-') ?></td>
                                <td>
                                    <strong><?= e($sale['company_name']) ?></strong>
                                    <br><small class="text-muted"><?= e($sale['ico'] ?? '') ?></small>
                                </td>
                                <td><span class="badge badge-gray"><?= $sale['item_count'] ?></span></td>
                                <td><?= $sale['currency_rate_eur'] ? number_format($sale['currency_rate_eur'], 3) : '-' ?></td>
                                <td><?= formatCurrency($sale['subtotal']) ?></td>
                                <td><?= formatCurrency($sale['vat_amount']) ?></td>
                                <td>
                                    <?php if ($sale['margin_vat'] > 0): ?>
                                        <span class="text-warning"
                                            title="21% из маржи"><?= formatCurrency($sale['margin_vat']) ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= formatCurrency($sale['total']) ?></strong></td>
                                <td>
                                    <span
                                        class="badge badge-<?= $sale['status'] === 'completed' ? 'success' : ($sale['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                        <?= ucfirst($sale['status']) ?>
                                    </span>
                                </td>
                                <td class="text-right" onclick="event.stopPropagation()">
                                    <div style="display: flex; gap: 0.25rem; justify-content: flex-end;">
                                        <a href="edit-sale.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline"
                                            title="<?= __('edit') ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </a>
                                        <a href="view-sale.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline"
                                            title="<?= __('view') ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
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
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
                            class="btn btn-sm btn-secondary">←</a>
                    <?php endif; ?>

                    <span style="padding: 0.375rem 0.875rem; color: var(--gray-600);">
                        <?= $page ?> /
                        <?= $pagination['total_pages'] ?>
                    </span>

                    <?php if ($pagination['has_next']): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>"
                            class="btn btn-sm btn-secondary">→</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Sale Details Modal -->
<div class="modal-overlay" id="saleDetailsModal">
    <div class="modal" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">Sale Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('saleDetailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="saleDetailsBody" style="max-height: 80vh; overflow-y: auto;">
            <div class="loading">
                <div class="spinner"></div>
            </div>
        </div>
    </div>
</div>

<!-- Sale Type Selection Modal -->
<div class="modal-overlay" id="saleTypeModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title"><?= __('new_sale') ?></h3>
            <button type="button" class="modal-close" onclick="closeModal('saleTypeModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding: 2rem;">
            <p style="text-align: center; color: var(--gray-600); margin-bottom: 2rem;">Выберите тип продажи для
                продолжения:</p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <a href="new-sale.php?type=wholesale" class="card"
                    style="padding: 1.5rem; text-align: center; text-decoration: none; transition: transform 0.2s; border: 2px solid var(--primary-100);">
                    <div style="font-size: 2.5rem; margin-bottom: 1rem;">🏢</div>
                    <div style="font-weight: 700; color: var(--primary-600);"><?= __('wholesale') ?></div>
                    <div style="font-size: 0.8rem; color: var(--gray-500); margin-top: 0.5rem;">Продажа компаниям</div>
                </a>
                <a href="new-sale.php?type=retail" class="card"
                    style="padding: 1.5rem; text-align: center; text-decoration: none; transition: transform 0.2s; border: 2px solid var(--success-100);">
                    <div style="font-size: 2.5rem; margin-bottom: 1rem;">🛍️</div>
                    <div style="font-weight: 700; color: var(--success-600);"><?= __('retail') ?></div>
                    <div style="font-size: 0.8rem; color: var(--gray-500); margin-top: 0.5rem;">Продажа физ. лицам</div>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function showSaleDetails(id) {
        document.getElementById('saleDetailsModal').classList.add('active');
        const body = document.getElementById('saleDetailsBody');
        body.innerHTML = '<div style="text-align:center; padding:2rem;"><div class="spinner"></div> <?= __('loading') ?></div>';

        fetch('<?= APP_URL ?>/api/ajax-handlers.php?action=get_sale_details&id=' + id, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const s = res.data;
                    let html = `
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; background:#f9fafb; padding:1.5rem; border-radius:8px;">
                    <div>
                        <label class="text-muted small"><?= __('client') ?></label>
                        <div class="font-weight-bold">${s.client_name}</div>
                        ${s.client_ico ? `<div class="text-muted small">IČO: ${s.client_ico}</div>` : ''}
                    </div>
                    <div>
                        <label class="text-muted small">Invoice / Date</label>
                        <div>${s.invoice_number || '-'} / ${s.sale_date}</div>
                    </div>
                </div>

                <h4 style="margin-bottom:1rem; border-bottom:1px solid #eee; padding-bottom:0.5rem;">Items (${s.items.length})</h4>
                <div style="background:#fff; border:1px solid #eee; border-radius:8px; overflow-x:auto; margin-bottom:1.5rem;">
                    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                        <thead>
                            <tr style="background:#f9fafb; border-bottom:1px solid #eee;">
                                <th style="padding:0.6rem 1rem; text-align:left; color:#6b7280;">Product</th>
                                <th style="padding:0.6rem 1rem; text-align:right; color:#6b7280;">Qty</th>
                                <th style="padding:0.6rem 1rem; text-align:right; color:#6b7280;">Price</th>
                                <th style="padding:0.6rem 1rem; text-align:right; color:#6b7280;">Deliv. (Exp)</th>
                                <th style="padding:0.6rem 1rem; text-align:right; color:#6b7280;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

                    let totalItemDeliveryCZK = 0;
                    s.items.forEach(item => {
                        const rate = item.item_delivery_currency === 'EUR' ? s.currency_rate_eur : (item.item_delivery_currency === 'USD' ? s.currency_rate_usd : 1);
                        totalItemDeliveryCZK += (parseFloat(item.item_delivery_cost) || 0) * item.quantity * rate;

                        html += `
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:0.6rem 1rem;">${item.name}</td>
                        <td style="padding:0.6rem 1rem; text-align:right;">${item.quantity}</td>
                        <td style="padding:0.6rem 1rem; text-align:right;">${parseFloat(item.unit_price).toFixed(2)} ${item.sale_currency}</td>
                        <td style="padding:0.6rem 1rem; text-align:right; color:#6b7280;">${parseFloat(item.item_delivery_cost).toFixed(2)} ${item.item_delivery_currency}</td>
                        <td style="padding:0.6rem 1rem; text-align:right; font-weight:600;">${parseFloat(item.total_price).toFixed(2)} ${item.sale_currency}</td>
                    </tr>
                `;
                    });

                    html += `
                        </tbody>
                    </table>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                    <div style="flex:1;">
                        ${s.attachment_path ? `
                            <a href="<?= APP_URL ?>${s.attachment_path}" target="_blank" class="btn btn-outline" style="display:inline-flex; align-items:center; gap:0.5rem;" onclick="event.stopPropagation()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                Download Invoice
                            </a>
                        ` : '<span class="text-muted small">No attachment</span>'}
                    </div>
                    <div style="text-align:right; background:#f9fafb; padding:1.2rem; border-radius:8px; border:1px solid #eee;">
                        <div class="text-muted small" style="margin-bottom:0.25rem;">Total (Sale):</div>
                        <div style="font-size:1.4rem; font-weight:800; color:var(--primary-600); line-height:1; margin-bottom:0.5rem;">${parseFloat(s.total).toFixed(2)} CZK</div>
                        <div class="text-muted small" style="border-top:1px solid #eee; padding-top:0.5rem; margin-top:0.5rem;">Total Item Delivery (Exp):</div>
                        <div style="font-weight:600; color:#dc2626;">${totalItemDeliveryCZK.toFixed(2)} CZK</div>
                    </div>
                </div>
            `;
                    body.innerHTML = html;
                } else {
                    body.innerHTML = '<div class="alert alert-danger">Error loading data</div>';
                }
            })
            .catch(err => {
                console.error(err);
                body.innerHTML = '<div class="alert alert-danger">Network error</div>';
            });
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>