<?php
/**
 * J2i Warehouse Management System
 * Add Client Page
 */
require_once __DIR__ . '/../../config/config.php';

$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sameContact = isset($_POST['same_contact']) ? 1 : 0;

        $stmt = $db->prepare("
            INSERT INTO clients (
                company_name, ico, dic, legal_address, warehouse_address,
                manager_name, manager_phone, manager_email,
                warehouse_contact_name, warehouse_contact_phone, warehouse_contact_email,
                same_contact, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // If same contact, copy manager info to warehouse contact
        $warehouseName = $sameContact ? $_POST['manager_name'] : $_POST['warehouse_contact_name'];
        $warehousePhone = $sameContact ? $_POST['manager_phone'] : $_POST['warehouse_contact_phone'];
        $warehouseEmail = $sameContact ? $_POST['manager_email'] : $_POST['warehouse_contact_email'];

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
            $_SESSION['user_id']
        ]);

        $clientId = $db->lastInsertId();
        logActivity('client_created', 'client', $clientId);

        setFlashMessage('success', __('success_save'));
        redirect('list.php');

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = __('add_client');
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="8.5" cy="7" r="4" />
                <line x1="20" y1="8" x2="20" y2="14" />
                <line x1="23" y1="11" x2="17" y2="11" />
            </svg>
            <?= __('add_client') ?>
        </h3>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="clientForm">
            <!-- Company Info -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">🏢 Информация о компании</h4>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label required">
                            <?= __('company_name') ?>
                        </label>
                        <input type="text" name="company_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('ico') ?>
                        </label>
                        <input type="text" name="ico" class="form-control" maxlength="20" placeholder="12345678">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('dic') ?>
                        </label>
                        <input type="text" name="dic" class="form-control" maxlength="20" placeholder="CZ12345678">
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
                        <textarea name="legal_address" class="form-control" rows="2"
                            placeholder="Улица, номер дома, город, индекс"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('warehouse_address') ?>
                        </label>
                        <textarea name="warehouse_address" class="form-control" rows="2"
                            placeholder="Адрес склада для доставки"></textarea>
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
                        <input type="text" name="manager_name" class="form-control" placeholder="Имя Фамилия">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_phone') ?>
                        </label>
                        <input type="tel" name="manager_phone" class="form-control" placeholder="+420 123 456 789">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_email') ?>
                        </label>
                        <input type="email" name="manager_email" class="form-control" placeholder="email@company.cz">
                    </div>
                </div>
            </div>

            <!-- Same Contact Checkbox -->
            <div style="margin-bottom: 1.5rem;">
                <label class="form-check">
                    <input type="checkbox" name="same_contact" id="sameContact" class="form-check-input" value="1">
                    <span class="form-check-label">
                        <?= __('same_contact') ?> (менеджер = складовщик)
                    </span>
                </label>
            </div>

            <!-- Warehouse Contact -->
            <div style="margin-bottom: 2rem;" id="warehouseContactSection">
                <h4 style="margin-bottom: 1rem; color: var(--gray-600);">📦
                    <?= __('warehouse_contact') ?>
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_name') ?>
                        </label>
                        <input type="text" name="warehouse_contact_name" class="form-control" placeholder="Имя Фамилия">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_phone') ?>
                        </label>
                        <input type="tel" name="warehouse_contact_phone" class="form-control"
                            placeholder="+420 123 456 789">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <?= __('contact_email') ?>
                        </label>
                        <input type="email" name="warehouse_contact_email" class="form-control"
                            placeholder="sklad@company.cz">
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div style="margin-bottom: 2rem;">
                <div class="form-group">
                    <label class="form-label">
                        <?= __('notes') ?>
                    </label>
                    <textarea name="notes" class="form-control" rows="3"
                        placeholder="Дополнительные заметки о клиенте..."></textarea>
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