<?php

declare(strict_types=1);

use Naf\Database\Core\Database;
use Naf\Database\Support\MigrationRegistry;
use Naf\Session\Core\Session;
use Naf\Session\Storage\DatabaseSessionHandler;

use function Naf\app;
use function Naf\config;
use function Naf\log;
use function Naf\Session\session;

$container = app()->container();

$container->set(Session::class, function () use ($container) {
    $session = new Session();

    $session->configureProxyTrust(
        config('session:trust_proxy_headers', false),
        config('session:trusted_proxies', []),
    );

    $storage = config('session:storage', 'default');

    if (
        $storage === 'database'
    ) {
        if (!app()->hasPlugin('naf/database')) {
            log()->warning('You\'ve configured to use the database as the session storage but the plugin naf/database is missing.');

            return $session;
        }

        $database   = $container->get(Database::class);
        $connection = $database->getConnection();
        $table      = config('session:database_table', 'sessions');

        $session->setSessionHandler(new DatabaseSessionHandler(
            $connection,
            $table,
        ));
    }

    return $session;
});

if (app()->hasPlugin('naf/database')) {
    MigrationRegistry::addPath(__DIR__ . '/src/Migrations');
}

if (PHP_SAPI !== 'cli') {
    session()->start();
}
