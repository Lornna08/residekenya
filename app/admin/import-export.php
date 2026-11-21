<?php
/** admin/import-export.php — CSV import/export of property listings. */
require __DIR__ . '/../includes/bootstrap.php';
require_role(['super_admin', 'admin']);

$exportCols = ['ref_code', 'title', 'purpose', 'status', 'currency', 'price', 'sale_price', 'rent_price',
    'bedrooms', 'bathrooms', 'area_sqm', 'land_size_sqm', 'address', 'latitude', 'longitude',
    'is_featured', 'is_luxury', 'description'];

// Export
if (input('export') === 'csv') {
    $all = Database::all("SELECT " . implode(',', $exportCols) . " FROM properties ORDER BY id DESC");
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reside-properties-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $exportCols);
    foreach ($all as $r) fputcsv($out, array_map(fn($c) => $r[$c] ?? '', $exportCols));
    fclose($out);
    exit;
}

$report = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = fgetcsv($fh);
        $header = array_map('trim', (array) $header);
        $created = 0; $updated = 0; $skipped = 0;
        while (($line = fgetcsv($fh)) !== false) {
            $row = @array_combine($header, $line);
            if (!$row || empty($row['title'])) { $skipped++; continue; }
            // Build data limited to known columns
            $data = [];
            foreach ($exportCols as $c) {
                if (!array_key_exists($c, $row)) continue;
                $v = $row[$c];
                if (in_array($c, ['price', 'sale_price', 'rent_price', 'area_sqm', 'land_size_sqm', 'latitude', 'longitude'], true)) $v = ($v === '' ? null : (float) $v);
                elseif (in_array($c, ['bedrooms', 'bathrooms', 'is_featured', 'is_luxury'], true)) $v = ($v === '' ? null : (int) $v);
                $data[$c] = $v;
            }
            if (empty($data['purpose']) || !in_array($data['purpose'], ['sale', 'rent', 'airbnb', 'commercial', 'land'], true)) $data['purpose'] = 'sale';
            if (empty($data['status'])) $data['status'] = 'draft';

            // Update by ref_code if exists, else insert
            $ref = trim((string) ($row['ref_code'] ?? ''));
            $existing = $ref ? Database::row("SELECT id FROM properties WHERE ref_code=?", [$ref]) : null;
            if ($existing) {
                $sets = []; $vals = [];
                foreach ($data as $k => $v) { if ($k === 'ref_code') continue; $sets[] = "$k=?"; $vals[] = $v; }
                $vals[] = $existing['id'];
                Database::run("UPDATE properties SET " . implode(',', $sets) . " WHERE id=?", $vals);
                $updated++;
            } else {
                $data['ref_code'] = $ref ?: Property::generateRef();
                $data['slug'] = unique_slug('properties', $data['title']);
                $data['created_by'] = current_user()['id'];
                $cols = array_keys($data); $ph = implode(',', array_fill(0, count($cols), '?'));
                Database::run("INSERT INTO properties (" . implode(',', $cols) . ") VALUES ($ph)", array_values($data));
                $created++;
            }
        }
        fclose($fh);
        log_activity('import_properties', 'property', null);
        $report = compact('created', 'updated', 'skipped');
        flash('success', "Import complete: {$created} created, {$updated} updated, {$skipped} skipped.");
    } else {
        flash('error', 'Please choose a CSV file to import.');
    }
    redirect('admin/import-export.php');
}

$pageTitle = 'Import / Export';
$activeAdmin = 'import';
include __DIR__ . '/templates/layout-head.php';
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.6rem" class="split-grid">
  <div class="panel">
    <div class="panel-head"><h2>Export listings</h2></div>
    <div class="panel-body">
      <p class="muted" style="font-size:.9rem;line-height:1.6">Download all properties as a CSV file — useful for backups, bulk editing in a spreadsheet, or migrating data.</p>
      <a class="a-btn gold" href="?export=csv" style="margin-top:1rem">Download CSV</a>
    </div>
  </div>
  <div class="panel">
    <div class="panel-head"><h2>Import listings</h2></div>
    <div class="panel-body">
      <p class="muted" style="font-size:.9rem;line-height:1.6">Upload a CSV with a header row. Rows matching an existing <code>ref_code</code> are updated; others are created as drafts. Recognised columns:</p>
      <p class="muted" style="font-size:.78rem;margin:.6rem 0"><?= e(implode(', ', $exportCols)) ?></p>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="a-field"><input class="a-input" type="file" name="csv" accept=".csv,text/csv" required></div>
        <button class="a-btn gold" type="submit" onclick="return confirm('Import this CSV now?')">Import CSV</button>
      </form>
    </div>
  </div>
</div>
<style>@media(max-width:820px){.split-grid{grid-template-columns:1fr!important}}</style>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
