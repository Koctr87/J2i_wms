<?php
/**
 * J2i Warehouse Management System
 * Suppliers List Page
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
    $countSql = "SELECT COUNT(*) FROM suppliers $where";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    $pagination = getPagination($total, $page, $perPage);

    // Get suppliers
    $sql = "SELECT s.*, 
            (SELECT comment FROM supplier_comments WHERE supplier_id = s.id ORDER BY created_at DESC LIMIT 1) as last_comment
            FROM suppliers s $where ORDER BY s.company_name LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll();

} catch (Throwable $e) {
    die("<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>");
}

$pageTitle = __('suppliers');
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
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <polyline points="16 11 18 13 22 9" />
            </svg>
            <?= __('suppliers') ?>
            <span class="badge badge-primary" style="margin-left: 0.5rem;">
                <?= $total ?>
            </span>
        </h3>
        <a href="add-supplier.php" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <?= __('add') ?>
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
        <?php if (empty($suppliers)): ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                </svg>
                <h3>
                    <?= __('no_data') ?>
                </h3>
                <p><a href="add-supplier.php">
                        <?= __('add') ?>
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
                                <?= __('ico') ?> /
                                <?= __('dic') ?>
                            </th>
                            <th>
                                <?= __('contact_name') ?>
                            </th>
                            <th>
                                <?= __('email') ?> /
                                <?= __('phone') ?>
                            </th>
                            <th>
                                <?= __('actions') ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr style="cursor: pointer;" onclick="showSupplierDetails(<?= $supplier['id'] ?>)">
                                <td>
                                    <strong>
                                        <?= e($supplier['company_name']) ?>
                                    </strong>
                                    <?php if ($supplier['bank_account']): ?>
                                        <br><small class="text-muted">🏦
                                            <?= e($supplier['bank_account']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= e($supplier['ico'] ?: '-') ?> /
                                    <?= e($supplier['dic'] ?: '-') ?>
                                </td>
                                <td>
                                    <?= e($supplier['contact_name'] ?: '-') ?>
                                </td>
                                <td>
                                    <?= e($supplier['email'] ?: '-') ?><br>
                                    <small>
                                        <?= e($supplier['phone'] ?: '-') ?>
                                    </small>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.25rem;" onclick="event.stopPropagation()">
                                        <div class="comment-trigger" onclick="event.stopPropagation()">
                                            <button type="button" class="btn btn-sm btn-outline"
                                                data-name="<?= e($supplier['company_name']) ?>"
                                                onclick="openCommentModal(<?= $supplier['id'] ?>, this.getAttribute('data-name'))"
                                                title="<?= __('add') ?> <?= __('latest_comment') ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                                </svg>
                                            </button>
                                            <?php if ($supplier['last_comment']): ?>
                                                <div class="comment-cloud">
                                                    <strong><?= __('latest_comment') ?>:</strong><br>
                                                    <?= e($supplier['last_comment']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <a href="edit-supplier.php?id=<?= $supplier['id'] ?>" class="btn btn-sm btn-outline"
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
                <div class="card-footer" style="display: flex; justify-content: center; gap: 0.5rem; padding: 1rem;">
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
<div id="supplierDetailsModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Информация о поставщике</h3>
            <button class="modal-close" onclick="closeModal('supplierDetailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="supplierDetailsBody">
            <!-- Details will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary"
                onclick="closeModal('supplierDetailsModal')"><?= __('close') ?></button>
        </div>
    </div>
</div>

<!-- Comments Modal -->
<div id="commentModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="commentModalTitle"><?= __('comments') ?>: <span></span></h3>
            <button class="modal-close" onclick="closeModal('commentModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="commentList" style="max-height: 300px; overflow-y: auto; margin-bottom: 1.5rem;">
                <!-- Comments will be loaded here -->
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('add') ?> <?= __('latest_comment') ?></label>
                <textarea id="newComment" class="form-control" rows="3" placeholder="..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary"
                onclick="closeModal('commentModal')"><?= __('cancel') ?></button>
            <button type="button" class="btn btn-primary" id="saveCommentBtn"><?= __('save') ?></button>
        </div>
    </div>
</div>

<script>
    let currentSupplierId = null;

    async function showSupplierDetails(id) {
        openModal('supplierDetailsModal');
        const body = document.getElementById('supplierDetailsBody');
        body.innerHTML = '<div class="text-center"><div class="spinner"></div> <?= __('loading') ?>...</div>';

        try {
            const [detailsRes, historyRes] = await Promise.all([
                fetch(`../../api/ajax-handlers.php?action=get_supplier_details&id=${id}`),
                fetch(`../../api/ajax-handlers.php?action=get_supplier_history&id=${id}`)
            ]);

            const res = await detailsRes.json();
            const history = await historyRes.json();

            if (res.success) {
                const s = res.data;

                let historyHtml = '';
                if (history && history.length > 0) {
                    historyHtml = `
                        <div class="history-card">
                            <h4 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                 <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                                 <?= __('summary') ?> (<?= __('purchase') ?>)
                            </h4>
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('invoice_number') ?></th>
                                        <th><?= __('items_count') ?></th>
                                        <th><?= __('vat_est') ?></th>
                                        <th><?= __('total') ?></th>
                                        <th><?= __('vat_mode') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${history.map(p => `
                                        <tr>
                                            <td>${p.purchase_date}</td>
                                            <td>${p.invoice_number || 'N/A'}</td>
                                            <td>${p.items_count}</td>
                                            <td>${parseFloat(p.vat_amount_est || 0).toLocaleString()} ${p.currency}</td>
                                            <td><strong>${parseFloat(p.total_amount).toLocaleString()} ${p.currency}</strong></td>
                                            <td><span class="badge badge-gray">${p.vat_mode}</span></td>
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
                        <div style="grid-column: span 2;">
                            <label class="text-muted small"><?= __('company_name') ?></label>
                            <div style="font-size: 1.25rem; font-weight: 600;">${e(s.company_name)}</div>
                        </div>
                        <div>
                            <label class="text-muted small"><?= __('ico') ?></label>
                            <div>${e(s.ico) || '-'}</div>
                        </div>
                        <div>
                            <label class="text-muted small"><?= __('dic') ?></label>
                            <div>${e(s.dic) || '-'}</div>
                        </div>
                        <div>
                            <label class="text-muted small"><?= __('contact_name') ?></label>
                            <div>${e(s.contact_name) || '-'}</div>
                        </div>
                        <div>
                            <label class="text-muted small"><?= __('email') ?></label>
                            <div>${e(s.email) || '-'}</div>
                        </div>
                        <div>
                            <label class="text-muted small"><?= __('phone') ?></label>
                            <div>${e(s.phone) || '-'}</div>
                        </div>
                        <div>
                            <label class="text-muted small"><?= __('bank_account') ?></label>
                            <div>${e(s.bank_account) || '-'}</div>
                        </div>
                        <div style="grid-column: span 2;">
                            <label class="text-muted small"><?= __('legal_address') ?></label>
                            <div style="white-space: pre-wrap;">${e(s.address) || '-'}</div>
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

    function openCommentModal(id, name) {
        currentSupplierId = id;
        document.querySelector('#commentModalTitle span').textContent = name;
        document.getElementById('newComment').value = '';
        document.getElementById('commentList').innerHTML = '<p class="text-center text-muted">Загрузка...</p>';

        // Fetch existing comments
        fetch(`../../api/ajax-handlers.php?action=get_supplier_comments&supplier_id=${id}`)
            .then(res => res.json())
            .then(data => {
                const list = document.getElementById('commentList');
                if (data.length === 0) {
                    list.innerHTML = '<p class="text-center text-muted">Нет комментариев</p>';
                } else {
                    list.innerHTML = data.map(c => `
                        <div style="padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius); margin-bottom: 0.5rem; font-size: 0.9rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                <strong style="color: var(--gray-700);">${e(c.first_name + ' ' + c.last_name)}</strong>
                                <small class="text-muted">${c.created_at}</small>
                            </div>
                            <div style="color: var(--gray-600); white-space: pre-wrap;">${e(c.comment)}</div>
                        </div>
                    `).join('');
                }
            });

        openModal('commentModal');
    }

    document.getElementById('saveCommentBtn').addEventListener('click', function () {
        const comment = document.getElementById('newComment').value.trim();
        if (!comment) return;

        this.disabled = true;
        fetch('../../api/ajax-handlers.php?action=add_supplier_comment', {
            method: 'POST',
            body: JSON.stringify({
                supplier_id: currentSupplierId,
                comment: comment
            })
        })
            .then(res => res.json())
            .then(data => {
                this.disabled = false;
                if (data.success) {
                    closeModal('commentModal');
                    setFlashMessage('success', 'Комментарий добавлен');
                    location.reload(); // Simplest way to reflect in list if we added badge, but here just success
                }
            });
    });

    function e(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>