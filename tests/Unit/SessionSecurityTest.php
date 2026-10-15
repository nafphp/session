<?php

declare(strict_types=1);

namespace Tests\Unit;

use Naf\Session\Core\Session;
use Naf\Session\Storage\DatabaseSessionHandler;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SessionSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');
        $_SESSION = [];
        $_COOKIE  = [];
    }

    public static function storageHandlers(): iterable
    {
        yield 'files' => [false];
        yield 'database' => [true];
    }

    #[DataProvider('storageHandlers')]
    public function testUnknownIdsAreRejectedAndExistingSessionsCanResume(bool $database): void
    {
        $path = sys_get_temp_dir() . '/naf-session-security-' . bin2hex(random_bytes(8));
        mkdir($path, 0700);
        session_save_path($path);
        $session = new Session();
        if ($database) {
            $connection = new PDO('sqlite::memory:');
            $connection->exec('CREATE TABLE sessions (id TEXT PRIMARY KEY, payload TEXT, last_activity INTEGER, ip_address TEXT, user_agent TEXT, user_id INTEGER)');
            $session->setSessionHandler(new DatabaseSessionHandler($connection, 'sessions'));
        }

        try {
            session_id('attackerchosen123');
            $session->start();
            self::assertNotSame('attackerchosen123', session_id());
            self::assertSame('1', ini_get('session.use_strict_mode'));
            self::assertSame('1', ini_get('session.use_only_cookies'));
            self::assertSame('0', ini_get('session.use_trans_sid'));
            $id = session_id();
            $session->set('cart', ['item']);
            session_write_close();
            $_SESSION = [];
            session_id($id);
            $session->start();
            self::assertSame($id, session_id());
            self::assertSame(['item'], $session->get('cart'));
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            foreach (glob($path . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($path);
        }
    }

    public function testCookieSharingRequiresAnExplicitDomain(): void
    {
        $_SERVER['HTTP_HOST'] = 'example.test:8080';
        $session              = new Session();
        $session->start();
        self::assertSame('', session_get_cookie_params()['domain']);
        session_destroy();

        $session->configureCookieDomain('example.test');
        $session->start();
        self::assertSame('example.test', session_get_cookie_params()['domain']);
        $session->clear();
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame('example.test', session_get_cookie_params()['domain']);
    }

    public function testDatabaseStrictValidationRejectsExpiredIdsAndRefreshesUnchangedSessions(): void
    {
        $connection = new PDO('sqlite::memory:');
        $connection->exec('CREATE TABLE sessions (id TEXT PRIMARY KEY, payload TEXT, last_activity INTEGER, ip_address TEXT, user_agent TEXT, user_id INTEGER)');
        $handler = new DatabaseSessionHandler($connection, 'sessions');
        self::assertFalse($handler->validateId('missing'));
        self::assertTrue($handler->write('known', 'cart|s:4:"item";'));
        self::assertTrue($handler->validateId('known'));
        $connection->exec('UPDATE sessions SET last_activity = 1');
        self::assertFalse($handler->validateId('known'));
        self::assertTrue($handler->updateTimestamp('known', 'cart|s:4:"item";'));
        self::assertTrue($handler->validateId('known'));
        self::assertSame('cart|s:4:"item";', $handler->read('known'));
    }
}
