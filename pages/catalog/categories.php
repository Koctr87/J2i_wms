<?php
/**
 * J2i Warehouse Management System
 * Categories Management Page
 */
require_once __DIR__ . '/../../config/config.php';

try {
    $db = getDB();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $stmt = $db->prepare("INSERT INTO categories (name_ru, name_cs, name_uk, name_en, icon) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name_ru'], $_POST['name_cs'], $_POST['name_uk'], $_POST['name_en'], $_POST['icon'] ?: null]);
            setFlashMessage('success', __('success_save'));
        } elseif ($action === 'update') {
            $stmt = $db->prepare("UPDATE categories SET name_ru = ?, name_cs = ?, name_uk = ?, name_en = ?, icon = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$_POST['name_ru'], $_POST['name_cs'], $_POST['name_uk'], $_POST['name_en'], $_POST['icon'] ?: null, isset($_POST['is_active']) ? 1 : 0, $_POST['id']]);
            setFlashMessage('success', __('success_save'));
        }
        redirect('categories.php');
    }

    // Get all categories
    $categories = $db->query("SELECT *, (SELECT COUNT(*) FROM products WHERE category_id = categories.id) as product_count FROM categories ORDER BY name_en")->fetchAll();

} catch (Throwable $e) {
    die("<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>");
}

$pageTitle = __('categories');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <line x1="12" y1="2" x2="12" y2="22" />
            </svg>
            <?= __('categories') ?>
            <span class="badge badge-primary" style="margin-left: 0.5rem;">
                <?= count($categories) ?>
            </span>
        </h3>
        <button class="btn btn-primary" onclick="openModal('addCategoryModal')">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <?= __('add') ?>
            <?= __('category') ?>
        </button>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Icon</th>
                        <th>RU</th>
                        <th>CS</th>
                        <th>UK</th>
                        <th>EN</th>
                        <th>Моделей</th>
                        <th>
                            <?= __('status') ?>
                        </th>
                        <th>
                            <?= __('actions') ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td style="font-size: 1.5rem;">
                                <?= e($cat['icon'] ?? '📦') ?>
                            </td>
                            <td>
                                <?= e($cat['name_ru']) ?>
                            </td>
                            <td>
                                <?= e($cat['name_cs']) ?>
                            </td>
                            <td>
                                <?= e($cat['name_uk']) ?>
                            </td>
                            <td><strong>
                                    <?= e($cat['name_en']) ?>
                                </strong></td>
                            <td><span class="badge badge-gray">
                                    <?= $cat['product_count'] ?>
                                </span></td>
                            <td>
                                <span class="badge badge-<?= $cat['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline"
                                    onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)">
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
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal-overlay" id="addCategoryModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('add') ?>
                <?= __('category') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('addCategoryModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">🇷🇺 Русский</label>
                        <input type="text" name="name_ru" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">🇨🇿 Čeština</label>
                        <input type="text" name="name_cs" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">🇺🇦 Українська</label>
                        <input type="text" name="name_uk" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">🇬🇧 English</label>
                        <input type="text" name="name_en" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Иконка (emoji)</label>
                    <input type="text" name="icon" class="form-control" placeholder="📱 📦 💻">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCategoryModal')">
                    <?= __('cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal-overlay" id="editCategoryModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('edit') ?>
                <?= __('category') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('editCategoryModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editCatId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">🇷🇺 Русский</label>
                        <input type="text" name="name_ru" id="editCatRu" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">🇨🇿 Čeština</label>
                        <input type="text" name="name_cs" id="editCatCs" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">🇺🇦 Українська</label>
                        <input type="text" name="name_uk" id="editCatUk" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">🇬🇧 English</label>
                        <input type="text" name="name_en" id="editCatEn" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Иконка (emoji)</label>
                        <input type="text" name="icon" id="editCatIcon" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-check" style="margin-top: 2rem;">
                            <input type="checkbox" name="is_active" id="editCatActive" class="form-check-input">
                            <span class="form-check-label">Активна</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCategoryModal')">
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
    function editCategory(cat) {
        document.getElementById('editCatId').value = cat.id;
        document.getElementById('editCatRu').value = cat.name_ru;
        document.getElementById('editCatCs').value = cat.name_cs;
        document.getElementById('editCatUk').value = cat.name_uk;
        document.getElementById('editCatEn').value = cat.name_en;
        document.getElementById('editCatIcon').value = cat.icon || '';
        document.getElementById('editCatActive').checked = cat.is_active == 1;
        openModal('editCategoryModal');
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>