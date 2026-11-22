<?php
/**
 * Web Push notifications for the admin app (VAPID + aes128gcm, RFC 8291/8292).
 * No libraries needed — uses PHP's openssl + curl.
 *
 * Keys come from env: VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY (base64url, raw P-256).
 */

function b64u_enc(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64u_dec(string $s): string { return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

function vapid_public_key(): string { return getenv('VAPID_PUBLIC_KEY') ?: ''; }
function push_enabled(): bool { return vapid_public_key() !== '' && (getenv('VAPID_PRIVATE_KEY') ?: '') !== ''; }

/** One-off schema additions for features added after the move to Vercel. */
function ensure_schema(): void
{
    if ((int) setting('schema_version', '1') >= 2) return;
    try {
        Database::conn()->exec("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
              id serial PRIMARY KEY,
              user_id integer REFERENCES users(id) ON DELETE CASCADE,
              endpoint text NOT NULL UNIQUE,
              p256dh text NOT NULL,
              auth text NOT NULL,
              user_agent varchar(255),
              created_at timestamp NOT NULL DEFAULT NOW(),
              last_ok_at timestamp
            );
            ALTER TABLE properties ADD COLUMN IF NOT EXISTS source_url varchar(500);
        ");
        setting_save('schema_version', '2');
    } catch (Throwable $e) {
        error_log('ensure_schema: ' . $e->getMessage());
    }
}

/** PEM for a raw uncompressed P-256 public key (65 bytes). */
function p256_public_pem(string $raw): string
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** PEM for a raw P-256 private key (32 bytes) + its public key (65 bytes). */
function p256_private_pem(string $d, string $pub): string
{
    $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $pub;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

/** DER ECDSA signature -> raw 64-byte r||s (JOSE format). */
function ecdsa_der_to_raw(string $der): string
{
    $pos = 2; // SEQUENCE header (short form for P-256)
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$pos + 1]);
        $int = substr($der, $pos + 2, $len);
        $int = ltrim($int, "\x00");
        $out .= str_pad($int, 32, "\x00", STR_PAD_LEFT);
        $pos += 2 + $len;
    }
    return $out;
}

function vapid_headers(string $endpoint): array
{
    $pub = b64u_dec(vapid_public_key());
    $key = openssl_pkey_get_private(p256_private_pem(b64u_dec(getenv('VAPID_PRIVATE_KEY')), $pub));
    $u = parse_url($endpoint);
    $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $h = b64u_enc(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $p = b64u_enc(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:residekenya@gmail.com'], JSON_UNESCAPED_SLASHES));
    openssl_sign("$h.$p", $sig, $key, OPENSSL_ALGO_SHA256);
    $jwt = "$h.$p." . b64u_enc(ecdsa_der_to_raw($sig));
    return ['Authorization: vapid t=' . $jwt . ', k=' . vapid_public_key()];
}

function hkdf_sha256(string $salt, string $ikm, string $info, int $len): string
{
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $len);
}

/** Encrypt a payload for one subscription (aes128gcm content coding). */
function push_encrypt(string $payload, string $uaPublicB64, string $authB64): string
{
    $uaPublic = b64u_dec($uaPublicB64);
    $authSecret = b64u_dec($authB64);

    $local = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $det = openssl_pkey_get_details($local)['ec'];
    $asPublic = "\x04" . str_pad($det['x'], 32, "\x00", STR_PAD_LEFT) . str_pad($det['y'], 32, "\x00", STR_PAD_LEFT);

    $shared = openssl_pkey_derive(openssl_pkey_get_public(p256_public_pem($uaPublic)), $local, 32);

    $ikm   = hkdf_sha256($authSecret, $shared, "WebPush: info\x00" . $uaPublic . $asPublic, 32);
    $salt  = random_bytes(16);
    $cek   = hkdf_sha256($salt, $ikm, "Content-Encoding: aes128gcm\x00", 16);
    $nonce = hkdf_sha256($salt, $ikm, "Content-Encoding: nonce\x00", 12);

    $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $salt . pack('N', 4096) . chr(65) . $asPublic . $ct . $tag;
}

/**
 * Send a notification to every admin device that turned notifications on.
 * $data: title, body, url, tag. Returns number of devices reached.
 */
function notify_user(int $userId, string $title, string $body, string $url = 'admin/', string $tag = 'reside'): int
{
    return notify_admins($title, $body, $url, $tag, $userId);
}

function notify_admins(string $title, string $body, string $url = 'admin/', string $tag = 'reside', ?int $onlyUser = null): int
{
    if (!push_enabled()) return 0;
    try {
        ensure_schema();
        $subs = $onlyUser
            ? Database::all("SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?", [$onlyUser])
            : Database::all("SELECT id, endpoint, p256dh, auth FROM push_subscriptions");
    } catch (Throwable $e) {
        return 0;
    }
    if (!$subs) return 0;

    $badge = 0;
    try {
        $badge = (int) Database::row("SELECT
            (SELECT COUNT(*) FROM inquiries WHERE is_read = 0) +
            (SELECT COUNT(*) FROM bookings WHERE status = 'pending') +
            (SELECT COUNT(*) FROM submissions WHERE status = 'new') AS n")['n'];
    } catch (Throwable $e) {}

    $payload = json_encode([
        'title' => $title, 'body' => $body, 'url' => url($url), 'tag' => $tag, 'badge' => $badge,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $mh = curl_multi_init();
    $handles = [];
    foreach ($subs as $s) {
        try {
            $bodyBin = push_encrypt($payload, $s['p256dh'], $s['auth']);
        } catch (Throwable $e) { continue; }
        $ch = curl_init($s['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $bodyBin,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => array_merge(vapid_headers($s['endpoint']), [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 86400',
                'Urgency: high',
                'Topic: ' . substr(preg_replace('/[^A-Za-z0-9_-]/', '', $tag), 0, 32),
            ]),
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[(int) $s['id']] = $ch;
    }
    do { $st = curl_multi_exec($mh, $running); if ($running) curl_multi_select($mh, 1); } while ($running && $st === CURLM_OK);

    $ok = 0;
    foreach ($handles as $id => $ch) {
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code >= 200 && $code < 300) {
            $ok++;
            Database::run("UPDATE push_subscriptions SET last_ok_at = NOW() WHERE id = ?", [$id]);
        } elseif (in_array($code, [404, 410], true)) {
            Database::run("DELETE FROM push_subscriptions WHERE id = ?", [$id]); // device unsubscribed
        } else {
            error_log("push $id failed: HTTP $code " . substr((string) curl_multi_getcontent($ch), 0, 200));
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $ok;
}
