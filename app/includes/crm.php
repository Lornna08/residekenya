<?php
/**
 * Reside Kenya workspace (new admin) — server side of the CRM.
 *
 * The admin app keeps one state object in the browser. This file builds that
 * state from the database and saves the changes the app sends back:
 *   - listings  <-> `properties` (+ images, amenities) so the public site stays in sync
 *   - team      <-  `users`
 *   - contacts, leads, deals, events, documents, leases, payments, maintenance,
 *     applications, chat messages, activity, notifications -> `crm_records` (JSON)
 * Website inquiries, bookings and property submissions become leads automatically.
 */

const CRM_COLLS = ['contacts', 'leads', 'deals', 'events', 'docs', 'leases', 'payments', 'maintenance', 'applications', 'msgs', 'activity', 'notifications'];

const CRM_STATUS_TO_DB = ['active' => 'available', 'under_offer' => 'under_offer', 'sold' => 'sold', 'let' => 'rented', 'draft' => 'draft', 'withdrawn' => 'archived'];

function crm_schema(): void
{
    $ver = (int) setting('schema_version', '1');
    if ($ver >= 4) return;
    ensure_schema();
    if ($ver < 3) crm_schema_v3();
    // v4: Reside Kenya branding and the luxury dark theme as the site default.
    Database::run("UPDATE settings SET setting_value = regexp_replace(setting_value, 'Reside Ke(?!nya)', 'Reside Kenya', 'g') WHERE setting_value ~ 'Reside Ke(?!nya)'");
    setting_save('default_theme', 'dark');
    setting_save('schema_version', '4');
}

function crm_schema_v3(): void
{
    $db = Database::conn();
    $db->exec("
        CREATE TABLE IF NOT EXISTS crm_records (
          coll varchar(24) NOT NULL,
          id varchar(64) NOT NULL,
          data jsonb NOT NULL,
          updated_at timestamptz NOT NULL DEFAULT NOW(),
          updated_by integer,
          PRIMARY KEY (coll, id)
        );
        CREATE INDEX IF NOT EXISTS idx_crm_coll_upd ON crm_records (coll, updated_at);
        CREATE TABLE IF NOT EXISTS chat_reads (
          user_id integer NOT NULL,
          conv_id varchar(64) NOT NULL,
          read_at timestamptz NOT NULL DEFAULT NOW(),
          PRIMARY KEY (user_id, conv_id)
        );
        ALTER TABLE properties ADD COLUMN IF NOT EXISTS crm jsonb NOT NULL DEFAULT '{}'::jsonb;
        ALTER TABLE users ADD COLUMN IF NOT EXISTS title varchar(120);
        ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen_at timestamptz;
    ");
    // Bring existing website messages into the CRM as leads.
    foreach (Database::all("SELECT * FROM inquiries ORDER BY id") as $q) crm_lead_from_inquiry($q, false);
    foreach (Database::all("SELECT * FROM bookings ORDER BY id") as $b) crm_lead_from_booking($b, false);
    foreach (Database::all("SELECT * FROM submissions ORDER BY id") as $s) crm_lead_from_submission($s, false);
    setting_save('crm_settings', json_encode(['saleRate' => 3, 'rentMonths' => 1, 'monthlyTarget' => 1000000, 'agency' => 'Reside Kenya', 'currency' => 'KES']));
    setting_save('schema_version', '3');
}

/* ------------------------------------------------------------------ *
 *  helpers
 * ------------------------------------------------------------------ */
function crm_iso($ts): ?string
{
    if (!$ts) return null;
    $t = is_numeric($ts) ? (int) $ts : strtotime((string) $ts);
    return $t ? gmdate('Y-m-d\TH:i:s.000\Z', $t) : null;
}
function crm_uid(string $p): string { return $p . base_convert((string) (int) (microtime(true) * 1000), 10, 36) . bin2hex(random_bytes(2)); }
function crm_put(string $coll, string $id, array $data, ?int $by = null): void
{
    Database::run(
        "INSERT INTO crm_records (coll, id, data, updated_at, updated_by) VALUES (?, ?, CAST(? AS jsonb), NOW(), ?)
         ON CONFLICT (coll, id) DO UPDATE SET data = EXCLUDED.data, updated_at = NOW(), updated_by = EXCLUDED.updated_by",
        [$coll, $id, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $by]
    );
}
function crm_get(string $coll, string $id): ?array
{
    $r = Database::row("SELECT data FROM crm_records WHERE coll = ? AND id = ?", [$coll, $id]);
    return $r ? json_decode($r['data'], true) : null;
}
function crm_settings(): array
{
    $s = json_decode((string) setting('crm_settings', ''), true) ?: [];
    return array_merge(['saleRate' => 3, 'rentMonths' => 1, 'monthlyTarget' => 1000000, 'agency' => 'Reside Kenya', 'currency' => 'KES'], $s);
}
function crm_default_agent(): string
{
    $u = Database::row("SELECT id FROM users WHERE is_active = 1 ORDER BY field(role, 'super_admin','admin','editor','agent'), id LIMIT 1");
    return $u ? 'u' . $u['id'] : 'u1';
}
function crm_notify(string $kind, string $text, ?array $ref): void
{
    crm_put('notifications', crm_uid('n'), ['id' => null, 'kind' => $kind, 'text' => $text, 'at' => crm_iso(time()), 'readBy' => [], 'ref' => $ref]);
}
function crm_activity(string $kind, string $text, ?array $ref, ?string $by = null): void
{
    $id = crm_uid('a');
    crm_put('activity', $id, ['id' => $id, 'at' => crm_iso(time()), 'by' => $by ?: 'web', 'kind' => $kind, 'text' => $text, 'ref' => $ref]);
}

/* ------------------------------------------------------------------ *
 *  website -> leads
 * ------------------------------------------------------------------ */
function crm_prop_title(?int $pid): string
{
    if (!$pid) return '';
    $p = Database::row("SELECT title FROM properties WHERE id = ?", [$pid]);
    return $p['title'] ?? '';
}
function crm_lead_from_inquiry(array $q, bool $live = true): void
{
    $id = 'ldw' . $q['id'];
    if (crm_get('leads', $id)) return;
    $pt = crm_prop_title($q['property_id'] ? (int) $q['property_id'] : null);
    $kind = ['viewing' => 'Viewing request', 'agent' => 'Property enquiry', 'contact' => 'Contact form', 'general' => 'Enquiry'][$q['type']] ?? 'Enquiry';
    $note = trim($kind . ($q['preferred_date'] ? ' — preferred date ' . date('D d M Y', strtotime($q['preferred_date'])) : '') . ($q['message'] ? ":\n" . $q['message'] : ''));
    $lead = [
        'id' => $id, 'name' => $q['name'], 'phone' => (string) $q['phone'], 'email' => (string) $q['email'],
        'source' => 'Website', 'propertyId' => $q['property_id'] ? 'p' . $q['property_id'] : null,
        'budget' => null, 'location' => '', 'ptype' => '', 'agentId' => crm_default_agent(),
        'status' => $q['is_read'] ? 'contacted' : 'new', 'lastContact' => null,
        'nextFollowUp' => $q['type'] === 'viewing' && $q['preferred_date'] ? crm_iso($q['preferred_date'] . ' 10:00:00') : null,
        'createdAt' => crm_iso($q['created_at']), 'contactId' => null, 'inquiryId' => (int) $q['id'], 'kind' => $q['type'],
        'notes' => $note ? [['at' => crm_iso($q['created_at']), 'by' => 'web', 'text' => $note]] : [],
    ];
    crm_put('leads', $id, $lead);
    if ($live) {
        crm_notify('lead', ($q['type'] === 'viewing' ? 'Viewing request: ' : 'New lead: ') . $q['name'] . ($pt ? ' — ' . $pt : ''), ['type' => 'lead', 'id' => $id]);
        crm_activity('lead', 'New website lead ' . $q['name'] . ($pt ? ' for ' . $pt : ''), ['type' => 'lead', 'id' => $id]);
    }
}
function crm_lead_from_booking(array $b, bool $live = true): void
{
    $id = 'ldb' . $b['id'];
    if (crm_get('leads', $id)) return;
    $pt = crm_prop_title((int) $b['property_id']);
    $note = 'Airbnb booking request: ' . date('d M', strtotime($b['check_in'])) . ' – ' . date('d M Y', strtotime($b['check_out'])) . ', ' . (int) $b['guests'] . ' guest(s)' . ($b['total_amount'] ? ', total KES ' . number_format((float) $b['total_amount']) : '') . ($b['notes'] ? "\n" . $b['notes'] : '');
    crm_put('leads', $id, [
        'id' => $id, 'name' => $b['guest_name'], 'phone' => (string) $b['guest_phone'], 'email' => (string) $b['guest_email'],
        'source' => 'Website booking', 'propertyId' => 'p' . $b['property_id'], 'budget' => $b['total_amount'] ? (float) $b['total_amount'] : null,
        'location' => '', 'ptype' => '', 'agentId' => crm_default_agent(),
        'status' => $b['status'] === 'pending' ? 'new' : ($b['status'] === 'confirmed' ? 'converted' : 'lost'),
        'lastContact' => null, 'nextFollowUp' => null, 'createdAt' => crm_iso($b['created_at']), 'contactId' => null, 'bookingId' => (int) $b['id'],
        'notes' => [['at' => crm_iso($b['created_at']), 'by' => 'web', 'text' => $note]],
    ]);
    if ($live) {
        crm_notify('lead', 'Booking request: ' . $b['guest_name'] . ' — ' . $pt, ['type' => 'lead', 'id' => $id]);
        crm_activity('lead', 'Booking request from ' . $b['guest_name'] . ' for ' . $pt, ['type' => 'lead', 'id' => $id]);
    }
}
function crm_lead_from_submission(array $s, bool $live = true): void
{
    $id = 'lds' . $s['id'];
    if (crm_get('leads', $id)) return;
    $note = 'Wants to list a property (' . $s['property_status'] . '): ' . trim(($s['property_type'] ?: '') . ($s['location'] ? ' in ' . $s['location'] : '')) . ($s['expected_price'] ? ' · asking KES ' . number_format((float) $s['expected_price']) : '') . ($s['description'] ? "\n" . $s['description'] : '');
    crm_put('leads', $id, [
        'id' => $id, 'name' => $s['full_name'], 'phone' => (string) $s['phone'], 'email' => (string) $s['email'],
        'source' => 'Website — list property', 'propertyId' => null, 'budget' => $s['expected_price'] ? (float) $s['expected_price'] : null,
        'location' => (string) $s['location'], 'ptype' => (string) $s['property_type'], 'agentId' => crm_default_agent(),
        'status' => $s['status'] === 'new' ? 'new' : ($s['status'] === 'published' ? 'converted' : ($s['status'] === 'rejected' ? 'lost' : 'contacted')),
        'lastContact' => null, 'nextFollowUp' => null, 'createdAt' => crm_iso($s['created_at']), 'contactId' => null, 'submissionId' => (int) $s['id'], 'seller' => true,
        'notes' => [['at' => crm_iso($s['created_at']), 'by' => 'web', 'text' => $note]],
    ]);
    if ($live) {
        crm_notify('lead', 'Property submitted to list: ' . $s['full_name'], ['type' => 'lead', 'id' => $id]);
        crm_activity('lead', $s['full_name'] . ' submitted a property to list', ['type' => 'lead', 'id' => $id]);
    }
}
/** Called by the public forms after they save. Never breaks the public form. */
function crm_capture(string $kind, int $id): void
{
    try {
        crm_schema();
        if ($kind === 'inquiry' && ($r = Database::row("SELECT * FROM inquiries WHERE id = ?", [$id]))) crm_lead_from_inquiry($r);
        if ($kind === 'booking' && ($r = Database::row("SELECT * FROM bookings WHERE id = ?", [$id]))) crm_lead_from_booking($r);
        if ($kind === 'submission' && ($r = Database::row("SELECT * FROM submissions WHERE id = ?", [$id]))) crm_lead_from_submission($r);
    } catch (Throwable $e) {
        error_log('crm_capture: ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------------ *
 *  listings <-> properties
 * ------------------------------------------------------------------ */
function crm_lookups(): array
{
    static $L = null;
    if ($L) return $L;
    $L = [
        'cats'  => Database::all("SELECT id, name FROM categories ORDER BY sort_order, name"),
        'counties' => Database::all("SELECT id, name FROM counties ORDER BY sort_order, name"),
        'towns' => Database::all("SELECT t.id, t.name, t.county_id, c.name AS county FROM towns t JOIN counties c ON c.id = t.county_id ORDER BY t.name"),
        'amen'  => Database::all("SELECT id, name FROM amenities ORDER BY name"),
        'agentsByUser' => [],
    ];
    foreach (Database::all("SELECT id, user_id FROM agents WHERE user_id IS NOT NULL") as $a) $L['agentsByUser'][(int) $a['user_id']] = (int) $a['id'];
    return $L;
}

function crm_map_property(array $r, array $imgs, array $amen): array
{
    $c = json_decode((string) ($r['crm'] ?? '{}'), true) ?: [];
    $purpose = $r['purpose'] ?: 'sale';
    $cat = in_array($purpose, ['rent', 'airbnb'], true) ? 'rent' : 'sale';
    $status = array_search($r['status'], CRM_STATUS_TO_DB, true) ?: ($r['status'] === '' || $r['status'] === null ? 'draft' : 'active');
    $price = (float) ($r['price'] ?: ($cat === 'rent' ? $r['rent_price'] : $r['sale_price']) ?: 0);
    $agentUser = $c['agentId'] ?? null;
    if (!$agentUser && $r['agent_id']) {
        $u = Database::row("SELECT user_id FROM agents WHERE id = ?", [$r['agent_id']]);
        if ($u && $u['user_id']) $agentUser = 'u' . $u['user_id'];
    }
    if (!$agentUser && $r['created_by']) $agentUser = 'u' . $r['created_by'];
    $images = array_map('asset_url', array_values(array_unique(array_filter(array_merge($r['featured_image'] ? [$r['featured_image']] : [], $imgs)))));
    $noPhotos = !$images;
    if ($noPhotos) $images = [ph_url(ph_key_for(($r['purpose'] ?? '') . ' ' . ($r['category_name'] ?? '') . ' ' . $r['title']), 1080)];
    return [
        'id' => 'p' . $r['id'], 'dbId' => (int) $r['id'], 'ref' => $r['ref_code'], 'slug' => $r['slug'], 'url' => url('property/' . $r['slug']),
        'title' => $r['title'], 'type' => $r['category_name'] ?: 'Property', 'categoryId' => $r['category_id'] ? (int) $r['category_id'] : null,
        'category' => $cat, 'purpose' => $purpose, 'rentPeriod' => $r['rent_period'] ?: ($purpose === 'airbnb' ? 'night' : 'month'),
        'status' => $status, 'archived' => $r['status'] === 'archived' && !empty($c['archived']),
        'published' => $r['status'] === 'available' || $r['status'] === 'under_offer',
        'price' => $price, 'minPrice' => isset($c['minPrice']) ? (float) $c['minPrice'] : round($price * 0.96),
        'estimate' => isset($c['estimate']) ? (float) $c['estimate'] : $price,
        'area' => $r['town_name'] ?: ($r['county_name'] ?: ''), 'city' => $r['county_name'] ?: '', 'townId' => $r['town_id'] ? (int) $r['town_id'] : null, 'countyId' => $r['county_id'] ? (int) $r['county_id'] : null,
        'address' => (string) $r['address'], 'beds' => (int) $r['bedrooms'], 'baths' => (int) $r['bathrooms'],
        'size' => (float) ($r['area_sqm'] ?: 0), 'plot' => $c['plot'] ?? ($r['land_size_sqm'] ? rtrim(rtrim(number_format((float) $r['land_size_sqm'], 2, '.', ''), '0'), '.') . ' m²' : ''),
        'parking' => (int) $r['parking_spaces'], 'floors' => (int) ($r['floors'] ?: 1),
        'agentId' => $agentUser ?: crm_default_agent(), 'ownerId' => $c['ownerId'] ?? null,
        'ownerName' => (string) $r['owner_name'], 'ownerContact' => (string) $r['owner_contact'], 'sourceUrl' => (string) ($r['source_url'] ?? ''),
        'featured' => (bool) $r['is_featured'], 'luxury' => (bool) $r['is_luxury'], 'views' => (int) $r['views'],
        'listedAt' => crm_iso($r['created_at']), 'updatedAt' => crm_iso($r['updated_at']),
        'images' => $images, 'noPhotos' => $noPhotos, 'amenities' => $amen, 'description' => (string) $r['description'],
        'notes' => $c['notes'] ?? [], 'youtube' => (string) $r['youtube_url'],
    ];
}

function crm_properties(?int $onlyId = null): array
{
    $where = $onlyId ? 'WHERE p.id = ' . (int) $onlyId : '';
    $rows = Database::all("SELECT p.*, c.name AS category_name, t.name AS town_name, co.name AS county_name
        FROM properties p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN towns t ON t.id = p.town_id LEFT JOIN counties co ON co.id = p.county_id
        $where ORDER BY p.created_at DESC, p.id DESC");
    $imgs = []; $amen = [];
    foreach (Database::all("SELECT property_id, file_path FROM property_images WHERE is_floorplan = 0 " . ($onlyId ? 'AND property_id = ' . (int) $onlyId : '') . " ORDER BY sort_order, id") as $i) $imgs[$i['property_id']][] = $i['file_path'];
    foreach (Database::all("SELECT pa.property_id, a.name FROM property_amenities pa JOIN amenities a ON a.id = pa.amenity_id " . ($onlyId ? 'WHERE pa.property_id = ' . (int) $onlyId : '')) as $a) $amen[$a['property_id']][] = $a['name'];
    return array_map(fn($r) => crm_map_property($r, $imgs[$r['id']] ?? [], $amen[$r['id']] ?? []), $rows);
}

/** Turn an absolute URL from the app back into the stored path. */
function crm_store_path(string $u): ?string
{
    $base = rtrim(BASE_URL, '/') . '/';
    if (str_starts_with($u, $base)) $u = substr($u, strlen($base));
    elseif (preg_match('#^https?://[^/]+/(uploads/.+)$#', $u, $m) && !str_contains($u, 'blob.vercel-storage.com')) $u = $m[1];
    if (str_starts_with($u, 'assets/img/placeholder')) return null;
    if (is_stored_upload($u) || preg_match('#^uploads/properties/[A-Za-z0-9._-]+$#', $u)) return $u;
    if (preg_match('#^(uploads|assets/img)/[A-Za-z0-9/._-]+\.(jpe?g|png|webp|gif)$#i', $u) && !str_contains($u, '..')) return $u;
    if (preg_match('#^https://[a-z0-9.-]+\.public\.blob\.vercel-storage\.com/uploads/[A-Za-z0-9/._-]+$#i', $u)) return $u;
    return null;
}

/** Save a listing sent by the app. Returns [id, ref, slug]. */
function crm_save_property(array $p, int $userId): array
{
    $L = crm_lookups();
    $dbId = preg_match('/^p(\d+)$/', (string) ($p['id'] ?? ''), $m) ? (int) $m[1] : 0;
    $existing = $dbId ? Database::row("SELECT * FROM properties WHERE id = ?", [$dbId]) : null;
    if ($dbId && !$existing) $dbId = 0;

    $purpose = in_array($p['purpose'] ?? '', ['sale', 'rent', 'airbnb', 'commercial', 'land'], true) ? $p['purpose'] : (($p['category'] ?? 'sale') === 'rent' ? 'rent' : 'sale');
    $catId = null;
    foreach ($L['cats'] as $c) if (strcasecmp($c['name'], (string) ($p['type'] ?? '')) === 0) $catId = (int) $c['id'];
    if (!$catId && !empty($p['type']) && $p['type'] !== 'Property') {
        $catId = Database::insert("INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, 99)", [$p['type'], unique_slug('categories', $p['type'])]);
    }
    $countyId = null; $townId = null;
    foreach ($L['counties'] as $c) if (strcasecmp($c['name'], (string) ($p['city'] ?? '')) === 0) $countyId = (int) $c['id'];
    foreach ($L['towns'] as $t) if (strcasecmp($t['name'], (string) ($p['area'] ?? '')) === 0 && (!$countyId || (int) $t['county_id'] === $countyId)) { $townId = (int) $t['id']; $countyId = $countyId ?: (int) $t['county_id']; }
    if (!$townId && !empty($p['area']) && $countyId) {
        $townId = Database::insert("INSERT INTO towns (county_id, name, slug) VALUES (?, ?, ?)", [$countyId, $p['area'], slugify($p['area']) . '-' . substr(bin2hex(random_bytes(2)), 0, 3)]);
    }
    $status = CRM_STATUS_TO_DB[$p['status'] ?? 'active'] ?? 'available';
    if (!empty($p['archived'])) $status = 'archived';
    $price = (float) ($p['price'] ?? 0);
    $rent = in_array($purpose, ['rent', 'airbnb'], true);
    $agentUser = preg_match('/^u(\d+)$/', (string) ($p['agentId'] ?? ''), $am) ? (int) $am[1] : null;

    $crm = json_decode((string) ($existing['crm'] ?? '{}'), true) ?: [];
    foreach (['minPrice', 'estimate', 'notes', 'ownerId', 'plot', 'agentId'] as $k) if (array_key_exists($k, $p)) $crm[$k] = $p[$k];
    $crm['archived'] = !empty($p['archived']);

    $owner = !empty($p['ownerId']) ? crm_get('contacts', (string) $p['ownerId']) : null;
    $plotNum = isset($p['plot']) && preg_match('/^([\d.]+)\s*(m²|m2|sqm)?$/i', trim((string) $p['plot']), $pm) ? (float) $pm[1] : null;

    $data = [
        'title' => mb_substr(trim((string) ($p['title'] ?? 'Untitled')), 0, 190),
        'description' => (string) ($p['description'] ?? ''),
        'purpose' => $purpose, 'category_id' => $catId, 'status' => $status,
        'price' => $price ?: null, 'sale_price' => $rent ? null : ($price ?: null), 'rent_price' => $rent ? ($price ?: null) : null,
        'rent_period' => $rent ? (in_array($p['rentPeriod'] ?? '', ['month', 'year', 'night', 'week'], true) ? $p['rentPeriod'] : ($purpose === 'airbnb' ? 'night' : 'month')) : ($existing['rent_period'] ?? 'month'),
        'county_id' => $countyId, 'town_id' => $townId, 'address' => trim((string) ($p['address'] ?? '')) ?: null,
        'bedrooms' => isset($p['beds']) && $p['beds'] !== '' ? (int) $p['beds'] : null,
        'bathrooms' => isset($p['baths']) && $p['baths'] !== '' ? (int) $p['baths'] : null,
        'parking_spaces' => isset($p['parking']) && $p['parking'] !== '' ? (int) $p['parking'] : null,
        'floors' => isset($p['floors']) && $p['floors'] !== '' ? (int) $p['floors'] : null,
        'area_sqm' => !empty($p['size']) ? (float) $p['size'] : null,
        'land_size_sqm' => $plotNum ?? ($existing['land_size_sqm'] ?? null),
        'is_featured' => !empty($p['featured']) ? 1 : 0,
        'crm' => json_encode($crm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
    if (array_key_exists('luxury', $p)) $data['is_luxury'] = !empty($p['luxury']) ? 1 : 0;
    if ($owner) { $data['owner_name'] = $owner['name'] ?? null; $data['owner_contact'] = trim(($owner['phone'] ?? '') . ' ' . ($owner['email'] ?? '')) ?: null; }
    elseif (array_key_exists('ownerName', $p)) { $data['owner_name'] = $p['ownerName'] ?: null; $data['owner_contact'] = $p['ownerContact'] ?: null; }
    if (array_key_exists('sourceUrl', $p)) $data['source_url'] = filter_var((string) $p['sourceUrl'], FILTER_VALIDATE_URL) ?: null;
    if ($agentUser && isset($L['agentsByUser'][$agentUser])) $data['agent_id'] = $L['agentsByUser'][$agentUser];

    // Photos: first one is the cover.
    $paths = [];
    foreach ((array) ($p['images'] ?? []) as $u) { $sp = crm_store_path((string) $u); if ($sp && !in_array($sp, $paths, true)) $paths[] = $sp; }
    $data['featured_image'] = $paths[0] ?? ($existing['featured_image'] ?? null);

    if ($dbId) {
        $sets = implode(', ', array_map(fn($k) => "$k = " . ($k === 'crm' ? 'CAST(? AS jsonb)' : '?'), array_keys($data)));
        Database::run("UPDATE properties SET $sets WHERE id = ?", array_merge(array_values($data), [$dbId]));
    } else {
        $data['ref_code'] = Property::generateRef();
        $data['slug'] = unique_slug('properties', $data['title']);
        $data['created_by'] = $userId;
        $cols = array_keys($data);
        $vals = implode(',', array_map(fn($k) => $k === 'crm' ? 'CAST(? AS jsonb)' : '?', $cols));
        $dbId = Database::insert("INSERT INTO properties (" . implode(',', $cols) . ") VALUES ($vals)", array_values($data));
    }

    // Sync gallery when the app sent a photo list.
    if (array_key_exists('images', $p)) {
        $cur = array_column(Database::all("SELECT file_path FROM property_images WHERE property_id = ? AND is_floorplan = 0 ORDER BY sort_order, id", [$dbId]), 'file_path');
        if ($cur !== $paths && $paths) {
            Database::run("DELETE FROM property_images WHERE property_id = ? AND is_floorplan = 0", [$dbId]);
            foreach ($paths as $i => $path) Database::run("INSERT INTO property_images (property_id, file_path, alt_text, sort_order) VALUES (?,?,?,?)", [$dbId, $path, mb_substr($data['title'], 0, 190), $i]);
            foreach (array_diff($cur, $paths) as $gone) delete_upload($gone);
        }
    }
    // Amenities by name.
    if (array_key_exists('amenities', $p)) {
        $ids = [];
        foreach ((array) $p['amenities'] as $name) {
            $hit = null;
            foreach ($L['amen'] as $a) if (strcasecmp($a['name'], (string) $name) === 0) $hit = (int) $a['id'];
            if (!$hit && trim((string) $name) !== '') $hit = (int) Database::row("INSERT INTO amenities (name) VALUES (?) ON CONFLICT (name) DO UPDATE SET name = EXCLUDED.name RETURNING id", [trim((string) $name)])['id'];
            if ($hit) $ids[] = $hit;
        }
        Database::run("DELETE FROM property_amenities WHERE property_id = ?", [$dbId]);
        foreach (array_unique($ids) as $aid) Database::run("INSERT INTO property_amenities (property_id, amenity_id) VALUES (?,?) ON CONFLICT DO NOTHING", [$dbId, $aid]);
    }
    if (!empty($p['featured'])) Database::run("UPDATE properties SET is_featured = 1 WHERE id = ?", [$dbId]);
    $row = Database::row("SELECT ref_code, slug FROM properties WHERE id = ?", [$dbId]);
    return ['id' => 'p' . $dbId, 'ref' => $row['ref_code'], 'slug' => $row['slug'], 'url' => url('property/' . $row['slug'])];
}

/* ------------------------------------------------------------------ *
 *  team & chat
 * ------------------------------------------------------------------ */
function crm_role_label(string $r): string { return ['super_admin' => 'Principal', 'admin' => 'Administrator', 'editor' => 'Listings editor', 'agent' => 'Agent'][$r] ?? 'Agent'; }
function crm_team(): array
{
    return array_map(fn($u) => [
        'id' => 'u' . $u['id'], 'dbId' => (int) $u['id'], 'name' => $u['name'], 'role' => $u['title'] ?: crm_role_label($u['role']), 'perm' => $u['role'],
        'avatar' => $u['avatar'] ? asset_url($u['avatar']) : '', 'phone' => (string) $u['phone'], 'email' => $u['email'],
        'online' => $u['last_seen_at'] && strtotime($u['last_seen_at']) > time() - 180,
        'lastSeen' => crm_iso($u['last_seen_at']),
    ], Database::all("SELECT id, name, email, role, title, phone, avatar, last_seen_at FROM users WHERE is_active = 1 ORDER BY field(role, 'super_admin','admin','editor','agent'), name"));
}
function crm_conv_id(int $a, int $b): string { return 'dm-' . min($a, $b) . '-' . max($a, $b); }

/* ------------------------------------------------------------------ *
 *  full state for the signed-in user
 * ------------------------------------------------------------------ */
function crm_state(int $uid): array
{
    $S = ['version' => 1, 'settings' => crm_settings(), 'team' => crm_team(), 'properties' => crm_properties()];
    foreach (CRM_COLLS as $c) $S[$c] = [];
    foreach (Database::all("SELECT coll, data FROM crm_records WHERE coll <> 'msgs' AND coll <> 'activity' AND coll <> 'notifications'") as $r) $S[$r['coll']][] = json_decode($r['data'], true);
    $S['activity'] = array_map(fn($r) => json_decode($r['data'], true), Database::all("SELECT data FROM crm_records WHERE coll = 'activity' ORDER BY data->>'at' DESC LIMIT 150"));
    // Fill the feed with listing and lead history that predates the workspace.
    if (count($S['activity']) < 40) {
        $seen = [];
        foreach ($S['activity'] as $a) if (!empty($a['ref']['id'])) $seen[$a['ref']['id']] = true;
        foreach (array_slice($S['properties'], 0, 25) as $p) if (empty($seen[$p['id']])) $S['activity'][] = ['id' => 'vp' . $p['dbId'], 'at' => $p['listedAt'], 'by' => $p['agentId'], 'kind' => 'listing', 'text' => 'New listing — ' . $p['title'], 'ref' => ['type' => 'property', 'id' => $p['id']], 'virtual' => true];
        foreach ($S['leads'] ?? [] as $l) if (empty($seen[$l['id']]) && !empty($l['createdAt'])) $S['activity'][] = ['id' => 'vl' . $l['id'], 'at' => $l['createdAt'], 'by' => 'web', 'kind' => 'lead', 'text' => 'New lead ' . ($l['name'] ?? '') . (!empty($l['source']) ? ' · ' . $l['source'] : ''), 'ref' => ['type' => 'lead', 'id' => $l['id']], 'virtual' => true];
        usort($S['activity'], fn($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        $S['activity'] = array_slice($S['activity'], 0, 150);
    }
    $S['notifications'] = array_map(function ($r) use ($uid) {
        $n = json_decode($r['data'], true); $n['id'] = $r['id'];
        $n['read'] = in_array($uid, $n['readBy'] ?? [], true); unset($n['readBy']);
        return $n;
    }, Database::all("SELECT id, data FROM crm_records WHERE coll = 'notifications' ORDER BY data->>'at' DESC LIMIT 60"));

    // Chat: one conversation with each teammate, from this user's point of view.
    $reads = [];
    foreach (Database::all("SELECT conv_id, read_at FROM chat_reads WHERE user_id = ?", [$uid]) as $r) $reads[$r['conv_id']] = strtotime($r['read_at']);
    $convs = []; $ids = [];
    foreach ($S['team'] as $u) {
        if ($u['dbId'] === $uid) continue;
        $cid = crm_conv_id($uid, $u['dbId']); $ids[] = $cid;
        $convs[$cid] = ['id' => $cid, 'kind' => 'dm', 'userId' => $u['id'], 'unread' => 0, 'last' => null];
    }
    $S['msgs'] = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (Database::all("SELECT data FROM crm_records WHERE coll = 'msgs' AND data->>'convId' IN ($in) ORDER BY data->>'at' DESC LIMIT 600", $ids) as $r) {
            $m = json_decode($r['data'], true); $S['msgs'][] = $m;
            $c = &$convs[$m['convId']];
            if (!$c['last'] || $m['at'] > $c['last']) $c['last'] = $m['at'];
            if ($m['from'] !== 'u' . $uid && strtotime($m['at']) > ($reads[$m['convId']] ?? 0)) $c['unread']++;
            unset($c);
        }
    }
    $convs = array_values($convs);
    usort($convs, fn($a, $b) => strcmp((string) $b['last'], (string) $a['last']));
    $S['convs'] = $convs;

    // Website section for the overview.
    $S['website'] = [
        'inquiries' => array_map(fn($q) => [
            'id' => (int) $q['id'], 'type' => $q['type'], 'name' => $q['name'], 'phone' => (string) $q['phone'], 'email' => (string) $q['email'],
            'message' => (string) $q['message'], 'propertyId' => $q['property_id'] ? 'p' . $q['property_id'] : null, 'propertyTitle' => (string) $q['ptitle'],
            'read' => (bool) $q['is_read'], 'at' => crm_iso($q['created_at']), 'leadId' => 'ldw' . $q['id'],
        ], Database::all("SELECT i.*, p.title AS ptitle FROM inquiries i LEFT JOIN properties p ON p.id = i.property_id ORDER BY i.created_at DESC LIMIT 8")),
        'counts' => array_map('intval', Database::row("SELECT
            (SELECT COUNT(*) FROM inquiries WHERE is_read = 0) AS inquiries,
            (SELECT COUNT(*) FROM bookings WHERE status = 'pending') AS bookings,
            (SELECT COUNT(*) FROM submissions WHERE status = 'new') AS submissions,
            (SELECT COUNT(*) FROM subscribers) AS subscribers")),
    ];
    $L = crm_lookups();
    $S['lookups'] = [
        'types' => array_column($L['cats'], 'name'),
        'counties' => array_column($L['counties'], 'name'),
        'towns' => array_map(fn($t) => ['name' => $t['name'], 'county' => $t['county']], $L['towns']),
        'amenities' => array_column($L['amen'], 'name'),
    ];
    $S['me'] = 'u' . $uid;
    $S['serverTime'] = crm_iso(time());
    return $S;
}

/* ------------------------------------------------------------------ *
 *  save changes sent by the app
 * ------------------------------------------------------------------ */
function crm_sync(array $body, int $uid): array
{
    $out = ['ok' => true, 'ids' => [], 'props' => []];
    $db = Database::conn();
    $db->beginTransaction();
    try {
        foreach ((array) ($body['upserts']['properties'] ?? []) as $p) {
            if (!is_array($p) || empty($p['title'])) continue;
            $res = crm_save_property($p, $uid);
            if (($p['id'] ?? '') !== $res['id']) $out['ids'][(string) $p['id']] = $res['id'];
            $out['props'][$res['id']] = $res;
        }
        foreach ((array) ($body['deletes']['properties'] ?? []) as $pid) {
            if (preg_match('/^p(\d+)$/', (string) $pid, $m)) Database::run("UPDATE properties SET status = 'archived' WHERE id = ?", [(int) $m[1]]);
        }
        foreach (CRM_COLLS as $coll) {
            foreach ((array) ($body['upserts'][$coll] ?? []) as $rec) {
                if (!is_array($rec) || empty($rec['id']) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $rec['id'])) continue;
                $id = (string) $rec['id'];
                if ($coll === 'notifications') {
                    // Only the read flag is per person; everything else is shared.
                    $cur = crm_get('notifications', $id) ?: $rec;
                    $readBy = $cur['readBy'] ?? [];
                    if (!empty($rec['read'])) $readBy = array_values(array_unique(array_merge($readBy, [$uid])));
                    unset($rec['read']); $rec = array_merge($cur, $rec, ['readBy' => $readBy]);
                }
                if ($coll === 'msgs') {
                    // Only allow messages in this user's own conversations.
                    if (!preg_match('/^dm-(\d+)-(\d+)$/', (string) ($rec['convId'] ?? ''), $cm) || !in_array($uid, [(int) $cm[1], (int) $cm[2]], true)) continue;
                    $rec['from'] = 'u' . $uid;
                    if (!crm_get('msgs', $id)) crm_chat_push($rec, $uid);
                }
                crm_put($coll, $id, $rec, $uid);
                // Keep the old website tables in step with lead statuses.
                if ($coll === 'leads' && !empty($rec['inquiryId']) && ($rec['status'] ?? 'new') !== 'new') Database::run("UPDATE inquiries SET is_read = 1 WHERE id = ? AND is_read = 0", [(int) $rec['inquiryId']]);
            }
            foreach ((array) ($body['deletes'][$coll] ?? []) as $id) {
                if ($coll === 'docs' && ($d = crm_get('docs', (string) $id)) && !empty($d['url'])) delete_upload($d['url']);
                Database::run("DELETE FROM crm_records WHERE coll = ? AND id = ?", [$coll, (string) $id]);
            }
        }
        if (isset($body['settings']) && is_array($body['settings'])) {
            $s = array_intersect_key($body['settings'], array_flip(['saleRate', 'rentMonths', 'monthlyTarget', 'agency', 'currency']));
            setting_save('crm_settings', json_encode(array_merge(crm_settings(), $s)));
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    Database::run("UPDATE users SET last_seen_at = NOW() WHERE id = ?", [$uid]);
    return $out;
}

/** Push a chat message to the recipient's phone. */
function crm_chat_push(array $m, int $uid): void
{
    if (!preg_match('/^dm-(\d+)-(\d+)$/', (string) $m['convId'], $cm)) return;
    $to = (int) $cm[1] === $uid ? (int) $cm[2] : (int) $cm[1];
    $me = current_user();
    try { notify_user($to, '💬 ' . ($me['name'] ?? 'Team'), mb_strimwidth((string) ($m['text'] ?: 'Shared a listing'), 0, 120, '…'), 'admin/#/chat', 'chat-' . $m['convId']); } catch (Throwable $e) {}
}
