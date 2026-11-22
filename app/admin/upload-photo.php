<?php
/**
 * admin/upload-photo.php — receives ONE photo per request (keeps each request
 * well under Vercel's 4.5 MB limit, so a listing can have any number of photos).
 * Returns JSON {ok, path, url}.
 */
require __DIR__ . '/../includes/bootstrap.php';
if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Please sign in again.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Invalid request.'], 405);
require_csrf();

$file = $_FILES['photo'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    json_out(['ok' => false, 'error' => $code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE ? 'Photo is too large.' : 'No photo received.'], 422);
}
$path = save_upload($file, 'properties', ALLOWED_IMAGE_EXT);
if (!$path) json_out(['ok' => false, 'error' => 'That file is not a supported photo (JPG, PNG, WebP).'], 422);
json_out(['ok' => true, 'path' => $path, 'url' => asset_url($path)]);
