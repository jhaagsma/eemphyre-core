<?php
use PHPUnit\Framework\TestCase;

final class RouteCacherTest extends TestCase
{
    /**
     * ROUTER_NAME as a fresh PHP process defines it for a request with this Host header (it is a
     * constant, set once when RouteCacher loads, so each case needs its own process).
     */
    private static function routerNameFor($host)
    {
        $code = 'require ' . var_export(dirname(__DIR__) . '/Autoloader.php', true) . ';'
            . '\EmPHyre\Autoloader::register(); class_exists("\EmPHyre\RouteCacher"); echo ROUTER_NAME;';
        $cmd  = 'HTTP_HOST=' . escapeshellarg($host) . ' ' . escapeshellarg(PHP_BINARY)
            . ' -n -r ' . escapeshellarg($code);

        return shell_exec($cmd);
    }

    // Regression: #7. The key held the raw Host header: every made-up host built and cached
    // another copy of the router (2-day TTL), pushing other entries out of APCu.
    public function testRouterCacheKeyIgnoresTheHostHeader()
    {
        $a = self::routerNameFor('www.example.com');
        $b = self::routerNameFor('attacker-' . mt_rand() . '.example');

        $this->assertStringStartsWith('R-', (string)$a, 'the child process printed ROUTER_NAME');
        $this->assertSame($a, $b);
        $this->assertStringNotContainsString('example', $a, 'no part of the Host header in the key');
    }
}
