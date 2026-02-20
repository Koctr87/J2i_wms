<?php
/**
 * J2i Warehouse Management System
 * Login Page - Premium ShipNow Style
 */
require_once __DIR__ . '/../../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('../dashboard.php');
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('login.php');
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    // Get the first active user (usually admin)
    $stmt = $db->query("SELECT * FROM users WHERE is_active = 1 LIMIT 1");
    $user = $stmt->fetch();

    if ($user) {
        // Success
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['language'] = $user['language'];

        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$user['id']]);

        redirect('../dashboard.php');
    } else {
        $error = "No active user found in database.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - J2i WMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #E74C3C;
            --primary-hover: #D44332;
            --primary-light: #FDEAEA;
            --gray-50: #F8F9FA;
            --gray-100: #F1F3F5;
            --gray-200: #E9ECEF;
            --gray-300: #DEE2E6;
            --gray-400: #CED4DA;
            --gray-500: #ADB5BD;
            --gray-600: #6C757D;
            --gray-700: #495057;
            --gray-800: #343A40;
            --gray-900: #212529;
            --white: #FFFFFF;
            --radius: 8px;
            --radius-lg: 12px;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #F4F5F7 0%, #E9ECEF 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            min-height: 600px;
        }

        /* Left Panel - Branding */
        .login-brand {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, #C0392B 100%);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .login-brand::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        }

        .login-brand-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .login-brand-logo svg {
            width: 48px;
            height: 48px;
        }

        .login-brand h1 {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .login-brand p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        .login-features {
            margin-top: 3rem;
            position: relative;
            z-index: 1;
        }

        .login-feature {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .login-feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .login-feature-text {
            font-size: 0.95rem;
        }

        /* Right Panel - Form */
        .login-form-container {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 2rem;
        }

        .login-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--gray-800);
            background: var(--white);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-control::placeholder {
            color: var(--gray-400);
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        .form-check-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .forgot-link {
            font-size: 0.875rem;
            color: var(--primary);
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 1rem;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            color: var(--white);
            background: var(--primary);
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .btn:hover {
            background: var(--primary-hover);
        }

        .btn:active {
            transform: scale(0.98);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }

        .login-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        /* Languages */
        .lang-switcher {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .lang-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-100);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 1.25rem;
            text-decoration: none;
            transition: background 0.2s;
        }

        .lang-btn:hover {
            background: var(--gray-200);
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 400px;
            }

            .login-brand {
                padding: 2rem;
                min-height: auto;
            }

            .login-brand h1 {
                font-size: 1.75rem;
            }

            .login-features {
                display: none;
            }

            .login-form-container {
                padding: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Left Brand Panel -->
        <div class="login-form-container">
            <div class="login-header" style="text-align: center;">
                <img src="<?= APP_URL ?>/assets/img/logo.png" alt="J2i Logo"
                    style="width: 120px; height: auto; margin-bottom: 2rem;">
                <h2>Welcome</h2>
                <p>Access the J2i Warehouse Management System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="auto_login" value="1">
                <button type="submit" class="btn" style="height: 60px; font-size: 1.25rem;">Войти</button>
            </form>

            <div class="login-footer">
                <p>&copy; 2024 J2i WMS. All rights reserved.</p>
            </div>
        </div>

        <div class="login-footer">
            <p>&copy; 2024 J2i WMS. All rights reserved.</p>

            <div class="lang-switcher">
                <a href="?lang=ru" class="lang-btn">🇷🇺</a>
                <a href="?lang=cs" class="lang-btn">🇨🇿</a>
                <a href="?lang=uk" class="lang-btn">🇺🇦</a>
                <a href="?lang=en" class="lang-btn">🇬🇧</a>
            </div>
        </div>
    </div>
    </div>
</body>

</html>