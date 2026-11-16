<?php
use EmPHyre\MysqlDb;
use PHPUnit\Framework\TestCase;

/**
 * MysqlDb without a server: no test here opens a connection (the object connects on the first query).
 */
final class MysqlDbTest extends TestCase
{
    /** @var string */
    private $log;
    /** @var string|false */
    private $oldLog;

    protected function setUp(): void
    {
        $this->log    = tempnam(sys_get_temp_dir(), 'emphyre-log');
        $this->oldLog = ini_set('error_log', $this->log);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string)$this->oldLog);
        if (is_file($this->log)) {
            unlink($this->log);
        }
    }

    // Regression: #7. The E_USER_ERROR message is what a visitor sees with display_errors on.
    public function testFailedQueryKeepsTheSqlOutOfTheShownError()
    {
        set_error_handler(function ($no, $msg) {
            if ($no == E_USER_ERROR) {
                throw new \RuntimeException($msg);   // instead of the exit that follows it
            }
            return true;   // queryError() on an object that never connected: notices about $con
        });

        $db    = new MysqlDb('host', 'db', 'user', 'pass');
        $shown = null;
        try {
            $db->queryError("SELECT secret_column FROM users WHERE email = 'a@b.c'", 'Unknown column');
        } catch (\RuntimeException $e) {
            $shown = $e->getMessage();
        } finally {
            restore_error_handler();
        }

        $this->assertNotNull($shown, 'queryError() raises E_USER_ERROR');
        $this->assertStringNotContainsString('secret_column', $shown, 'the shown error has no SQL text');
        $this->assertStringNotContainsString('Unknown column', $shown, 'the shown error has no MySQL error');
        $logged = file_get_contents($this->log);
        $this->assertStringContainsString('SELECT secret_column FROM users', $logged, 'the log has the SQL');
        $this->assertStringContainsString('Unknown column', $logged, 'the log has the MySQL error');
    }

    // Regression: #7. Was inverted: pinged only when used in the last 10 s, so a connection the server
    // had dropped was never noticed ("MySQL server has gone away" in long-running scripts).
    public function testOnlyAnIdleConnectionIsPinged()
    {
        $needsPing = new \ReflectionMethod(MysqlDb::class, 'needsPing');
        $needsPing->setAccessible(true);
        $now = 1700000000;

        $this->assertTrue($needsPing->invoke(null, $now - 60, $now), 'idle for a minute: ping');
        $this->assertFalse($needsPing->invoke(null, $now - 2, $now), 'used 2 s ago: no ping');
        $this->assertFalse($needsPing->invoke(null, $now - MysqlDb::PING_AFTER_IDLE, $now), 'exactly the limit: no ping');
    }

    // Regression: #7. count(array_unique(...) === 1) counted a bool: on 7.2 every column was
    // dropped (whole paths lost), on PHP 8 it is a TypeError inside queryError().
    public function testTrimFilesStripsOnlyTheCommonDirectories()
    {
        $db = new MysqlDb('host', 'db', 'user', 'pass');

        $this->assertSame(
            ['x.php', 'c/y.php'],
            $db->trimFiles(['/srv/app/x.php', '/srv/app/c/y.php'])
        );
        $this->assertSame(['a.php', 'b.php'], $db->trimFiles(['a.php', 'b.php']), 'nothing in common: unchanged');
        $this->assertSame(['x.php'], $db->trimFiles(['/srv/app/x.php']), 'one file keeps its name');
        $this->assertSame([], $db->trimFiles([]));
    }
}
