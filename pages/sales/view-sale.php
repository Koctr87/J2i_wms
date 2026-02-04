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
        $rate = 1;
        if ($dev['purchase_currency'] === 'EUR')
            $rate = $sale['currency_rate_eur'] ?: 25;
        elseif ($dev['purchase_currency'] === 'USD')
            $rate = $sale['currency_rate_usd'] ?: 23;

        $purchasePriceCZK = ($dev['purchase_price'] + ($dev['delivery_cost'] ?? 0)) * $rate;
    } elseif (!empty($item['accessory_id'])) {
        $acc = $db->query("SELECT a.*, t.name_en as type_name FROM accessories a JOIN accessory_types t ON a.type_id = t.id WHERE a.id = {$item['accessory_id']}")->fetch();
        $name = $acc['type_name'] . ': ' . $acc['name'];
        $purchasePriceCZK = 0; // Accessories logic simplified or purchase price needed
    }

    // Calculate Margin VAT
    $marginVat = 0;
    if ($item['vat_mode'] === 'marginal') {
        $sellingPrice = $item['unit_price'] * $item['quantity'];
        // Assuming purchase price applies to total quantity? No, unit purchase price * qty
        // Wait, device is 1 piece usually. If qty > 1, multiply.
        $totalPurchase = $purchasePriceCZK * $item['quantity'];
        $margin = $sellingPrice - $totalPurchase;
        $marginVat = $margin * (21 / 121);
    } elseif ($item['vat_mode'] === 'reverse') {
        // No VAT
    } else {
        // No VAT (or Standard? logic in new-sale was vat=0 for 'no')
    }

    if ($marginVat < 0)
        $marginVat = 0;
    $totalMarginVat += $marginVat;

    $items[] = [
        'name' => $name,
        'quantity' => $item['quantity'],
        'unit_price' => $item['unit_price'],
        'total_price' => $item['total_price'],
        'vat_mode' => $item['vat_mode'],
        'vat_amount' => $item['vat_amount'], // This is stored VAT (might be 0 for marginal in some logic, or calculated)
        'margin_vat_calc' => $marginVat // Calculated on fly for display
    ];
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <a href="history.php" class="btn btn-sm btn-outline" style="margin-right: 1rem;">←</a>
            <?= __('sale') ?> #
            <?= $sale['id'] ?>
            <span class="badge badge-<?= $sale['status'] === 'completed' ? 'success' : 'warning' ?>"
                style="margin-left: 0.5rem;">
                <?= ucfirst($sale['status']) ?>
            </span>
        </h3>
        <div style="display: flex; gap: 0.5rem;">
            <a href="edit-sale.php?id=<?= $sale['id'] ?>" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
                <?= __('edit') ?>
            </a>
            <button onclick="window.print()" class="btn btn-secondary">🖨️ Print</button>
        </div>
    </div>

    <div class="card-body">
        <!-- Info Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h4 style="margin-bottom: 0.5rem; color: var(--gray-600);">Supplier</h4>
                <strong>J2i Warehouse s.r.o.</strong><br>
                Example Street 123<br>
                123 00 Prague<br>
                IČO: 12345678
            </div>
            <div>
                <h4 style="margin-bottom: 0.5rem; color: var(--gray-600);">Customer</h4>
                <strong>
                    <?= e($sale['company_name']) ?>
                </strong><br>
                <?= e($sale['legal_address'] ?? '') ?><br>
                IČO:
                <?= e($sale['ico'] ?? '-') ?><br>
                DIČ:
                <?= e($sale['dic'] ?? '-') ?>
            </div>
        </div>

        <div
            style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; background: var(--gray-50); padding: 1rem; border-radius: var(--radius-md);">
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-600);">Date</div>
                <strong>
                    <?= formatDate($sale['sale_date']) ?>
                </strong>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-600);">Invoice No.</div>
                <strong>
                    <?= e($sale['invoice_number'] ?? '-') ?>
                </strong>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-600);">Created By</div>
                <strong>
                    <?= e($sale['creator_name'] ?? 'System') ?>
                </strong>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--gray-600);">Rates</div>
                EUR:
                <?= number_format($sale['currency_rate_eur'], 3) ?><br>
                USD:
                <?= number_format($sale['currency_rate_usd'], 3) ?>
            </div>
        </div>

        <!-- Items -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th>VAT Mode</th>
                        <th class="text-right">Margin VAT (21%)</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?= e($item['name']) ?>
                            </td>
                            <td class="text-right">
                                <?= $item['quantity'] ?>
                            </td>
                            <td class="text-right">
                                <?= formatCurrency($item['unit_price']) ?>
                            </td>
                            <td><span class="badge badge-gray">
                                    <?= $item['vat_mode'] ?>
                                </span></td>
                            <td class="text-right">
                                <?php if ($item['margin_vat_calc'] > 0): ?>
                                    <?= formatCurrency($item['margin_vat_calc']) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><strong>
                                    <?= formatCurrency($item['total_price']) ?>
                                </strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div style="display: flex; justify-content: flex-end; margin-top: 2rem;">
            <div style="min-width: 300px;">
                <div
                    style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                    <span>Subtotal:</span>
                    <strong>
                        <?= formatCurrency($sale['subtotal']) ?>
                    </strong>
                </div>
                <div
                    style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                    <span>Total Margin VAT:</span>
                    <strong>
                        <?= formatCurrency($totalMarginVat) ?>
                    </strong>
                </div>
                <div
                    style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
                    <span>Total Standard VAT:</span>
                    <strong>
                        <?= formatCurrency($sale['vat_amount']) ?>
                    </strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 1rem 0; font-size: 1.25rem;">
                    <span>Total:</span>
                    <strong>
                        <?= formatCurrency($sale['total']) ?>
                    </strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>