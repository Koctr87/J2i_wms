<?php
/**
 * J2i Warehouse Management System
 * Products (Models) Management Page
 */
require_once __DIR__ . '/../../config/config.php';

// Init DB
$db = getDB();

// Handle form submission (Must be before header)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO products (brand_id, category_id, name) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['brand_id'], $_POST['category_id'], $_POST['name']]);
            setFlashMessage('success', __('success_save'));
        } elseif ($action === 'update') {
            $stmt = $db->prepare("UPDATE products SET brand_id = ?, category_id = ?, name = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$_POST['brand_id'], $_POST['category_id'], $_POST['name'], isset($_POST['is_active']) ? 1 : 0, $_POST['id']]);
            setFlashMessage('success', __('success_save'));
        }
        redirect('products.php');
    } catch (Throwable $e) {
        setFlashMessage('error', $e->getMessage());
    }
}

try {
    // Pagination
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 30;

    // Filters
    $brandFilter = $_GET['brand'] ?? '';
    $categoryFilter = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';

    $where = [];
    $params = [];

    if ($brandFilter) {
        $where[] = "p.brand_id = ?";
        $params[] = $brandFilter;
    }

    if ($categoryFilter) {
        $where[] = "p.category_id = ?";
        $params[] = $categoryFilter;
    }

    if ($search) {
        $where[] = "(p.name LIKE ? OR b.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

    // Count
    $countSql = "SELECT COUNT(*) FROM products p JOIN brands b ON p.brand_id = b.id $whereClause";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    $pagination = getPagination($total, $page, $perPage);

    // Get products
    $lang = $current_lang ?? 'cs'; // fallback
    $sql = "SELECT p.*, b.name as brand_name, c.name_" . $lang . " as category_name,
                   (SELECT COUNT(*) FROM devices WHERE product_id = p.id) as device_count
            FROM products p
            JOIN brands b ON p.brand_id = b.id
            JOIN categories c ON p.category_id = c.id
            $whereClause
            ORDER BY b.name, p.name
            LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Get brands and categories for filters
    $brands = $db->query("SELECT * FROM brands WHERE is_active = 1 ORDER BY name")->fetchAll();
    $categories = $db->query("SELECT * FROM categories WHERE is_active = 1")->fetchAll();

} catch (Throwable $e) {
    die("<div class='alert alert-danger'>Error in products.php: " . $e->getMessage() . "</div>");
}

$pageTitle = __('models');
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
            <?= __('models') ?>
            <span class="badge badge-primary" style="margin-left: 0.5rem;">
                <?= $total ?>
            </span>
        </h3>
        <button class="btn btn-primary" onclick="openModal('addProductModal')">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <?= __('add') ?>
            <?= __('model') ?>
        </button>
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
                <select name="category" class="form-control">
                    <option value="">
                        <?= __('all') ?>
                        <?= __('categories') ?>
                    </option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                            <?= e(getLocalizedField($cat, 'name')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">
                <?= __('filter') ?>
            </button>
        </form>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
                </svg>
                <h3>
                    <?= __('no_data') ?>
                </h3>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>
                                <?= __('brand') ?>
                            </th>
                            <th>
                                <?= __('model') ?>
                            </th>
                            <th>
                                <?= __('category') ?>
                            </th>
                            <th>На складе</th>
                            <th>
                                <?= __('status') ?>
                            </th>
                            <th>
                                <?= __('actions') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><strong>
                                        <?= e($product['brand_name']) ?>
                                    </strong></td>
                                <td>
                                    <?= e($product['name']) ?>
                                </td>
                                <td><span class="badge badge-info">
                                        <?= e($product['category_name']) ?>
                                    </span></td>
                                <td><span class="badge badge-gray">
                                        <?= $product['device_count'] ?>
                                    </span></td>
                                <td>
                                    <span class="badge badge-<?= $product['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $product['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline"
                                        onclick="editProduct(<?= htmlspecialchars(json_encode($product)) ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </button>
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
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&brand=<?= $brandFilter ?>&category=<?= $categoryFilter ?>"
                            class="btn btn-sm btn-secondary">←</a>
                    <?php endif; ?>

                    <span style="padding: 0.375rem 0.875rem; color: var(--gray-600);">
                        <?= $page ?> /
                        <?= $pagination['total_pages'] ?>
                    </span>

                    <?php if ($pagination['has_next']): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&brand=<?= $brandFilter ?>&category=<?= $categoryFilter ?>"
                            class="btn btn-sm btn-secondary">→</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal-overlay" id="addProductModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('add') ?>
                <?= __('model') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('addProductModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('brand') ?>
                        </label>
                        <select name="brand_id" class="form-control" required>
                            <option value="">--</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>">
                                    <?= e($brand['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('category') ?>
                        </label>
                        <select name="category_id" class="form-control" required>
                            <option value="">--</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= e(getLocalizedField($cat, 'name')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label required">
                        <?= __('model') ?>
                    </label>
                    <input type="text" name="name" class="form-control" required placeholder="iPhone 15 Pro Max">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')">
                    <?= __('cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal-overlay" id="editProductModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('edit') ?>
                <?= __('model') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('editProductModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editProdId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('brand') ?>
                        </label>
                        <select name="brand_id" id="editProdBrand" class="form-control" required>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>">
                                    <?= e($brand['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">
                            <?= __('category') ?>
                        </label>
                        <select name="category_id" id="editProdCategory" class="form-control" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= e(getLocalizedField($cat, 'name')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label required">
                        <?= __('model') ?>
                    </label>
                    <input type="text" name="name" id="editProdName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" id="editProdActive" class="form-check-input">
                        <span class="form-check-label">Активна</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProductModal')">
                    <?= __('cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function editProduct(product) {
        document.getElementById('editProdId').value = product.id;
        document.getElementById('editProdBrand').value = product.brand_id;
        document.getElementById('editProdCategory').value = product.category_id;
        document.getElementById('editProdName').value = product.name;
        document.getElementById('editProdActive').checked = product.is_active == 1;
        openModal('editProductModal');
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>