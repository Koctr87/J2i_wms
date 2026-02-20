<?php
/**
 * J2i Warehouse Management System
 * Brands Management Page
 */
require_once __DIR__ . '/../../config/config.php';

// Define upload directory
define('BRAND_LOGO_DIR', __DIR__ . '/../../uploads/brands/');
define('BRAND_LOGO_URL', APP_URL . '/uploads/brands/');

try {
    $db = getDB();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            // Handle logo upload
            $logoPath = null;
            if (!empty($_FILES['logo_file']['name'])) {
                $logoPath = uploadBrandLogo($_FILES['logo_file']);
            } elseif (!empty($_POST['logo_url'])) {
                $logoPath = $_POST['logo_url'];
            }

            $stmt = $db->prepare("INSERT INTO brands (name, logo_url, website) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['name'], $logoPath, $_POST['website'] ?: null]);
            setFlashMessage('success', __('success_save'));

        } elseif ($action === 'update') {
            // Handle logo upload
            $logoPath = $_POST['existing_logo'] ?? null;
            if (!empty($_FILES['logo_file']['name'])) {
                // Delete old logo if it's a local file
                if ($logoPath && strpos($logoPath, '/uploads/brands/') !== false) {
                    $oldFile = __DIR__ . '/../../' . parse_url($logoPath, PHP_URL_PATH);
                    if (file_exists($oldFile)) {
                        @unlink($oldFile);
                    }
                }
                $logoPath = uploadBrandLogo($_FILES['logo_file']);
            } elseif (!empty($_POST['logo_url']) && $_POST['logo_url'] !== $logoPath) {
                $logoPath = $_POST['logo_url'];
            }

            $stmt = $db->prepare("UPDATE brands SET name = ?, logo_url = ?, website = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $logoPath, $_POST['website'] ?: null, isset($_POST['is_active']) ? 1 : 0, $_POST['id']]);
            setFlashMessage('success', __('success_save'));

        } elseif ($action === 'delete') {
            // Get logo path before deactivating
            $stmt = $db->prepare("SELECT logo_url FROM brands WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $brand = $stmt->fetch();

            $stmt = $db->prepare("UPDATE brands SET is_active = 0 WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            setFlashMessage('success', 'Бренд деактивирован');

        } elseif ($action === 'create_model') {
            $stmt = $db->prepare("INSERT INTO products (brand_id, category_id, name) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['brand_id'], $_POST['category_id'], $_POST['model_name']]);
            setFlashMessage('success', __('success_save'));

        } elseif ($action === 'delete_model') {
            // Check if there are devices linked to this model
            $stmt = $db->prepare("SELECT COUNT(*) FROM devices WHERE product_id = ?");
            $stmt->execute([$_POST['model_id']]);
            $deviceCount = $stmt->fetchColumn();

            if ($deviceCount > 0) {
                setFlashMessage('error', 'Невозможно удалить модель - есть связанные устройства на складе');
            } else {
                $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$_POST['model_id']]);
                setFlashMessage('success', 'Модель удалена');
            }
        }
        redirect('brands.php');
    }

    // Get all brands with models count
    $brands = $db->query("
        SELECT b.*, 
               (SELECT COUNT(*) FROM products WHERE brand_id = b.id) as model_count,
               (SELECT COUNT(*) FROM devices d JOIN products p ON d.product_id = p.id WHERE p.brand_id = b.id) as device_count
        FROM brands b 
        ORDER BY b.name
    ")->fetchAll();

    // Get categories for model creation
    $categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name_en")->fetchAll();

    // Get all models grouped by brand for the modal
    $allModels = $db->query("
        SELECT p.*, c.name_ru, c.name_cs, c.name_uk, c.name_en
        FROM products p 
        JOIN categories c ON p.category_id = c.id
        ORDER BY p.name
    ")->fetchAll();

    // Group models by brand_id
    $modelsByBrand = [];
    foreach ($allModels as $model) {
        $modelsByBrand[$model['brand_id']][] = $model;
    }

} catch (Throwable $e) {
    die("<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>");
}

/**
 * Upload brand logo file
 * @param array $file $_FILES array element
 * @return string|null URL path to uploaded file
 */
function uploadBrandLogo(array $file): ?string
{
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if (!in_array($file['type'], $allowedTypes)) {
        setFlashMessage('error', 'Недопустимый формат файла. Разрешены: JPG, PNG, GIF, WebP, SVG');
        return null;
    }

    if ($file['size'] > $maxSize) {
        setFlashMessage('error', 'Файл слишком большой. Максимум 2MB');
        return null;
    }

    // Create directory if not exists
    if (!is_dir(BRAND_LOGO_DIR)) {
        mkdir(BRAND_LOGO_DIR, 0755, true);
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('brand_') . '.' . strtolower($ext);
    $filepath = BRAND_LOGO_DIR . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return BRAND_LOGO_URL . $filename;
    }

    return null;
}

$pageTitle = __('brands');
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .brand-row {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .brand-row:hover {
        background-color: var(--gray-50);
    }

    .brand-row.expanded {
        background-color: var(--primary-50);
    }

    .models-container {
        display: none;
        background: var(--gray-50);
        border-top: 1px solid var(--gray-200);
    }

    .models-container.show {
        display: table-row;
    }

    .models-list {
        padding: 1rem 1.5rem 1rem 4rem;
    }

    .model-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 0.75rem;
        background: var(--bg-card);
        border-radius: var(--radius);
        margin-bottom: 0.5rem;
        border: 1px solid var(--gray-200);
    }

    .model-item:last-child {
        margin-bottom: 0;
    }

    .model-name {
        font-weight: 500;
    }

    .model-category {
        font-size: 0.75rem;
        color: var(--gray-500);
    }

    .add-model-inline {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px dashed var(--gray-300);
    }

    .logo-upload-container {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .logo-preview {
        width: 60px;
        height: 60px;
        border: 2px dashed var(--gray-300);
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: var(--gray-100);
    }

    .logo-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .logo-preview-placeholder {
        color: var(--gray-400);
        font-size: 0.75rem;
        text-align: center;
    }

    .expand-icon {
        transition: transform 0.2s ease;
        margin-right: 0.5rem;
    }

    .brand-row.expanded .expand-icon {
        transform: rotate(90deg);
    }

    .upload-tabs {
        display: flex;
        gap: 0.25rem;
        margin-bottom: 0.5rem;
    }

    .upload-tab {
        padding: 0.375rem 0.75rem;
        border: 1px solid var(--gray-200);
        background: var(--gray-100);
        border-radius: var(--radius);
        cursor: pointer;
        font-size: 0.75rem;
        transition: all 0.15s ease;
    }

    .upload-tab.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .upload-file-input,
    .upload-url-input {
        display: none;
    }

    .upload-file-input.active,
    .upload-url-input.active {
        display: block;
    }
</style>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
                <line x1="7" y1="7" x2="7.01" y2="7" />
            </svg>
            <?= __('brands') ?>
            <span class="badge badge-primary" style="margin-left: 0.5rem;">
                <?= count($brands) ?>
            </span>
        </h3>
        <button class="btn btn-primary" onclick="openModal('addBrandModal')">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <?= __('add') ?>
            <?= __('brand') ?>
        </button>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>Логотип</th>
                        <th>
                            <?= __('brand') ?>
                        </th>
                        <th>Сайт</th>
                        <th>Моделей</th>
                        <th>Устройств</th>
                        <th>
                            <?= __('status') ?>
                        </th>
                        <th>
                            <?= __('actions') ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($brands as $brand): ?>
                        <tr class="brand-row" onclick="toggleModels(<?= $brand['id'] ?>, event)"
                            data-brand-id="<?= $brand['id'] ?>">
                            <td>
                                <svg class="expand-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </td>
                            <td>
                                <?php if ($brand['logo_url']): ?>
                                    <img src="<?= e($brand['logo_url']) ?>" alt="<?= e($brand['name']) ?>"
                                        style="max-width: 40px; max-height: 40px; border-radius: var(--radius);">
                                <?php else: ?>
                                    <div
                                        style="width: 40px; height: 40px; background: var(--gray-200); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: var(--gray-400); font-weight: 600;">
                                        <?= mb_substr($brand['name'], 0, 1) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong>
                                    <?= e($brand['name']) ?>
                                </strong></td>
                            <td>
                                <?php if ($brand['website']): ?>
                                    <a href="<?= e($brand['website']) ?>" target="_blank" onclick="event.stopPropagation();">
                                        <?= e(parse_url($brand['website'], PHP_URL_HOST)) ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-info">
                                    <?= $brand['model_count'] ?>
                                </span></td>
                            <td><span class="badge badge-gray">
                                    <?= $brand['device_count'] ?>
                                </span></td>
                            <td>
                                <span class="badge badge-<?= $brand['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $brand['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline"
                                    onclick="event.stopPropagation(); editBrand(<?= htmlspecialchars(json_encode($brand)) ?>)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <!-- Models sub-row -->
                        <tr class="models-container" id="models-<?= $brand['id'] ?>">
                            <td colspan="8">
                                <div class="models-list">
                                    <strong style="display: block; margin-bottom: 0.5rem; color: var(--gray-700);">
                                        Модели <?= e($brand['name']) ?>:
                                    </strong>
                                    <?php if (isset($modelsByBrand[$brand['id']]) && count($modelsByBrand[$brand['id']]) > 0): ?>
                                        <?php foreach ($modelsByBrand[$brand['id']] as $model): ?>
                                            <div class="model-item">
                                                <div>
                                                    <span class="model-name"><?= e($model['name']) ?></span>
                                                    <span
                                                        class="model-category">(<?= e(getLocalizedField($model, 'name')) ?>)</span>
                                                </div>
                                                <form method="POST" style="margin: 0;"
                                                    onsubmit="return confirm('Удалить эту модель?');">
                                                    <input type="hidden" name="action" value="delete_model">
                                                    <input type="hidden" name="model_id" value="<?= $model['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Удалить модель">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                            <path
                                                                d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p style="color: var(--gray-500); font-size: 0.875rem; margin: 0;">
                                            Нет моделей для этого бренда
                                        </p>
                                    <?php endif; ?>

                                    <!-- Add model inline form -->
                                    <form method="POST" class="add-model-inline" onclick="event.stopPropagation();">
                                        <input type="hidden" name="action" value="create_model">
                                        <input type="hidden" name="brand_id" value="<?= $brand['id'] ?>">
                                        <input type="text" name="model_name" class="form-control"
                                            placeholder="Название модели" required style="flex: 2;">
                                        <select name="category_id" class="form-control" required style="flex: 1;">
                                            <option value="">Категория</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>"><?= e(getLocalizedField($cat, 'name')) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                            Добавить
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Brand Modal -->
<div class="modal-overlay" id="addBrandModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('add') ?>
                <?= __('brand') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('addBrandModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">
                        <?= __('brand') ?>
                    </label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Логотип</label>
                    <div class="logo-upload-container">
                        <div class="upload-tabs">
                            <span class="upload-tab active" onclick="switchUploadTab(this, 'add', 'file')">📁 Загрузить
                                файл</span>
                            <span class="upload-tab" onclick="switchUploadTab(this, 'add', 'url')">🔗 URL ссылка</span>
                        </div>
                        <div class="upload-file-input active" id="add-file-input">
                            <input type="file" name="logo_file" class="form-control" accept="image/*"
                                onchange="previewLogo(this, 'addLogoPreview')">
                        </div>
                        <div class="upload-url-input" id="add-url-input">
                            <input type="url" name="logo_url" class="form-control" placeholder="https://...">
                        </div>
                        <div class="logo-preview" id="addLogoPreview">
                            <span class="logo-preview-placeholder">Предпросмотр</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Сайт</label>
                    <input type="url" name="website" class="form-control" placeholder="https://...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addBrandModal')">
                    <?= __('cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Brand Modal -->
<div class="modal-overlay" id="editBrandModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('edit') ?>
                <?= __('brand') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('editBrandModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editBrandId">
            <input type="hidden" name="existing_logo" id="editBrandExistingLogo">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">
                        <?= __('brand') ?>
                    </label>
                    <input type="text" name="name" id="editBrandName" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Логотип</label>
                    <div class="logo-upload-container">
                        <div class="upload-tabs">
                            <span class="upload-tab active" onclick="switchUploadTab(this, 'edit', 'file')">📁 Загрузить
                                файл</span>
                            <span class="upload-tab" onclick="switchUploadTab(this, 'edit', 'url')">🔗 URL ссылка</span>
                        </div>
                        <div class="upload-file-input active" id="edit-file-input">
                            <input type="file" name="logo_file" class="form-control" accept="image/*"
                                onchange="previewLogo(this, 'editLogoPreview')">
                        </div>
                        <div class="upload-url-input" id="edit-url-input">
                            <input type="url" name="logo_url" id="editBrandLogo" class="form-control">
                        </div>
                        <div class="logo-preview" id="editLogoPreview">
                            <span class="logo-preview-placeholder">Предпросмотр</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Сайт</label>
                    <input type="url" name="website" id="editBrandWebsite" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" id="editBrandActive" class="form-check-input">
                        <span class="form-check-label">Активен</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editBrandModal')">
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
    function editBrand(brand) {
        document.getElementById('editBrandId').value = brand.id;
        document.getElementById('editBrandName').value = brand.name;
        document.getElementById('editBrandLogo').value = brand.logo_url || '';
        document.getElementById('editBrandExistingLogo').value = brand.logo_url || '';
        document.getElementById('editBrandWebsite').value = brand.website || '';
        document.getElementById('editBrandActive').checked = brand.is_active == 1;

        // Show logo preview if exists
        const preview = document.getElementById('editLogoPreview');
        if (brand.logo_url) {
            preview.innerHTML = `<img src="${brand.logo_url}" alt="Logo">`;
        } else {
            preview.innerHTML = '<span class="logo-preview-placeholder">Предпросмотр</span>';
        }

        openModal('editBrandModal');
    }

    function toggleModels(brandId, event) {
        // Don't toggle if clicking on a button or link
        if (event.target.closest('button') || event.target.closest('a') || event.target.closest('form')) {
            return;
        }

        const row = document.querySelector(`tr[data-brand-id="${brandId}"]`);
        const modelsRow = document.getElementById(`models-${brandId}`);

        row.classList.toggle('expanded');
        modelsRow.classList.toggle('show');
    }

    function switchUploadTab(tab, modalPrefix, type) {
        // Update tabs
        tab.parentElement.querySelectorAll('.upload-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        // Update inputs
        const fileInput = document.getElementById(`${modalPrefix}-file-input`);
        const urlInput = document.getElementById(`${modalPrefix}-url-input`);

        if (type === 'file') {
            fileInput.classList.add('active');
            urlInput.classList.remove('active');
        } else {
            fileInput.classList.remove('active');
            urlInput.classList.add('active');
        }
    }

    function previewLogo(input, previewId) {
        const preview = document.getElementById(previewId);

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.innerHTML = '<span class="logo-preview-placeholder">Предпросмотр</span>';
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>