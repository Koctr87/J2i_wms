<?php
/**
 * Global Search Page
 */
require_once __DIR__ . '/../config/config.php';

$pageTitle = __('search');
require_once __DIR__ . '/../includes/header.php';

$query = trim($_GET['q'] ?? '');
$results = [
    'devices' => [],
    'clients' => [],
    'suppliers' => [],
    'sales' => [],
    'purchases' => []
];

if (strlen($query) >= 2) {
    try {
        $db = getDB();
        $limit = 10;
        $params = ["%$query%"];

        // 1. Search Devices (IMEI, Product Name)
        // Note: Joining products, brands to search by name/brand too
        $stmt = $db->prepare("
            SELECT d.id, d.imei, p.name as product_name, b.name as brand_name, d.status 
            FROM devices d
            JOIN products p ON d.product_id = p.id
            JOIN brands b ON p.brand_id = b.id
            WHERE d.imei LIKE ? 
               OR p.name LIKE ? 
               OR b.name LIKE ?
            LIMIT $limit
        ");
        $stmt->execute(["%$query%", "%$query%", "%$query%"]);
        $results['devices'] = $stmt->fetchAll();

        // 2. Search Clients (Name, ICO, DIC, Email, Phone)
        $stmt = $db->prepare("
            SELECT id, company_name, ico, contact_name, email, phone 
            FROM clients
            WHERE company_name LIKE ? 
               OR ico LIKE ? 
               OR dic LIKE ?
               OR contact_name LIKE ?
               OR email LIKE ?
               OR phone LIKE ?
            LIMIT $limit
        ");
        $stmt->execute(array_fill(0, 6, "%$query%"));
        $results['clients'] = $stmt->fetchAll();

        // 3. Search Suppliers (Name, ICO, DIC)
        $stmt = $db->prepare("
            SELECT id, company_name, ico, contact_name, email 
            FROM suppliers
            WHERE company_name LIKE ? 
               OR ico LIKE ? 
               OR dic LIKE ?
            LIMIT $limit
        ");
        $stmt->execute(["%$query%", "%$query%", "%$query%"]);
        $results['suppliers'] = $stmt->fetchAll();

        // 4. Search Sales (Invoice Number)
        $stmt = $db->prepare("
            SELECT s.id, s.invoice_number, s.sale_date, c.company_name as client_name, s.total, s.currency
            FROM sales s
            LEFT JOIN clients c ON s.client_id = c.id
            WHERE s.invoice_number LIKE ?
            LIMIT $limit
        ");
        $stmt->execute(["%$query%"]);
        $results['sales'] = $stmt->fetchAll();

        // 5. Search Purchases (Invoice Number)
        $stmt = $db->prepare("
            SELECT p.id, p.invoice_number, p.purchase_date, s.company_name as supplier_name, p.total_amount, p.currency
            FROM purchases p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.invoice_number LIKE ?
            LIMIT $limit
        ");
        $stmt->execute(["%$query%"]);
        $results['purchases'] = $stmt->fetchAll();

    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <?= __('search') ?>
        </h3>
    </div>
    <div class="card-body">
        <form action="" method="GET" style="max-width: 600px; margin-bottom: 2rem;">
            <div class="form-group" style="position: relative;">
                <input type="text" name="q" class="form-control form-control-lg"
                    placeholder="<?= __('search_placeholder') ?? 'Search IMEI, Invoice, Company...' ?>"
                    value="<?= e($query) ?>" autofocus>
                <button type="submit" class="btn btn-primary"
                    style="position: absolute; right: 5px; top: 5px; bottom: 5px;">
                    <?= __('search') ?>
                </button>
            </div>
        </form>

        <?php if ($query): ?>
            <?php if (empty(array_filter($results))): ?>
                <div class="alert alert-info">
                    <?= __('no_results_found') ?? 'No results found for' ?> "<strong>
                        <?= e($query) ?>
                    </strong>"
                </div>
            <?php else: ?>
                <div class="search-results-grid">

                    <!-- Devices -->
                    <?php if (!empty($results['devices'])): ?>
                        <div class="search-section">
                            <h4 class="section-title">
                                <?= __('devices') ?> (
                                <?= count($results['devices']) ?>)
                            </h4>
                            <div class="list-group">
                                <?php foreach ($results['devices'] as $item): ?>
                                    <a href="<?= APP_URL ?>/pages/inventory/devices.php?search=<?= urlencode($item['imei']) ?>"
                                        class="list-group-item">
                                        <div style="font-weight: 600;">
                                            <?= e($item['brand_name'] . ' ' . $item['product_name']) ?>
                                        </div>
                                        <div class="text-muted small">IMEI:
                                            <?= e($item['imei']) ?>
                                        </div>
                                        <span
                                            class="badge badge-sm badge-<?= $item['status'] === 'in_stock' ? 'success' : 'secondary' ?>">
                                            <?= $item['status'] ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Clients -->
                    <?php if (!empty($results['clients'])): ?>
                        <div class="search-section">
                            <h4 class="section-title">
                                <?= __('clients') ?> (
                                <?= count($results['clients']) ?>)
                            </h4>
                            <div class="list-group">
                                <?php foreach ($results['clients'] as $item): ?>
                                    <a href="<?= APP_URL ?>/pages/clients/list.php?search=<?= urlencode($item['company_name']) ?>"
                                        class="list-group-item">
                                        <div style="font-weight: 600;">
                                            <?= e($item['company_name']) ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= e($item['contact_name']) ?> •
                                            <?= e($item['phone']) ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Suppliers -->
                    <?php if (!empty($results['suppliers'])): ?>
                        <div class="search-section">
                            <h4 class="section-title">
                                <?= __('suppliers') ?> (
                                <?= count($results['suppliers']) ?>)
                            </h4>
                            <div class="list-group">
                                <?php foreach ($results['suppliers'] as $item): ?>
                                    <a href="<?= APP_URL ?>/pages/suppliers/edit-supplier.php?id=<?= $item['id'] ?>"
                                        class="list-group-item">
                                        <div style="font-weight: 600;">
                                            <?= e($item['company_name']) ?>
                                        </div>
                                        <div class="text-muted small">ICO:
                                            <?= e($item['ico']) ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sales -->
                    <?php if (!empty($results['sales'])): ?>
                        <div class="search-section">
                            <h4 class="section-title">
                                <?= __('sales') ?> (
                                <?= count($results['sales']) ?>)
                            </h4>
                            <div class="list-group">
                                <?php foreach ($results['sales'] as $item): ?>
                                    <a href="<?= APP_URL ?>/pages/sales/history.php?search=<?= urlencode($item['invoice_number']) ?>"
                                        class="list-group-item">
                                        <div style="font-weight: 600;">
                                            <?= e($item['invoice_number'] ?: 'Draft #' . $item['id']) ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= e($item['client_name']) ?> •
                                            <?= formatDate($item['sale_date']) ?>
                                        </div>
                                        <div class="text-right font-weight-bold">
                                            <?= formatCurrency($item['total'], $item['currency']) ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Purchases -->
                    <?php if (!empty($results['purchases'])): ?>
                        <div class="search-section">
                            <h4 class="section-title">
                                <?= __('purchases') ?? 'Purchases' ?> (
                                <?= count($results['purchases']) ?>)
                            </h4>
                            <div class="list-group">
                                <?php foreach ($results['purchases'] as $item): ?>
                                    <a href="<?= APP_URL ?>/pages/suppliers/edit-supplier.php?id=<?= $item['supplier_id'] ?? 0 ?>&tab=purchases"
                                        class="list-group-item">
                                        <div style="font-weight: 600;">
                                            <?= e($item['invoice_number']) ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= e($item['supplier_name']) ?> •
                                            <?= formatDate($item['purchase_date']) ?>
                                        </div>
                                        <div class="text-right font-weight-bold">
                                            <?= formatCurrency($item['total_amount'], $item['currency']) ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    .search-results-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .search-section {
        background: var(--white);
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        overflow: hidden;
    }

    .section-title {
        padding: 0.75rem 1rem;
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--gray-700);
    }

    .list-group {
        display: flex;
        flex-direction: column;
    }

    .list-group-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--gray-100);
        text-decoration: none;
        color: inherit;
        transition: background 0.2s;
        display: block;
    }

    .list-group-item:last-child {
        border-bottom: none;
    }

    .list-group-item:hover {
        background: var(--gray-50);
    }

    .form-control-lg {
        height: 50px;
        font-size: 1.1rem;
        padding-right: 100px;
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>