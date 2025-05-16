<?php

/** @noinspection SpellCheckingInspection */

namespace nzedb\db;

use Exception;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use SimpleXMLElement;

/**
* Class for handling connection to database (MySQL or PostgreSQL) using PDO.
*
* The class extends PDO, thereby exposing all of PDO's functionality directly
* without the need to wrap each and every method here.
*/
class DB extends PDO
{
/**
 * @var PDO|null Instance of PDO class.
 */
    private static ?PDO $pdo = null;

/**
 * @var string Lower-cased name of DBMS in use.
 */
    private readonly string $DbSystem;

/**
 * @var array Options passed into the constructor or defaulted.
 */
    private readonly array $opts;

/**
 * Constructor. Sets up all necessary properties. Instantiates a PDO object
 * if needed, otherwise returns the current one.
 * @noinspection PhpMissingParentConstructorInspection
 */
    public function __construct(array $options = [])
    {
        $defaults = [
            'checkVersion' => false,
            'createDb'     => false,
            'dbhost'       => defined('DB_HOST') ? DB_HOST : '',
            'dbname'       => defined('DB_NAME') ? DB_NAME : '',
            'dbpass'       => defined('DB_PASSWORD') ? DB_PASSWORD : '',
            'dbport'       => defined('DB_PORT') ? DB_PORT : '',
            'dbsock'       => defined('DB_SOCKET') ? DB_SOCKET : '',
            'dbtype'       => 'mysql',
            'dbuser'       => defined('DB_USER') ? DB_USER : ''
        ];
        $this->opts = $options + $defaults;

        $this->DbSystem = strtolower($this->opts['dbtype'] ?? 'mysql');

        if (!(self::$pdo instanceof PDO)) {
            $this->initialiseDatabase();
        }

        return self::$pdo;
    }

    public function checkDbExists(?string $name = null): bool
    {
        if (empty($name)) {
            $name = $this->opts['dbname'];
        }

        $found = false;
        $tables = self::getTableList();
        foreach ($tables as $table) {
            if ($table['Database'] == $name) {
                $found = true;
                break;
            }
        }
        return $found;
    }

    public function getTableList(): array|false
    {
        $result = self::$pdo->query('SHOW DATABASES');
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }

/**
 * Init PDO instance.
 */
    private function initialiseDatabase(): void
    {
        $dsn = !empty($this->opts['dbsock'])
        ? "{$this->DbSystem}:unix_socket={$this->opts['dbsock']}"
        : "{$this->DbSystem}:host={$this->opts['dbhost']}" .
          (!empty($this->opts['dbport']) ? ";port={$this->opts['dbport']}" : "");

        $dsn .= ';charset=utf8';

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 180
        ];

        if ($this->DbSystem === 'mysql') {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8";
            $options[PDO::MYSQL_ATTR_LOCAL_INFILE] = true;
        }

        $dsn1 = $dsn;
        self::$pdo = new PDO($dsn1, $this->opts['dbuser'], $this->opts['dbpass'], $options);

        $found = self::checkDbExists();
        if ($this->DbSystem === 'pgsql' && !$found) {
            throw new RuntimeException(
                "Could not find your database: {$this->opts['dbname']}, " .
                "please see Install.txt for instructions on how to create a database.",
                1
            );
        }

        if ($this->opts['createDb']) {
            if ($found) {
                try {
                    self::$pdo->query("DROP DATABASE {$this->opts['dbname']}");
                } catch (Exception) {
                    throw new RuntimeException("Error trying to drop your old database: '{$this->opts['dbname']}'", 2);
                }
                $found = self::checkDbExists();
            }

            if ($found) {
                var_dump(self::getTableList());
                throw new RuntimeException("Could not drop your old database: '{$this->opts['dbname']}'", 2);
            } else {
                self::$pdo->query("CREATE DATABASE `{$this->opts['dbname']}` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci");

                if (!self::checkDbExists()) {
                    throw new RuntimeException("Could not create new database: '{$this->opts['dbname']}'", 3);
                }
            }
        }
        self::$pdo->query("USE {$this->opts['dbname']}");

        if (self::$pdo === false) {
            $this->echoError(
                "Unable to create connection to the Database!",
                'initialiseDatabase',
                1,
                true
            );
        }

        self::$pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

/**
 * Echo error, optionally exit.
 */
    protected function echoError(string $error, string $method, int $severity, bool $exit = false): void
    {
        echo "($method) $error [$severity]\n";

        if ($exit) {
            exit();
        }
    }

/**
 * Returns a string, escaped with single quotes, false on failure.
 */
    public function escapeString(SimpleXMLElement|string|null $str): string
    {
        if (is_null($str)) {
            return 'NULL';
        }

        return self::$pdo->quote($str);
    }

/**
 * For inserting a row. Returns last insert ID. queryExec is better if you do not need the id.
 */
    public function queryInsert(string $query): bool|string|int
    {
        if (empty($query)) {
            return false;
        }

        for ($i = 2; $i < 11; $i++) {
            $result = $this->queryExecHelper($query, true);
            if (is_array($result) && isset($result['deadlock'])) {
                if ($result['deadlock'] === true) {
                    $this->echoError("A Deadlock or lock wait timeout has occurred, sleeping.(" . ($i - 1) . ")", 'queryInsert', 4);
                    continue;
                }
                break;
            } elseif ($result === false) {
                break;
            } else {
                return $result;
            }
        }
        return false;
    }

/**
 * Used for deleting, updating (and inserting without needing the last insert id).
 */
    public function queryExec(string $query): PDOStatement|array|string|bool
    {
        if (empty($query)) {
            return false;
        }

        for ($i = 2; $i < 11; $i++) {
            $result = $this->queryExecHelper($query);
            if (is_array($result) && isset($result['deadlock'])) {
                if ($result['deadlock'] === true) {
                    $this->echoError("A Deadlock or lock wait timeout has occurred, sleeping.(" . ($i - 1) . ")", 'queryExec', 4);
                    continue;
                }
                break;
            } elseif ($result === false) {
                break;
            } else {
                return $result;
            }
        }
        return false;
    }

/**
 * Helper method for queryInsert and queryExec, checks for deadlocks.
 */
    protected function queryExecHelper(string $query, bool $insert = false): PDOStatement|array|string|false
    {
        try {
            if (!$insert) {
                $run = self::$pdo->prepare($query);
                $run->execute();
                return $run;
            } else {
                if ($this->DbSystem === 'mysql') {
                    $ins = self::$pdo->prepare($query);
                    $ins->execute();
                    return self::$pdo->lastInsertId();
                } else {
                    $p = self::$pdo->prepare($query . ' RETURNING id');
                    $p->execute();
                    $r = $p->fetch(PDO::FETCH_ASSOC);
                    return $r['id'];
                }
            }
        } catch (PDOException $e) {
            // Deadlock or lock wait timeout, try 10 times.
            $deadlockCodes = [1213, 40001, 1205];
            $deadlockMessage = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';

            if (
                in_array($e->errorInfo[1] ?? 0, $deadlockCodes) ||
                $e->errorInfo[0] == 40001 ||
                $e->getMessage() == $deadlockMessage
            ) {
                return ['deadlock' => true, 'message' => $e->getMessage()];
            }
            var_dump($e->getMessage());
            return ['deadlock' => false, 'message' => $e->getMessage()];
        }
    }

/**
 * Direct query. Return the affected row count.
 */
    public function Exec(string $statement): false|int
    {
        if (empty($statement)) {
            return false;
        }

        try {
            return self::$pdo->exec($statement);
        } catch (PDOException $e) {
            $this->echoError($e->getMessage(), 'Exec', 4);
            return false;
        }
    }

/**
 * Returns an array of result (empty array if no results or an error occurs)
 */
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetch_mode_args): PDOStatement|array|string|bool
    {
        if (empty($query)) {
            return false;
        }

        $result = $this->queryArray($query);

        return ($result === false) ? [] : $result;
    }

/**
 * Main method for creating results as an array.
 */
    public function queryArray(string $query): bool|array
    {
        if (empty($query)) {
            return false;
        }

        $result = $this->queryDirect($query);
        if ($result === false) {
            return false;
        }

        $rows = [];
        foreach ($result as $row) {
            $rows[] = $row;
        }

        return !empty($rows) ? $rows : false;
    }

/**
 * Query without returning an empty array like our function query().
 */
    public function queryDirect(string $query): PDOStatement|bool
    {
        if (empty($query)) {
            return false;
        }

        try {
            return self::$pdo->query($query);
        } catch (PDOException $e) {
            $this->echoError($e->getMessage(), 'queryDirect', 4);
            return false;
        }
    }

/**
 * Returns the first row of the query.
 */
    public function queryOneRow(string $query): array|bool
    {
        $rows = $this->query($query);

        if (!$rows || count($rows) === 0) {
            return false;
        }

        return $rows[0];
    }

/**
 * Optimises/repairs tables on mysql. Vacuum/analyze on postgresql.
 */
    public function optimise(bool $admin = false, string $type = ''): int
    {
        $tableCount = 0;

        if ($this->DbSystem === 'mysql') {
            $allTables = match (true) {
                $type === 'true' || $type === 'full' || $type === 'analyze'
                => $this->query('SHOW TABLE STATUS'),
                default
                => $this->query('SHOW TABLE STATUS WHERE Data_free / Data_length > 0.005')
            };

            $tableCount = count($allTables);

            if ($type === 'all' || $type === 'full') {
                $tables = implode(', ', array_column($allTables, 'name'));

                if (!$admin) {
                    echo "Optimizing tables: $tables";
                }
                $this->queryExec("OPTIMIZE LOCAL TABLE $tables");
            } else {
                foreach ($allTables as $table) {
                    if ($type === 'analyze') {
                        $this->queryExec('ANALYZE LOCAL TABLE `' . $table['name'] . '`');
                    } else {
                        if (!$admin) {
                            echo 'Optimizing table: ' . $table['name'];
                        }
                        if (strtolower($table['engine']) == 'myisam') {
                            $this->queryExec('REPAIR TABLE `' . $table['name'] . '`');
                        }
                        $this->queryExec('OPTIMIZE LOCAL TABLE `' . $table['name'] . '`');
                    }
                }
            }

            if ($type !== 'analyze') {
                $this->queryExec('FLUSH TABLES');
            }
        } elseif ($this->DbSystem === 'pgsql') {
            $allTables = $this->query("SELECT table_name as name FROM information_schema.tables WHERE table_schema = 'public'");
            $tableCount = count($allTables);
            foreach ($allTables as $table) {
                if (!$admin) {
                    echo "Vacuuming table: {$table['name']}.\n";
                }
                $this->query('VACUUM (ANALYZE) ' . $table['name']);
            }
        }

        return $tableCount;
    }

/**
 * PHP interpretation of MySQL's from_unixtime method.
 */
    public function from_unixtime(int $utime): string
    {
        return match ($this->DbSystem) {
            'mysql' => "FROM_UNIXTIME($utime)",
            default => "TO_TIMESTAMP($utime)::TIMESTAMP"
        };
    }

/**
 * Checks whether the connection to the server is working.
 */
    public function ping(bool $restart = false): bool
    {
        try {
            return (bool) self::$pdo->query('SELECT 1+1');
        } catch (PDOException) {
            if ($restart) {
                $this->initialiseDatabase();
            }
            return false;
        }
    }
}
