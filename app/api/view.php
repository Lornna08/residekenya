<?php
/** api/view.php — counts a property view (sent by a beacon from the property page). */
require __DIR__ . '/../includes/bootstrap.php';
header('Cache-Control: no-store');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$bot = preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
if ($id > 0 && !$bot && same_origin_request()) Property::incrementViews($id);
http_response_code(204);
