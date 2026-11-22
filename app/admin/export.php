<?php
/**
 * admin/export.php — downloads.
 *   ?what=listings&format=csv|json   every listing with photo links and the full description
 *   ?what=leads|contacts|deals&format=csv
 *   ?what=all&format=json            full backup (listings, CRM records, website data) — admins only
 */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/transfer.php';
require_login();
crm_schema();

$what = (string) input('what', 'listings');
$format = input('format') === 'json' ? 'json' : 'csv';
$day = date('Y-m-d');

function ex_records(string $coll): array
{
    return array_map(fn($r) => json_decode($r['data'], true), Database::all("SELECT data FROM crm_records WHERE coll = ? ORDER BY updated_at DESC", [$coll]));
}
$names = []; foreach (crm_team() as $t) $names[$t['id']] = $t['name'];
$titles = []; foreach (Database::all("SELECT id, title FROM properties") as $p) $titles['p' . $p['id']] = $p['title'];
$contacts = []; foreach (ex_records('contacts') as $c) $contacts[$c['id']] = $c['name'] ?? '';
$d10 = fn($v) => $v ? substr((string) $v, 0, 16) : '';

if ($what === 'listings') {
    $rows = tx_listing_rows();
    if ($format === 'json') tx_json_download("reside-kenya-listings-$day.json", ['format' => 'reside-kenya/listings@1', 'exportedAt' => date('c'), 'count' => count($rows), 'listings' => $rows]);
    tx_csv_download("reside-kenya-listings-$day.csv", TX_COLS, $rows);
}

if ($what === 'leads') {
    $cols = ['name', 'phone', 'email', 'status', 'source', 'property', 'agent', 'budget', 'location', 'created', 'next_follow_up', 'last_contact', 'latest_note'];
    $rows = array_map(fn($l) => [
        'name' => $l['name'] ?? '', 'phone' => $l['phone'] ?? '', 'email' => $l['email'] ?? '', 'status' => $l['status'] ?? '', 'source' => $l['source'] ?? '',
        'property' => $titles[$l['propertyId'] ?? ''] ?? '', 'agent' => $names[$l['agentId'] ?? ''] ?? '', 'budget' => $l['budget'] ?? '', 'location' => $l['location'] ?? '',
        'created' => $d10($l['createdAt'] ?? ''), 'next_follow_up' => $d10($l['nextFollowUp'] ?? ''), 'last_contact' => $d10($l['lastContact'] ?? ''),
        'latest_note' => (string) (($l['notes'][0]['text'] ?? '')),
    ], ex_records('leads'));
    if ($format === 'json') tx_json_download("reside-kenya-leads-$day.json", $rows);
    tx_csv_download("reside-kenya-leads-$day.csv", $cols, $rows);
}

if ($what === 'contacts') {
    $cols = ['name', 'phone', 'email', 'types', 'company', 'source', 'owner', 'created'];
    $rows = array_map(fn($c) => [
        'name' => $c['name'] ?? '', 'phone' => $c['phone'] ?? '', 'email' => $c['email'] ?? '', 'types' => implode('|', (array) ($c['types'] ?? [])),
        'company' => $c['company'] ?? '', 'source' => $c['source'] ?? '', 'owner' => $names[$c['ownerId'] ?? ''] ?? '', 'created' => $d10($c['createdAt'] ?? ''),
    ], ex_records('contacts'));
    if ($format === 'json') tx_json_download("reside-kenya-contacts-$day.json", $rows);
    tx_csv_download("reside-kenya-contacts-$day.csv", $cols, $rows);
}

if ($what === 'deals') {
    $cols = ['ref', 'kind', 'stage', 'property', 'buyer_or_tenant', 'value_kes', 'agent', 'expected_close', 'closed', 'commission_status'];
    $rows = array_map(fn($d) => [
        'ref' => $d['ref'] ?? '', 'kind' => $d['kind'] ?? '', 'stage' => $d['stage'] ?? '', 'property' => $titles[$d['propertyId'] ?? ''] ?? '',
        'buyer_or_tenant' => $contacts[$d['buyerId'] ?? ''] ?? '', 'value_kes' => $d['value'] ?? '', 'agent' => $names[$d['agentId'] ?? ''] ?? '',
        'expected_close' => $d10($d['expectedClose'] ?? ''), 'closed' => $d10($d['closedAt'] ?? ''), 'commission_status' => $d['commissionStatus'] ?? '',
    ], ex_records('deals'));
    if ($format === 'json') tx_json_download("reside-kenya-deals-$day.json", $rows);
    tx_csv_download("reside-kenya-deals-$day.csv", $cols, $rows);
}

if ($what === 'all') {
    require_role(['super_admin', 'admin']);
    $crm = [];
    foreach (CRM_COLLS as $c) if ($c !== 'msgs') $crm[$c] = ex_records($c);
    $site = [];
    foreach (['inquiries', 'bookings', 'submissions', 'subscribers', 'testimonials', 'categories', 'counties', 'towns', 'amenities', 'agents'] as $t) {
        try { $site[$t] = Database::all("SELECT * FROM $t ORDER BY id"); } catch (Throwable $e) { $site[$t] = []; }
    }
    try { $site['settings'] = Database::all("SELECT * FROM settings"); } catch (Throwable $e) { }
    tx_json_download("reside-kenya-backup-$day.json", [
        'format' => 'reside-kenya/backup@1', 'exportedAt' => date('c'), 'site' => rtrim(BASE_URL, '/'),
        'listings' => tx_listing_rows(), 'workspace' => $crm + ['settings' => crm_settings()],
        'team' => array_map(fn($t) => array_intersect_key($t, array_flip(['id', 'name', 'role', 'email', 'phone'])), crm_team()),
        'website' => $site,
    ]);
}

http_response_code(400);
echo 'Unknown export.';
