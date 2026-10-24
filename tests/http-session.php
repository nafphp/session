<?php

declare(strict_types=1);

use Naf\Session\Core\Session;
use Naf\Session\Storage\DatabaseSessionHandler;

require dirname(__DIR__) . '/vendor/autoload.php';

$session = new Session();
if (isset($_GET['domain'])) {
    $session->configureCookieDomain('example.test');
}
if (isset($_GET['database'])) {
    $connection = new PDO('sqlite:' . session_save_path() . '/sessions.sqlite');
    $connection->exec('CREATE TABLE IF NOT EXISTS sessions (id TEXT PRIMARY KEY, payload TEXT, last_activity INTEGER, ip_address TEXT, user_agent TEXT, user_id INTEGER)');
    $session->setSessionHandler(new DatabaseSessionHandler($connection, 'sessions'));
}
$session->start();
$session->set('visits', $session->get('visits', 0) + 1);
$result = ['id' => session_id(), 'visits' => $session->get('visits')];
if (isset($_GET['clear'])) {
    $session->clear();
}
header('Content-Type: application/json');
echo json_encode($result, JSON_THROW_ON_ERROR);
