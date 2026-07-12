<?php
/**
 * WebCraft User Authentication and Session Manager
 */
require_once __DIR__ . '/config.php';

$auth_error = '';
$auth_success = '';

// Check actions for authentication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['auth_action'])) {
    $action = $_GET['auth_action'];
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $auth_error = "Security validation failed (Invalid CSRF token).";
    } else {
        $db = get_db_connection();

        if ($action === 'register') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Server-side strict input validation
            if (empty($username) || empty($email) || empty($password)) {
                $auth_error = "All fields are required.";
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                $auth_error = "Username must be 3-20 characters and contain only letters, numbers, or underscores.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
                $auth_error = "Invalid email format (max 100 characters).";
            } elseif (strlen($password) < 8) {
                $auth_error = "Password must be at least 8 characters long.";
            } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $auth_error = "Password must contain at least one letter and one number.";
            } elseif ($password !== $confirm_password) {
                $auth_error = "Passwords do not match.";
            } else {
                // Check if username or email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    $auth_error = "Username or Email already exists.";
                } else {
                    // Check if there are any users in the DB yet; if not, first user becomes Admin!
                    $stmt_count = $db->query("SELECT COUNT(*) as user_count FROM users");
                    $res_count = $stmt_count->fetch();
                    $role = ($res_count['user_count'] == 0) ? 'admin' : 'user';

                    // Hash password using secure bcrypt algorithm
                    $hash = password_hash($password, PASSWORD_BCRYPT);

                    $stmt_insert = $db->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
                    try {
                        $stmt_insert->execute([$username, $email, $hash, $role]);
                        $auth_success = "Registration successful! You can now log in.";
                        header("Location: index.php?action=login&success=" . urlencode($auth_success));
                        exit;
                    } catch (PDOException $e) {
                        $auth_error = "Error registering user: " . $e->getMessage();
                    }
                }
            }
        } elseif ($action === 'login') {
            $username_or_email = trim($_POST['username_or_email'] ?? '');
            $password = $_POST['password'] ?? '';

            // Initialize brute-force rate-limiting tracker in session
            if (empty($_SESSION['login_attempts'])) {
                $_SESSION['login_attempts'] = 0;
                $_SESSION['login_attempts_time'] = time();
            }

            // If 5 consecutive failed login attempts occur within 1 minute, block login attempt
            if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['login_attempts_time']) < 60) {
                $wait = 60 - (time() - $_SESSION['login_attempts_time']);
                $auth_error = "Too many login attempts. Please wait {$wait} seconds.";
            } else {
                // Reset limit block window if 60 seconds have elapsed
                if ((time() - $_SESSION['login_attempts_time']) >= 60) {
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['login_attempts_time'] = time();
                }

                if (empty($username_or_email) || empty($password)) {
                    $auth_error = "Please fill in all credentials.";
                } else {
                    // Fetch user by either username or email securely
                    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username_or_email, $username_or_email]);
                    $user = $stmt->fetch();

                    if ($user && password_verify($password, $user['password_hash'])) {
                        if ($user['status'] !== 'active') {
                            $auth_error = "Your account has been suspended.";
                        } else {
                            // Reset login rate limiting on successful login
                            unset($_SESSION['login_attempts']);
                            unset($_SESSION['login_attempts_time']);

                            // Setup authenticated session variables
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['user_role'] = $user['role'];

                            // Regenerate session id to prevent Session Fixation
                            session_regenerate_id(true);

                            header("Location: admin.php");
                            exit;
                        }
                    } else {
                        $_SESSION['login_attempts']++;
                        $_SESSION['login_attempts_time'] = time();
                        $auth_error = "Invalid username/email or password.";
                    }
                }
            }
        }
    }

    // If there's an error, redirect back with the error message
    if (!empty($auth_error)) {
        $redirect_action = ($action === 'register') ? 'register' : 'login';
        header("Location: index.php?action=" . $redirect_action . "&error=" . urlencode($auth_error));
        exit;
    }
}

// Log out operation
if (isset($_GET['auth_action']) && $_GET['auth_action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: index.php?action=login&success=" . urlencode("Logged out successfully."));
    exit;
}
