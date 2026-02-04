<?php
/**
 * J2i Warehouse Management System
 * Clients List Page
 */
require_once __DIR__ . '/../../config/config.php';

try {
    $db = getDB();

    // Pagination
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;

    $search = $_GET['search'] ?? '';

    $where = "";
    $params = [];

    if ($search) {
        $where = "WHERE (company_name LIKE ? OR ico LIKE ?)";
        $params = ["%$search%", "%$search%"];
    }

    // Count total
    $countSql = "SELECT COUNT(*) FROM clients $where";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    $pagination = getPagination($total, $page, $perPage);

    // Get clients
    $sql = "SELECT * FROM clients $where ORDER BY company_name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

} catch (Throwable $e) {
    die("<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>");
}

$pageTitle = __('clients');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            <?= __('client_list') ?>
            <span class="badge badge-primary" style="margin-left: 0.5rem;">
                <?= $total ?>
            </span>
        </h3>
        <a href="add-client.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <?= __('add_client') ?>
        </a>
    </div>

    <!-- Search -->
    <div style="padding: 1rem 1.5rem; background: var(--gray-50); border-bottom: 1px solid var(--gray-200);">
        <form method="GET" style="display: flex; gap: 1rem;">
            <input type="text" name="search" class="form-control" placeholder="<?= __('search') ?>..."
                value="<?= e($search) ?>" style="max-width: 300px;">
            <button type="submit" class="btn btn-secondary">
                <?= __('search') ?>
            </button>
        </form>
    </div>

    <div class="card-body" style="padding: 0;">
        <?php if (empty($clients)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                </svg>
                <h3>
                    <?= __('no_data') ?>
                </h3>
                <p><a href="add-client.php">
                        <?= __('add_client') ?>
                    </a></p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>
                                <?= __('company_name') ?>
                            </th>
                            <th>
                                <?= __('ico') ?>
                            </th>
                            <th>
                                <?= __('dic') ?>
                            </th>
                            <th>
                                <?= __('manager_contact') ?>
                            </th>
                            <th>
                                <?= __('warehouse_contact') ?>
                            </th>
                            <th>
                                <?= __('actions') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?= e($client['company_name']) ?>
                                    </strong>
                                    <?php if ($client['legal_address']): ?>
                                        <br><small class="text-muted">
                                            <?= e(mb_substr($client['legal_address'], 0, 50)) ?>...
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e($client['ico'] ?? '-') ?>
                                </td>
                                <td>
                                    <?= e($client['dic'] ?? '-') ?>
                                </td>
                                <td>
                                    <?php if ($client['manager_name']): ?>
                                        <strong>
                                            <?= e($client['manager_name']) ?>
                                        </strong><br>
                                        <small>
                                            <?= e($client['manager_phone']) ?>
                                        </small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($client['same_contact']): ?>
                                        <span class="badge badge-gray">
                                            <?= __('same_contact') ?>
                                        </span>
                                    <?php elseif ($client['warehouse_contact_name']): ?>
                                        <strong>
                                            <?= e($client['warehouse_contact_name']) ?>
                                        </strong><br>
                                        <small>
                                            <?= e($client['warehouse_contact_phone']) ?>
                                        </small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.25rem;">
                                        <button type="button" class="btn btn-sm btn-outline"
                                            onclick="openCommentModal(<?= $client['id'] ?>, '<?= e($client['company_name']) ?>')"
                                            title="Добавить комментарий">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                            </svg>
                                        </button>
                                        <a href="edit-client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline"
                                            title="<?= __('edit') ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                            </svg>
                                        </a>
                                    </div>
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
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-secondary">←</a>
                    <?php endif; ?>

                    <span style="padding: 0.375rem 0.875rem; color: var(--gray-600);">
                        <?= $page ?> /
                        <?= $pagination['total_pages'] ?>
                    </span>

                    <?php if ($pagination['has_next']): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-secondary">→</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Comment Modal -->
<div class="modal-overlay" id="commentModal">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Добавить комментарий</h3>
            <button type="button" class="modal-close" onclick="closeModal('commentModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p id="commentClientName" style="margin-bottom: 1rem; font-weight: 600;"></p>
            <form id="commentForm">
                <input type="hidden" name="client_id" id="commentClientId">
                <div class="form-group">
                    <textarea name="comment" id="commentText" class="form-control" rows="4"
                        placeholder="Введите комментарий..." required></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary"
                        onclick="closeModal('commentModal')"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openCommentModal(clientId, clientName) {
        document.getElementById('commentClientId').value = clientId;
        document.getElementById('commentClientName').textContent = clientName;
        document.getElementById('commentText').value = '';
        openModal('commentModal');
        // Auto focus
        setTimeout(() => document.getElementById('commentText').focus(), 100);
    }

    document.getElementById('commentForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const data = {
            action: 'add_client_comment',
            client_id: document.getElementById('commentClientId').value,
            comment: document.getElementById('commentText').value
        };

        try {
            const response = await fetch('../../api/ajax-handlers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                showToast('Комментарий добавлен', 'success');
                closeModal('commentModal');
            } else {
                showToast(result.message || 'Ошибка', 'error');
            }
        } catch (e) {
            showToast('Ошибка сети', 'error');
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>