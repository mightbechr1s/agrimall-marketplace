<?php
// PDO connection singleton (NFR-SEC-001).
declare(strict_types=1);

require_once __DIR__ . '/view_sql.php';

/**
 * Rewrites the two reporting view names into inline derived tables before a
 * statement reaches MySQL. Keeps the rest of the codebase on the view names
 * and works on hosts that do not grant CREATE VIEW.
 */
final class AppPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(expand_view_aliases($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = expand_view_aliases($query);
        return $fetchMode === null
            ? parent::query($query)
            : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(expand_view_aliases($statement));
    }
}

final class DB
{
    private static ?PDO $pdo = null;

    public static function init(array $dbConfig): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['name'],
            $dbConfig['charset']
        );
        self::$pdo = new AppPDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('DB not initialized.');
        }
        return self::$pdo;
    }
}
