<?php
/**
 * GET /api/towns.php?county_id=N
 * Returns JSON array of towns for the given county (used by dependent dropdowns).
 */
require __DIR__ . '/../includes/bootstrap.php';

$countyId = input_int('county_id');
if (!$countyId) {
    json_out([]);
}

$towns = Database::all(
    "SELECT id, name, slug FROM towns WHERE county_id = ? ORDER BY name ASC",
    [$countyId]
);
json_out($towns);
