<?php
use EmPHyre\Script;
use PHPUnit\Framework\TestCase;

/**
 * Script::post() / get() against a local `php -S` server (tests/fixtures/script-http-server.php).
 * URLs are given without "http://": urlfix() mangles URLs that already have it (see the PR).
 */
final class ScriptTest extends TestCase
{
    /** @var resource|null the php -S process */
    private static $server;
    /** @var string host:port of the server */
    private static $base;

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('curl_init')) {
            self::markTestSkipped('needs the curl extension');
        }

        // a free port: bind to port 0, read what the kernel chose, release it
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name  = stream_socket_get_name($probe, false);
        fclose($probe);
        self::$base = $name;

        // exec: proc_terminate() then stops php itself, not a shell around it.
        // Workers (PHP 7.4+) so the /slow request does not hold up the next test.
        $cmd = 'PHP_CLI_SERVER_WORKERS=4 exec '.escapeshellarg(PHP_BINARY).' -S '.escapeshellarg($name)
            .' '.escapeshellarg(__DIR__.'/fixtures/script-http-server.php');
        self::$server = proc_open($cmd, [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);

        // wait for it to listen; the refused connects before that warn, which is expected here
        set_error_handler(function () {
            return true;
        });
        $up = false;
        for ($i = 0; $i < 100 && !$up; $i++) {
            $sock = stream_socket_client('tcp://'.$name, $errno, $errstr, 0.1);
            if ($sock) {
                fclose($sock);
                $up = true;
            } else {
                usleep(20000);
            }
        }
        restore_error_handler();
        if (!$up) {
            self::fail('php -S did not start on '.$name);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    /**
     * The RuntimeException $call throws, or null. Not try/fail()/catch: PHPUnit's own
     * failure exceptions are RuntimeExceptions too, so catch would swallow the fail().
     */
    private static function thrownBy(callable $call)
    {
        try {
            $call();
        } catch (\RuntimeException $e) {
            return $e;
        }
        return null;
    }

    protected function setUp(): void
    {
        Script::setTimeouts();   // the defaults; tests that change them must not leak
    }

    public function testGetReturnsTheBodyOnSuccess()
    {
        $this->assertSame('GET {"a":"1"}', Script::get(self::$base.'/echo', ['a' => 1]));
    }

    public function testPostReturnsTheBodyOnSuccess()
    {
        $this->assertSame('POST {"a":"1"}', Script::post(self::$base.'/echo', ['a' => 1]));
    }

    public function testEmptyBodyOnSuccessIsNotAnError()
    {
        $this->assertSame('', Script::get(self::$base.'/status/204', []));
    }

    // Regression: #11 (a 500 page came back as if it were the answer)
    public function testNon2xxStatusThrowsWithTheStatusAsCode()
    {
        $e = self::thrownBy(function () {
            Script::post(self::$base.'/status/500', ['a' => 1]);
        });
        $this->assertNotNull($e, 'a 500 throws');
        $this->assertSame(500, $e->getCode());
        $this->assertStringContainsString('HTTP 500', $e->getMessage());
    }

    // Regression: #11
    public function testClientErrorStatusThrows()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(404);
        Script::get(self::$base.'/status/404', []);
    }

    // Regression: #11 (curl_exec() returned false and the caller got false as the "output")
    public function testConnectionFailureThrows()
    {
        // the server's port with nothing listening: take a second free port
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name  = stream_socket_get_name($probe, false);
        fclose($probe);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(0);
        Script::get($name.'/echo', []);
    }

    // Regression: #11 (no timeout: a slow server hung the caller)
    public function testSlowServerTimesOut()
    {
        Script::setTimeouts(1, 1);
        $start = microtime(true);
        $e     = self::thrownBy(function () {
            Script::get(self::$base.'/slow', []);
        });
        $this->assertNotNull($e, 'a request slower than the timeout throws');
        $this->assertLessThan(1.9, microtime(true) - $start, 'gives up after the 1 s timeout');
    }

    public function testErrorMessageLeavesOutTheQueryString()
    {
        $e = self::thrownBy(function () {
            Script::get(self::$base.'/status/403', ['apicode' => 'secret123']);
        });
        $this->assertNotNull($e, 'a 403 throws');
        $this->assertStringNotContainsString('secret123', $e->getMessage(), 'GET data can hold keys: not in the message');
        $this->assertStringContainsString('/status/403', $e->getMessage());
    }
}
