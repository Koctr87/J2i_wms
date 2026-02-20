<?php
/**
 * J2i Warehouse Management System
 * Add Supplier Page
 */
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();

        $stmt = $db->prepare("
            INSERT INTO suppliers (company_name, ico, dic, contact_name, email, phone, address, bank_account)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['company_name'],
            $_POST['ico'] ?: null,
            $_POST['dic'] ?: null,
            $_POST['contact_name'] ?: null,
            $_POST['email'] ?: null,
            $_POST['phone'] ?: null,
            $_POST['address'] ?: null,
            $_POST['bank_account'] ?: null
        ]);

        setFlashMessage('success', __('success_save'));
        redirect('list.php');

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = __('add') . ' ' . __('supplier');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <?= __('add') ?>
            <?= __('supplier') ?>
        </h3>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">🏢
                    <?= __('company_info') ?>
                </h4>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label required">
                            <?= __('company_name') ?>
                        </label>
                        <input type="text" name="company_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('ico') ?>
                        </label>
                        <input type="text" name="ico" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('dic') ?>
                        </label>
                        <input type="text" name="dic" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('bank_account') ?>
                        </label>
                        <input type="text" name="bank_account" class="form-control">
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
                        <input type="text" name="contact_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('email') ?>
                        </label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('phone') ?>
                        </label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <?= __('legal_address') ?>
                    </label>
                    <textarea name="address" class="form-control" rows="3"></textarea>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="list.php" class="btn btn-secondary">
                    <?= __('cancel') ?>
                </a>
                <button type="submit" class="btn btn-primary">
                    <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>