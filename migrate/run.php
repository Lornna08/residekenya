<?php
/**
 * One-time import of the cPanel database into Neon Postgres.
 * Needs MIGRATE_KEY (decryption key) and MIGRATE_TOKEN set in Vercel, and
 * the request must carry ?token=<MIGRATE_TOKEN>. Refuses to overwrite a
 * database that already has properties unless &force=1 is given.
 */
header('Content-Type: text/plain; charset=UTF-8');
$key   = getenv('MIGRATE_KEY') ?: '';
$token = getenv('MIGRATE_TOKEN') ?: '';
$file  = __DIR__ . '/data.enc';
if ($key === '' || $token === '' || !is_file($file) || !hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit('Not found');
}
require __DIR__ . '/../app/includes/config.php';
require __DIR__ . '/../app/includes/db.php';
$pdo = Database::conn();

$hasData = false;
try { $hasData = (int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn() > 0; } catch (Throwable $e) {}
if ($hasData && empty($_GET['force'])) exit("Database already has properties — nothing done.\n");

$raw = base64_decode(file_get_contents($file));
$iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $ct = substr($raw, 28);
$sql = openssl_decrypt($ct, 'aes-256-gcm', hex2bin($key), OPENSSL_RAW_DATA, $iv, $tag);
if ($sql === false) { http_response_code(500); exit("Could not decrypt data.\n"); }
$sql = gzdecode($sql);

$pdo->exec(file_get_contents(__DIR__ . '/../sql/schema.pgsql'));
$pdo->exec($sql);

foreach (['properties', 'property_images', 'users', 'agents', 'categories', 'counties', 'towns', 'inquiries', 'settings', 'testimonials', 'activity_log'] as $t) {
    echo str_pad($t, 18) . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() . "\n";
}
echo "OK\n";
