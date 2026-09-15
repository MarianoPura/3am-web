<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use InvalidArgumentException;

/**
 * Database access layer.
 *
 * DESIGN CONSTRAINT — read this before adding a method
 * ====================================================
 * This project has no framework, so there is no framework default to fall back
 * on. The defence against SQL injection therefore cannot be "developers are
 * careful"; it has to be structural.
 *
 * Accordingly: **no public method on this class accepts a value that is placed
 * into SQL.** Every method takes a SQL string with placeholders plus a separate
 * array of bindings. There is deliberately no query builder, no `where()`
 * helper, no `raw()` escape hatch. If you find yourself wanting to concatenate
 * a value into a query string, the answer is a placeholder — always, with no
 * exceptions worth making.
 *
 * The one genuinely dynamic part of SQL that cannot be parameterised is an
 * identifier (a column name in ORDER BY). That is handled by identifier(),
 * which requires an explicit allowlist and throws otherwise.
 *
 * Emulated prepares are OFF. With emulation on, PDO interpolates client-side
 * and the driver's own escaping becomes the last line of defence — which is a
 * meaningfully weaker position, particularly with multi-byte charsets.
 */
final class Database
{
    private ?PDO $pdo = null;

    /** @var list<array{sql:string, time:float}> Query log, dev only. */
    private array $log = [];

    private int $transactionDepth = 0;

    public function __construct(
        private readonly array $config,
        private readonly bool $logQueries = false,
    ) {
    }

    /**
     * Connect lazily. A request served entirely from cache should not open a
     * database connection at all.
     */
    private function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            (int) $this->config['port'],
            $this->config['database'],
            $this->config['charset'] ?? 'utf8mb4',
        );

        try {
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // Non-negotiable. See the class docblock.
                PDO::ATTR_EMULATE_PREPARES   => false,

                // Return native ints/floats rather than strings, so that
                // `$row['is_featured'] === 1` behaves the way it reads.
                PDO::ATTR_STRINGIFY_FETCHES  => false,

                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND =>
                    "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            ]);
        } catch (PDOException $e) {
            // Never leak DSN or credentials into an exception that might be
            // rendered or logged somewhere less careful than we are.
            throw new RuntimeException('Database connection failed.', 0, $e);
        }

        return $this->pdo;
    }

    // ─────────────────────────────────────────────────────────
    // Reads
    // ─────────────────────────────────────────────────────────

    /**
     * @param  array<string|int, scalar|null> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll();
    }

    /**
     * @param  array<string|int, scalar|null> $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Single scalar — counts, EXISTS checks, aggregates.
     *
     * @param array<string|int, scalar|null> $bindings
     */
    public function selectValue(string $sql, array $bindings = []): mixed
    {
        $value = $this->run($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Key/value map, e.g. settings. First column becomes the key.
     *
     * @param  array<string|int, scalar|null> $bindings
     * @return array<string, mixed>
     */
    public function selectPairs(string $sql, array $bindings = []): array
    {
        $out = [];
        foreach ($this->run($sql, $bindings)->fetchAll(PDO::FETCH_NUM) as $row) {
            $out[(string) $row[0]] = $row[1] ?? null;
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────
    // Writes
    // ─────────────────────────────────────────────────────────

    /** @param array<string|int, scalar|null> $bindings */
    public function insert(string $sql, array $bindings = []): int
    {
        $this->run($sql, $bindings);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string|int, scalar|null> $bindings */
    public function update(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    /** @param array<string|int, scalar|null> $bindings */
    public function delete(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    /**
     * DDL and anything without a meaningful return. Used by the migration
     * runner; application code should not need it.
     *
     * @param array<string|int, scalar|null> $bindings
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->run($sql, $bindings)->rowCount() >= 0;
    }

    // ─────────────────────────────────────────────────────────
    // Transactions (re-entrant via savepoints)
    // ─────────────────────────────────────────────────────────

    /**
     * Run a callback in a transaction, committing on success and rolling back
     * on any throwable. Nested calls use savepoints, so a model method that
     * opens its own transaction still composes correctly inside a larger one.
     *
     * @template T
     * @param  callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . $this->transactionDepth);
        }

        $this->transactionDepth++;
    }

    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            throw new RuntimeException('commit() called with no active transaction.');
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            $this->pdo()->commit();
        } else {
            $this->pdo()->exec('RELEASE SAVEPOINT trans' . $this->transactionDepth);
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }

        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            $this->pdo()->rollBack();
        } else {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactionDepth);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Safe identifiers — the only non-parameterised path in this class
    // ─────────────────────────────────────────────────────────

    /**
     * Validate a column/table identifier against an explicit allowlist and
     * return it backtick-quoted.
     *
     * This exists for ORDER BY and similar clauses, which cannot take a bound
     * parameter. The allowlist is mandatory: there is no "trust the caller"
     * mode, because the caller is usually a query-string parameter.
     *
     *     $col = $db->identifier($_GET['sort'] ?? 'year', Project::SORTABLE);
     *     $dir = $db->direction($_GET['dir'] ?? 'desc');
     *     $db->select("SELECT * FROM projects ORDER BY {$col} {$dir}");
     *
     * @param list<string> $allowed
     */
    public function identifier(string $identifier, array $allowed): string
    {
        if (!in_array($identifier, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf('Identifier "%s" is not in the allowlist.', $identifier)
            );
        }

        // Defensive second check: even an allowlisted value must look like an
        // identifier. This catches an allowlist that someone has built from
        // user input, which is the way this kind of guard actually gets broken.
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException('Malformed identifier.');
        }

        return '`' . $identifier . '`';
    }

    /**
     * Normalise a sort direction to exactly ASC or DESC. Anything unrecognised
     * becomes ASC rather than throwing — a bad ?dir= value should not be a 500.
     */
    public function direction(string $direction): string
    {
        return strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
    }

    /**
     * Build a placeholder list for an IN clause, e.g. `IN (?,?,?)`.
     *
     * Callers still pass the values through $bindings — this only produces the
     * placeholders, never the values.
     *
     * @param array<int, mixed> $values
     */
    public function placeholders(array $values): string
    {
        if ($values === []) {
            // `IN ()` is a syntax error; NULL is never equal to anything, which
            // gives the correct empty-set semantics.
            return 'NULL';
        }

        return implode(',', array_fill(0, count($values), '?'));
    }

    // ─────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────

    /** @param array<string|int, scalar|null> $bindings */
    private function run(string $sql, array $bindings): PDOStatement
    {
        $start = microtime(true);

        try {
            $statement = $this->pdo()->prepare($sql);
            $this->bind($statement, $bindings);
            $statement->execute();
        } catch (PDOException $e) {
            // Include the SQL (structure only — bindings are never inlined)
            // so a failure is debuggable, while values stay out of logs.
            throw new RuntimeException(
                sprintf('Query failed: %s — SQL: %s', $e->getMessage(), $sql),
                0,
                $e
            );
        }

        if ($this->logQueries) {
            $this->log[] = ['sql' => $sql, 'time' => (microtime(true) - $start) * 1000];
        }

        return $statement;
    }

    /**
     * Bind with explicit types. Passing an array to execute() would coerce
     * everything to string, which breaks strict comparisons and, more subtly,
     * makes `LIMIT ?` fail outright under non-emulated prepares.
     *
     * @param array<string|int, scalar|null> $bindings
     */
    private function bind(PDOStatement $statement, array $bindings): void
    {
        foreach ($bindings as $key => $value) {
            // Positional placeholders are 1-indexed.
            $param = is_int($key) ? $key + 1 : $key;

            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };

            $statement->bindValue($param, $value, $type);
        }
    }

    /** @return list<array{sql:string, time:float}> */
    public function queryLog(): array
    {
        return $this->log;
    }
}
