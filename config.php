<?php
/**
 * WebCraft Open-Source Site Builder Configuration
 * High Security, Modular Architecture compatible with PHP 7.4+ and MySQL/MariaDB
 */

// Basic Settings
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'site_builder');
define('DB_USER', 'builder_user');
define('DB_PASS', 'builder_pass');

// Start PHP Session safely if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Enable session security configurations
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/**
 * Get Secure Database Connection via PDO
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Securely log errors or display a safe error message
            error_log("DB connection error: " . $e->getMessage());
            die("Database connection failed. Please check the system logs.");
        }
    }
    return $pdo;
}

/**
 * Cross-Site Request Forgery (CSRF) protection helper
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize HTML/Text to prevent XSS (Cross Site Scripting)
 */
function sanitize_output($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Authenticated user access checks
 */
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: index.php?action=login");
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        header("Location: admin.php?error=" . urlencode("Unauthorized access. Admin role required."));
        exit;
    }
}
