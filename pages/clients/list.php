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
    $typeFilter = $_GET['type'] ?? '';

    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(company_name LIKE ? OR ico LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($typeFilter) {
        $where[] = "type = ?";
        $params[] = $typeFilter;
    }

    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

    // Count total
    $countSql = "SELECT COUNT(*) FROM clients $whereClause";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    $pagination = getPagination($total, $page, $perPage);

    // Get clients
    $sql = "SELECT c.*, 
            (SELECT comment FROM client_comments WHERE client_id = c.id ORDER BY created_at DESC LIMIT 1) as last_comment
            FROM clients c $whereClause ORDER BY c.company_name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

} catch (Throwable $e) {
    die("<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>");
}

$pageTitle = __('clients');
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .comment-trigger {
        position: relative;
        display: inline-block;
    }

    .comment-cloud {
        display: none;
        position: absolute;
        bottom: 120%;
        left: 50%;
        transform: translateX(-50%);
        background: white;
        border: 1px solid var(--gray-200);
        padding: 0.75rem;
        border-radius: 8px;
        box-shadow: var(--shadow-lg);
        width: 250px;
        z-index: 100;
        font-size: 13px;
        white-space: normal;
        pointer-events: none;
    }

    .comment-cloud::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: white transparent transparent transparent;
    }

    .comment-trigger:hover .comment-cloud {
        display: block;
    }

    .history-card {
        background: var(--gray-50);
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .history-table {
        width: 100%;
        font-size: 13px;
    }

    .history-table th {
        text-align: left;
        color: var(--gray-500);
        padding-bottom: 0.5rem;
    }

    .history-table td {
        padding: 0.5rem 0;
        border-top: 1px solid var(--gray-200);
    }
</style>

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
        <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <input type="text" name="search" class="form-control" placeholder="<?= __('search') ?>..."
                    value="<?= e($search) ?>" style="max-width: 300px;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <select name="type" class="form-control" style="width: 150px;">
                    <option value=""><?= __('all') ?> Типы</option>
                    <option value="wholesale" <?= $typeFilter === 'wholesale' ? 'selected' : '' ?>><?= __('wholesale') ?>
                    </option>
                    <option value="retail" <?= $typeFilter === 'retail' ? 'selected' : '' ?>><?= __('retail') ?></option>
                </select>
            </div>
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
                                <?= __('type') ?>
                            </th>
                            <th>
                                <?= __('actions') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr style="cursor: pointer;" onclick="showClientDetails(<?= $client['id'] ?>)">
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
                                    <span class="badge badge-<?= $client['type'] === 'wholesale' ? 'primary' : 'success' ?>">
                                        <?= __($client['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.25rem;" onclick="event.stopPropagation()">
                                        <div class="comment-trigger" onclick="event.stopPropagation()">
                                            <button type="button" class="btn btn-sm btn-outline"
                                                data-name="<?= e($client['company_name']) ?>"
                                                onclick="openCommentModal(<?= $client['id'] ?>, this.getAttribute('data-name'))"
                                                title="<?= __('add') ?> <?= __('latest_comment') ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                                </svg>
                                            </button>
                                            <?php if ($client['last_comment']): ?>
                                                <div class="comment-cloud">
                                                    <strong><?= __('latest_comment') ?>:</strong><br>
                                                    <?= e($client['last_comment']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
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

<!-- Details Modal -->
<div id="clientDetailsModal" class="modal-overlay">
    <div class="modal" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">Информация о клиенте</h3>
            <button class="modal-close" onclick="closeModal('clientDetailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="clientDetailsBody">
            <!-- Details loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary"
                onclick="closeModal('clientDetailsModal')"><?= __('close') ?></button>
        </div>
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

            <!-- Existing Comments List -->
            <div id="clientCommentsList"
                style="margin-bottom: 1.5rem; max-height: 300px; overflow-y: auto; background: var(--gray-50); border-radius: 8px; padding: 1rem;">
                <div class="text-center text-muted small">Load comments...</div>
            </div>

            <form id="commentForm">
                <input type="hidden" name="client_id" id="commentClientId">
                <div class="form-group">
                    <label class="small text-muted mb-1"><?= __('add') ?> <?= __('latest_comment') ?></label>
                    <textarea name="comment" id="commentText" class="form-control" rows="3" placeholder="..."
                        required></textarea>
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
    async function openCommentModal(clientId, clientName) {
        document.getElementById('commentClientId').value = clientId;
        document.getElementById('commentClientName').textContent = clientName;
        document.getElementById('commentText').value = '';
        openModal('commentModal');

        // Auto focus
        setTimeout(() => document.getElementById('commentText').focus(), 100);

        // Fetch comments
        const list = document.getElementById('clientCommentsList');
        list.innerHTML = '<div class="text-center spinner"></div>';

        try {
            const res = await fetch(`../../api/ajax-handlers.php?action=get_client_comments&client_id=${clientId}`);
            const comments = await res.json();

            if (comments && comments.length > 0) {
                list.innerHTML = comments.map(c => `
                    <div style="margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--gray-200); last-child:border-none;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <strong style="font-size: 0.85rem;">${c.first_name || 'Admin'} ${c.last_name || ''}</strong>
                            <span class="text-muted small" style="font-size: 0.75rem;">${c.created_at}</span>
                        </div>
                        <div style="font-size: 0.9rem; white-space: pre-wrap;">${c.comment}</div>
                    </div>
                `).join('');
            } else {
                list.innerHTML = '<div class="text-center text-muted small"><?= __('no_data') ?></div>';
            }
        } catch (e) {
            console.error(e);
            list.innerHTML = '<div class="text-center text-danger small">Error loading comments</div>';
        }
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
    async function showClientDetails(id) {
        openModal('clientDetailsModal');
        const body = document.getElementById('clientDetailsBody');
        body.innerHTML = '<div class="text-center"><div class="spinner"></div> <?= __('loading') ?>...</div>';

        try {
            // Fetch history summary simultaneously
            const [detailsRes, historyRes] = await Promise.all([
                fetch(`../../api/ajax-handlers.php?action=get_client_details&id=${id}`),
                fetch(`../../api/ajax-handlers.php?action=get_client_history&id=${id}`)
            ]);

            const result = await detailsRes.json();
            const history = await historyRes.json();

            if (result.success) {
                const c = result.data;

                let historyHtml = '';
                if (history && history.length > 0) {
                    historyHtml = `
                        <div class="history-card">
                            <h4 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                                <?= __('summary') ?> (<?= __('sales') ?>)
                            </h4>
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('invoice_number') ?></th>
                                        <th><?= __('items_count') ?></th>
                                        <th><?= __('vat_amount') ?></th>
                                        <th><?= __('total') ?></th>
                                        <th><?= __('status') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${history.map(s => `
                                        <tr>
                                            <td>${s.sale_date}</td>
                                            <td>${s.invoice_number || 'N/A'}</td>
                                            <td>${s.items_count}</td>
                                            <td>${parseFloat(s.vat_amount).toLocaleString()} CZK</td>
                                            <td><strong>${parseFloat(s.total).toLocaleString()} CZK</strong></td>
                                            <td><span class="badge badge-gray">${s.status || 'Done'}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }

                body.innerHTML = `
                    ${historyHtml}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div style="grid-column: span 2; border-bottom: 1px solid var(--gray-200); padding-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                            <div style="font-size: 1.25rem; font-weight: 600;">${e(c.company_name)}</div>
                            <span class="badge badge-${c.type === 'wholesale' ? 'primary' : 'success'}">${c.type.toUpperCase()}</span>
                        </div>
                        
                        <div>
                            <h4 style="margin-bottom: 0.75rem; color: var(--gray-600);">🏢 <?= __('company_info') ?></h4>
                            <div style="margin-bottom: 0.5rem;">
                                <label class="text-muted small"><?= __('ico') ?> / <?= __('dic') ?></label>
                                <div>${e(c.ico) || '-'} / ${e(c.dic) || '-'}</div>
                            </div>
                            <div style="margin-bottom: 0.5rem;">
                                <label class="text-muted small"><?= __('legal_address') ?></label>
                                <div style="white-space: pre-wrap;">${e(c.legal_address) || '-'}</div>
                            </div>
                        </div>

                        <div>
                            <h4 style="margin-bottom: 0.75rem; color: var(--gray-600);">👤 <?= __('manager_contact') ?></h4>
                            <div style="margin-bottom: 0.5rem;">
                                <label class="text-muted small"><?= __('contact_name') ?></label>
                                <div>${e(c.manager_name) || '-'}</div>
                            </div>
                            <div style="margin-bottom: 0.5rem;">
                                <label class="text-muted small"><?= __('email') ?> / <?= __('phone') ?></label>
                                <div>${e(c.manager_email) || '-'} / ${e(c.manager_phone) || '-'}</div>
                            </div>
                        </div>

                        <div>
                            <h4 style="margin-bottom: 0.75rem; color: var(--gray-600);">📍 <?= __('warehouse_info') ?></h4>
                            <div style="margin-bottom: 0.5rem;">
                                <label class="text-muted small"><?= __('address') ?></label>
                                <div style="white-space: pre-wrap;">${e(c.warehouse_address) || '-'}</div>
                            </div>
                             <div style="margin-bottom: 0.5rem;">
                                <label class="text-muted small"><?= __('contact_name') ?></label>
                                <div>${e(c.warehouse_contact_name) || '-'} (${e(c.warehouse_contact_phone) || '-'})</div>
                            </div>
                        </div>

                        <div>
                            <h4 style="margin-bottom: 0.75rem; color: var(--gray-600);">🚚 <?= __('delivery_address') ?></h4>
                            <div style="white-space: pre-wrap;">${e(c.delivery_address) || '-'}</div>
                        </div>
                    </div>
                `;
            } else {
                body.innerHTML = '<div class="alert alert-danger">Error loading details</div>';
            }
        } catch (e) {
            console.error(e);
            body.innerHTML = '<div class="alert alert-danger">Network error</div>';
        }
    }

    function e(str) {
        if (!str) return '';
        return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>