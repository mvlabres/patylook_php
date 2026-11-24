<?php

declare(strict_types=1);

/**
 * Runs every tests/*Test.php (no PHPUnit needed):
 *
 *     php tests/run.php [filter]
 *
 * Integration tests that need MySQL use the separate database TEST_DB_NAME (default
 * patylook_php_test) and are skipped when it is not reachable. They never touch DB_NAME.
 */

use Patylook\Tests\TestCase;

define('TESTING', true);
$base = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($base): void {
    foreach (['Patylook\\Tests\\' => $base . '/tests/', 'Patylook\\' => $base . '/app/'] as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
});
require $base . '/app/Support/helpers.php';
require __DIR__ . '/Fakes/Fakes.php';
date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);
set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
    if ((error_reporting() & $no) === 0) {
        return false; // silenced with @
    }
    throw new ErrorException($msg, 0, $no, $file, $line);
});

$filter = $argv[1] ?? '';
$passed = 0;
$failed = [];
$skipped = [];
$assertions = 0;
foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    $class = 'Patylook\\Tests\\' . basename($file, '.php');
    if ($filter !== '' && !str_contains($class, $filter)) {
        continue;
    }
    $test = new $class();
    if (!$test instanceof TestCase) {
        continue;
    }
    $result = $test->run();
    $passed += $result['passed'];
    $failed = [...$failed, ...$result['failed']];
    $skipped = [...$skipped, ...$result['skipped']];
    $assertions += $test->assertions;
}

foreach ($skipped as $s) {
    echo "SKIP  $s\n";
}
foreach ($failed as $f) {
    echo "FALHA $f\n";
}
printf("\n%d testes passaram, %d falharam, %d ignorados (%d asserções)\n", $passed, count($failed), count($skipped), $assertions);
exit($failed === [] ? 0 : 1);
