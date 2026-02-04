<?php
/**
 * J2i Warehouse Management System
 * Dashboard Page - ShipNow Style (Rebuilt)
 */
require_once __DIR__ . '/../config/config.php';
$pageTitle = __('dashboard');
require_once __DIR__ . '/../includes/header.php';

// Global Try-Catch for Debugging
try {
    // Language switch handler
    if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGUAGES)) {
        $_SESSION['language'] = $_GET['lang'];
        $stmt = getDB()->prepare("UPDATE users SET language = ? WHERE id = ?");
        $stmt->execute([$_GET['lang'], $_SESSION['user_id']]);
        redirect('dashboard.php');
    }

    $db = getDB();

    // Init variables to avoid undefined variable warnings if try block fails
    $skuCount = 0;
    $totalDevices = 0;
    $stockValue = 0;
    $capacityUsage = 0;
    $byCategory = [];
    $storageData = [];
    $recentArrivals = [];
    $recentSales = [];
    $monthlySales = ['count' => 0, 'total' => 0];

    // Dashboard statistics
    try {
        // Total devices count (SKU)
        $stmt = $db->query("SELECT COUNT(DISTINCT product_id) as sku_count FROM devices WHERE status = 'in_stock'");
        $skuCount = $stmt->fetchColumn();

        // Total devices in stock (quantity)
        $stmt = $db->query("SELECT COALESCE(SUM(quantity_available), 0) as total FROM devices WHERE status = 'in_stock'");
        $totalDevices = $stmt->fetchColumn();

        // Total stock value
        $stmt = $db->query("SELECT COALESCE(SUM(retail_price * quantity_available), 0) as value FROM devices WHERE status =
'in_stock'");
        $stockValue = $stmt->fetchColumn();

        // Capacity usage (simulated - based on filled vs max)
        $maxCapacity = 1000; // Configure this
        $capacityUsage = $totalDevices > 0 ? min(100, round(($totalDevices / $maxCapacity) * 100, 1)) : 0;

        // Devices by category
// Ensure current_lang is safe
        $safeLang = $current_lang ?? 'cs';
        $stmt = $db->query("
SELECT c.id, c.name_" . $safeLang . " as name, c.icon, COUNT(d.id) as count, COALESCE(SUM(d.quantity_available), 0) as
total
FROM categories c
LEFT JOIN products p ON p.category_id = c.id
LEFT JOIN devices d ON d.product_id = p.id AND d.status = 'in_stock'
GROUP BY c.id
ORDER BY total DESC
LIMIT 6
");
        $byCategory = $stmt->fetchAll();

        // Storage by section (using categories as sections simulation)
        $storageData = $db->query("
SELECT c.name_" . $safeLang . " as section,
COALESCE(SUM(d.quantity_available), 0) as used,
100 as capacity
FROM categories c
LEFT JOIN products p ON p.category_id = c.id
LEFT JOIN devices d ON d.product_id = p.id AND d.status = 'in_stock'
GROUP BY c.id
ORDER BY used DESC
LIMIT 5
")->fetchAll();

        // Recent arrivals (last 5)
        $stmt = $db->query("
SELECT d.*, p.name as product_name, b.name as brand_name, m.size as memory,
cl.name_" . $safeLang . " as color
FROM devices d
JOIN products p ON d.product_id = p.id
JOIN brands b ON p.brand_id = b.id
LEFT JOIN memory_options m ON d.memory_id = m.id
LEFT JOIN color_options cl ON d.color_id = cl.id
ORDER BY d.created_at DESC
LIMIT 5
");
        $recentArrivals = $stmt->fetchAll();

        // Recent sales (last 5)
        $stmt = $db->query("
SELECT s.*, c.company_name, u.first_name, u.last_name
FROM sales s
JOIN clients c ON s.client_id = c.id
LEFT JOIN users u ON s.created_by = u.id
ORDER BY s.created_at DESC
LIMIT 4
");
        $recentSales = $stmt->fetchAll();

        // Monthly sales this month
        $firstDay = date('Y-m-01');
        $stmt = $db->prepare("
SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total
FROM sales
WHERE sale_date >= ? AND status = 'completed'
");
        $stmt->execute([$firstDay]);
        $monthlySales = $stmt->fetch();

        // Total Net Profit Calculation
        $stmt = $db->query("
            SELECT 
                SUM(
                    si.total_price - 
                    (
                        (COALESCE(d.purchase_price, 0) + COALESCE(d.delivery_cost, 0)) * 
                        CASE 
                            WHEN d.purchase_currency = 'EUR' THEN s.currency_rate_eur
                            WHEN d.purchase_currency = 'USD' THEN s.currency_rate_usd
                            ELSE 1 
                        END * si.quantity
                    ) - 
                    si.vat_amount
                ) as net_profit
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            LEFT JOIN devices d ON si.device_id = d.id
            WHERE s.status = 'completed'
        ");
        $netProfit = $stmt->fetchColumn() ?: 0;

        // Monthly Net Profit
        $stmt = $db->prepare("
            SELECT 
                SUM(
                    si.total_price - 
                    (
                        (COALESCE(d.purchase_price, 0) + COALESCE(d.delivery_cost, 0)) * 
                        CASE 
                            WHEN d.purchase_currency = 'EUR' THEN s.currency_rate_eur
                            WHEN d.purchase_currency = 'USD' THEN s.currency_rate_usd
                            ELSE 1 
                        END * si.quantity
                    ) - 
                    si.vat_amount
                ) as net_profit
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            LEFT JOIN devices d ON si.device_id = d.id
            WHERE s.status = 'completed' AND s.sale_date >= ?
        ");
        $stmt->execute([$firstDay]);
        $monthlyNetProfit = $stmt->fetchColumn() ?: 0;

    } catch (PDOException $e) {
        echo "<div class='alert alert-warning'>Database Error: " . $e->getMessage() . "</div>";
    }

    // Get current currency rates
// Assuming getCNBRate is safe or will return null
    $eurRate = getCNBRate('EUR') ?? 25.00;
    $usdRate = getCNBRate('USD') ?? 23.00;

    // Calculate percentages
    $totalCategoryItems = array_sum(array_column($byCategory, 'total'));

    echo "<div>Debug: Logic Complete. Variables set.</div>";
    echo "<div>Debug: skuCount = $skuCount</div>";

} catch (Throwable $e) {
    echo "<div class='alert alert-danger'>FATAL ERROR IN DASHBOARD: " . $e->getMessage() . "<br>" . $e->getTraceAsString() .
        "</div>";
    die();
}
?>

<!-- HTML will go here -->

<!-- Stats Row -->
<div class="stats-row">
    <div class="stat-box">
        <div class="stat-box-label">Total SKU</div>
        <div class="stat-box-value">
            <?= number_format($skuCount) ?>
            <span class="stat-box-change positive">+2.58%</span>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-box-label"><?= __('quantity') ?></div>
        <div class="stat-box-value">
            <?= number_format($totalDevices) ?>
            <span class="stat-box-unit">units</span>
            <span class="stat-box-change positive">+4.37%</span>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-box-label"><?= __('total_value') ?></div>
        <div class="stat-box-value">
            <?= number_format($stockValue, 0, ',', ' ') ?>
            <span class="stat-box-unit">Kč</span>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-box-label">Net Profit</div>
        <div class="stat-box-value">
            <?= number_format($netProfit, 0, ',', ' ') ?>
            <span class="stat-box-unit">Kč</span>
        </div>
        <div style="font-size: 0.85rem; color: #10b981; margin-top: 5px;">
            + <?= number_format($monthlyNetProfit, 0, ',', ' ') ?> this month
        </div>
    </div>
</div>

<!-- Dashboard Grid -->
<div class="dashboard-grid">
    <!-- Main Column -->
    <div class="dashboard-main">
        <!-- Warehouse Inventory + Capacity -->
        <div class="card-row">
            <!-- Warehouse Inventory -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('warehouse') ?> Inventory</h3>
                    <button class="btn btn-sm btn-outline">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="1" />
                            <circle cx="19" cy="12" r="1" />
                            <circle cx="5" cy="12" r="1" />
                        </svg>
                    </button>
                </div>
                <div class="card-body">
                    <div class="inventory-header">
                        <span class="inventory-total"><?= number_format($totalDevices) ?></span>
                        <span class="inventory-unit"><?= __('devices') ?></span>
                    </div>

                    <div class="inventory-bars">
                        <?php foreach ($byCategory as $cat):
                            $percentage = $totalCategoryItems > 0 ? round(($cat['total'] / $totalCategoryItems) * 100) : 0;
                            $height = max(20, $percentage * 1.2);
                            ?>
                            <div class="inventory-bar-item">
                                <div class="inventory-bar" style="height: 100px;">
                                    <div class="inventory-bar-fill" style="height: <?= $height ?>%;"></div>
                                </div>
                                <div class="inventory-bar-label"><?= e(mb_substr($cat['name'], 0, 10)) ?></div>
                                <div class="inventory-bar-value"><?= $percentage ?>% <?= number_format($cat['total']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Capacity Usage -->
            <div class="card capacity-card">
                <div class="card-header">
                    <h3 class="card-title">Capacity Usage</h3>
                </div>
                <div class="card-body">
                    <div class="capacity-gauge">
                        <svg width="140" height="140" viewBox="0 0 140 140">
                            <circle class="capacity-gauge-bg" cx="70" cy="70" r="58" />
                            <circle class="capacity-gauge-fill" cx="70" cy="70" r="58" stroke-dasharray="364.42"
                                stroke-dashoffset="<?= 364.42 * (1 - $capacityUsage / 100) ?>" />
                        </svg>
                        <div class="capacity-gauge-text">
                            <div class="capacity-gauge-label">Total Usage</div>
                            <div class="capacity-gauge-value"><?= $capacityUsage ?>%</div>
                        </div>
                    </div>

                    <div class="capacity-legend">
                        <div class="capacity-legend-item">
                            <div class="capacity-legend-label">Loaded</div>
                            <div class="capacity-legend-value"><?= number_format($totalDevices) ?> units</div>
                        </div>
                        <div class="capacity-legend-item">
                            <div class="capacity-legend-label">Empty</div>
                            <div class="capacity-legend-value">
                                <?= number_format(max(0, $maxCapacity - $totalDevices)) ?> slots
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warehouse Storage Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('warehouse') ?> Storage</h3>
                <div class="card-actions">
                    <button class="btn btn-sm btn-outline">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                        </svg>
                        Filter
                    </button>
                    <select class="form-control" style="width: auto; padding: 4px 8px;">
                        <option>Sort by: Section</option>
                    </select>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Floor</th>
                                <th>Section</th>
                                <th><?= __('category') ?></th>
                                <th>Storage Used</th>
                                <th>Percentage</th>
                                <th>Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $floor = 1;
                            $sections = ['A1 – A10', 'B1 – B10', 'C1 – C10', 'D1 – D10', 'E1 – E10'];
                            foreach ($storageData as $idx => $row):
                                $percentage = min(100, $row['used']);
                                ?>
                                <tr>
                                    <td><?= $floor ?></td>
                                    <td><?= $sections[$idx] ?? 'X1 – X10' ?></td>
                                    <td><?= e($row['section']) ?></td>
                                    <td>
                                        <div class="progress-cell">
                                            <div class="progress-bar">
                                                <div class="progress-bar-fill" style="width: <?= $percentage ?>%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= $percentage ?>%</td>
                                    <td><?= max(0, $row['capacity'] - $row['used']) ?>/<?= $row['capacity'] ?></td>
                                </tr>
                                <?php
                                if ($idx % 2 == 1)
                                    $floor++;
                            endforeach;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Warehouse Map -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('warehouse') ?> Map</h3>
                <div class="warehouse-map-tabs">
                    <span class="warehouse-map-tab active">Floor 1</span>
                    <span class="warehouse-map-tab">Floor 2</span>
                    <span class="warehouse-map-tab">Floor 3</span>
                </div>
            </div>
            <div class="card-body">
                <div class="warehouse-sections">
                    <?php foreach ($byCategory as $idx => $cat): ?>
                        <div class="warehouse-section">
                            <div class="warehouse-section-title"><?= e($cat['name']) ?></div>
                            <div class="warehouse-slots">
                                <?php
                                $letter = chr(65 + $idx);
                                $filledSlots = min(10, ceil($cat['total'] / 10));
                                for ($i = 1; $i <= 10; $i++):
                                    ?>
                                    <div class="warehouse-slot <?= $i <= $filledSlots ? 'filled' : '' ?>"><?= $letter . $i ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <div class="warehouse-section-meta">
                                Available Space: <strong><?= number_format($cat['total']) ?></strong>/100
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1rem; font-size: 0.75rem; color: var(--gray-500);">
                    <span><span class="warehouse-slot filled"
                            style="display: inline-block; width: 20px; height: 16px; vertical-align: middle;"></span>
                        Available</span>
                    <span><span class="warehouse-slot"
                            style="display: inline-block; width: 20px; height: 16px; vertical-align: middle;"></span>
                        Full</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Aside Column -->
    <div class="dashboard-aside">
        <!-- Package Status (Recent Arrivals) -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Package Status</h3>
            </div>
            <div class="status-tabs">
                <span class="status-tab active">All</span>
                <span class="status-tab">Expected</span>
                <span class="status-tab">Received</span>
                <span class="status-tab">Sent</span>
            </div>
            <div class="status-list">
                <?php if (empty($recentArrivals)): ?>
                    <div class="empty-state">
                        <p><?= __('no_data') ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($recentArrivals, 0, 3) as $device):
                        $statuses = ['sent', 'received', 'expected'];
                        $status = $statuses[array_rand($statuses)];
                        ?>
                        <div class="status-item">
                            <div class="status-item-icon <?= $status ?>">📦</div>
                            <div class="status-item-content">
                                <div class="status-item-title">PKG-<?= str_pad($device['id'], 5, '0', STR_PAD_LEFT) ?></div>
                                <div class="status-item-meta"><?= formatDate($device['purchase_date']) ?></div>
                            </div>
                            <span class="status-item-badge <?= $status ?>"><?= ucfirst($status) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('warehouse') ?> Activity Log</h3>
                <button class="btn btn-sm btn-outline">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1" />
                        <circle cx="19" cy="12" r="1" />
                        <circle cx="5" cy="12" r="1" />
                    </svg>
                </button>
            </div>
            <div class="activity-list">
                <?php if (empty($recentSales)): ?>
                    <div class="empty-state">
                        <p><?= __('no_data') ?></p>
                    </div>
                <?php else: ?>
                    <?php
                    $colors = ['primary', 'success', 'warning', 'info'];
                    foreach ($recentSales as $idx => $sale):
                        $initials = mb_substr($sale['first_name'] ?? 'U', 0, 1) . mb_substr($sale['last_name'] ?? '', 0, 1);
                        ?>
                        <div class="activity-item">
                            <div class="activity-avatar <?= $colors[$idx % count($colors)] ?>"><?= $initials ?></div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?= e($sale['first_name'] . ' ' . $sale['last_name']) ?></strong>
                                    created a sale to <?= e($sale['company_name']) ?>
                                    for <?= formatCurrency($sale['total']) ?>
                                </div>
                                <div class="activity-time"><?= formatDate($sale['sale_date']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Currency Rates -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('currency_rate') ?> ČNB</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius);">
                        <div>
                            <div style="font-size: 0.75rem; color: var(--gray-500);">EUR → CZK</div>
                            <div style="font-size: 1.25rem; font-weight: 700; color: var(--gray-900);">
                                <?= number_format($eurRate, 3) ?>
                            </div>
                        </div>
                        <span style="font-size: 1.5rem;">🇪🇺</span>
                    </div>
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius);">
                        <div>
                            <div style="font-size: 0.75rem; color: var(--gray-500);">USD → CZK</div>
                            <div style="font-size: 1.25rem; font-weight: 700; color: var(--gray-900);">
                                <?= number_format($usdRate, 3) ?>
                            </div>
                        </div>
                        <span style="font-size: 1.5rem;">🇺🇸</span>
                    </div>
                </div>
                <div style="text-align: center; font-size: 0.75rem; color: var(--gray-400); margin-top: 0.75rem;">
                    <?= formatDate(date('Y-m-d')) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Page-specific overrides */
    .stats-row .stat-box:nth-child(1) .stat-box-value {
        color: var(--gray-900);
    }

    .stats-row .stat-box:nth-child(2) .stat-box-value {
        color: var(--gray-900);
    }

    /* Fix capacity gauge circle */
    .capacity-gauge-fill {
        stroke: var(--primary);
    }
</style>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>