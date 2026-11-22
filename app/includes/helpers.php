<?php
/**
 * Reside Kenya — shared helpers.
 * Security-focused: escaping, CSRF, prepared-statement wrappers, safe uploads.
 */

/* ------------------------------------------------------------------ *
 *  Session bootstrap
 * ------------------------------------------------------------------ */
function boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    static $handler = null;
    if ($handler === null) {
        $handler = new DbSessionHandler();
        session_set_save_handler($handler, true);
    }
    session_name(SESSION_NAME);
    // Stay signed in on this device: the cookie lasts 90 days and is renewed every time the site is used.
    ini_set('session.gc_maxlifetime', (string) SESSION_DAYS * 86400);
    session_set_cookie_params([
        'lifetime' => SESSION_DAYS * 86400,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ------------------------------------------------------------------ *
 *  Output escaping (XSS prevention)
 * ------------------------------------------------------------------ */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for use inside a URL query value. */
function eurl(?string $value): string
{
    return urlencode((string) $value);
}

/* ------------------------------------------------------------------ *
 *  CSRF protection
 * ------------------------------------------------------------------ */
function csrf_token(): string
{
    boot_session();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string
{
    // Public pages for visitors without a session: no cookie, no token (the form
    // handlers accept same-site posts; see csrf_verify()). Keeps pages cacheable.
    if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE[SESSION_NAME]) && !str_starts_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/')) {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="">';
    }
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    boot_session();
    $sent = $_POST[CSRF_TOKEN_NAME] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if ($sent !== '' && $stored !== '' && hash_equals($stored, $sent)) return true;
    // The session cookie is SameSite=Lax, so a cross-site form post never carries it.
    // A post whose Origin (or Referer) is this same site is therefore genuine even if the
    // token went stale (an old tab, a new session after sign-in, a second device).
    return same_origin_request();
}

/** True when the browser says this request came from a page on this site. */
function same_origin_request(): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') return false;
    $src = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($src === '' || $src === 'null') $src = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($src === '') return false;
    $h = strtolower((string) parse_url($src, PHP_URL_HOST));
    $strip = fn($x) => preg_replace('/^www\./', '', $x);
    return $h !== '' && $strip($h) === $strip($host);
}

/** Abort with 419 if CSRF invalid (for POST handlers). */
/**
 * Website editors call this at the top of their POST handler. A request with
 * _draft=1 only updates the live preview; a real save clears the draft.
 */
function begin_site_save(): void
{
    boot_session();
    if (input('_draft')) { $GLOBALS['SETTINGS_DRAFT'] = true; $_FILES = []; return; }
    unset($_SESSION['site_draft']);
}

function require_csrf(): void
{
    if (!csrf_verify()) {
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'json')) {
            http_response_code(419); header('Content-Type: application/json');
            exit(json_encode(['ok' => false, 'error' => 'Your session refreshed — please try again.']));
        }
        flash('error', 'That form had expired, so nothing was saved. Please try again.');
        $back = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: ' . $back, true, 303);
        exit;
    }
}

/* ------------------------------------------------------------------ *
 *  Input helpers
 * ------------------------------------------------------------------ */
function input(string $key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function input_int(string $key, ?int $default = null): ?int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    if ($v === null || $v === '') return $default;
    return (int) $v;
}

function input_float(string $key, ?float $default = null): ?float
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    if ($v === null || $v === '') return $default;
    return (float) str_replace(',', '', $v);
}

function is_email(?string $v): bool
{
    return (bool) filter_var((string) $v, FILTER_VALIDATE_EMAIL);
}

/* ------------------------------------------------------------------ *
 *  Slugify
 * ------------------------------------------------------------------ */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');
    return $text === '' ? 'item' : $text;
}

/** Ensure slug is unique within a table. */
function unique_slug(string $table, string $base, ?int $ignoreId = null): string
{
    $slug = slugify($base);
    $i = 1;
    $candidate = $slug;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?" . ($ignoreId ? " AND id <> ?" : "") . " LIMIT 1";
        $params = $ignoreId ? [$candidate, $ignoreId] : [$candidate];
        $row = Database::row($sql, $params);
        if (!$row) return $candidate;
        $candidate = $slug . '-' . (++$i);
    }
}

/* ------------------------------------------------------------------ *
 *  Settings (cached)
 * ------------------------------------------------------------------ */
function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (Database::all("SELECT setting_key, setting_value FROM settings") as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
        // Admin preview: unsaved edits from the website editor are layered on top.
        if (isset($_GET['preview']) && !empty($_COOKIE[SESSION_NAME])) {
            boot_session();
            if (!empty($_SESSION['user']['id']) && !empty($_SESSION['site_draft']) && is_array($_SESSION['site_draft'])) {
                $cache = array_merge($cache, $_SESSION['site_draft']);
            }
        }
    }
    return $cache;
}

function setting(string $key, $default = ''): string
{
    $s = settings();
    return $s[$key] ?? (string) $default;
}

function setting_save(string $key, string $value): void
{
    if (!empty($GLOBALS['SETTINGS_DRAFT'])) { // preview only: keep it in this admin's session
        boot_session();
        $_SESSION['site_draft'][$key] = $value;
        return;
    }
    Database::run(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value",
        [$key, $value]
    );
}

/* ------------------------------------------------------------------ *
 *  Formatting
 * ------------------------------------------------------------------ */
function money(?float $amount, string $currency = 'KES'): string
{
    if ($amount === null) return 'Price on request';
    return $currency . ' ' . number_format($amount, 0);
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Public URL for an uploaded/stored asset path. */
/**
 * A resized, compressed (WebP) copy of an uploaded or bundled image, served from
 * Vercel's image CDN. Falls back to the original file when running locally.
 */
const IMG_WIDTHS = [256, 384, 640, 828, 1080, 1200, 1920];
function img_src(?string $path, int $w = 828): string
{
    $u = asset_url($path);
    if (getenv('VERCEL') !== '1' || str_ends_with(strtolower(parse_url($u, PHP_URL_PATH) ?? ''), '.svg')) return $u;
    $base = rtrim(BASE_URL, '/');
    if (str_starts_with($u, $base . '/')) $u = substr($u, strlen($base));
    elseif (!preg_match('#^https://[a-z0-9]+\.public\.blob\.vercel-storage\.com/uploads/#', $u)) return $u;
    if (!preg_match('#^(/(uploads|assets)/|https://)#', $u)) return $u;
    foreach (IMG_WIDTHS as $s) if ($s >= $w) { $w = $s; break; }
    if ($w > 1920) $w = 1920;
    return '/_vercel/image?url=' . rawurlencode($u) . '&w=' . $w . '&q=70';
}

/** Picture for a property-type tile: chosen image, else a listing photo, else a placeholder. */
function cat_tile_image(array $cat): string
{
    if ($img = real_photo($cat['tile_image'] ?? null)) return $img;
    return ph_url(ph_key_for(($cat['name'] ?? '') . ' ' . ($cat['slug'] ?? '')), 800);
}

/** srcset for responsive images: img_srcset($path, [384, 640, 1080]). */
function img_srcset(?string $path, array $widths): string
{
    if (getenv('VERCEL') !== '1') return '';
    return implode(', ', array_map(fn($w) => img_src($path, $w) . ' ' . $w . 'w', $widths));
}

function asset_url(?string $path): string
{
    if (!$path) return url('assets/img/placeholder.jpg');
    if (preg_match('#^https?://#', $path)) return $path;
    return url($path);
}

/* ------------------------------------------------------------------ *
 *  Flash messages
 * ------------------------------------------------------------------ */
function flash(string $key, ?string $msg = null)
{
    // Reading a message needs no new session: a visitor without one has none waiting.
    if ($msg === null && session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE[SESSION_NAME])) return null;
    if ($msg !== null && !empty($GLOBALS['SETTINGS_DRAFT'])) return null;
    boot_session();
    if ($msg !== null) { $_SESSION['_flash'][$key] = $msg; return null; }
    $v = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $v;
}

/* ------------------------------------------------------------------ *
 *  Redirect
 * ------------------------------------------------------------------ */
function redirect(string $to): void
{
    if (!empty($GLOBALS['SETTINGS_DRAFT'])) { // preview save: answer the editor instead of navigating
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'draft' => true]);
        exit;
    }
    header('Location: ' . (preg_match('#^https?://#', $to) ? $to : url($to)));
    exit;
}

/* ------------------------------------------------------------------ *
 *  Rate limiting (DB-backed sliding window)
 * ------------------------------------------------------------------ */
/** True when this visitor has already used up the allowance (does not count a hit). */
function rate_blocked(string $bucketKey, int $max, int $windowSeconds): bool
{
    $row = Database::row("SELECT hits FROM rate_limits WHERE bucket = ? AND window_start >= ? ORDER BY id DESC LIMIT 1",
        [$bucketKey . ':' . client_ip(), date('Y-m-d H:i:s', time() - $windowSeconds)]);
    return $row && (int) $row['hits'] >= $max;
}

function rate_limit(string $bucketKey, int $max, int $windowSeconds): bool
{
    $ip = client_ip();
    $bucket = $bucketKey . ':' . $ip;
    $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);
    Database::run("DELETE FROM rate_limits WHERE window_start < ?", [$cutoff]);
    $row = Database::row("SELECT id, hits FROM rate_limits WHERE bucket = ? ORDER BY id DESC LIMIT 1", [$bucket]);
    if ($row) {
        if ((int)$row['hits'] >= $max) return false;
        Database::run("UPDATE rate_limits SET hits = hits + 1 WHERE id = ?", [$row['id']]);
    } else {
        Database::run("INSERT INTO rate_limits (bucket, hits) VALUES (?, 1)", [$bucket]);
    }
    return true;
}

function client_ip(): string
{
    $fwd = $_SERVER['HTTP_X_REAL_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($fwd !== '') {
        $ip = trim(explode(',', $fwd)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* ------------------------------------------------------------------ *
 *  Activity log
 * ------------------------------------------------------------------ */
function log_activity(string $action, ?string $entity = null, ?int $entityId = null): void
{
    if (!empty($GLOBALS['SETTINGS_DRAFT'])) return;
    $uid = $_SESSION['user']['id'] ?? null;
    Database::run(
        "INSERT INTO activity_log (user_id, action, entity, entity_id, ip_address) VALUES (?,?,?,?,?)",
        [$uid, $action, $entity, $entityId, client_ip()]
    );
}

/* ------------------------------------------------------------------ *
 *  Secure file upload
 *  Returns relative stored path (e.g. uploads/properties/xxx.webp) or null.
 * ------------------------------------------------------------------ */
function save_upload(array $file, string $subdir, array $allowedExt): ?string
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_UPLOAD_BYTES) return null;
    if (!is_uploaded_file($file['tmp_name'])) return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) return null;

    // Verify MIME by content, not just extension.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $okMimes = [
        'image/jpeg','image/png','image/webp','image/gif','application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip','application/x-zip-compressed','application/octet-stream',
    ];
    // Generic binary types are only accepted for office/zip files, never as images.
    if ($mime === 'application/octet-stream' && !in_array($ext, ['doc','docx','xls','xlsx','zip'], true)) return null;
    if (str_starts_with($mime, 'image/') === false && in_array($ext, ALLOWED_IMAGE_EXT, true)) return null;
    if (!in_array($mime, $okMimes, true)) return null;

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $rel  = 'uploads/' . trim($subdir, '/') . '/' . $name;

    // On Vercel the filesystem is read-only: store the file in Vercel Blob
    // and keep its full public URL (asset_url() passes absolute URLs through).
    if (BLOB_TOKEN !== '') {
        return blob_put($rel, (string) file_get_contents($file['tmp_name']), $mime);
    }

    $dir = UPLOAD_PATH . '/' . trim($subdir, '/');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    @chmod($dest, 0644);

    return $rel;
}

/* ------------------------------------------------------------------ *
 *  Vercel Blob storage (REST API)
 * ------------------------------------------------------------------ */
function blob_request(string $method, string $path, ?string $body, array $headers): ?array
{
    if (BLOB_TOKEN === '') return null;
    $parts = explode('_', BLOB_TOKEN);
    $storeId = $parts[3] ?? '';
    $h = [
        'authorization: Bearer ' . BLOB_TOKEN,
        'x-api-version: 13',
        'x-vercel-blob-store-id: ' . $storeId,
    ];
    foreach ($headers as $k => $v) $h[] = $k . ': ' . $v;
    $ch = curl_init('https://vercel.com/api/blob' . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $h,
        CURLOPT_TIMEOUT        => 60,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $code >= 300) {
        error_log('Blob ' . $method . ' ' . $path . ' failed: HTTP ' . $code . ' ' . substr((string) $resp, 0, 300));
        return null;
    }
    return json_decode((string) $resp, true) ?: [];
}

/** Upload bytes to Vercel Blob; returns the public URL or null. */
function blob_put(string $pathname, string $bytes, string $contentType): ?string
{
    $res = blob_request('PUT', '/?' . http_build_query(['pathname' => $pathname]), $bytes, [
        'x-vercel-blob-access' => 'public',
        'x-content-type'       => $contentType,
        'x-add-random-suffix'  => '0',
        'x-content-length'     => (string) strlen($bytes),
    ]);
    return $res['url'] ?? null;
}

/** True for a path/URL produced by save_upload() (guards hidden form fields). */
function is_stored_upload(string $path): bool
{
    return (bool) preg_match('#^(uploads/properties/[a-f0-9]{32}\.(jpe?g|png|webp|gif)|https://[a-z0-9.-]+\.blob\.vercel-storage\.com/uploads/properties/[a-f0-9]{32}\.(jpe?g|png|webp|gif))$#i', $path);
}

/** Remove a stored upload (Blob URL or local file). */
function delete_upload(?string $path): void
{
    if (!$path) return;
    if (preg_match('#^https://[^/]+\.blob\.vercel-storage\.com/#', $path)) {
        blob_request('POST', '/delete', json_encode(['urls' => [$path]]), ['content-type' => 'application/json']);
        return;
    }
    if (str_starts_with($path, 'uploads/')) {
        $abs = PUBLIC_PATH . '/' . $path;
        if (is_file($abs) && is_writable($abs)) @unlink($abs);
    }
}

/* ------------------------------------------------------------------ *
 *  Simple mailer (uses PHP mail(); swap for SMTP/PHPMailer if needed)
 * ------------------------------------------------------------------ */
function send_mail(string $to, string $subject, string $htmlBody): bool
{
    // Outgoing email is switched off on the Vercel hosting: every inquiry,
    // booking and submission is saved and shows up in the admin panel.
    // Plug an email provider in here later if notifications are wanted.
    return true;
}

/* ------------------------------------------------------------------ *
 *  reCAPTCHA verify (no-op if keys blank)
 * ------------------------------------------------------------------ */
function recaptcha_ok(): bool
{
    if (RECAPTCHA_SECRET_KEY === '') return true; // disabled
    $token = $_POST['g-recaptcha-response'] ?? '';
    if ($token === '') return false;
    $resp = @file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify?' . http_build_query([
            'secret'   => RECAPTCHA_SECRET_KEY,
            'response' => $token,
            'remoteip' => client_ip(),
        ])
    );
    $data = json_decode($resp ?: '{}', true);
    return !empty($data['success']);
}

/* ------------------------------------------------------------------ *
 *  JSON response helper (for API endpoints)
 * ------------------------------------------------------------------ */
function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
