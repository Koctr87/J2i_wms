<?php
/**
 * J2i Warehouse Management System
 * Prices Management Page
 */
require_once __DIR__ . '/../../config/config.php';

$db = getDB();

// Current grade from URL (for display)
$currentGrade = $_GET['grade'] ?? 'A';
if (!in_array($currentGrade, ['A', 'B', 'C']))
    $currentGrade = 'A';

// Handle mass update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prices'])) {
    try {
        $db->beginTransaction();
        $postGrade = $_POST['current_grade'] ?? 'A';
        if (!in_array($postGrade, ['A', 'B', 'C']))
            $postGrade = 'A';

        foreach ($_POST['prices'] as $productId => $memories) {
            foreach ($memories as $memoryId => $conditions) {
                foreach ($conditions as $condition => $vatModes) {
                    foreach ($vatModes as $vatMode => $data) {
                        $wholesale = (float) ($data['wholesale'] ?? 0);
                        $retail = (float) ($data['retail'] ?? 0);
                        $currency = $data['currency'] ?? 'CZK';

                        // For USED items, use the selected grade; for NEW items, grade is always A
                        $grade = ($condition === 'used') ? $postGrade : 'A';

                        if ($wholesale > 0 || $retail > 0) {
                            $stmt = $db->prepare("
                                INSERT INTO product_prices (product_id, memory_id, `condition`, vat_mode, grade, wholesale_price, retail_price, currency)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                    wholesale_price = VALUES(wholesale_price),
                                    retail_price = VALUES(retail_price),
                                    currency = VALUES(currency)
                            ");
                            $stmt->execute([$productId, $memoryId, $condition, $vatMode, $grade, $wholesale, $retail, $currency]);
                        } else {
                            $stmt = $db->prepare("
                                DELETE FROM product_prices 
                                WHERE product_id = ? AND memory_id = ? AND `condition` = ? AND vat_mode = ? AND grade = ?
                            ");
                            $stmt->execute([$productId, $memoryId, $condition, $vatMode, $grade]);
                        }
                    }
                }
            }
        }

        $db->commit();
        setFlashMessage('success', __('success_save'));
        redirect('index.php?grade=' . $postGrade);
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

// Filters
$brandFilter = $_GET['brand'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for products — sorted by category priority: Phones(1) → Tablets(2) → Watches(4) → Laptops(3) → rest
try {
    $where = ["p.is_active = 1"];
    $params = [];

    if ($brandFilter) {
        $where[] = "p.brand_id = ?";
        $params[] = $brandFilter;
    }

    if ($search) {
        $where[] = "(p.name LIKE ? OR b.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereClause = "WHERE " . implode(" AND ", $where);

    $sql = "SELECT p.id as product_id, p.name as product_name, b.name as brand_name, b.id as brand_id,
                   c.id as category_id, c.name_{$current_lang} as category_name,
                   CASE c.id
                       WHEN 1 THEN 1
                       WHEN 2 THEN 2
                       WHEN 4 THEN 3
                       WHEN 3 THEN 4
                       ELSE 10
                   END as cat_sort
            FROM products p
            JOIN brands b ON p.brand_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            $whereClause
            ORDER BY cat_sort, c.id, b.name, p.name";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Get all memory options
    $memories = $db->query("SELECT * FROM memory_options WHERE id > 1 ORDER BY sort_order")->fetchAll();

    // Get existing prices for current grade (USED items) + grade A for NEW items
    $pricesRaw = $db->query("SELECT * FROM product_prices WHERE grade = " . $db->quote($currentGrade) . " OR `condition` = 'new'")->fetchAll();
    $prices = [];
    foreach ($pricesRaw as $p) {
        // For 'new' items, only take grade A; for 'used' items, take current grade 
        if ($p['condition'] === 'new' && $p['grade'] !== 'A')
            continue;
        $prices[$p['product_id']][$p['memory_id']][$p['condition']][$p['vat_mode']] = $p;
    }

    // Get brands for filter
    $brands = $db->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();

    // Get current rates for display
    try {
        $eurRateNow = getCNBRate('EUR') ?? 25.0;
        $usdRateNow = getCNBRate('USD') ?? 23.0;
    } catch (Exception $e) {
        $eurRateNow = 25.0;
        $usdRateNow = 23.0;
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$pageTitle = "Prices (Прайс-лист)";
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .price-grid-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .price-grid-table th,
    .price-grid-table td {
        border: 1px solid var(--gray-200);
        padding: 0.5rem;
    }

    .price-grid-table th {
        background: var(--bg-card);
        position: sticky;
        top: 0;
        z-index: 10;
        text-align: center;
    }

    /* First header row stays at very top */
    .price-grid-table thead tr:first-child th {
        top: 0;
        z-index: 11;
    }

    /* Second header row sits just below first header row */
    .price-grid-table thead tr:last-child th {
        top: 44px;
        z-index: 10;
    }

    .price-block {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .price-input-group {
        display: flex;
        gap: 0.25rem;
    }

    .price-input {
        width: 70px;
        padding: 0.25rem;
        border: 1px solid var(--gray-300);
        border-radius: 4px;
        text-align: right;
    }

    .currency-select {
        padding: 0.25rem;
        border: 1px solid var(--gray-300);
        border-radius: 4px;
        font-size: 0.75rem;
    }

    .model-name-cell {
        min-width: 150px;
        background: var(--bg-card);
    }

    .section-header {
        background: var(--gray-100);
        font-weight: 700;
        text-align: left !important;
        padding-left: 1rem !important;
    }

    .category-header {
        background: var(--gray-200);
        font-weight: 800;
        text-align: left !important;
        padding: 0.75rem 1rem !important;
        font-size: 0.95rem;
        color: var(--gray-800);
        letter-spacing: 0.02em;
    }

    .sticky-col {
        position: sticky;
        left: 0;
        background: var(--bg-card);
        z-index: 5;
    }

    .czk-hint {
        font-size: 0.7rem;
        color: var(--gray-500);
        text-align: right;
        margin-top: 2px;
    }

    .rate-badge {
        font-size: 0.75rem;
        background: var(--primary-light);
        color: var(--primary);
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
    }

    .import-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #10b981;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .import-btn:hover {
        background: #059669;
        transform: translateY(-1px);
    }

    /* Grade tabs */
    .grade-tabs {
        display: inline-flex;
        gap: 2px;
        background: rgba(0, 0, 0, 0.08);
        border-radius: 6px;
        padding: 2px;
        margin-left: 0.5rem;
        vertical-align: middle;
    }

    .grade-tab {
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 0.72rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        color: rgba(0, 0, 0, 0.5);
        text-decoration: none;
        line-height: 1.6;
    }

    .grade-tab:hover {
        background: rgba(255, 255, 255, 0.6);
        color: rgba(0, 0, 0, 0.8);
    }

    .grade-tab.active {
        background: var(--bg-card);
        color: var(--primary);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
    }
</style>

<!-- Load SheetJS for Excel parsing -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
            </svg>
            Управление ценами (Prices)
            <div style="margin-left: 1rem; display: flex; gap: 0.5rem;">
                <span class="rate-badge">EUR: <?= number_format($eurRateNow, 2) ?></span>
                <span class="rate-badge">USD: <?= number_format($usdRateNow, 2) ?></span>
            </div>
        </h3>
        <div class="card-actions">
            <input type="file" id="excelFile" style="display: none;" accept=".xlsx, .xls, .csv">
            <button type="button" class="btn import-btn" onclick="document.getElementById('excelFile').click()">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3" />
                </svg>
                <?= __('import_excel') ?>
            </button>
            <button type="submit" form="priceForm" class="btn btn-primary">
                <?= __('save') ?> изменения
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div style="padding: 1rem 1.5rem; background: var(--gray-50); border-bottom: 1px solid var(--gray-200);">
        <form method="GET" class="form-row" style="align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0; flex: 1;">
                <input type="text" name="search" class="form-control" placeholder="<?= __('search') ?>..."
                    value="<?= e($search) ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <select name="brand" class="form-control">
                    <option value=""><?= __('all') ?> <?= __('brands') ?></option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= $brand['id'] ?>" <?= $brandFilter == $brand['id'] ? 'selected' : '' ?>>
                            <?= e($brand['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="grade" value="<?= $currentGrade ?>">
            <button type="submit" class="btn btn-secondary">
                <?= __('filter') ?>
            </button>
            <label class="hide-empty-toggle"
                style="display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 0; cursor: pointer; user-select: none; font-size: 0.85rem; padding: 0.4rem 0.75rem; background: var(--primary-light); border-radius: var(--radius-md); border: 1px solid var(--primary); color: var(--primary); font-weight: 600;">
                <input type="checkbox" id="hideEmptyToggle" checked
                    style="accent-color: var(--primary); width: 16px; height: 16px;">
                Скрыть пустые
            </label>
        </form>
    </div>

    <div class="card-body" style="padding: 0; overflow: auto; max-height: calc(100vh - 250px);">
        <form method="POST" id="priceForm">
            <input type="hidden" name="current_grade" value="<?= $currentGrade ?>">
            <table class="price-grid-table" id="priceTable">
                <thead>
                    <tr>
                        <th rowspan="2" class="sticky-col">Модель + Память</th>
                        <th colspan="2" style="background: #dbeafe; color: #1e40af;">
                            USED - M-VAT
                            <div class="grade-tabs">
                                <?php foreach (['A', 'B', 'C'] as $g): ?>
                                    <a href="?grade=<?= $g ?>&brand=<?= urlencode($brandFilter) ?>&search=<?= urlencode($search) ?>"
                                        class="grade-tab <?= $currentGrade === $g ? 'active' : '' ?>"><?= $g ?></a>
                                <?php endforeach; ?>
                            </div>
                        </th>
                        <th colspan="2" style="background: #fef3c7; color: #92400e;">
                            USED - Reverse
                            <div class="grade-tabs">
                                <?php foreach (['A', 'B', 'C'] as $g): ?>
                                    <a href="?grade=<?= $g ?>&brand=<?= urlencode($brandFilter) ?>&search=<?= urlencode($search) ?>"
                                        class="grade-tab <?= $currentGrade === $g ? 'active' : '' ?>"><?= $g ?></a>
                                <?php endforeach; ?>
                            </div>
                        </th>
                        <th colspan="2" style="background: #dcfce7; color: #166534;">NEW - Reverse</th>
                    </tr>
                    <tr>
                        <!-- USED M-VAT -->
                        <th style="background: #eff6ff;">Опт / Розница</th>
                        <th style="background: #eff6ff;">Валюта</th>
                        <!-- USED Reverse -->
                        <th style="background: #fffbeb;">Опт / Розница</th>
                        <th style="background: #fffbeb;">Валюта</th>
                        <!-- NEW Reverse -->
                        <th style="background: #f0fdf4;">Опт / Розница</th>
                        <th style="background: #f0fdf4;">Валюта</th>
                    </tr>
                </thead>
                <tbody id="priceTableBody">
                    <?php
                    $rowIndex = 0;
                    $currentCategory = '';
                    $currentBrand = '';
                    foreach ($products as $product):
                        // Category header
                        $catName = $product['category_name'] ?? 'Other';
                        if ($currentCategory !== $catName):
                            $currentCategory = $catName;
                            $currentBrand = ''; // reset brand when category changes
                            ?>
                            <tr class="category-header-row">
                                <td colspan="7" class="category-header">
                                    📁 <?= e($currentCategory) ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php
                        if ($currentBrand !== $product['brand_name']):
                            $currentBrand = $product['brand_name'];
                            ?>
                            <tr class="brand-header-row">
                                <td colspan="7" class="section-header"><?= e($currentBrand) ?></td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($memories as $mem):
                            $pid = $product['product_id'];
                            $mid = $mem['id'];
                            $rowPrices = $prices[$pid][$mid] ?? [];

                            // Check if row has any prices
                            $hasAnyPrice = false;
                            $checkConfigs = [['used', 'marginal'], ['used', 'reverse'], ['new', 'reverse']];
                            foreach ($checkConfigs as $cc) {
                                $pd = $rowPrices[$cc[0]][$cc[1]] ?? null;
                                if ($pd && ((float) ($pd['wholesale_price'] ?? 0) > 0 || (float) ($pd['retail_price'] ?? 0) > 0)) {
                                    $hasAnyPrice = true;
                                    break;
                                }
                            }
                            ?>
                            <tr class="price-data-row <?= !$hasAnyPrice ? 'empty-row' : 'filled-row' ?>"
                                data-brand="<?= e($product['brand_name']) ?>" data-original-index="<?= $rowIndex++ ?>">
                                <td class="sticky-col model-name-cell">
                                    <strong><?= e($product['product_name']) ?></strong><br>
                                    <span class="text-muted"><?= e($mem['size']) ?></span>
                                </td>

                                <?php
                                $configs = [
                                    ['used', 'marginal'],
                                    ['used', 'reverse'],
                                    ['new', 'reverse']
                                ];

                                foreach ($configs as $cfg):
                                    $cond = $cfg[0];
                                    $vat = $cfg[1];
                                    $pData = $rowPrices[$cond][$vat] ?? null;
                                    ?>
                                    <td>
                                        <div class="price-block">
                                            <div class="price-input-group">
                                                <input type="number" step="0.01"
                                                    name="prices[<?= $pid ?>][<?= $mid ?>][<?= $cond ?>][<?= $vat ?>][wholesale]"
                                                    class="price-input wholesale-input" placeholder="Wh"
                                                    value="<?= $pData ? $pData['wholesale_price'] : '' ?>"
                                                    oninput="updateHint(this); updateRowState(this);">
                                                <input type="number" step="0.01"
                                                    name="prices[<?= $pid ?>][<?= $mid ?>][<?= $cond ?>][<?= $vat ?>][retail]"
                                                    class="price-input retail-input" placeholder="Rt"
                                                    value="<?= $pData ? $pData['retail_price'] : '' ?>"
                                                    oninput="updateHint(this); updateRowState(this);">
                                            </div>
                                            <div class="czk-hint"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <select name="prices[<?= $pid ?>][<?= $mid ?>][<?= $cond ?>][<?= $vat ?>][currency]"
                                            class="currency-select"
                                            onchange="updateHint(this.closest('tr').querySelector('.wholesale-input'))">
                                            <option value="CZK" <?= ($pData['currency'] ?? 'CZK') == 'CZK' ? 'selected' : '' ?>>Kč
                                            </option>
                                            <option value="EUR" <?= ($pData['currency'] ?? '') == 'EUR' ? 'selected' : '' ?>>€</option>
                                            <option value="USD" <?= ($pData['currency'] ?? '') == 'USD' ? 'selected' : '' ?>>$</option>
                                        </select>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
</div>

<script>
    const eurRate = <?= $eurRateNow ?>;
    const usdRate = <?= $usdRateNow ?>;

    function updateHint(input) {
        const row = input.closest('td');
        const configCell = input.closest('td');
        const currencySelect = configCell.nextElementSibling.querySelector('select');
        const hintDiv = row.querySelector('.czk-hint');

        const whInput = row.querySelector('.wholesale-input');
        const rtInput = row.querySelector('.retail-input');

        const currency = currencySelect.value;
        const rate = currency === 'EUR' ? eurRate : (currency === 'USD' ? usdRate : 1);

        if (currency === 'CZK') {
            hintDiv.innerHTML = '';
            return;
        }

        const whVal = parseFloat(whInput.value) || 0;
        const rtVal = parseFloat(rtInput.value) || 0;

        if (whVal > 0 || rtVal > 0) {
            hintDiv.innerHTML = `≈ ${(whVal * rate).toFixed(0)} / ${(rtVal * rate).toFixed(0)} Kč`;
        } else {
            hintDiv.innerHTML = '';
        }
    }

    // Update row state (filled/empty) when user types into inputs
    function updateRowState(input) {
        const row = input.closest('tr.price-data-row');
        if (!row) return;
        const inputs = row.querySelectorAll('.price-input');
        let hasValue = false;
        inputs.forEach(inp => {
            if (parseFloat(inp.value) > 0) hasValue = true;
        });
        row.classList.toggle('empty-row', !hasValue);
        row.classList.toggle('filled-row', hasValue);
        // Note: We intentionally do NOT call applyHideEmpty() here to prevent rows from jumping while typing.
    }

    // Hide empty rows logic with Brand Grouping preservation
    function applyHideEmpty() {
        const toggle = document.getElementById('hideEmptyToggle');
        const hide = toggle.checked;

        // Persist state
        localStorage.setItem('j2i_prices_hide_empty', hide);

        const tbody = document.getElementById('priceTableBody');
        const allRows = Array.from(tbody.children);

        // Group rows to preserve hierarchy: Category -> Brand -> Rows
        let structure = [];
        let currentBrandGroup = null;

        allRows.forEach(tr => {
            if (tr.classList.contains('category-header-row')) {
                structure.push({ type: 'cat', row: tr });
                currentBrandGroup = null; // Reset brand group
            } else if (tr.classList.contains('brand-header-row')) {
                currentBrandGroup = { type: 'brand', header: tr, rows: [] };
                structure.push(currentBrandGroup);
            } else if (tr.classList.contains('price-data-row')) {
                if (currentBrandGroup) {
                    currentBrandGroup.rows.push(tr);
                } else {
                    // Fallback for rows without brand header (should not happen with logic)
                }
            }
        });

        // Re-construct tbody
        structure.forEach(item => {
            if (item.type === 'cat') {
                tbody.appendChild(item.row);
            } else if (item.type === 'brand') {
                tbody.appendChild(item.header);

                let rows = item.rows;
                if (hide) {
                    // Sort: Filled first, Empty last. Within groups, maintain relative order (via index)
                    // Actually, we just need to partition them.
                    const filled = rows.filter(r => !r.classList.contains('empty-row'));
                    const empty = rows.filter(r => r.classList.contains('empty-row'));

                    filled.forEach(r => {
                        r.style.opacity = '1';
                        r.style.background = '';
                        tbody.appendChild(r);
                    });
                    empty.forEach(r => {
                        r.style.opacity = '0.4';
                        r.style.background = '#f9fafb';
                        tbody.appendChild(r);
                    });
                } else {
                    // Restore original order using data-original-index
                    const sorted = rows.sort((a, b) => {
                        return parseInt(a.dataset.originalIndex) - parseInt(b.dataset.originalIndex);
                    });

                    sorted.forEach(r => {
                        r.style.opacity = '1';
                        r.style.background = '';
                        tbody.appendChild(r);
                    });
                }
            }
        });
    }

    // Toggle handler
    document.getElementById('hideEmptyToggle').addEventListener('change', applyHideEmpty);

    // Initialize hints
    document.querySelectorAll('.wholesale-input').forEach(updateHint);

    // Apply hide-empty on page load based on localStorage or default
    document.addEventListener('DOMContentLoaded', () => {
        const saved = localStorage.getItem('j2i_prices_hide_empty');
        if (saved !== null) {
            document.getElementById('hideEmptyToggle').checked = (saved === 'true');
        }
        applyHideEmpty();
    });

    // Excel Import Logic
    document.getElementById('excelFile').onchange = async function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = async function (e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(firstSheet);

            if (jsonData.length === 0) {
                showToast('The file is empty', 'error');
                return;
            }

            if (!confirm(`Import ${jsonData.length} price records?`)) return;

            showToast('Importing... Please wait.', 'info');

            try {
                // Use relative path to avoid dependency on global JS constant
                const response = await fetch('../../api/ajax-handlers.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'import_prices',
                        items: jsonData
                    })
                });
                const result = await response.json();

                if (result.success) {
                    showToast(`Successfully imported ${result.count} prices!`, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message || 'Error importing prices', 'error');
                }
            } catch (err) {
                showToast('Network error during import', 'error');
                console.error(err);
            }
        };
        reader.readAsArrayBuffer(file);
        this.value = '';
    };

    // Prohibit form submission on Enter in inputs to prevent accidental save
    document.getElementById('priceForm').onkeydown = function (e) {
        if (e.keyCode == 13) {
            e.preventDefault();
        }
    };
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>