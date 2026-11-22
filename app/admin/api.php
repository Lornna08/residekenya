<?php
/**
 * admin/api.php — JSON API for the Reside Kenya workspace (the new admin app).
 *   GET  ?action=state              full workspace state for the signed-in user
 *   POST ?action=sync               save changed records (listings, leads, deals, …)
 *   POST ?action=chat_read          mark a conversation read
 *   POST ?action=password           change own password {current, new}
 *   POST ?action=avatar             upload a profile photo (multipart "avatar")
 *   POST ?action=profile            update own name / phone / job title
 *   POST ?action=upload             upload a photo or document (multipart "file")
 *   GET  ?action=blob_token         short-lived token for large direct uploads
 */
require __DIR__ . '/../includes/bootstrap.php';
if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Your session has ended. Please sign in again.', 'login' => true], 401);
crm_schema();
$uid = (int) current_user()['id'];
$action = (string) input('action', 'state');

try {
    if ($action === 'state') {
        Database::run("UPDATE users SET last_seen_at = NOW() WHERE id = ?", [$uid]);
        header('Cache-Control: no-store');
        json_out(['ok' => true, 'state' => crm_state($uid)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Invalid request.'], 405);
    require_csrf();
    $body = json_decode((string) file_get_contents('php://input'), true) ?: [];

    if ($action === 'sync') json_out(crm_sync($body, $uid));

    if ($action === 'chat_read') {
        $cid = (string) ($body['convId'] ?? '');
        if (preg_match('/^dm-(\d+)-(\d+)$/', $cid, $m) && in_array($uid, [(int) $m[1], (int) $m[2]], true)) {
            Database::run("INSERT INTO chat_reads (user_id, conv_id, read_at) VALUES (?,?,NOW()) ON CONFLICT (user_id, conv_id) DO UPDATE SET read_at = NOW()", [$uid, $cid]);
        }
        json_out(['ok' => true]);
    }

    if ($action === 'profile') {
        $target = preg_match('/^u?(\d+)$/', (string) ($body['user'] ?? ''), $tm) ? (int) $tm[1] : $uid;
        if ($target !== $uid && !in_array(current_user()['role'], ['super_admin', 'admin'], true)) json_out(['ok' => false, 'error' => 'Only admins can edit other people’s profiles.'], 403);
        $name = trim((string) ($body['name'] ?? ''));
        if (mb_strlen($name) < 2) json_out(['ok' => false, 'error' => 'Enter a name.'], 422);
        Database::run("UPDATE users SET name = ?, phone = ?, title = ? WHERE id = ?", [mb_substr($name, 0, 120), mb_substr(trim((string) ($body['phone'] ?? '')), 0, 30) ?: null, mb_substr(trim((string) ($body['title'] ?? '')), 0, 120) ?: null, $target]);
        if ($target === $uid) $_SESSION['user']['name'] = $name;
        json_out(['ok' => true, 'team' => crm_team()]);
    }

    if ($action === 'password') {
        $cur = (string) ($body['current'] ?? ''); $new = (string) ($body['new'] ?? '');
        $row = Database::row("SELECT password_hash FROM users WHERE id = ?", [$uid]);
        if (!$row || !password_verify($cur, (string) $row['password_hash'])) json_out(['ok' => false, 'error' => 'Your current password isn’t right.'], 422);
        if (strlen($new) < 8) json_out(['ok' => false, 'error' => 'Use at least 8 characters for the new password.'], 422);
        Database::run("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), $uid]);
        json_out(['ok' => true]);
    }

    if ($action === 'avatar') {
        $target = (int) input('user', $uid);
        if ($target !== $uid && !in_array(current_user()['role'], ['super_admin', 'admin'], true)) json_out(['ok' => false, 'error' => 'Only admins can change other people’s photos.'], 403);
        $path = save_upload($_FILES['avatar'] ?? [], 'avatars', ['jpg', 'jpeg', 'png', 'webp']);
        if (!$path) json_out(['ok' => false, 'error' => 'Choose a JPG, PNG or WebP photo.'], 422);
        $old = Database::row("SELECT avatar FROM users WHERE id = ?", [$target]);
        Database::run("UPDATE users SET avatar = ? WHERE id = ?", [$path, $target]);
        if (!empty($old['avatar'])) delete_upload($old['avatar']);
        json_out(['ok' => true, 'avatar' => asset_url($path), 'team' => crm_team()]);
    }

    if ($action === 'upload') {
        $kind = (string) input('kind', 'photo');
        $allowed = $kind === 'doc' ? ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp', 'zip', 'xls', 'xlsx'] : ALLOWED_IMAGE_EXT;
        $path = save_upload($_FILES['file'] ?? [], $kind === 'doc' ? 'documents' : 'properties', $allowed);
        if (!$path) json_out(['ok' => false, 'error' => $kind === 'doc' ? 'That file type isn’t allowed (PDF, Word, Excel, images or ZIP).' : 'Choose a JPG, PNG or WebP photo.'], 422);
        json_out(['ok' => true, 'path' => $path, 'url' => asset_url($path)]);
    }

    if ($action === 'blob_token') {
        // Lets the browser send a large document straight to Vercel Blob (no 4.5 MB limit).
        if (BLOB_TOKEN === '') json_out(['ok' => false, 'local' => true]);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($body['name'] ?? 'file'));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp', 'zip', 'xls', 'xlsx'], true)) json_out(['ok' => false, 'error' => 'That file type isn’t allowed.'], 422);
        $pathname = 'uploads/documents/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $payload = base64_encode(json_encode(['pathname' => $pathname, 'maximumSizeInBytes' => 25 * 1024 * 1024, 'addRandomSuffix' => false, 'validUntil' => (time() + 600) * 1000], JSON_UNESCAPED_SLASHES));
        $sig = hash_hmac('sha256', $payload, BLOB_TOKEN);
        $storeId = explode('_', BLOB_TOKEN)[3] ?? '';
        json_out(['ok' => true, 'pathname' => $pathname, 'storeId' => $storeId, 'token' => 'vercel_blob_client_' . $storeId . '_' . base64_encode($sig . '.' . $payload)]);
    }

    json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log('admin api ' . $action . ': ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
    json_out(['ok' => false, 'error' => APP_ENV === 'production' ? 'Couldn’t save — please try again.' : $e->getMessage()], 500);
}
