<?php
/** admin/quick-add.php — AJAX: create a category, county, or town inline. */
require __DIR__ . '/../includes/bootstrap.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Bad method']);
    exit;
}
require_csrf();

$kind = (string) input('kind', '');
$name = trim((string) input('name', ''));

if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'Please enter a name.']);
    exit;
}

try {
    if ($kind === 'category') {
        $slug = unique_slug('categories', $name);
        Database::run("INSERT INTO categories (name, slug, sort_order, is_active) VALUES (?,?,0,1)", [$name, $slug]);
        $id = (int) Database::conn()->lastInsertId();
        log_activity('quick_add_category', 'category', $id);
        echo json_encode(['ok' => true, 'id' => $id, 'name' => $name]);
    } elseif ($kind === 'county') {
        $slug = unique_slug('counties', $name);
        Database::run("INSERT INTO counties (name, slug, sort_order, is_active) VALUES (?,?,0,1)", [$name, $slug]);
        $id = (int) Database::conn()->lastInsertId();
        log_activity('quick_add_county', 'county', $id);
        echo json_encode(['ok' => true, 'id' => $id, 'name' => $name]);
    } elseif ($kind === 'town') {
        $countyId = input_int('county_id');
        if (!$countyId) { echo json_encode(['ok' => false, 'error' => 'Choose a county first.']); exit; }
        $slug = unique_slug('towns', $name);
        Database::run("INSERT INTO towns (county_id, name, slug) VALUES (?,?,?)", [$countyId, $name, $slug]);
        $id = (int) Database::conn()->lastInsertId();
        log_activity('quick_add_town', 'town', $id);
        echo json_encode(['ok' => true, 'id' => $id, 'name' => $name]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Unknown type.']);
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'That name may already exist.']);
}
