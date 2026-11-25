<?php

declare(strict_types=1);

/**
 * Bootstraps the application: autoloading, configuration, PHP settings and the service container.
 * Returns the container; public/index.php (web) and bin/patylook (command line) both start here.
 */

use Patylook\Core\Config;
use Patylook\Core\Container;

if (PHP_VERSION_ID < 80200) {
    // The project targets PHP 8.2; older versions are refused with a clear message instead of a parse error.
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('VL Store requer PHP 8.2 ou superior (versão atual: ' . PHP_VERSION . ').');
}

$missingExtensions = array_values(array_filter(
    ['pdo_mysql', 'mbstring', 'gd', 'ctype', 'json'],
    static fn (string $name): bool => !extension_loaded($name),
));
if ($missingExtensions !== []) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    exit('VL Store precisa das extensões PHP: ' . implode(', ', $missingExtensions) . '. Ative-as no painel da hospedagem (php.ini).' . PHP_EOL);
}

const BASE_PATH = __DIR__;

// A minimal PSR-4 autoloader (Patylook\ => app/), so no Composer install is needed on the server.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Patylook\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
require BASE_PATH . '/app/Support/helpers.php';

$config = Config::load(BASE_PATH);
date_default_timezone_set($config->timezone->getName());
mb_internal_encoding('UTF-8');
ini_set('default_charset', 'UTF-8');
// Errors are logged by the application; never shown to visitors.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$container = new Container();
(require BASE_PATH . '/config/container.php')($container, $config);
return $container;
