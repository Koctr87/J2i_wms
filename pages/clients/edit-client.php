<?php
/**
 * J2i Warehouse Management System
 * Edit Client Page with Comments
 */
require_once __DIR__ . '/../../config/config.php';

$db = getDB();

// Get client ID
$clientId = (int) ($_GET['id'] ?? 0);
if (!$clientId) {
    setFlashMessage('error', 'Клиент не найден');
    redirect('list.php');
}

// Get client data
$stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$clientId]);
$client = $stmt->fetch();

if (!$client) {
    setFlashMessage('error', 'Клиент не найден');
    redirect('list.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';

    try {
        if ($action === 'update') {
            $sameContact = isset($_POST['same_contact']) ? 1 : 0;

            // If same contact, copy manager info to warehouse contact
            $warehouseName = $sameContact ? $_POST['manager_name'] : $_POST['warehouse_contact_name'];
            $warehousePhone = $sameContact ? $_POST['manager_phone'] : $_POST['warehouse_contact_phone'];
            $warehouseEmail = $sameContact ? $_POST['manager_email'] : $_POST['warehouse_contact_email'];

            $stmt = $db->prepare("
                UPDATE clients SET
                    company_name = ?, ico = ?, dic = ?, legal_address = ?, warehouse_address = ?,
                    manager_name = ?, manager_phone = ?, manager_email = ?,
                    warehouse_contact_name = ?, warehouse_contact_phone = ?, warehouse_contact_email = ?,
                    same_contact = ?, notes = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $_POST['company_name'],
                $_POST['ico'] ?: null,
                $_POST['dic'] ?: null,
                $_POST['legal_address'] ?: null,
                $_POST['warehouse_address'] ?: null,
                $_POST['manager_name'] ?: null,
                $_POST['manager_phone'] ?: null,
                $_POST['manager_email'] ?: null,
                $warehouseName ?: null,
                $warehousePhone ?: null,
                $warehouseEmail ?: null,
                $sameContact,
                $_POST['notes'] ?: null,
                $clientId
            ]);

            logActivity('client_updated', 'client', $clientId);
            setFlashMessage('success', __('success_save'));
            redirect('edit-client.php?id=' . $clientId);

        } elseif ($action === 'add_comment') {
            $comment = trim($_POST['comment'] ?? '');
            if ($comment) {
                $stmt = $db->prepare("
                    INSERT INTO client_comments (client_id, comment, created_by)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$clientId, $comment, $_SESSION['user_id'] ?? null]);
                setFlashMessage('success', 'Комментарий добавлен');
            }
            redirect('edit-client.php?id=' . $clientId);

        } elseif ($action === 'delete_comment') {
            $commentId = (int) ($_POST['comment_id'] ?? 0);
            if ($commentId) {
                $stmt = $db->prepare("DELETE FROM client_comments WHERE id = ? AND client_id = ?");
                $stmt->execute([$commentId, $clientId]);
                setFlashMessage('success', 'Комментарий удалён');
            }
            redirect('edit-client.php?id=' . $clientId);
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get comments
$stmt = $db->prepare("
    SELECT c.*, u.first_name, u.last_name 
    FROM client_comments c
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.client_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$clientId]);
$comments = $stmt->fetchAll();

$pageTitle = __('edit') . ' ' . __('client');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
            </svg>
            <?= e($client['company_name']) ?>
        </h3>
        <span class="badge badge-<?= $client['is_active'] ? 'success' : 'danger' ?>">
            <?= $client['is_active'] ? 'Active' : 'Inactive' ?>
        </span>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="clientForm">
            <input type="hidden" name="action" value="update">

            <!-- Company Info -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">🏢 Информация о компании</h4>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label required">
                            <?= __('company_name') ?>
                        </label>
                        <input type="text" name="company_name" class="form-control" required
                            value="<?= e($client['company_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('ico') ?>
                        </label>
                        <input type="text" name="ico" class="form-control" maxlength="20"
                            value="<?= e($client['ico']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('dic') ?>
                        </label>
                        <input type="text" name="dic" class="form-control" maxlength="20"
                            value="<?= e($client['dic']) ?>">
                    </div>
                </div>
            </div>

            <!-- Addresses -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">📍 Адреса</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('legal_address') ?>
                        </label>
                        <textarea name="legal_address" class="form-control"
                            rows="2"><?= e($client['legal_address']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('warehouse_address') ?>
                        </label>
                        <textarea name="warehouse_address" class="form-control"
                            rows="2"><?= e($client['warehouse_address']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Manager Contact -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">👤
                    <?= __('manager_contact') ?>
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_name') ?>
                        </label>
                        <input type="text" name="manager_name" class="form-control"
                            value="<?= e($client['manager_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_phone') ?>
                        </label>
                        <input type="tel" name="manager_phone" class="form-control"
                            value="<?= e($client['manager_phone']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_email') ?>
                        </label>
                        <input type="email" name="manager_email" class="form-control"
                            value="<?= e($client['manager_email']) ?>">
                    </div>
                </div>
            </div>

            <!-- Same Contact Checkbox -->
            <div style="margin-bottom: 1.5rem;">
                <label class="form-check">
                    <input type="checkbox" name="same_contact" id="sameContact" class="form-check-input" value="1"
                        <?= $client['same_contact'] ? 'checked' : '' ?>>
                    <span class="form-check-label">
                        <?= __('same_contact') ?> (менеджер = складовщик)
                    </span>
                </label>
            </div>

            <!-- Warehouse Contact -->
            <div style="margin-bottom: 2rem; <?= $client['same_contact'] ? 'display: none;' : '' ?>"
                id="warehouseContactSection">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">📦
                    <?= __('warehouse_contact') ?>
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_name') ?>
                        </label>
                        <input type="text" name="warehouse_contact_name" class="form-control"
                            value="<?= e($client['warehouse_contact_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_phone') ?>
                        </label>
                        <input type="tel" name="warehouse_contact_phone" class="form-control"
                            value="<?= e($client['warehouse_contact_phone']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_email') ?>
                        </label>
                        <input type="email" name="warehouse_contact_email" class="form-control"
                            value="<?= e($client['warehouse_contact_email']) ?>">
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div style="margin-bottom: 2rem;">
                <div class="form-group">
                    <label class="form-label">
                        <?= __('notes') ?>
                    </label>
                    <textarea name="notes" class="form-control" rows="3"><?= e($client['notes']) ?></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="list.php" class="btn btn-secondary">
                    <?= __('cancel') ?>
                </a>
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                        <polyline points="17 21 17 13 7 13 7 21" />
                        <polyline points="7 3 7 8 15 8" />
                    </svg>
                    <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Comments Section -->
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            Комментарии
            <span class="badge badge-primary" style="margin-left: 0.5rem;">
                <?= count($comments) ?>
            </span>
        </h3>
    </div>
    <div class="card-body">
        <!-- Add Comment Form -->
        <form method="POST" style="margin-bottom: 1.5rem;">
            <input type="hidden" name="action" value="add_comment">
            <div class="form-group">
                <textarea name="comment" class="form-control" rows="2" placeholder="Напишите комментарий..."
                    required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Добавить комментарий
            </button>
        </form>

        <!-- Comments List -->
        <?php if (empty($comments)): ?>
            <div class="empty-state" style="padding: 2rem;">
                <p style="color: var(--gray-500);">Нет комментариев</p>
            </div>
        <?php else: ?>
            <div class="comments-list">
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-item"
                        style="padding: 1rem; background: var(--gray-50); border-radius: var(--radius); margin-bottom: 0.75rem;">
                        <div
                            style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            <div>
                                <strong style="color: var(--gray-800);">
                                    <?= e(($comment['first_name'] ?? '') . ' ' . ($comment['last_name'] ?? '')) ?: 'System' ?>
                                </strong>
                                <span style="color: var(--gray-500); font-size: 0.75rem; margin-left: 0.5rem;">
                                    <?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?>
                                </span>
                            </div>
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Удалить комментарий?');">
                                <input type="hidden" name="action" value="delete_comment">
                                <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                <button type="submit" class="btn btn-sm"
                                    style="padding: 0.25rem; background: none; border: none; color: var(--gray-400); cursor: pointer;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18" />
                                        <line x1="6" y1="6" x2="18" y2="18" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                        <p style="margin: 0; color: var(--gray-700); white-space: pre-wrap;">
                            <?= e($comment['comment']) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Toggle warehouse contact section
    document.getElementById('sameContact').addEventListener('change', function () {
        const section = document.getElementById('warehouseContactSection');
        if (this.checked) {
            section.style.display = 'none';
        } else {
            section.style.display = 'block';
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>