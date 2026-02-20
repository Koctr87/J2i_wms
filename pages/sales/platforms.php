<?php
/**
 * J2i Warehouse Management System
 * Sales Platforms Management (Marketplaces)
 */
require_once __DIR__ . '/../../config/config.php';

$db = getDB();

// Cleanup duplicates if any
$db->exec("DELETE FROM sales_platforms WHERE id NOT IN (SELECT id FROM (SELECT MIN(id) as id FROM sales_platforms GROUP BY name) as tmp)");
// Ensure unique constraint exists
try {
    $db->exec("ALTER TABLE sales_platforms ADD UNIQUE INDEX (name)");
} catch (Exception $e) { /* already exists */
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $name = trim($_POST['name']);
            $commission = (float) $_POST['commission_percentage'];

            if ($name) {
                $stmt = $db->prepare("INSERT INTO sales_platforms (name, commission_percentage) VALUES (?, ?)");
                $stmt->execute([$name, $commission]);
                setFlashMessage('success', 'Platform added successfully');
            }
        } elseif ($action === 'update') {
            $id = (int) $_POST['id'];
            $name = trim($_POST['name']);
            $commission = (float) $_POST['commission_percentage'];
            $active = isset($_POST['is_active']) ? 1 : 0;

            if ($name) {
                $stmt = $db->prepare("UPDATE sales_platforms SET name = ?, commission_percentage = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $commission, $active, $id]);
                setFlashMessage('success', 'Platform updated successfully');
            }
        } elseif ($action === 'delete') {
            $id = (int) $_POST['id'];
            // Check if platform is used in sales first? 
            $stmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE platform_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                setFlashMessage('error', 'Cannot delete platform: it is used in existing sales');
            } else {
                $stmt = $db->prepare("DELETE FROM sales_platforms WHERE id = ?");
                $stmt->execute([$id]);
                setFlashMessage('success', 'Platform deleted');
            }
        }
        redirect('platforms.php');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all platforms
$platforms = $db->query("SELECT * FROM sales_platforms ORDER BY name ASC")->fetchAll();

$pageTitle = 'Marketplaces Conditions';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            Условия маркетплейсов (Retail Platforms)
        </h3>
        <button type="button" class="btn btn-primary" onclick="openAddModal()">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Добавить площадку
        </button>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (isset($error)): ?>
            <div class="alert alert-error" style="margin: 1rem;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название площадки</th>
                        <th>Комиссия (%)</th>
                        <th>Статус</th>
                        <th>Дата создания</th>
                        <th class="text-right">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($platforms)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding: 2rem;">Площадки не добавлены</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($platforms as $p): ?>
                        <tr>
                            <td>
                                <?= $p['id'] ?>
                            </td>
                            <td><strong>
                                    <?= e($p['name']) ?>
                                </strong></td>
                            <td>
                                <span class="badge badge-warning" style="font-size: 0.9rem;">
                                    <?= number_format($p['commission_percentage'], 2) ?>%
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $p['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="text-muted">
                                <?= date('d.m.Y', strtotime($p['created_at'])) ?>
                            </td>
                            <td class="text-right">
                                <button type="button" class="btn btn-sm btn-outline"
                                    onclick='openEditModal(<?= json_encode($p) ?>)'>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Вы уверены?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline" style="color: var(--danger);">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path
                                                d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                            </path>
                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="platformModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Добавить площадку</h3>
            <button type="button" class="modal-close" onclick="closeModal('platformModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="platformId">

                <div class="form-group">
                    <label class="form-label required">Название площадки</label>
                    <input type="text" name="name" id="platformName" class="form-control"
                        placeholder="E.g. eBay, Amazon, Local Shop" required>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label required">Комиссия (%)</label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="number" name="commission_percentage" id="platformCommission" class="form-control"
                            step="0.01" min="0" max="100" value="0.00" required>
                        <span style="font-weight: 600;">%</span>
                    </div>
                    <small class="text-muted">Эта комиссия будет автоматически вычитаться из маржи при розничной
                        продаже.</small>
                </div>

                <div class="form-group" id="statusGroup" style="margin-top: 1rem; display: none;">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" id="platformActive" class="form-check-input" value="1">
                        <span class="form-check-label">Активна (доступна для выбора)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 1rem; justify-content: flex-end; padding: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('platformModal')">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Добавить площадку';
        document.getElementById('formAction').value = 'add';
        document.getElementById('platformId').value = '';
        document.getElementById('platformName').value = '';
        document.getElementById('platformCommission').value = '0.00';
        document.getElementById('statusGroup').style.display = 'none';
        openModal('platformModal');
    }

    function openEditModal(p) {
        document.getElementById('modalTitle').textContent = 'Редактировать площадку';
        document.getElementById('formAction').value = 'update';
        document.getElementById('platformId').value = p.id;
        document.getElementById('platformName').value = p.name;
        document.getElementById('platformCommission').value = p.commission_percentage;
        document.getElementById('statusGroup').style.display = 'block';
        document.getElementById('platformActive').checked = p.is_active == 1;
        openModal('platformModal');
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>