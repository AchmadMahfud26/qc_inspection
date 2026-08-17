<?php
// includes/functions.php
// Common helper functions for QC INSPECTION

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Output-safe escape for HTML
function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Flash messages (simple)
function set_flash(string $key, string $message)
{
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $m = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $m;
    }
    return null;
}

// CSRF token helpers
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(string $token): bool
{
    return hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token);
}

// Authentication helpers
function login_user(array $user)
{
    // store minimal user info in session
    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'role' => $user['role']
    ];
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function require_login()
{
    if (!is_logged_in()) {
        header('Location: /qc_inspection/auth/login.php');
        exit;
    }
}

function has_role(string $role): bool
{
    if (!is_logged_in()) return false;
    return $_SESSION['user']['role'] === $role;
}

// Simple IP helper
function get_user_ip(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// Activity log helper
function log_activity(PDO $pdo, ?int $user_id, string $activity, string $module = null, string $reference_id = null)
{
    $sql = "INSERT INTO activity_logs (user_id, activity, module, reference_id, ip_address) VALUES (:user_id, :activity, :module, :reference_id, :ip)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':activity' => $activity,
        ':module' => $module,
        ':reference_id' => $reference_id,
        ':ip' => get_user_ip()
    ]);
}

// Simple validation helpers
function validate_required(array $data, array $fields): array
{
    $errors = [];
    foreach ($fields as $f) {
        if (empty($data[$f]) && $data[$f] !== '0') {
            $errors[$f] = 'Field ' . $f . ' is required.';
        }
    }
    return $errors;
}

?>