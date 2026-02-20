<?php
/**
 * J2i Warehouse Management System
 * Authentication Functions
 */

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    static $user = null;

    if ($user === null) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }

    return $user ?: null;
}

/**
 * Require user to be logged in
 * Redirects to login page if not authenticated
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlashMessage('error', __('access_denied'));
        redirect(APP_URL . '/pages/users/login.php');
    }
}

/**
 * Check if current user has permission
 * @param string $permission
 * @return bool
 */
function hasPermission(string $permission): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    $role = $_SESSION['user_role'];
    $roles = ROLES;

    return isset($roles[$role][$permission]) && $roles[$role][$permission] === true;
}

/**
 * Require specific permission
 * @param string $permission
 */
function requirePermission(string $permission): void
{
    requireLogin();

    if (!hasPermission($permission)) {
        setFlashMessage('error', __('access_denied'));
        redirect(APP_URL . '/pages/dashboard.php');
    }
}

/**
 * Attempt to log in user
 * @param string $username
 * @param string $password
 * @return bool
 */
function attemptLogin(string $username, string $password): bool
{
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        logActivity('login_failed', 'user', null, ['username' => $username]);
        return false;
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'] ?? 'User';
    $_SESSION['language'] = $user['language'] ?? 'cs';

    // Regenerate session ID for security
    session_regenerate_id(true);

    logActivity('login_success', 'user', $user['id']);

    return true;
}

/**
 * Log out current user
 */
function logout(): void
{
    $userId = $_SESSION['user_id'] ?? null;

    if ($userId) {
        logActivity('logout', 'user', $userId);
    }

    // Clear session
    $_SESSION = [];

    // Destroy session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Create new user
 * @param array $data
 * @return int|false User ID or false on failure
 */
function createUser(array $data)
{
    $db = getDB();

    // Check if username or email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$data['username'], $data['email']]);
    if ($stmt->fetch()) {
        return false;
    }

    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, language) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['username'],
        $data['email'],
        $passwordHash,
        $data['full_name'],
        $data['role'] ?? 'seller',
        $data['language'] ?? DEFAULT_LANGUAGE
    ]);

    $userId = $db->lastInsertId();
    logActivity('user_created', 'user', $userId, ['username' => $data['username']]);

    return $userId;
}

/**
 * Update user
 * @param int $id
 * @param array $data
 * @return bool
 */
function updateUser(int $id, array $data): bool
{
    $db = getDB();

    $fields = [];
    $values = [];

    $allowedFields = ['email', 'full_name', 'role', 'language', 'is_active'];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }

    if (isset($data['password']) && !empty($data['password'])) {
        $fields[] = "password_hash = ?";
        $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (empty($fields)) {
        return false;
    }

    $values[] = $id;
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($values);

    if ($result) {
        logActivity('user_updated', 'user', $id);
    }

    return $result;
}

/**
 * Get all users
 * @return array
 */
function getAllUsers(): array
{
    $db = getDB();
    $stmt = $db->query("SELECT id, username, email, full_name, role, language, is_active, created_at FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

/**
 * Get user by ID
 * @param int $id
 * @return array|null
 */
function getUserById(int $id): ?array
{
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, email, full_name, role, language, is_active, created_at FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Get role display name
 * @param string $role
 * @return string
 */
function getRoleName(string $role): string
{
    return __('role_' . $role);
}
