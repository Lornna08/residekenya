<?php
/**
 * Property model — search, fetch, and CRUD.
 * All queries use prepared statements (SQL-injection safe).
 */
class Property
{
    /**
     * Build a filtered, paginated listing query.
     * $f = associative array of filters from GET.
     * Returns ['items'=>[], 'total'=>int, 'pages'=>int, 'page'=>int].
     */
    public static function search(array $f): array
    {
        $where  = ["p.status IN ('available','under_offer')"];
        $params = [];

        // Purpose / tab (buy=sale, rent, airbnb) + explicit purpose
        if (!empty($f['purpose']) && in_array($f['purpose'], ['sale','rent','airbnb','commercial','land'], true)) {
            $where[] = 'p.purpose = ?';
            $params[] = $f['purpose'];
        }
        if (!empty($f['category_id'])) { $where[] = 'p.category_id = ?'; $params[] = (int)$f['category_id']; }
        if (!empty($f['category'])) {   $where[] = 'c.slug = ?';        $params[] = $f['category']; }
        if (!empty($f['county_id'])) {  $where[] = 'p.county_id = ?';   $params[] = (int)$f['county_id']; }
        if (!empty($f['county'])) {     $where[] = 'co.slug = ?';       $params[] = $f['county']; }
        if (!empty($f['town_id'])) {    $where[] = 'p.town_id = ?';     $params[] = (int)$f['town_id']; }
        if (!empty($f['estate_id'])) {  $where[] = 'p.estate_id = ?';   $params[] = (int)$f['estate_id']; }

        if (!empty($f['bedrooms']))  { $where[] = 'p.bedrooms >= ?';  $params[] = (int)$f['bedrooms']; }
        if (!empty($f['bathrooms'])) { $where[] = 'p.bathrooms >= ?'; $params[] = (int)$f['bathrooms']; }

        if (!empty($f['min_price'])) { $where[] = 'p.price >= ?'; $params[] = (float)$f['min_price']; }
        if (!empty($f['max_price'])) { $where[] = 'p.price <= ?'; $params[] = (float)$f['max_price']; }

        if (!empty($f['min_area'])) { $where[] = 'p.area_sqm >= ?'; $params[] = (float)$f['min_area']; }
        if (!empty($f['max_area'])) { $where[] = 'p.area_sqm <= ?'; $params[] = (float)$f['max_area']; }

        if (!empty($f['featured'])) { $where[] = 'p.is_featured = 1'; }
        if (!empty($f['luxury']))   { $where[] = 'p.is_luxury = 1'; }

        // Keyword search (title / description / address)
        if (!empty($f['keyword'])) {
            $kw = '%' . $f['keyword'] . '%';
            $where[] = '(p.title LIKE ? OR p.description LIKE ? OR p.address LIKE ?)';
            array_push($params, $kw, $kw, $kw);
        }

        $whereSql = implode(' AND ', $where);

        // Sorting
        $order = 'p.is_featured DESC, p.created_at DESC';
        switch ($f['sort'] ?? '') {
            case 'price_asc':  $order = 'p.price ASC';  break;
            case 'price_desc': $order = 'p.price DESC'; break;
            case 'newest':     $order = 'p.created_at DESC'; break;
            case 'oldest':     $order = 'p.created_at ASC';  break;
        }

        // Count
        $countSql = "SELECT COUNT(*) AS n
            FROM properties p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN counties co ON co.id = p.county_id
            WHERE {$whereSql}";
        $total = (int) (Database::row($countSql, $params)['n'] ?? 0);

        // Pagination
        $perPage = max(1, min(48, (int)($f['per_page'] ?? 12)));
        $page    = max(1, (int)($f['page'] ?? 1));
        $pages   = max(1, (int) ceil($total / $perPage));
        $offset  = ($page - 1) * $perPage;

        $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                       co.name AS county_name, co.slug AS county_slug,
                       t.name AS town_name, a.name AS agent_name, a.phone AS agent_phone,
                       a.whatsapp AS agent_whatsapp
                FROM properties p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN counties co ON co.id = p.county_id
                LEFT JOIN towns t     ON t.id = p.town_id
                LEFT JOIN agents a    ON a.id = p.agent_id
                WHERE {$whereSql}
                ORDER BY {$order}
                LIMIT {$perPage} OFFSET {$offset}";
        $items = Database::all($sql, $params);

        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $perPage];
    }

    /** Fetch a single property by slug (public) with all joins. */
    public static function findBySlug(string $slug): ?array
    {
        return Database::row(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                    co.name AS county_name, co.slug AS county_slug,
                    t.name AS town_name, es.name AS estate_name,
                    a.name AS agent_name, a.title AS agent_title, a.phone AS agent_phone,
                    a.whatsapp AS agent_whatsapp, a.email AS agent_email, a.photo AS agent_photo,
                    a.bio AS agent_bio
             FROM properties p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN counties co ON co.id = p.county_id
             LEFT JOIN towns t     ON t.id = p.town_id
             LEFT JOIN estates es  ON es.id = p.estate_id
             LEFT JOIN agents a    ON a.id = p.agent_id
             WHERE p.slug = ? LIMIT 1",
            [$slug]
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::row("SELECT * FROM properties WHERE id = ? LIMIT 1", [$id]);
    }

    public static function images(int $propertyId, ?bool $floorplan = null): array
    {
        $sql = "SELECT * FROM property_images WHERE property_id = ?";
        $params = [$propertyId];
        if ($floorplan !== null) { $sql .= " AND is_floorplan = ?"; $params[] = $floorplan ? 1 : 0; }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        return Database::all($sql, $params);
    }

    public static function amenities(int $propertyId): array
    {
        return Database::all(
            "SELECT am.* FROM amenities am
             JOIN property_amenities pa ON pa.amenity_id = am.id
             WHERE pa.property_id = ? ORDER BY am.name",
            [$propertyId]
        );
    }

    public static function openHouses(int $propertyId): array
    {
        return Database::all(
            "SELECT * FROM open_houses WHERE property_id = ? AND end_at >= NOW() ORDER BY start_at ASC",
            [$propertyId]
        );
    }

    /** Related properties: same purpose or category, excluding self. */
    public static function related(array $p, int $limit = 3): array
    {
        return Database::all(
            "SELECT pr.*, co.name AS county_name, t.name AS town_name
             FROM properties pr
             LEFT JOIN counties co ON co.id = pr.county_id
             LEFT JOIN towns t ON t.id = pr.town_id
             WHERE pr.id <> ? AND pr.status = 'available'
               AND (pr.category_id = ? OR pr.county_id = ? OR pr.purpose = ?)
             ORDER BY pr.is_featured DESC, pr.created_at DESC
             LIMIT {$limit}",
            [$p['id'], $p['category_id'], $p['county_id'], $p['purpose']]
        );
    }

    public static function incrementViews(int $id): void
    {
        Database::run("UPDATE properties SET views = views + 1 WHERE id = ?", [$id]);
    }

    /** Latest N available properties. */
    /**
     * Fetch specific properties by ID, preserving the given order.
     * Used by the homepage editor's manual property selection.
     */
    public static function byIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $orderField = implode(',', $ids); // FIELD() preserves chosen order
        $rows = Database::all(
            "SELECT p.*, co.name AS county_name, t.name AS town_name, c.name AS category_name
             FROM properties p
             LEFT JOIN counties co ON co.id = p.county_id
             LEFT JOIN towns t ON t.id = p.town_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id IN ($in)
             ORDER BY FIELD(p.id, {$orderField})",
            $ids
        );
        return $rows;
    }

    public static function latest(int $limit = 6): array
    {
        $limit = (int)$limit;
        return Database::all(
            "SELECT p.*, co.name AS county_name, t.name AS town_name, c.name AS category_name
             FROM properties p
             LEFT JOIN counties co ON co.id = p.county_id
             LEFT JOIN towns t ON t.id = p.town_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'available'
             ORDER BY p.created_at DESC LIMIT {$limit}"
        );
    }

    public static function featured(int $limit = 6): array
    {
        $limit = (int)$limit;
        return Database::all(
            "SELECT p.*, co.name AS county_name, t.name AS town_name, c.name AS category_name
             FROM properties p
             LEFT JOIN counties co ON co.id = p.county_id
             LEFT JOIN towns t ON t.id = p.town_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.status = 'available' AND p.is_featured = 1
             ORDER BY p.created_at DESC LIMIT {$limit}"
        );
    }

    public static function byPurpose(string $purpose, int $limit = 6): array
    {
        $limit = (int)$limit;
        return Database::all(
            "SELECT p.*, co.name AS county_name, t.name AS town_name
             FROM properties p
             LEFT JOIN counties co ON co.id = p.county_id
             LEFT JOIN towns t ON t.id = p.town_id
             WHERE p.status = 'available' AND p.purpose = ?
             ORDER BY p.is_featured DESC, p.created_at DESC LIMIT {$limit}",
            [$purpose]
        );
    }

    public static function luxury(int $limit = 6): array
    {
        $limit = (int)$limit;
        return Database::all(
            "SELECT p.*, co.name AS county_name, t.name AS town_name
             FROM properties p
             LEFT JOIN counties co ON co.id = p.county_id
             LEFT JOIN towns t ON t.id = p.town_id
             WHERE p.status = 'available' AND p.is_luxury = 1
             ORDER BY p.created_at DESC LIMIT {$limit}"
        );
    }

    public static function generateRef(): string
    {
        $year = date('Y');
        $row = Database::row(
            "SELECT ref_code FROM properties WHERE ref_code LIKE ? ORDER BY id DESC LIMIT 1",
            ["RK-{$year}-%"]
        );
        $next = 1;
        if ($row && preg_match('/RK-\d{4}-(\d+)/', $row['ref_code'], $m)) {
            $next = (int)$m[1] + 1;
        }
        return sprintf('RK-%s-%04d', $year, $next);
    }
}
