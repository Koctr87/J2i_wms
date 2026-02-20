<?php
/**
 * J2i Warehouse Management System
 * Reports Dashboard
 */
require_once __DIR__ . '/../../config/config.php';

try {
    $db = getDB();

    // Date Range Filter
    $startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
    $endDate = $_GET['end_date'] ?? date('Y-m-d');

    // 1. Sales Summary (Wholesale vs Retail)
    $salesSql = "
        SELECT 
            type,
            SUM(subtotal) as total_subtotal,
            SUM(total) as total_gross,
            SUM(platform_commission_amount) as total_commission,
            SUM(vat_amount) as total_vat,
            COUNT(*) as sale_count
        FROM sales
        WHERE sale_date BETWEEN ? AND ?
        GROUP BY type
    ";
    $stmt = $db->prepare($salesSql);
    $stmt->execute([$startDate, $endDate]);
    $salesData = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    // 2. Costs Breakdown
    // Purchase costs of items sold in this period
    $costsSql = "
        SELECT 
            s.type,
            SUM((si.quantity * d.purchase_price * s.currency_rate_eur)) as total_purchase_eur_in_czk, -- This is a simplification, needs to handle rates properly
            SUM(si.quantity * si.item_delivery_cost * (CASE WHEN si.item_delivery_currency = 'EUR' THEN s.currency_rate_eur WHEN si.item_delivery_currency = 'USD' THEN s.currency_rate_usd ELSE 1 END)) as total_item_delivery,
            SUM(s.sale_delivery_cost * (CASE WHEN s.sale_delivery_currency = 'EUR' THEN s.currency_rate_eur WHEN s.sale_delivery_currency = 'USD' THEN s.currency_rate_usd ELSE 1 END)) as total_sale_delivery -- This needs care as it's per sale, not per item
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        LEFT JOIN devices d ON si.device_id = d.id
        WHERE s.sale_date BETWEEN ? AND ?
        GROUP BY s.type
    ";
    // Actually, getting purchase cost is easier if we look at the margins we already calculated or stored? 
    // We don't store total purchase cost in `sales` table yet, maybe we should?
    // Let's do a more robust query for purchase costs.

    $purchaseCostsSql = "
        SELECT 
            s.type,
            SUM(si.quantity * (
                (CASE 
                    WHEN si.device_id IS NOT NULL THEN (d.purchase_price * (CASE WHEN d.purchase_currency = 'EUR' THEN s.currency_rate_eur WHEN d.purchase_currency = 'USD' THEN s.currency_rate_usd ELSE 1 END))
                    WHEN si.accessory_id IS NOT NULL THEN (a.purchase_price * (CASE WHEN a.purchase_currency = 'EUR' THEN s.currency_rate_eur WHEN a.purchase_currency = 'USD' THEN s.currency_rate_usd ELSE 1 END))
                    ELSE 0 
                 END) + 
                (CASE 
                    WHEN si.device_id IS NOT NULL THEN (d.delivery_cost * (CASE WHEN d.delivery_currency = 'EUR' THEN s.currency_rate_eur WHEN d.delivery_currency = 'USD' THEN s.currency_rate_usd ELSE 1 END))
                    WHEN si.accessory_id IS NOT NULL THEN (a.delivery_cost * (CASE WHEN a.delivery_currency = 'EUR' THEN s.currency_rate_eur WHEN a.delivery_currency = 'USD' THEN s.currency_rate_usd ELSE 1 END))
                    ELSE 0 
                 END)
            )) as purchase_cost_total
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        LEFT JOIN devices d ON si.device_id = d.id
        LEFT JOIN accessories a ON si.accessory_id = a.id
        WHERE s.sale_date BETWEEN ? AND ?
        GROUP BY s.type
    ";
    $stmt = $db->prepare($purchaseCostsSql);
    $stmt->execute([$startDate, $endDate]);
    $purchaseCosts = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    // Logistics Costs (Sale Level Delivery)
    $logisticsSql = "
        SELECT 
            type,
            SUM(sale_delivery_cost * (CASE WHEN sale_delivery_currency = 'EUR' THEN currency_rate_eur WHEN sale_delivery_currency = 'USD' THEN currency_rate_usd ELSE 1 END)) as sale_delivery_total
        FROM sales
        WHERE sale_date BETWEEN ? AND ?
        GROUP BY type
    ";
    $stmt = $db->prepare($logisticsSql);
    $stmt->execute([$startDate, $endDate]);
    $saleLogistics = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    // Item Level Logistics Expense
    $itemLogisticsSql = "
        SELECT 
            s.type,
            SUM(si.quantity * si.item_delivery_cost * (CASE WHEN si.item_delivery_currency = 'EUR' THEN s.currency_rate_eur WHEN si.item_delivery_currency = 'USD' THEN s.currency_rate_usd ELSE 1 END)) as item_delivery_total
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE s.sale_date BETWEEN ? AND ?
        GROUP BY s.type
    ";
    $stmt = $db->prepare($itemLogisticsSql);
    $stmt->execute([$startDate, $endDate]);
    $itemLogistics = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    // Accessories breakdown for Retail
    $accBreakdownSql = "
        SELECT 
            t.name_en as accessory_type,
            SUM(si.quantity) as total_qty,
            SUM(si.quantity * si.unit_price * (CASE WHEN si.sale_currency = 'EUR' THEN s.currency_rate_eur WHEN si.sale_currency = 'USD' THEN s.currency_rate_usd ELSE 1 END)) as total_sales
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN accessories a ON si.accessory_id = a.id
        JOIN accessory_types t ON a.type_id = t.id
        WHERE s.type = 'retail' AND s.sale_date BETWEEN ? AND ?
        GROUP BY t.id
        ORDER BY total_sales DESC
    ";
    $stmt = $db->prepare($accBreakdownSql);
    $stmt->execute([$startDate, $endDate]);
    $accBreakdown = $stmt->fetchAll();

} catch (Throwable $e) {
    die("<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>");
}

$pageTitle = __('reports');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="reports-header"
    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h2 style="margin: 0;">
            <?= __('reports') ?>
        </h2>
        <p style="color: var(--gray-500); margin: 0.25rem 0 0 0;"><?= __('financial_report') ?></p>
        <a href="invoices.php" class="btn btn-outline-primary btn-sm"
            style="margin-top: 0.5rem; display: inline-flex; align-items: center; gap: 0.5rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            <?= __('invoices') ?>
        </a>
    </div>
    <form method="GET" class="card" style="padding: 0.75rem 1rem; flex-direction: row; gap: 1rem; align-items: center;">
        <div class="form-group" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <label
                style="font-size: 0.875rem; font-weight: 500; color: var(--gray-600);"><?= __('from_date') ?>:</label>
            <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>" style="width: auto;">
        </div>
        <div class="form-group" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <label style="font-size: 0.875rem; font-weight: 500; color: var(--gray-600);"><?= __('to_date') ?>:</label>
            <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>" style="width: auto;">
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.25rem;">
            <?= __('search') ?>
        </button>
    </form>
</div>

<!-- Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 2rem;">
    <?php
    $totalWholesale = ($salesData['wholesale']['total_subtotal'] ?? 0);
    $totalRetail = ($salesData['retail']['total_subtotal'] ?? 0);
    $grandTotalSales = $totalWholesale + $totalRetail;

    $totalPurchaseCost = ($purchaseCosts['wholesale']['purchase_cost_total'] ?? 0) + ($purchaseCosts['retail']['purchase_cost_total'] ?? 0);
    $totalLogistics = ($saleLogistics['wholesale']['sale_delivery_total'] ?? 0) + ($saleLogistics['retail']['sale_delivery_total'] ?? 0) +
        ($itemLogistics['wholesale']['item_delivery_total'] ?? 0) + ($itemLogistics['retail']['item_delivery_total'] ?? 0);
    $totalCommission = ($salesData['retail']['total_commission'] ?? 0);
    $totalVat = ($salesData['wholesale']['total_vat'] ?? 0) + ($salesData['retail']['total_vat'] ?? 0);

    $grossProfit = $grandTotalSales - $totalPurchaseCost - $totalLogistics - $totalCommission - $totalVat;
    $marginPercent = $grandTotalSales > 0 ? ($grossProfit / $grandTotalSales) * 100 : 0;
    ?>

    <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--primary);">
        <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.5rem;"><?= __('sales_summary') ?></div>
        <div style="font-size: 1.5rem; font-weight: 700; color: var(--gray-800);">
            <?= formatCurrency($grandTotalSales) ?>
        </div>
        <div style="margin-top: 0.5rem; font-size: 0.75rem;">
            <span style="color: var(--primary);">Wholesale: <?= formatCurrency($totalWholesale) ?></span><br>
            <span style="color: var(--success);">Retail: <?= formatCurrency($totalRetail) ?></span>
        </div>
    </div>

    <div class="card" style="padding: 1.25rem; border-left: 4px solid #ef4444;">
        <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.5rem;"><?= __('purchase_cost') ?></div>
        <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444;">
            <?= formatCurrency($totalPurchaseCost) ?>
        </div>
        <div style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--gray-400);">Закупка + доставка In</div>
    </div>

    <div class="card" style="padding: 1.25rem; border-left: 4px solid #f59e0b;">
        <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.5rem;">Косты & Комиссии</div>
        <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;">
            <?= formatCurrency($totalLogistics + $totalCommission) ?>
        </div>
        <div style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--gray-400);">
            🚚 <?= formatCurrency($totalLogistics) ?> | 💸 <?= formatCurrency($totalCommission) ?>
        </div>
    </div>

    <div class="card" style="padding: 1.25rem; border-left: 4px solid #6366f1;">
        <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.5rem;"><?= __('margin_vat') ?></div>
        <div style="font-size: 1.5rem; font-weight: 700; color: #6366f1;">
            <?= formatCurrency($totalVat) ?>
        </div>
        <div style="margin-top: 0.5rem; font-size: 0.75rem; color: var(--gray-400);">Налог уплаченный государству</div>
    </div>

    <div class="card"
        style="padding: 1.25rem; border-left: 4px solid var(--success); background: var(--success-light);">
        <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.5rem;"><?= __('net_profit') ?></div>
        <div style="font-size: 1.5rem; font-weight: 800; color: var(--success);">
            <?= formatCurrency($grossProfit) ?>
        </div>
        <div style="margin-top: 0.5rem; font-size: 0.875rem; font-weight: 600; color: var(--success);">
            Margin: <?= round($marginPercent, 1) ?>%
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Detailed Cost Itemization -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= __('costs_breakdown') ?></h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('type') ?></th>
                        <th><?= __('sales') ?> (CZK)</th>
                        <th><?= __('purchase_cost') ?> (CZK)</th>
                        <th><?= __('logistics_costs') ?> (CZK)</th>
                        <th><?= __('commission') ?> (CZK)</th>
                        <th><?= __('margin_vat') ?> (CZK)</th>
                        <th><?= __('net_profit') ?> (CZK)</th>
                        <th><?= __('profit_margin') ?> %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (['wholesale', 'retail'] as $type):
                        $sales = $salesData[$type]['total_subtotal'] ?? 0;
                        $purch = $purchaseCosts[$type]['purchase_cost_total'] ?? 0;
                        $log = ($saleLogistics[$type]['sale_delivery_total'] ?? 0) + ($itemLogistics[$type]['item_delivery_total'] ?? 0);
                        $comm = $salesData[$type]['total_commission'] ?? 0;
                        $vat = $salesData[$type]['total_vat'] ?? 0;
                        $profit = $sales - $purch - $log - $comm - $vat;
                        $perc = $sales > 0 ? ($profit / $sales) * 100 : 0;
                        ?>
                        <tr>
                            <td><strong>
                                    <?= strtoupper($type) ?>
                                </strong></td>
                            <td>
                                <?= formatCurrency($sales) ?>
                            </td>
                            <td>
                                <?= formatCurrency($purch) ?>
                            </td>
                            <td>
                                <?= formatCurrency($log) ?>
                            </td>
                            <td>
                                <?= formatCurrency($comm) ?>
                            </td>
                            <td>
                                <?= formatCurrency($vat) ?>
                            </td>
                            <td style="font-weight: 700; color: <?= $profit >= 0 ? 'var(--success)' : '#ef4444' ?>;">
                                <?= formatCurrency($profit) ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div
                                        style="flex: 1; height: 8px; background: var(--gray-100); border-radius: 4px; overflow: hidden;">
                                        <div
                                            style="width: <?= min(100, max(0, $perc)) ?>%; height: 100%; background: <?= $perc > 15 ? 'var(--success)' : ($perc > 5 ? '#f59e0b' : '#ef4444') ?>;">
                                        </div>
                                    </div>
                                    <span style="font-size: 0.75rem; font-weight: 600; min-width: 40px;">
                                        <?= round($perc, 1) ?>%
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Accessories Breakdown -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= __('top_accessories') ?> (Retail)</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Тип</th>
                        <th>Кол-во</th>
                        <th>Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($accBreakdown)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted" style="padding: 2rem;">Нет данных</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($accBreakdown as $acc): ?>
                        <tr>
                            <td>
                                <?= e($acc['accessory_type']) ?>
                            </td>
                            <td>
                                <?= $acc['total_qty'] ?> шт.
                            </td>
                            <td style="font-weight: 600;">
                                <?= formatCurrency($acc['total_sales']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">График динамики (по дням)</h3>
    </div>
    <div class="card-body">
        <canvas id="salesChart" style="max-height: 300px;"></canvas>
    </div>
</div>

<?php
// Prepare chart data
$chartSql = "
    SELECT 
        sale_date,
        SUM(CASE WHEN type = 'wholesale' THEN subtotal ELSE 0 END) as wholesale_val,
        SUM(CASE WHEN type = 'retail' THEN subtotal ELSE 0 END) as retail_val
    FROM sales
    WHERE sale_date BETWEEN ? AND ?
    GROUP BY sale_date
    ORDER BY sale_date ASC
";
$stmt = $db->prepare($chartSql);
$stmt->execute([$startDate, $endDate]);
$chartRows = $stmt->fetchAll();

$labels = [];
$wholesaleData = [];
$retailData = [];
foreach ($chartRows as $row) {
    $labels[] = date('d.m', strtotime($row['sale_date']));
    $wholesaleData[] = (float) $row['wholesale_val'];
    $retailData[] = (float) $row['retail_val'];
}
?>

<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Wholesale Sales',
                    data: <?= json_encode($wholesaleData) ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Retail Sales',
                    data: <?= json_encode($retailData) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f3f4f6' },
                    ticks: { callback: value => value.toLocaleString() + ' Kč' }
                },
                x: { grid: { display: false } }
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>