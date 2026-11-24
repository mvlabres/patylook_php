<?php

declare(strict_types=1);

namespace Patylook\Infrastructure;

use Patylook\Contracts\AdminBootstrapInterface;
use Patylook\Contracts\LoggerInterface;
use Patylook\Core\Config;
use Patylook\Exceptions\ValidationException;
use Patylook\Infrastructure\Database\SchemaVerifier;
use RuntimeException;

/**
 * The checks the Go version runs once when the process starts: verify the existing database and
 * create the first administrator when the user table is empty. PHP has no start-up moment, so they
 * run on a request when they are due (at most once every few minutes, serialized by a lock file).
 */
final class StartupTasks
{
    /** How long a successful check stays valid, in seconds. */
    private const VALID_FOR = 600;

    public function __construct(
        private readonly Config $config,
        private readonly SchemaVerifier $schema,
        private readonly AdminBootstrapInterface $bootstrap,
        private readonly LoggerInterface $log,
    ) {
    }

    /**
     * Runs the checks if the last successful run is missing, stale or for another configuration.
     *
     * @throws \Patylook\Exceptions\SchemaException when the database is not ready
     * @throws RuntimeException when the initial administrator cannot be created
     */
    public function runIfDue(): void
    {
        $dir = $this->config->storagePath('cache');
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException('não foi possível criar a pasta ' . $dir);
        }
        $marker = $dir . DIRECTORY_SEPARATOR . 'startup.ok';
        $fingerprint = sha1(implode('|', [$this->config->dbHost, $this->config->dbPort, $this->config->dbName, $this->config->bootstrapUsername]));
        if ($this->fresh($marker, $fingerprint)) {
            return;
        }

        $lock = fopen($dir . DIRECTORY_SEPARATOR . 'startup.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('não foi possível criar o arquivo de trava em ' . $dir);
        }
        try {
            flock($lock, LOCK_EX);
            if ($this->fresh($marker, $fingerprint)) { // another request did it while this one waited
                return;
            }
            $this->run();
            file_put_contents($marker, $fingerprint);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Runs the checks unconditionally (also used by the command line). */
    public function run(): void
    {
        $this->schema->verify();
        if ($this->config->bootstrapUsername !== '' && $this->config->bootstrapPassword !== '') {
            try {
                $created = $this->bootstrap->bootstrap(
                    $this->config->bootstrapUsername,
                    $this->config->bootstrapName,
                    $this->config->bootstrapPassword,
                );
            } catch (ValidationException $e) {
                throw new RuntimeException('criar administrador inicial: ' . $e->getMessage(), 0, $e);
            }
            if ($created) {
                $this->log->info('administrador inicial criado; remova BOOTSTRAP_ADMIN_* da configuração', ['username' => $this->config->bootstrapUsername]);
            }
        }
        if ($this->config->isProduction() && $this->config->dbUser === 'root' && $this->config->dbPassword === '') {
            $this->log->warning('APP_ENV=production com as credenciais LOCAIS do banco (root, sem senha): altere DB_HOST, DB_USER, DB_PASSWORD e DB_NAME');
        }
    }

    private function fresh(string $marker, string $fingerprint): bool
    {
        clearstatcache(true, $marker);
        return is_file($marker)
            && time() - (int) filemtime($marker) < self::VALID_FOR
            && @file_get_contents($marker) === $fingerprint;
    }
}
