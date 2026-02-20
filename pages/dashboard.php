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
    // Language switching is now handled centrally in config.php

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

        // Total stock value (in purchase prices, converted to CZK)
        // Total stock value (in purchase prices, converted to CZK)
        $stmt = $db->query("
            SELECT COALESCE(SUM(
                (COALESCE(purchase_price_czk, 0) + COALESCE(delivery_cost_czk, 0) + COALESCE(repair_cost_czk, 0)) * quantity_available
            ), 0) as value 
            FROM devices 
            WHERE status = 'in_stock'
        ");
        $stockValueDevices = $stmt->fetchColumn();

        // Accessories stock value
        $stmt = $db->query("
            SELECT COALESCE(SUM(
                (COALESCE(purchase_price, 0) * 
                 CASE 
                     WHEN purchase_currency = 'EUR' THEN 25
                     WHEN purchase_currency = 'USD' THEN 23
                     ELSE 1 
                 END +
                 COALESCE(delivery_cost, 0) * 
                 CASE 
                     WHEN delivery_currency = 'EUR' THEN 25
                     WHEN delivery_currency = 'USD' THEN 23
                     ELSE 1 
                 END
                ) * quantity_available
            ), 0) as value 
            FROM accessories 
            WHERE status = 'in_stock'
        ");
        $stockValueAccessories = $stmt->fetchColumn();

        $stockValue = $stockValueDevices + $stockValueAccessories;

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
        // Step 1: Item Profit
        $stmt = $db->query("
            SELECT 
                SUM(
                    si.total_price_czk - 
                    (
                        COALESCE(d.purchase_price_czk, 0) +
                        COALESCE(d.delivery_cost_czk, 0) +
                        COALESCE(d.repair_cost_czk, 0) +
                        COALESCE(si.item_delivery_cost_czk, 0)
                    ) * si.quantity - 
                    CASE 
                        WHEN si.vat_mode = 'reverse' THEN 0 
                        WHEN si.vat_mode = 'marginal' THEN 
                            GREATEST(0, (
                                si.total_price_czk - 
                                (COALESCE(d.purchase_price_czk, 0) + COALESCE(d.delivery_cost_czk, 0) + COALESCE(d.repair_cost_czk, 0)) * si.quantity
                            ) * 21/121)
                        ELSE si.vat_amount 
                    END
                ) as item_profit
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            LEFT JOIN devices d ON si.device_id = d.id
            WHERE s.status = 'completed'
        ");
        $itemProfit = $stmt->fetchColumn() ?: 0;

        // Step 2: Delivery Revenue
        $stmt = $db->query("
            SELECT SUM(COALESCE(sale_delivery_cost_czk, 0)) FROM sales WHERE status = 'completed'
        ");
        $deliveryProfit = $stmt->fetchColumn() ?: 0;

        $netProfit = $itemProfit + $deliveryProfit;

        // Monthly Net Profit
        // Step 1: Item Profit
        $stmt = $db->prepare("
            SELECT 
                SUM(
                    si.total_price_czk - 
                    (
                        COALESCE(d.purchase_price_czk, 0) +
                        COALESCE(d.delivery_cost_czk, 0) +
                        COALESCE(d.repair_cost_czk, 0) +
                        COALESCE(si.item_delivery_cost_czk, 0)
                    ) * si.quantity - 
                    CASE 
                        WHEN si.vat_mode = 'reverse' THEN 0 
                        WHEN si.vat_mode = 'marginal' THEN 
                            GREATEST(0, (
                                si.total_price_czk - 
                                (COALESCE(d.purchase_price_czk, 0) + COALESCE(d.delivery_cost_czk, 0) + COALESCE(d.repair_cost_czk, 0)) * si.quantity
                            ) * 21/121)
                        ELSE si.vat_amount 
                    END
                ) as item_profit
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            LEFT JOIN devices d ON si.device_id = d.id
            WHERE s.status = 'completed' AND s.sale_date >= ?
        ");
        $stmt->execute([$firstDay]);
        $monthlyItemProfit = $stmt->fetchColumn() ?: 0;

        // Step 2: Delivery Revenue
        $stmt = $db->prepare("
            SELECT SUM(COALESCE(sale_delivery_cost_czk, 0)) FROM sales WHERE status = 'completed' AND sale_date >= ?
        ");
        $stmt->execute([$firstDay]);
        $monthlyDeliveryProfit = $stmt->fetchColumn() ?: 0;

        $monthlyNetProfit = $monthlyItemProfit + $monthlyDeliveryProfit;

        // Historical Net Profit
        $historyMonth = $_GET['h_month'] ?? '';
        $historyYear = $_GET['h_year'] ?? '';
        $historyProfit = 0;
        $showHistory = false;

        if ($historyMonth && $historyYear) {
            $showHistory = true;
            $startDate = "$historyYear-$historyMonth-01";
            $endDate = date('Y-m-t', strtotime($startDate));

            $stmt = $db->prepare("
                SELECT 
                    SUM(
                        si.total_price_czk - 
                        (
                            COALESCE(d.purchase_price_czk, 0) +
                            COALESCE(d.delivery_cost_czk, 0) +
                            COALESCE(d.repair_cost_czk, 0) +
                            COALESCE(si.item_delivery_cost_czk, 0)
                        ) * si.quantity - 
                        CASE 
                        WHEN si.vat_mode = 'reverse' THEN 0 
                        WHEN si.vat_mode = 'marginal' THEN 
                            GREATEST(0, (
                                si.total_price_czk - 
                                (COALESCE(d.purchase_price_czk, 0) + COALESCE(d.delivery_cost_czk, 0) + COALESCE(d.repair_cost_czk, 0)) * si.quantity
                            ) * 21/121)
                        ELSE si.vat_amount 
                    END
                    ) as item_profit
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                LEFT JOIN devices d ON si.device_id = d.id
                WHERE s.status = 'completed' AND s.sale_date BETWEEN ? AND ?
            ");
            $stmt->execute([$startDate, $endDate]);
            $hItemProfit = $stmt->fetchColumn() ?: 0;

            $stmt = $db->prepare("
                SELECT SUM(COALESCE(sale_delivery_cost_czk, 0)) FROM sales WHERE status = 'completed' AND sale_date BETWEEN ? AND ?
            ");
            $stmt->execute([$startDate, $endDate]);
            $hDeliveryProfit = $stmt->fetchColumn() ?: 0;

            $historyProfit = $hItemProfit + $hDeliveryProfit;
        }

    } catch (PDOException $e) {
        echo "<div class='alert alert-warning'>Database Error: " . $e->getMessage() . "</div>";
    }

    // Get current currency rates
// Assuming getCNBRate is safe or will return null
    $eurRate = getCNBRate('EUR') ?? 25.00;
    $usdRate = getCNBRate('USD') ?? 23.00;

    // Calculate percentages
    $totalCategoryItems = array_sum(array_column($byCategory, 'total'));

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
        <div class="stat-box-label"><?= __('total') ?> SKU</div>
        <div class="stat-box-value">
            <?= number_format($skuCount) ?>
            <span class="stat-box-change positive">+2.58%</span>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-box-label"><?= __('quantity') ?></div>
        <div class="stat-box-value">
            <?= number_format($totalDevices) ?>
            <span class="stat-box-unit"><?= __('units') ?></span>
            <span class="stat-box-change positive">+4.37%</span>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-box-label"><?= __('total_value') ?> <span class="text-muted"
                style="font-size: 0.7em;">(<?= __('purchase_price') ?>)</span></div>
        <div class="stat-box-value">
            <?= number_format($stockValue, 0, ',', ' ') ?>
            <span class="stat-box-unit">Kč</span>
        </div>
    </div>
</div>

<!-- Profit Stats Row -->
<div class="stats-row">
    <div class="stat-box">
        <div class="stat-box-label"><?= __('net_profit') ?> (<?= __('total') ?>)</div>
        <div class="stat-box-value">
            <?= number_format($netProfit, 0, ',', ' ') ?>
            <span class="stat-box-unit">Kč</span>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-box-label"><?= __('net_profit') ?> (<?= __('this_month') ?>)</div>
        <div class="stat-box-value">
            <?= number_format($monthlyNetProfit, 0, ',', ' ') ?>
            <span class="stat-box-unit">Kč</span>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-box-label"><?= __('net_profit') ?> (<?= __('history') ?? 'History' ?>)</div>
        <form method="GET" style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
            <select name="h_month" class="form-control" style="padding: 2px 4px; font-size: 0.8rem; height: auto;">
                <?php
                $months = [
                    '01' => 'Jan',
                    '02' => 'Feb',
                    '03' => 'Mar',
                    '04' => 'Apr',
                    '05' => 'May',
                    '06' => 'Jun',
                    '07' => 'Jul',
                    '08' => 'Aug',
                    '09' => 'Sep',
                    '10' => 'Oct',
                    '11' => 'Nov',
                    '12' => 'Dec'
                ];
                $curM = date('m');
                foreach ($months as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($historyMonth ?: $curM) == $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <select name="h_year" class="form-control" style="padding: 2px 4px; font-size: 0.8rem; height: auto;">
                <?php
                $curY = date('Y');
                for ($y = $curY; $y >= $curY - 2; $y--): ?>
                    <option value="<?= $y ?>" <?= ($historyYear ?: $curY) == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-secondary" style="padding: 2px 6px;">🔍</button>
        </form>

        <?php if ($showHistory): ?>
            <div class="stat-box-value" style="font-size: 1.25rem;">
                <?= number_format($historyProfit, 0, ',', ' ') ?>
                <span class="stat-box-unit">Kč</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--gray-500);">
                <?= $months[$historyMonth] . ' ' . $historyYear ?>
            </div>
        <?php else: ?>
            <div style="font-size: 0.8rem; color: var(--gray-400);">Select period</div>
        <?php endif; ?>
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
                    <h3 class="card-title"><?= __('warehouse_inventory') ?></h3>
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

            <div class="card capacity-card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('capacity_usage') ?></h3>
                </div>
                <div class="card-body">
                    <div class="capacity-gauge">
                        <svg width="140" height="140" viewBox="0 0 140 140">
                            <circle class="capacity-gauge-bg" cx="70" cy="70" r="58" />
                            <circle class="capacity-gauge-fill" cx="70" cy="70" r="58" stroke-dasharray="364.42"
                                stroke-dashoffset="<?= 364.42 * (1 - $capacityUsage / 100) ?>" />
                        </svg>
                        <div class="capacity-gauge-text">
                            <div class="capacity-gauge-label"><?= __('total') ?></div>
                            <div class="capacity-gauge-value"><?= $capacityUsage ?>%</div>
                        </div>
                    </div>

                    <div class="capacity-legend">
                        <div class="capacity-legend-item">
                            <div class="capacity-legend-label"><?= __('loaded') ?></div>
                            <div class="capacity-legend-value"><?= number_format($totalDevices) ?> <?= __('units') ?>
                            </div>
                        </div>
                        <div class="capacity-legend-item">
                            <div class="capacity-legend-label"><?= __('empty') ?></div>
                            <div class="capacity-legend-value">
                                <?= number_format(max(0, $maxCapacity - $totalDevices)) ?> <?= __('slots') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warehouse Storage Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('storage') ?></h3>
                <div class="card-actions">
                    <button class="btn btn-sm btn-outline">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                        </svg>
                        <?= __('filter') ?>
                    </button>
                    <select class="form-control" style="width: auto; padding: 4px 8px;">
                        <option><?= __('section') ?></option>
                    </select>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('floor') ?></th>
                                <th><?= __('section') ?></th>
                                <th><?= __('category') ?></th>
                                <th><?= __('loaded') ?></th>
                                <th>%</th>
                                <th><?= __('available') ?></th>
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('map') ?></h3>
                <div class="warehouse-map-tabs">
                    <span class="warehouse-map-tab active"><?= __('floor') ?> 1</span>
                    <span class="warehouse-map-tab"><?= __('floor') ?> 2</span>
                    <span class="warehouse-map-tab"><?= __('floor') ?> 3</span>
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
                                <?= __('available') ?>: <strong><?= number_format($cat['total']) ?></strong>/100
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1rem; font-size: 0.75rem; color: var(--gray-500);">
                    <span><span class="warehouse-slot filled"
                            style="display: inline-block; width: 20px; height: 16px; vertical-align: middle;"></span>
                        <?= __('available') ?></span>
                    <span><span class="warehouse-slot"
                            style="display: inline-block; width: 20px; height: 16px; vertical-align: middle;"></span>
                        <?= __('full') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Aside Column -->
    <div class="dashboard-aside">
        <!-- Package Status (Recent Arrivals) -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('package_status') ?></h3>
            </div>
            <div class="status-tabs">
                <span class="status-tab active"><?= __('all') ?></span>
                <span class="status-tab"><?= __('expected') ?></span>
                <span class="status-tab"><?= __('received') ?></span>
                <span class="status-tab"><?= __('sent') ?></span>
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
                <h3 class="card-title"><?= __('activity_log') ?></h3>
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
                                    <?= str_replace(
                                        ['{name}', '{company}', '{amount}'],
                                        [
                                            '<strong>' . e($sale['first_name'] . ' ' . $sale['last_name']) . '</strong>',
                                            e($sale['company_name']),
                                            formatCurrency($sale['total'])
                                        ],
                                        __('activity_sale')
                                    ) ?>
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