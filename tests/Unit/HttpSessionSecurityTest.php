<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HttpSessionSecurityTest extends TestCase
{
    private static mixed $server;
    private static string $url;
    private static string $directory;

    public static function setUpBeforeClass(): void
    {
        self::$directory = sys_get_temp_dir() . '/naf-session-http-' . bin2hex(random_bytes(8));
        mkdir(self::$directory, 0700);
        $socket  = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$url    = 'http://' . $address;
        self::$server = proc_open([
            PHP_BINARY, '-d', 'session.save_path=' . self::$directory,
            '-S', $address, dirname(__DIR__) . '/http-session.php',
        ], [0 => ['pipe', 'r'], 1 => ['file', self::$directory . '/http.log', 'a'], 2 => ['file', self::$directory . '/http.log', 'a']], $pipes);
        fclose($pipes[0]);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $probe = @stream_socket_client('tcp://' . $address, $error, $message, 0.1);
            if (is_resource($probe)) {
                fclose($probe);

                return;
            }
            usleep(10000);
        }
        throw new RuntimeException('Session fixture server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$server);
        proc_close(self::$server);
        foreach (glob(self::$directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir(self::$directory);
    }

    public static function cookieModes(): iterable
    {
        yield 'files, host-only' => ['/', ''];
        yield 'database, host-only' => ['/?database=1', ''];
        yield 'explicit domain' => ['/?domain=1', 'domain=example.test'];
    }

    #[DataProvider('cookieModes')]
    public function testRealCookieLifecycleRejectsUnknownIdsAndExpiresTheSameCookie(string $path, string $domain): void
    {
        [$first, $headers] = $this->request($path, 'attackerchosen123');
        self::assertNotSame('attackerchosen123', $first['id']);
        self::assertSame(1, $first['visits']);
        self::assertStringContainsString('Set-Cookie: PHPSESSID=', $headers);
        self::assertStringContainsString('HttpOnly', $headers);
        self::assertStringContainsString('SameSite=Lax', $headers);
        if ($domain === '') {
            self::assertStringNotContainsString('domain=', strtolower($headers));
        } else {
            self::assertStringContainsString($domain, $headers);
        }

        [$next] = $this->request($path, $first['id']);
        self::assertSame($first['id'], $next['id']);
        self::assertSame(2, $next['visits']);
        [, $deletedHeaders] = $this->request($path . (str_contains($path, '?') ? '&' : '?') . 'clear=1', $first['id']);
        self::assertStringContainsString('Max-Age=0', $deletedHeaders);
        self::assertStringContainsString('path=/', $deletedHeaders);
        if ($domain === '') {
            self::assertStringNotContainsString('domain=', strtolower($deletedHeaders));
        } else {
            self::assertStringContainsString($domain, $deletedHeaders);
        }
        [$afterClear] = $this->request($path, $first['id']);
        self::assertNotSame($first['id'], $afterClear['id']);
        self::assertSame(1, $afterClear['visits']);
    }

    /** @return array{array{id:string,visits:int},string} */
    private function request(string $path, string $id): array
    {
        $connection = stream_socket_client(str_replace('http://', 'tcp://', self::$url));
        fwrite($connection, "GET " . $path . " HTTP/1.1\r\nHost: example.test\r\nCookie: PHPSESSID=" . $id . "\r\nConnection: close\r\n\r\n");
        $raw = stream_get_contents($connection);
        fclose($connection);
        self::assertIsString($raw);
        self::assertStringContainsString('HTTP/1.1 200 OK', $raw);
        $size = strpos($raw, "\r\n\r\n");
        self::assertNotFalse($size);

        return [json_decode(substr($raw, $size + 4), true, flags: JSON_THROW_ON_ERROR), substr($raw, 0, $size)];
    }
}
