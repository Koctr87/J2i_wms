<?php
/**
 * J2i Warehouse Management System
 * User Management Page
 */
$pageTitle = __('users');
require_once __DIR__ . '/../../includes/header.php';

// Only directors can manage users
requireRole('director');

$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            // Check if email exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            if ($stmt->fetch()) {
                throw new Exception('Email уже используется');
            }

            $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name, role, language, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $_POST['email'],
                $passwordHash,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['role'],
                $_POST['language'] ?? 'ru'
            ]);

            $userId = $db->lastInsertId();
            logActivity('user_created', 'user', $userId);
            setFlashMessage('success', __('success_save'));

        } elseif ($action === 'update') {
            $updateFields = "first_name = ?, last_name = ?, role = ?, language = ?, is_active = ?";
            $params = [
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['role'],
                $_POST['language'],
                isset($_POST['is_active']) ? 1 : 0
            ];

            // Update password if provided
            if (!empty($_POST['password'])) {
                $updateFields .= ", password_hash = ?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }

            $params[] = $_POST['id'];

            $stmt = $db->prepare("UPDATE users SET $updateFields WHERE id = ?");
            $stmt->execute($params);

            logActivity('user_updated', 'user', $_POST['id']);
            setFlashMessage('success', __('success_save'));
        }

        redirect('list.php');

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all users
$users = $db->query("SELECT * FROM users ORDER BY role, first_name")->fetchAll();

$roles = [
    'director' => ['label' => 'Директор', 'badge' => 'primary'],
    'admin' => ['label' => 'Администратор', 'badge' => 'info'],
    'manager' => ['label' => 'Менеджер склада', 'badge' => 'warning'],
    'seller' => ['label' => 'Продавец', 'badge' => 'success'],
    'logist' => ['label' => 'Логист', 'badge' => 'gray']
];
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
            <?= __('users') ?>
            <span class="badge badge-primary" style="margin-left: 0.5rem;">
                <?= count($users) ?>
            </span>
        </h3>
        <button class="btn btn-primary" onclick="openModal('addUserModal')">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            <?= __('add') ?>
            <?= __('user') ?>
        </button>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error" style="margin: 1rem;">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Имя</th>
                        <th>Email</th>
                        <th>Роль</th>
                        <th>Язык</th>
                        <th>Последний вход</th>
                        <th>
                            <?= __('status') ?>
                        </th>
                        <th>
                            <?= __('actions') ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= e($user['first_name'] . ' ' . $user['last_name']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= e($user['email']) ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $roles[$user['role']]['badge'] ?? 'gray' ?>">
                                    <?= $roles[$user['role']]['label'] ?? $user['role'] ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $langFlags = ['ru' => '🇷🇺', 'cs' => '🇨🇿', 'uk' => '🇺🇦', 'en' => '🇬🇧'];
                                echo $langFlags[$user['language']] ?? $user['language'];
                                ?>
                            </td>
                            <td>
                                <?= $user['last_login'] ? formatDate($user['last_login'], 'd.m.Y H:i') : '-' ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-outline"
                                        onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                        </svg>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">Вы</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('add') ?>
                <?= __('user') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Имя</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Фамилия</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label required">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label required">Пароль</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Роль</label>
                        <select name="role" class="form-control" required>
                            <?php foreach ($roles as $key => $role): ?>
                                <option value="<?= $key ?>">
                                    <?= $role['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Язык</label>
                        <select name="language" class="form-control">
                            <option value="ru">🇷🇺 Русский</option>
                            <option value="cs">🇨🇿 Čeština</option>
                            <option value="uk">🇺🇦 Українська</option>
                            <option value="en">🇬🇧 English</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">
                    <?= __('cancel') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <?= __('save') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">
                <?= __('edit') ?>
                <?= __('user') ?>
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editUserId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Имя</label>
                        <input type="text" name="first_name" id="editUserFirstName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Фамилия</label>
                        <input type="text" name="last_name" id="editUserLastName" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Новый пароль</label>
                    <input type="password" name="password" class="form-control" minlength="6"
                        placeholder="Оставьте пустым, если не меняете">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required">Роль</label>
                        <select name="role" id="editUserRole" class="form-control" required>
                            <?php foreach ($roles as $key => $role): ?>
                                <option value="<?= $key ?>">
                                    <?= $role['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Язык</label>
                        <select name="language" id="editUserLanguage" class="form-control">
                            <option value="ru">🇷🇺 Русский</option>
                            <option value="cs">🇨🇿 Čeština</option>
                            <option value="uk">🇺🇦 Українська</option>
                            <option value="en">🇬🇧 English</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" id="editUserActive" class="form-check-input">
                        <span class="form-check-label">Активен</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">
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
    function editUser(user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserFirstName').value = user.first_name;
        document.getElementById('editUserLastName').value = user.last_name;
        document.getElementById('editUserRole').value = user.role;
        document.getElementById('editUserLanguage').value = user.language;
        document.getElementById('editUserActive').checked = user.is_active == 1;
        openModal('editUserModal');
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>