<?php

declare(strict_types=1);

use Naf\Session\Migrations\SessionTableMigration;
use Naf\Session\Storage\DatabaseSessionHandler;

// An explicit disposable host supplies real PDO and the optional database plugin.
if (getenv('APP_ENV') !== 'test' || getenv('DB_DATABASE') !== 'nafinity_test') {
    throw new RuntimeException('Use the disposable nafinity_test integration host.');
}
$bootstrap = getenv('NAF_SESSION_TEST_BOOTSTRAP');
if (!$bootstrap || !is_file($bootstrap)) {
    throw new RuntimeException('Set NAF_SESSION_TEST_BOOTSTRAP to the test host bootstrap.');
}
require $bootstrap;
$pdo       = \Naf\app()->container()->get(PDO::class);
$migration = new SessionTableMigration();
$migration->up($pdo);
$handler = new DatabaseSessionHandler($pdo, 'sessions');
$id      = 'naf-session-contract-' . bin2hex(random_bytes(12));

try {
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        $pdo->beginTransaction();

        try {
            $migration->up($pdo);
            $migration->up($pdo);
            if ((int) $pdo->query('SELECT 1')->fetchColumn() !== 1) {
                throw new RuntimeException('Repeated session migration broke the transaction.');
            }
        } finally {
            $pdo->rollBack();
        }
    }
    if (!$handler->write($id, 'first') || !$handler->write($id, 'updated')) {
        throw new RuntimeException('Session upsert failed.');
    }
    if ($handler->read($id) !== 'updated') {
        throw new RuntimeException('Session update was lost.');
    }
    if (!$handler->destroy($id) || $handler->read($id) !== '') {
        throw new RuntimeException('Session destroy failed.');
    }
    echo 'Database session contract passed: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
} finally {
    $handler->destroy($id);
}
