<?php
/**
 * Nuvis Webidesigner Open-Source Site Builder Configuration
 * High Security, Modular Architecture compatible with PHP 8.1+ and MySQL/MariaDB
 */

// Load Environment Variables if .env exists
function load_env_variables($file_path) {
    if (!file_exists($file_path)) {
        return;
    }
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Load configurations from .env
load_env_variables(__DIR__ . '/.env');

// Basic Settings with fallbacks
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'site_builder');
define('DB_USER', getenv('DB_USER') ?: 'builder_user');
define('DB_PASS', getenv('DB_PASS') ?: 'builder_pass');

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

            // If the request is a standard GET page load without any action query, output user-friendly raw text.
            // Otherwise (AJAX, POST, or action API queries), return a clean, valid JSON payload to prevent front-end alert parsing crashes.
            $is_page_load = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !isset($_GET['action']));
            if ($is_page_load) {
                die("Database connection failed. Please check the system logs.");
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => "Database connection failed. Details: " . $e->getMessage() . ". Please check your database server status."
                ]);
                exit;
            }
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

/**
 * Write a debug or system log message to the system_logs database table safely.
 *
 * @param string $level Log level (e.g., info, warning, error, debug)
 * @param string $message The log message description
 * @param mixed $context Optional context array or string
 * @return bool True if successfully logged to database, false otherwise.
 */
function write_system_log($level, $message, $context = null) {
    try {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'site_builder';
        $user = getenv('DB_USER') ?: 'builder_user';
        $pass = getenv('DB_PASS') ?: 'builder_pass';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Quietly instantiate PDO
        $pdo = new PDO($dsn, $user, $pass, $options);

        // Prepare context
        $context_str = null;
        if ($context !== null) {
            $context_str = is_string($context) ? $context : json_encode($context);
        }

        $stmt = $pdo->prepare("INSERT INTO `system_logs` (`log_level`, `message`, `context`) VALUES (?, ?, ?)");
        return $stmt->execute([strtolower($level), $message, $context_str]);
    } catch (Throwable $e) {
        // Fallback to PHP system error_log to ensure we never crash
        error_log("Failed to write system log to DB: " . $e->getMessage() . " | Message was: " . $message);
        return false;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        header("Location: admin.php?error=" . urlencode("Unauthorized access. Admin role required."));
        exit;
    }
}
