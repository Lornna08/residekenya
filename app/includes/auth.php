<?php
/**
 * Auth — session-based admin authentication with role guards & throttling.
 */

function auth_attempt(string $email, string $password): bool
{
    if (rate_blocked('login', 8, 900)) { // 8 wrong passwords / 15 min
        flash('error', 'Too many attempts. Please wait 15 minutes and try again.');
        return false;
    }
    $user = Database::row(
        "SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1",
        [strtolower(trim($email))]
    );
    if (!$user || !password_verify($password, $user['password_hash'])) {
        rate_limit('login', 8, 900); // only wrong passwords count toward the lock-out
        return false;
    }
    // Rehash if algorithm/cost changed.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        Database::run("UPDATE users SET password_hash = ? WHERE id = ?",
            [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    boot_session();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => (int)$user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
    Database::run("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
    log_activity('login', 'user', (int)$user['id']);
    return true;
}

function auth_logout(): void
{
    boot_session();
    log_activity('logout', 'user', $_SESSION['user']['id'] ?? null);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    boot_session();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Guard: redirect to login if not authenticated. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please sign in to continue.');
        redirect('admin/login');
    }
}

/** Guard: require one of the given roles. */
function require_role(array $roles): void
{
    require_login();
    $u = current_user();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function user_can(string ...$roles): bool
{
    $u = current_user();
    return $u && in_array($u['role'], $roles, true);
}
