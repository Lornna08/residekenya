<?php
/**
 * Database — PDO singleton with safe defaults.
 * Usage: $db = Database::conn();  then prepared statements.
 *
 * Vercel edition: connects to Postgres (Neon) using DATABASE_URL and
 * translates the few MySQL-only idioms the app uses (LIKE is
 * case-insensitive in MySQL, so it becomes ILIKE here).
 */
class Database
{
    private static ?PDO $instance = null;

    public static function conn(): PDO
    {
        if (self::$instance === null) {
            $u = parse_url(DATABASE_URL);
            parse_str($u['query'] ?? '', $q);
            $host = $u['host'] ?? 'localhost';
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $u['port'] ?? 5432, ltrim($u['path'] ?? '/postgres', '/'));
            $local = in_array($host, ['localhost', '127.0.0.1'], true) || str_starts_with($host, '/');
            $dsn .= ';sslmode=' . ($q['sslmode'] ?? ($local ? 'disable' : 'require'));
            // Neon routes by SNI; pass the endpoint id too for older libpq builds.
            if (!$local && preg_match('/^(ep-[a-z0-9-]+?)(-pooler)?\./', $host, $m)) {
                $dsn .= ';options=endpoint=' . $m[1];
            }
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ];
            try {
                self::$instance = new PDO($dsn, urldecode($u['user'] ?? ''), urldecode($u['pass'] ?? ''), $options);
                self::$instance->exec("SET TIME ZONE 'Africa/Nairobi'");
            } catch (PDOException $e) {
                if (APP_ENV === 'production') {
                    error_log('DB connection failed: ' . $e->getMessage());
                    http_response_code(500);
                    exit('Database connection failed. Please try again later.');
                }
                throw $e;
            }
        }
        return self::$instance;
    }

    /** Translate MySQL idioms to Postgres. */
    public static function sql(string $sql): string
    {
        // MySQL LIKE is case-insensitive with utf8mb4_unicode_ci.
        return preg_replace('/(?<![A-Za-z_])LIKE(?![A-Za-z_])/i', 'ILIKE', $sql);
    }

    private static function stmt(string $sql, array $params): PDOStatement
    {
        $stmt = self::conn()->prepare(self::sql($sql));
        $stmt->execute(array_map(fn($v) => is_bool($v) ? (int) $v : $v, array_values($params)));
        return $stmt;
    }

    /** Convenience: fetch one row. */
    public static function row(string $sql, array $params = []): ?array
    {
        $row = self::stmt($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Convenience: fetch all rows. */
    public static function all(string $sql, array $params = []): array
    {
        return self::stmt($sql, $params)->fetchAll();
    }

    /** Convenience: run a write, return affected rows. */
    public static function run(string $sql, array $params = []): int
    {
        return self::stmt($sql, $params)->rowCount();
    }

    /** Insert and return last id. */
    public static function insert(string $sql, array $params = []): int
    {
        self::stmt($sql, $params);
        return (int) self::conn()->lastInsertId();
    }
}

/**
 * Sessions stored in the database, so a login survives across the
 * many short-lived serverless instances Vercel runs the site on.
 */
class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $row = Database::row("SELECT data FROM sessions WHERE id = ? AND updated_at > NOW() - INTERVAL '7 days'", [$id]);
        return $row['data'] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        Database::run(
            "INSERT INTO sessions (id, data, updated_at) VALUES (?, ?, NOW())
             ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, updated_at = NOW()",
            [$id, $data]
        );
        return true;
    }

    public function destroy(string $id): bool
    {
        Database::run("DELETE FROM sessions WHERE id = ?", [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return Database::run("DELETE FROM sessions WHERE updated_at < NOW() - INTERVAL '7 days'");
    }

    public function validateId(string $id): bool
    {
        return Database::row("SELECT 1 AS ok FROM sessions WHERE id = ?", [$id]) !== null;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        Database::run("UPDATE sessions SET updated_at = NOW() WHERE id = ?", [$id]);
        return true;
    }
}
