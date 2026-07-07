<?php

declare(strict_types=1);

namespace nzedb\db;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use SimpleXMLElement;

final class DB
{
    private PDO $pdo;

    /**
     * @var array<string, bool|string>
     */
    private readonly array $opts;

    /**
     * @param array<string, bool|string> $options
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            'checkVersion' => false,
            'createDb' => false,
            'dbhost' => defined('DB_HOST') ? DB_HOST : 'localhost',
            'dbname' => defined('DB_NAME') ? DB_NAME : '',
            'dbpass' => defined('DB_PASSWORD') ? DB_PASSWORD : '',
            'dbport' => defined('DB_PORT') ? DB_PORT : '',
            'dbsock' => defined('DB_SOCKET') ? DB_SOCKET : '',
            'dbtype' => 'mysql',
            'dbuser' => defined('DB_USER') ? DB_USER : '',
        ];

        $this->opts = $options + $defaults;
        $this->initialiseDatabase();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function checkDbExists(?string $name = null): bool
    {
        $databaseName = $name ?: (string) $this->opts['dbname'];

        if ($databaseName === '') {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :database_name'
        );
        $statement->execute(['database_name' => $databaseName]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTableList(): array
    {
        $result = $this->pdo->query('SHOW DATABASES');

        if (!$result instanceof PDOStatement) {
            return [];
        }

        return $result->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $query, array $parameters = []): PDOStatement
    {
        if ($query === '') {
            throw new RuntimeException('Cannot execute an empty SQL statement.');
        }

        return $this->executeWithRetry($query, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $query, array $parameters = []): array
    {
        return $this->execute($query, $parameters)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>|false
     */
    public function fetchOne(string $query, array $parameters = []): array|false
    {
        $row = $this->execute($query, $parameters)->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : false;
    }

    public function escapeString(SimpleXMLElement|string|null $str): string
    {
        if ($str === null) {
            return 'NULL';
        }

        return $this->pdo->quote((string) $str);
    }

    public function queryInsert(string $query): bool|string|int
    {
        if ($query === '') {
            return false;
        }

        try {
            $this->execute($query);

            return $this->pdo->lastInsertId();
        } catch (RuntimeException $e) {
            $this->echoError($e->getMessage(), 'queryInsert', 4);

            return false;
        }
    }

    public function queryExec(string $query): PDOStatement|bool
    {
        if ($query === '') {
            return false;
        }

        try {
            return $this->execute($query);
        } catch (RuntimeException $e) {
            $this->echoError($e->getMessage(), 'queryExec', 4);

            return false;
        }
    }

    public function Exec(string $statement): false|int
    {
        if ($statement === '') {
            return false;
        }

        try {
            $result = $this->pdo->exec($statement);

            return $result === false ? false : $result;
        } catch (PDOException $e) {
            $this->echoError($e->getMessage(), 'Exec', 4);

            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): array
    {
        unset($fetchMode, $fetchModeArgs);

        if ($query === '') {
            return [];
        }

        $result = $this->queryArray($query);

        return $result === false ? [] : $result;
    }

    /**
     * @return list<array<string, mixed>>|false
     */
    public function queryArray(string $query): bool|array
    {
        if ($query === '') {
            return false;
        }

        $result = $this->queryDirect($query);

        if ($result === false) {
            return false;
        }

        $rows = $result->fetchAll(PDO::FETCH_ASSOC);

        return $rows !== [] ? $rows : false;
    }

    public function queryDirect(string $query): PDOStatement|false
    {
        if ($query === '') {
            return false;
        }

        try {
            return $this->execute($query);
        } catch (RuntimeException $e) {
            $this->echoError($e->getMessage(), 'queryDirect', 4);

            return false;
        }
    }

    /**
     * @return array<string, mixed>|false
     */
    public function queryOneRow(string $query): array|bool
    {
        $rows = $this->query($query);

        return $rows[0] ?? false;
    }

    public function optimise(bool $admin = false, string $type = ''): int
    {
        $allTables = match (true) {
            $type === 'true' || $type === 'full' || $type === 'analyze' => $this->query('SHOW TABLE STATUS'),
            default => $this->query('SHOW TABLE STATUS WHERE Data_free / Data_length > 0.005'),
        };

        $tableCount = count($allTables);

        if ($tableCount === 0) {
            return 0;
        }

        if ($type === 'all' || $type === 'full') {
            $tables = implode(', ', array_map(fn (array $table): string => $this->quoteIdentifier(
                $this->tableName($table)
            ), $allTables));

            if (!$admin) {
                echo "Optimizing tables: {$tables}";
            }

            $this->queryExec("OPTIMIZE LOCAL TABLE {$tables}");
        } else {
            foreach ($allTables as $table) {
                $tableName = $this->tableName($table);
                $quotedTableName = $this->quoteIdentifier($tableName);

                if ($type === 'analyze') {
                    $this->queryExec("ANALYZE LOCAL TABLE {$quotedTableName}");
                    continue;
                }

                if (!$admin) {
                    echo "Optimizing table: {$tableName}";
                }

                if (strtolower($this->tableEngine($table)) === 'myisam') {
                    $this->queryExec("REPAIR TABLE {$quotedTableName}");
                }

                $this->queryExec("OPTIMIZE LOCAL TABLE {$quotedTableName}");
            }
        }

        if ($type !== 'analyze') {
            $this->queryExec('FLUSH TABLES');
        }

        return $tableCount;
    }

    public function from_unixtime(int $utime): string
    {
        return "FROM_UNIXTIME({$utime})";
    }

    public function ping(bool $restart = false): bool
    {
        try {
            $this->pdo->query('SELECT 1');

            return true;
        } catch (PDOException) {
            if ($restart) {
                $this->initialiseDatabase();

                return true;
            }

            return false;
        }
    }

    private function initialiseDatabase(): void
    {
        $dbType = strtolower((string) $this->opts['dbtype']);

        if (!in_array($dbType, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Only MySQL/MariaDB database connections are supported.');
        }

        $this->pdo = new PDO(
            $this->buildDsn($dbType, false),
            (string) $this->opts['dbuser'],
            (string) $this->opts['dbpass'],
            $this->pdoOptions()
        );

        $databaseName = (string) $this->opts['dbname'];

        if ($databaseName === '') {
            throw new RuntimeException('Database name is required.');
        }

        $found = $this->checkDbExists($databaseName);

        if ((bool) $this->opts['createDb']) {
            $this->recreateDatabase($databaseName, $found);
            $found = true;
        }

        if (!$found) {
            throw new RuntimeException(
                "Could not find database '{$databaseName}'. Please create it before starting the bot."
            );
        }

        $this->pdo->exec('USE ' . $this->quoteIdentifier($databaseName));
        $this->pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    private function buildDsn(string $dbType, bool $withDatabase): string
    {
        $parts = [];

        if ((string) $this->opts['dbsock'] !== '') {
            $parts[] = 'unix_socket=' . (string) $this->opts['dbsock'];
        } else {
            $host = (string) $this->opts['dbhost'];
            $parts[] = 'host=' . ($host === '' ? 'localhost' : $host);

            if ((string) $this->opts['dbport'] !== '') {
                $parts[] = 'port=' . (string) $this->opts['dbport'];
            }
        }

        if ($withDatabase) {
            $parts[] = 'dbname=' . (string) $this->opts['dbname'];
        }

        $parts[] = 'charset=utf8mb4';

        return $dbType . ':' . implode(';', $parts);
    }

    /**
     * @return array<int, mixed>
     */
    private function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 180,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            PDO::MYSQL_ATTR_LOCAL_INFILE => true,
        ];
    }

    private function recreateDatabase(string $databaseName, bool $found): void
    {
        $quotedDatabaseName = $this->quoteIdentifier($databaseName);

        if ($found) {
            $this->pdo->exec("DROP DATABASE {$quotedDatabaseName}");
        }

        $this->pdo->exec(
            "CREATE DATABASE {$quotedDatabaseName} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function executeWithRetry(string $query, array $parameters): PDOStatement
    {
        $attempt = 0;

        do {
            try {
                $statement = $this->pdo->prepare($query);
                $statement->execute($parameters);

                return $statement;
            } catch (PDOException $e) {
                if (!$this->isRetryableDeadlock($e) || ++$attempt >= 10) {
                    throw new RuntimeException($e->getMessage(), (int) $e->getCode(), $e);
                }

                $this->echoError(
                    'A deadlock or lock wait timeout occurred, retrying.(' . $attempt . ')',
                    'execute',
                    4
                );
                usleep(250_000);
            }
        } while (true);
    }

    private function isRetryableDeadlock(PDOException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;
        $sqlState = $e->errorInfo[0] ?? null;

        return in_array($driverCode, [1205, 1213], true) || $sqlState === '40001';
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException("Invalid SQL identifier '{$identifier}'.");
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * @param array<string, mixed> $table
     */
    private function tableName(array $table): string
    {
        $name = $table['name'] ?? '';

        if (!is_scalar($name) || $name === '') {
            throw new RuntimeException('Table status row is missing a valid table name.');
        }

        return (string) $name;
    }

    /**
     * @param array<string, mixed> $table
     */
    private function tableEngine(array $table): string
    {
        $engine = $table['engine'] ?? '';

        return is_scalar($engine) ? (string) $engine : '';
    }

    private function echoError(string $error, string $method, int $severity): void
    {
        echo "({$method}) {$error} [{$severity}]\n";
    }
}
