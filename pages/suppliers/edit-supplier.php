<?php
/**
 * J2i Warehouse Management System
 * Edit Supplier Page with Tickets
 */
require_once __DIR__ . '/../../config/config.php';

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    redirect('list.php');
}

// Fetch supplier
$stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    die("Supplier not found.");
}

// Handle supplier update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_supplier'])) {
    try {
        $updateStmt = $db->prepare("
            UPDATE suppliers SET 
                company_name = ?, ico = ?, dic = ?, contact_name = ?, 
                email = ?, phone = ?, address = ?, bank_account = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $_POST['company_name'],
            $_POST['ico'] ?: null,
            $_POST['dic'] ?: null,
            $_POST['contact_name'] ?: null,
            $_POST['email'] ?: null,
            $_POST['phone'] ?: null,
            $_POST['address'] ?: null,
            $_POST['bank_account'] ?: null,
            $id
        ]);
        setFlashMessage('success', __('success_update'));
        redirect("edit-supplier.php?id=$id");
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle ticket creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    try {
        $ticketStmt = $db->prepare("
            INSERT INTO supplier_tickets (supplier_id, purchase_id, title, description, type, image_url, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ticketStmt->execute([
            $id,
            $_POST['purchase_id'] ?: null,
            $_POST['title'],
            $_POST['description'],
            $_POST['type'],
            $_POST['image_url'] ?: null,
            $_SESSION['user_id']
        ]);
        setFlashMessage('success', 'Ticket created');
        redirect("edit-supplier.php?id=$id&tab=tickets");
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle ticket status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket_status'])) {
    try {
        $ticketId = (int) $_POST['ticket_id'];
        $newStatus = $_POST['status'];
        $updateStmt = $db->prepare("UPDATE supplier_tickets SET status = ? WHERE id = ?");
        $updateStmt->execute([$newStatus, $ticketId]);
        setFlashMessage('success', 'Ticket status updated');
        redirect("edit-supplier.php?id=$id&tab=tickets");
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle comment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_comment') {
        $comment = trim($_POST['comment'] ?? '');
        if ($comment) {
            $stmt = $db->prepare("INSERT INTO supplier_comments (supplier_id, comment, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$id, $comment, $_SESSION['user_id']]);
            setFlashMessage('success', 'Комментарий добавлен');
        }
        redirect("edit-supplier.php?id=$id&tab=comments");
    } elseif ($action === 'delete_comment') {
        $commentId = (int) ($_POST['comment_id'] ?? 0);
        if ($commentId) {
            $stmt = $db->prepare("DELETE FROM supplier_comments WHERE id = ? AND supplier_id = ?");
            $stmt->execute([$commentId, $id]);
            setFlashMessage('success', 'Комментарий удалён');
        }
        redirect("edit-supplier.php?id=$id&tab=comments");
    }
}

// Fetch tickets
try {
    $stmt = $db->prepare("SELECT * FROM supplier_tickets WHERE supplier_id = ? ORDER BY created_at DESC");
    $stmt->execute([$id]);
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    // Log error or set default empty tickets
    error_log("Error fetching tickets: " . $e->getMessage());
    $tickets = [];
}

// Fetch comments
$stmt = $db->prepare("
    SELECT sc.*, u.first_name, u.last_name 
    FROM supplier_comments sc
    LEFT JOIN users u ON sc.created_by = u.id
    WHERE sc.supplier_id = ?
    ORDER BY sc.created_at DESC
");
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

// Fetch purchases for this supplier with item count
$purchases = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, 
               (SELECT SUM(quantity) FROM devices WHERE purchase_id = p.id) as items_count,
               (SELECT SUM(purchase_price * quantity * 0.21) FROM devices WHERE purchase_id = p.id AND vat_mode = 'full') as vat_amount_est
        FROM purchases p 
        WHERE supplier_id = ? 
        ORDER BY purchase_date DESC
    ");
    $stmt->execute([$id]);
    $purchases = $stmt->fetchAll();
} catch (PDOException $e) {
    // Log error or just continue with empty purchases
    error_log("Error fetching purchases: " . $e->getMessage());
}

$activeTab = $_GET['tab'] ?? 'info';
$pageTitle = __('edit') . ' ' . __('supplier');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="tabs-container">
    <div class="tabs-header">
        <a href="?id=<?= $id ?>&tab=info" class="tab-item <?= $activeTab === 'info' ? 'active' : '' ?>">
            <?= __('info') ?>
        </a>
        <a href="?id=<?= $id ?>&tab=tickets" class="tab-item <?= $activeTab === 'tickets' ? 'active' : '' ?>">
            <?= __('tickets') ?> /
            <?= __('complaints') ?>
        </a>
        <a href="?id=<?= $id ?>&tab=history" class="tab-item <?= $activeTab === 'history' ? 'active' : '' ?>">
            <?= __('purchase_history') ?>
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if ($activeTab === 'info'): ?>
                <form method="POST" action="">
                    <input type="hidden" name="update_supplier" value="1">
                    <div style="margin-bottom: 2rem;">
                        <h4 style="margin-bottom: 1rem; color: var(--gray-600);">🏢
                            <?= __('company_info') ?>
                        </h4>
                        <div class="form-row">
                            <div class="form-group" style="flex: 2;">
                                <label class="form-label required">
                                    <?= __('company_name') ?>
                                </label>
                                <input type="text" name="company_name" class="form-control"
                                    value="<?= e($supplier['company_name']) ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <?= __('ico') ?>
                                </label>
                                <input type="text" name="ico" class="form-control" value="<?= e($supplier['ico']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <?= __('dic') ?>
                                </label>
                                <input type="text" name="dic" class="form-control" value="<?= e($supplier['dic']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <?= __('bank_account') ?>
                                </label>
                                <input type="text" name="bank_account" class="form-control"
                                    value="<?= e($supplier['bank_account']) ?>">
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 2rem;">
                        <h4 style="margin-bottom: 1rem; color: var(--gray-600);">👤
                            <?= __('contact_info') ?>
                        </h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <?= __('contact_name') ?>
                                </label>
                                <input type="text" name="contact_name" class="form-control"
                                    value="<?= e($supplier['contact_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <?= __('email') ?>
                                </label>
                                <input type="email" name="email" class="form-control" value="<?= e($supplier['email']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <?= __('phone') ?>
                                </label>
                                <input type="text" name="phone" class="form-control" value="<?= e($supplier['phone']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <?= __('legal_address') ?>
                            </label>
                            <textarea name="address" class="form-control" rows="3"><?= e($supplier['address']) ?></textarea>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <a href="list.php" class="btn btn-secondary">
                            <?= __('back') ?>
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <?= __('update') ?>
                        </button>
                    </div>
                </form>

            <?php elseif ($activeTab === 'tickets'): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3>
                        <?= __('tickets') ?> (
                        <?= count($tickets) ?>)
                    </h3>
                    <button class="btn btn-primary btn-sm" onclick="openModal('addTicketModal')">+ New Ticket</button>
                </div>

                <?php if (empty($tickets)): ?>
                    <div class="empty-state">
                        <p>No tickets found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <?= e($ticket['title']) ?>
                                            </strong><br>
                                            <small class="text-muted">
                                                <?= mb_substr(e($ticket['description']), 0, 50) ?>...
                                            </small>
                                        </td>
                                        <td><span class="badge badge-gray">
                                                <?= ucfirst($ticket['type']) ?>
                                            </span></td>
                                        <td><span
                                                class="badge badge-<?= $ticket['status'] === 'resolved' ? 'success' : ($ticket['status'] === 'open' ? 'warning' : 'gray') ?>">
                                                <?= ucfirst($ticket['status']) ?>
                                            </span></td>
                                        <td>
                                            <?= formatDate($ticket['created_at']) ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: flex; gap: 0.5rem; margin: 0;">
                                                <input type="hidden" name="update_ticket_status" value="1">
                                                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                                                <select name="status" class="form-control form-control-sm"
                                                    onchange="this.form.submit()">
                                                    <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Open
                                                    </option>
                                                    <option value="resolved" <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>>
                                                        Resolved</option>
                                                    <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>
                                                        Closed</option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif ($activeTab === 'history'): ?>
                <h3>
                    <?= __('purchase_history') ?>
                </h3>
                <?php if (empty($purchases)): ?>
                    <div class="empty-state">
                        <p>No purchases recorded.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice #</th>
                                    <th>Amount</th>
                                    <th>Condition</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchases as $p): ?>
                                    <tr>
                                        <td>
                                            <?= formatDate($p['purchase_date']) ?>
                                        </td>
                                        <td>
                                            <?= e($p['invoice_number'] ?: 'N/A') ?>
                                        </td>
                                        <td>
                                            <?= formatCurrency($p['total_amount'], $p['currency']) ?>
                                        </td>
                                        <td><span class="badge badge-info">
                                                <?= $p['condition'] ?>
                                            </span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
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
        <!-- Purchase History Summary -->
        <?php if (!empty($purchases)): ?>
            <div style="margin-bottom: 2rem; padding: 1rem; background: var(--gray-50); border-radius: 8px;">
                <h4 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--gray-700);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <path
                            d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
                    </svg>
                    <?= __('summary') ?> (<?= __('purchase') ?>)
                </h4>
                <div class="table-container">
                    <table class="table table-sm">
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
                            <?php
                            $summaryPurchases = array_slice($purchases, 0, 5);
                            foreach ($summaryPurchases as $p): ?>
                                <tr>
                                    <td><?= formatDate($p['purchase_date']) ?></td>
                                    <td><?= e($p['invoice_number'] ?: 'N/A') ?></td>
                                    <td><?= $p['items_count'] ?></td>
                                    <td><?= formatCurrency($p['vat_amount_est'] ?? 0, $p['currency']) ?></td>
                                    <td><strong><?= formatCurrency($p['total_amount'], $p['currency']) ?></strong></td>
                                    <td><span class="badge badge-gray"><?= e($p['vat_mode']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Add Comment Form -->
        <form method="POST" style="margin-bottom: 2rem;">
            <input type="hidden" name="action" value="add_comment">
            <div class="form-group">
                <textarea name="comment" class="form-control" rows="2" placeholder="Напишите комментарий..."
                    required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                Добавить комментарий
            </button>
        </form>

        <!-- Comments List -->
        <?php if (empty($comments)): ?>
            <div class="empty-state">
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

<!-- Add Ticket Modal -->
<div class="modal-overlay" id="addTicketModal">
    <div class="modal">
        <div class="modal-header">
            <h3>New Complaint / Ticket</h3>
            <button class="modal-close" onclick="closeModal('addTicketModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="create_ticket" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-control">
                            <option value="complaint">
                                <?= __('complaint') ?>
                            </option>
                            <option value="credit_note">
                                <?= __('credit_note') ?>
                            </option>
                            <option value="missing_item">Missing Item</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Related Invoice (Purchases ID)</label>
                        <select name="purchase_id" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($purchases as $p): ?>
                                <option value="<?= $p['id'] ?>">Inv:
                                    <?= e($p['invoice_number']) ?> (
                                    <?= $p['purchase_date'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Photo / Attachment URL</label>
                    <input type="text" name="image_url" class="form-control"
                        placeholder="https://example.com/photo.jpg">
                    <small class="text-muted">You can also attach the full invoice URL here.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Create Ticket</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>