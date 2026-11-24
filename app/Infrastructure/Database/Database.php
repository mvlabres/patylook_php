<?php

declare(strict_types=1);

namespace Patylook\Infrastructure\Database;

use DateTimeZone;
use PDO;
use PDOException;
use PDOStatement;
use Patylook\Exceptions\ConflictException;
use Patylook\Exceptions\InUseException;
use Patylook\Exceptions\NotFoundException;
use Throwable;

/**
 * Thin wrapper over one lazily opened PDO connection. Every repository shares the instance, which
 * is also what lets a transaction span several repositories. It performs DML only: the schema is
 * owned by the already existing database.
 */
final class Database
{
    private const ERR_DUPLICATE_ENTRY = 1062;
    private const ERR_ROW_IS_REFERENCED = 1451;
    private const ERR_NO_REFERENCED_ROW = 1452;
    private const ERR_NO_REFERENCED_ROW_2 = 1216;

    private ?PDO $pdo = null;
    private int $depth = 0;

    /**
     * @param string $tls "", "true", "skip-verify" or "preferred" (when the server requires TLS)
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
        private readonly string $name,
        private readonly DateTimeZone $timezone,
        private readonly string $tls = '',
    ) {
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /** Runs a SELECT and returns every row. @return list<array<string, mixed>> */
    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** Runs a SELECT and returns the first row, or null. @return array<string, mixed>|null */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Returns the first column of the first row. */
    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * Runs an INSERT/UPDATE/DELETE and returns the affected row count. Constraint violations are
     * translated into domain exceptions.
     */
    public function execute(string $sql, array $params = []): int
    {
        try {
            return $this->run($sql, $params)->rowCount();
        } catch (PDOException $e) {
            throw self::translate($e);
        }
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection()->lastInsertId();
    }

    /**
     * Runs $work in a transaction (joining the outer one when nested). Rolls back and rethrows on
     * failure.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        if ($this->depth > 0) {
            return $work();
        }
        $pdo = $this->connection();
        $pdo->beginTransaction();
        $this->depth = 1;
        try {
            $result = $work();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            $this->depth = 0;
        }
    }

    private function run(string $sql, array $params): PDOStatement
    {
        $statement = $this->connection()->prepare($sql);
        $position = 1;
        foreach ($params as $value) {
            if (is_bool($value)) {
                $value = (int) $value;
            }
            $statement->bindValue($position++, $value, match (true) {
                $value === null => PDO::PARAM_NULL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            });
        }
        $statement->execute();
        return $statement;
    }

    private function connection(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->host, $this->port, $this->name);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ] + $this->tlsOptions();

        try {
            $pdo = new PDO($dsn, $this->user, $this->password, $options);
        } catch (PDOException $e) {
            throw new \RuntimeException('conectar ao MySQL: ' . $e->getMessage(), 0, $e);
        }
        // Keeps CURRENT_TIMESTAMP and TIMESTAMP reads consistent with the application's time zone.
        $offset = (new \DateTimeImmutable('now', $this->timezone))->format('P');
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '" . $offset . "'");
        return $this->pdo = $pdo;
    }

    /** @return array<int, mixed> */
    private function tlsOptions(): array
    {
        if ($this->tls === '' || $this->tls === 'preferred') {
            return [];
        }
        // The driver constants moved to Pdo\Mysql in PHP 8.4 and the old ones are deprecated.
        $const = static fn (string $name): int => (int) constant(class_exists(\Pdo\Mysql::class) ? 'Pdo\Mysql::' . $name : 'PDO::MYSQL_' . $name);
        $ca = ini_get('openssl.cafile') ?: (function_exists('openssl_get_cert_locations') ? (openssl_get_cert_locations()['default_cert_file'] ?? '') : '');
        $options = [];
        if ($ca !== '' && is_file($ca)) {
            $options[$const('ATTR_SSL_CA')] = $ca;
        } else {
            // Any SSL attribute switches the driver to a TLS connection.
            $options[$const('ATTR_SSL_CIPHER')] = 'DEFAULT';
        }
        $options[$const('ATTR_SSL_VERIFY_SERVER_CERT')] = $this->tls === 'true';
        return $options;
    }

    /** Maps driver errors to domain exceptions; unknown errors are returned untouched. */
    public static function translate(PDOException $e): Throwable
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        return match ($code) {
            self::ERR_DUPLICATE_ENTRY => new ConflictException(self::duplicateField($e->errorInfo[2] ?? $e->getMessage())),
            self::ERR_ROW_IS_REFERENCED => new InUseException(),
            self::ERR_NO_REFERENCED_ROW, self::ERR_NO_REFERENCED_ROW_2 => new NotFoundException(),
            default => $e,
        };
    }

    /** Extracts the column name from "Duplicate entry 'x' for key 'table.name_UNIQUE'". */
    public static function duplicateField(string $message): string
    {
        $marker = "for key '";
        $i = strrpos($message, $marker);
        if ($i === false) {
            return '';
        }
        $key = substr($message, $i + strlen($marker));
        $end = strpos($key, "'");
        if ($end !== false) {
            $key = substr($key, 0, $end);
        }
        $dot = strrpos($key, '.');
        if ($dot !== false) {
            $key = substr($key, $dot + 1);
        }
        if (strcasecmp($key, 'PRIMARY') === 0) {
            return '';
        }
        foreach (['_UNIQUE', '_unique'] as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return substr($key, 0, -strlen($suffix));
            }
        }
        return $key;
    }
}
