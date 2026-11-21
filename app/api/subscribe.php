<?php
/**
 * POST /api/subscribe.php  (AJAX)
 * Newsletter subscription. Expects: email, _csrf.
 * Returns JSON { ok, message }.
 */
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Invalid request.'], 405);
}
if (!csrf_verify()) {
    json_out(['ok' => false, 'message' => 'Session expired. Please refresh and try again.'], 419);
}
if (!rate_limit('subscribe', 10, 3600)) {
    json_out(['ok' => false, 'message' => 'Too many attempts. Please try again later.'], 429);
}

$email = strtolower(trim((string) input('email', '')));
if (!is_email($email)) {
    json_out(['ok' => false, 'message' => 'Please enter a valid email address.'], 422);
}

try {
    Database::run(
        "INSERT INTO subscribers (email) VALUES (?)
         ON CONFLICT (email) DO NOTHING",
        [$email]
    );
} catch (Throwable $e) {
    json_out(['ok' => false, 'message' => 'Could not subscribe right now. Please try again.'], 500);
}

json_out(['ok' => true, 'message' => 'You are subscribed. Watch your inbox for new listings.']);
