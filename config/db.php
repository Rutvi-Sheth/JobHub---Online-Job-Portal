<?php
/**
 * JobHub - Database Configuration
 * File: config/db.php
 * Description: PDO connection, CSRF helpers, security utilities.
 *              All queries use prepared statements to prevent SQL injection.
 */

// ── Error reporting (disable in production) ──────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ── Database Credentials ─────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'jobhub_db');
define('DB_USER', 'root');
define('DB_PASS', '');       // WAMP default: empty password
define('DB_CHARSET', 'utf8mb4');

// ── Application Constants ─────────────────────────────────────────────────────
define('BASE_URL',    'http://localhost/JobPortal_Project');
define('UPLOAD_PATH', __DIR__ . '/../uploads/resumes/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024);  // 2 MB
define('ALLOWED_TYPES', ['application/pdf',
                          'application/msword',
                          'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
define('ALLOWED_EXT',   ['pdf', 'doc', 'docx']);

// ── Session Configuration ─────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);  // Prevent JS access to session cookie
    ini_set('session.use_strict_mode', 1);  // Prevent session fixation
    session_start();
}

// ── PDO Connection (Singleton) ────────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,  // Real prepared statements
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Generic error message to prevent info leakage
            die(json_encode(['error' => 'Database connection failed. Please try again later.']));
        }
    }
    return $pdo;
}

// ── CSRF Token Functions ──────────────────────────────────────────────────────

/**
 * Generate a CSRF token and store it in session
 */
function generateCSRF(): string {
    if (!empty($_SESSION['csrf_token']) && !empty($_SESSION['csrf_time']) && (time() - $_SESSION['csrf_time']) <= 1800) {
        return $_SESSION['csrf_token'];
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_time']  = time();
    return $token;
}

/**
 * Validate submitted CSRF token
 */
function validateCSRF(string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_time'])) {
        return false;
    }
    // Token expires after 30 minutes
    if ((time() - $_SESSION['csrf_time']) > 1800) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ── XSS Prevention ────────────────────────────────────────────────────────────

/**
 * Sanitise output to prevent XSS
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitise and trim input
 */
function sanitise(string $input): string {
    return trim(strip_tags($input));
}

// ── Authentication Helpers ────────────────────────────────────────────────────

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login — redirect to login page if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Require a specific role
 */
function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: ' . BASE_URL . '/login.php?error=access_denied');
        exit;
    }
}

/**
 * Redirect to appropriate dashboard based on role
 */
function redirectToDashboard(): void {
    $role = $_SESSION['user_role'] ?? '';
    switch ($role) {
        case 'admin':     header('Location: ' . BASE_URL . '/admin/dashboard.php');     break;
        case 'recruiter': header('Location: ' . BASE_URL . '/recruiter/dashboard.php'); break;
        default:          header('Location: ' . BASE_URL . '/candidate/dashboard.php'); break;
    }
    exit;
}

/**
 * Flash message helper — store in session, display once
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
