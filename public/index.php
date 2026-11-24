<?php

declare(strict_types=1);

/**
 * Front controller: every request that is not a static file lands here (see .htaccess), and the
 * router decides which controller answers it.
 */

use Patylook\Core\Application;

try {
    $container = require dirname(__DIR__) . '/bootstrap.php';
    $container->get(Application::class)->run();
} catch (Throwable $e) {
    // Failed before the application could answer (bad configuration, missing extension...).
    error_log('patylook: ' . $e::class . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Erro interno. Verifique a configuração (.env) e o log do servidor.';
}
