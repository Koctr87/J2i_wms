<?php
/**
 * J2i Warehouse Management System
 * View Sale Page
 */
require_once __DIR__ . '/../../config/config.php';

$saleId = $_GET['id'] ?? 0;
if (!$saleId) {
    die("Sale ID missing");
}

$db = getDB();

// Fetch sale
$stmt = $db->prepare("SELECT s.*, c.company_name, c.ico, c.dic, c.legal_address, u.full_name as creator_name 
                      FROM sales s 
                      JOIN clients c ON s.client_id = c.id 
                      LEFT JOIN users u ON s.created_by = u.id
                      WHERE s.id = ?");
$stmt->execute([$saleId]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Sale not found");
}

$pageTitle = __('sale') . ' #' . $saleId;
require_once __DIR__ . '/../../includes/header.php';

// Fetch items
$stmt = $db->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
$stmt->execute([$saleId]);
$dbItems = $stmt->fetchAll();

$items = [];
$totalMarginVat = 0;
$totalItemProfit = 0;
$totalItemDeliveryExpenseCZK = 0;

foreach ($dbItems as $item) {
    $name = 'Unknown';
    $purchasePriceCZK = 0;

    if (!empty($item['device_id'])) {
        $dev = $db->query("SELECT d.*, p.name as product_name, b.name as brand_name, m.size as memory, c.name_en as color 
                           FROM devices d 
                           JOIN products p ON d.product_id = p.id 
                           JOIN brands b ON p.brand_id = b.id 
                           LEFT JOIN memory_options m ON d.memory_id = m.id 
                           LEFT JOIN color_options c ON d.color_id = c.id
                           WHERE d.id = {$item['device_id']}")->fetch();

        $name = trim($dev['brand_name'] . ' ' . $dev['product_name'] . ' ' . ($dev['memory'] ?? '') . ' ' . ($dev['color'] ?? ''));

        // Calculate Purchase Price in CZK for Margin
        $pRate = 1;
        if ($dev['purchase_currency'] === 'EUR')
            $pRate = $sale['currency_rate_eur'] ?: 25;
        elseif ($dev['purchase_currency'] === 'USD')
            $pRate = $sale['currency_rate_usd'] ?: 23;

        $dRate = 1;
        if (($dev['delivery_currency'] ?? 'CZK') === 'EUR')
            $dRate = $sale['currency_rate_eur'] ?: 25;
        elseif (($dev['delivery_currency'] ?? 'CZK') === 'USD')
            $dRate = $sale['currency_rate_usd'] ?: 23;

        $purchasePriceCZK = ($dev['purchase_price'] * $pRate) + (($dev['delivery_cost'] ?? 0) * $dRate);
    } elseif (!empty($item['accessory_id'])) {
        $acc = $db->query("SELECT a.*, t.name_en as type_name FROM accessories a JOIN accessory_types t ON a.type_id = t.id WHERE a.id = {$item['accessory_id']}")->fetch();
        $name = ($acc['type_name'] ?? 'Acc') . ': ' . ($acc['name'] ?? 'Unknown');

        $pRate = 1;
        if (($acc['purchase_currency'] ?? 'CZK') === 'EUR')
            $pRate = $sale['currency_rate_eur'] ?: 25;
        elseif (($acc['purchase_currency'] ?? 'CZK') === 'USD')
            $pRate = $sale['currency_rate_usd'] ?: 23;

        $purchasePriceCZK = ($acc['purchase_price'] ?? 0) * $pRate;
    }

    $saleCurrency = $item['sale_currency'] ?? 'CZK';
    $saleRate = 1;
    if ($saleCurrency === 'EUR')
        $saleRate = $sale['currency_rate_eur'] ?: 25;
    elseif ($saleCurrency === 'USD')
        $saleRate = $sale['currency_rate_usd'] ?: 23;

    $sellingPriceCZK = ($item['unit_price'] * $item['quantity']) * $saleRate;

    // Rule: Retail accessories are 0 price
    if (!empty($item['accessory_id']) && $sale['type'] === 'retail') {
        $sellingPriceCZK = 0;
    }

    $totalPurchaseCZK = $purchasePriceCZK * $item['quantity'];

    // Calculate Item Delivery Expense in CZK (Expense)
    $itemDelCurrency = $item['item_delivery_currency'] ?? 'CZK';
    $itemDelRate = 1;
    if ($itemDelCurrency === 'EUR')
        $itemDelRate = $sale['currency_rate_eur'] ?: 25;
    elseif ($itemDelCurrency === 'USD')
        $itemDelRate = $sale['currency_rate_usd'] ?: 23;
    $itemDeliveryExpense = ($item['item_delivery_cost'] ?? 0) * $itemDelRate * $item['quantity'];

    // Item Profit Calculation
    $vatToSubtract = 0;
    $marginVat = 0;
    if ($item['vat_mode'] === 'marginal') {
        $vatBase = $sellingPriceCZK - $totalPurchaseCZK;
        $vatToSubtract = $vatBase > 0 ? $vatBase * (21 / 121) : 0;
        $marginVat = $vatToSubtract;
    } else {
        $vatToSubtract = $item['vat_amount'] ?? 0;
        $marginVat = 0; // Standard VAT isn't "margin VAT"
    }

    // Profit = Revenue - Purchase Cost - Outbound Shipping - Commission - VAT
    // Commission is handled at the sale level, so we subtract it from total profit later
    $itemProfit = $sellingPriceCZK - $totalPurchaseCZK - $itemDeliveryExpense - $marginVat;

    $items[] = [
        'name' => $name,
        'quantity' => $item['quantity'],
        'unit_price' => (!empty($item['accessory_id']) && $sale['type'] === 'retail') ? 0 : $item['unit_price'],
        'sale_currency' => $saleCurrency,
        'total_price' => (!empty($item['accessory_id']) && $sale['type'] === 'retail') ? 0 : $item['unit_price'] * $item['quantity'],
        'vat_mode' => $item['vat_mode'],
        'vat_amount' => $item['vat_amount'],
        'margin_vat_calc' => $marginVat,
        'item_delivery_cost' => $item['item_delivery_cost'] ?? 0,
        'item_delivery_currency' => $itemDelCurrency,
        'selling_price_czk' => $sellingPriceCZK,
    ];

    $totalItemProfit += $itemProfit;
    $totalMarginVat += $marginVat;
    $totalItemDeliveryExpenseCZK += $itemDeliveryExpense;
}

// Recalculate Subtotal and Total for display to handle retail accessory rules
$calculatedSubtotal = array_reduce($items, function ($sum, $item) use ($sale) {
    // Re-calculating in CZK
    return $sum + $item['selling_price_czk'];
}, 0);

$delRate = 1;
if (($sale['sale_delivery_currency'] ?? 'CZK') === 'EUR')
    $delRate = $sale['currency_rate_eur'];
elseif (($sale['sale_delivery_currency'] ?? 'CZK') === 'USD')
    $delRate = $sale['currency_rate_usd'];
$delCZK = ($sale['sale_delivery_cost'] ?? 0) * $delRate;

$calculatedTotal = $calculatedSubtotal + $delCZK;
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <a href="history.php" class="btn btn-sm btn-outline" style="margin-right: 1rem;">←</a>
            <?= __('sale') ?> #<?= $sale['id'] ?>
            <span class="badge badge-<?= $sale['status'] === 'completed' ? 'success' : 'warning' ?>"
                style="margin-left: 0.5rem;">
                <?= ucfirst($sale['status']) ?>
            </span>
        </h3>
        <div style="display: flex; gap: 0.5rem;">
            <a href="edit-sale.php?id=<?= $sale['id'] ?>" class="btn btn-primary">
                <?= __('edit') ?>
            </a>
            <button onclick="window.print()" class="btn btn-secondary">🖨️ Print</button>
        </div>
    </div>

    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h4 style="margin-bottom: 0.5rem; color: var(--gray-600);">Supplier</h4>
                <strong>J2i Warehouse s.r.o.</strong><br>
                Prague, CZ
            </div>
            <div>
                <h4 style="margin-bottom: 0.5rem; color: var(--gray-600);">Customer</h4>
                <strong><?= e($sale['company_name']) ?></strong><br>
                IČO: <?= e($sale['ico'] ?? '-') ?>
            </div>
        </div>

        <div
            style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; background: var(--gray-50); padding: 1rem; border-radius: var(--radius-md);">
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-600);">Date</div>
                <strong><?= formatDate($sale['sale_date']) ?></strong>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-600);">Invoice No.</div>
                <strong><?= e($sale['invoice_number'] ?? '-') ?></strong>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-600);">Rates</div>
                EUR: <?= number_format($sale['currency_rate_eur'], 3) ?><br>
                USD: <?= number_format($sale['currency_rate_usd'], 3) ?>
            </div>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Deliv. (Exp)</th>
                        <th class="text-right">Total</th>
                        <th>VAT Mode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['name']) ?></td>
                            <td class="text-right"><?= $item['quantity'] ?></td>
                            <td class="text-right"><?= formatCurrency($item['unit_price'], $item['sale_currency']) ?></td>
                            <td class="text-right">
                                <?= formatCurrency($item['item_delivery_cost'], $item['item_delivery_currency']) ?>
                            </td>
                            <td class="text-right">
                                <strong><?= formatCurrency($item['total_price'], $item['sale_currency']) ?></strong>
                            </td>
                            <td><span class="badge badge-gray"><?= $item['vat_mode'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 2rem;">
            <div style="min-width: 350px;">
                <div
                    style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                    <span>Subtotal:</span>
                    <strong><?= formatCurrency($calculatedSubtotal) ?></strong>
                </div>
                <div
                    style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                    <span>Delivery:</span>
                    <strong><?= formatCurrency($delCZK) ?></strong>
                </div>
                <div
                    style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                    <span>Item Delivery (Exp):</span>
                    <strong><?= formatCurrency($totalItemDeliveryExpenseCZK) ?></strong>
                </div>
                <div
                    style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                    <span>Margin VAT:</span>
                    <strong><?= formatCurrency($totalMarginVat) ?></strong>
                </div>
                <div
                    style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                    <span>Commission:</span>
                    <strong style="color: #f59e0b;">-
                        <?= formatCurrency($sale['platform_commission_amount'] ?? 0) ?></strong>
                </div>
                <div
                    style="display: flex; justify-content: space-between; padding: 1rem 0; font-size: 1.25rem; border-top: 2px solid var(--gray-100);">
                    <span>Total:</span>
                    <strong><?= formatCurrency($calculatedTotal) ?></strong>
                </div>
                <div
                    style="display: flex; justify-content: space-between; padding: 1rem; background: var(--success-light); border-radius: 8px; color: var(--success); margin-top: 1rem;">
                    <span style="font-weight: 700;">Net Profit:</span>
                    <strong>
                        <?php
                        $netProfit = $totalItemProfit + $delCZK - ($sale['platform_commission_amount'] ?? 0);
                        echo formatCurrency($netProfit);
                        ?>
                    </strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>