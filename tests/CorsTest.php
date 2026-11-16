<?php
use EmPHyre\Cors;
use PHPUnit\Framework\TestCase;

final class CorsTest extends TestCase
{
    private const APP   = 'https://alphaui.earthempires.com';
    private const PATHS = ['/rest'];

    /** @var array[] headers captured by the emitter: [line, replace] */
    private $sent = [];

    protected function setUp(): void
    {
        Cors::allow([]);   // back to the default: off
        $this->sent = [];
        Cors::setEmitterForTests(function ($line, $replace) {
            $this->sent[] = [$line, $replace];
        }, function () {
            return false;   // PHPUnit has printed output; the code under test must not see that
        });
        unset($_SERVER['HTTP_ORIGIN']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        Cors::setEmitterForTests(null);
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);
    }

    public function testOffByDefault()
    {
        $this->assertFalse(Cors::enabled(), 'CORS must be off until allow() is called');
        $this->assertSame([], Cors::headerLines(self::APP, true), 'no CORS headers at all while off');
    }

    public function testAllowedOriginOnPreflightGetsApproval()
    {
        Cors::allow([self::APP], self::PATHS, true);
        $lines = Cors::headerLines(self::APP, true);
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, $lines, 'expected header missing');
        $this->assertContains('Access-Control-Allow-Credentials: true', $lines, 'expected header missing');
        $this->assertContains('Access-Control-Allow-Methods: GET, POST', $lines, 'expected header missing');
        $this->assertContains('Access-Control-Allow-Headers: Content-Type', $lines, 'expected header missing');
        $this->assertContains('Vary: Origin, Access-Control-Request-Method', $lines, 'preflight varies on the requested method too');
    }

    public function testRealResponseHasNoPreflightHeaders()
    {
        Cors::allow([self::APP], self::PATHS, true);
        $lines = Cors::headerLines(self::APP, false);
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, $lines, 'expected header missing');
        $this->assertNotContains('Access-Control-Allow-Methods: GET, POST', $lines, 'header must not be sent here');
    }

    public function testCredentialsOnlyWhenAsked()
    {
        Cors::allow([self::APP], self::PATHS);
        $this->assertNotContains('Access-Control-Allow-Credentials: true', Cors::headerLines(self::APP, true), 'header must not be sent here');
    }

    /**
     * @dataProvider strangers
     */
    public function testOtherOriginsAreNeverEchoed($origin)
    {
        Cors::allow([self::APP], self::PATHS, true);
        $this->assertSame(['Vary: Origin, Access-Control-Request-Method'], Cors::headerLines($origin, true), "origin $origin must never be echoed");
    }

    public function strangers()
    {
        return [
            'other site'            => ['https://evil.example'],
            'unregistered .dev'     => ['https://client.earthempires.dev:3000'],
            'look-alike suffix'     => ['https://alphaui.earthempires.com.evil.example'],
            'http instead of https' => ['http://alphaui.earthempires.com'],
            'other port'            => ['https://alphaui.earthempires.com:8443'],
            'no origin'             => [''],
            'null origin'           => ['null'],
        ];
    }

    public function testCaseAndTrailingSlashAreNormalised()
    {
        Cors::allow([self::APP . '/'], self::PATHS, true);
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, Cors::headerLines('HTTPS://AlphaUI.EarthEmpires.com', false), 'expected header missing');
    }

    /**
     * @dataProvider invalidOrigins
     */
    public function testInvalidConfiguredOriginThrows($origin)
    {
        $this->expectException(\InvalidArgumentException::class);
        Cors::allow([self::APP, $origin], self::PATHS);
    }

    public function invalidOrigins()
    {
        return [
            'path'           => ['https://example.com/some/path'],
            'wildcard'       => ['*'],
            'no scheme'      => ['example.com'],
            'bad host'       => ['https://-'],
            'empty labels'   => ['https://..'],
            'not a string'   => [['https://example.com']],
            'query'          => ['https://example.com?x=1'],
            'port 0'         => ['https://example.com:0'],
            'port too big'   => ['https://example.com:99999'],
        ];
    }

    public function testLaterAllowResetsMethodsAndHeaders()
    {
        Cors::allow([self::APP], self::PATHS, true, ['PUT'], ['X-Foo']);
        Cors::allow([self::APP], self::PATHS, true);
        $lines = Cors::headerLines(self::APP, true);
        $this->assertContains('Access-Control-Allow-Methods: GET, POST', $lines, 'methods must go back to the default');
        $this->assertContains('Access-Control-Allow-Headers: Content-Type', $lines, 'headers must go back to the default');
    }

    public function testCustomMethodsAndHeaders()
    {
        Cors::allow([self::APP], self::PATHS, false, ['get', 'put'], ['Content-Type', 'X-Requested-With']);
        $lines = Cors::headerLines(self::APP, true);
        $this->assertContains('Access-Control-Allow-Methods: GET, PUT', $lines, 'methods are upper-cased');
        $this->assertContains('Access-Control-Allow-Headers: Content-Type, X-Requested-With', $lines, 'custom headers listed');
    }

    /**
     * @dataProvider defaultPorts
     */
    public function testDefaultPortIsNormalised($configured, $sent)
    {
        Cors::allow([$configured], self::PATHS, true);
        $this->assertNotNull(Cors::origin($sent), "configured $configured should match browser Origin $sent");
    }

    public function defaultPorts()
    {
        return [
            'https :443 configured' => ['https://a.example:443', 'https://a.example'],
            'http :80 configured'   => ['http://a.example:80', 'http://a.example'],
            'https :443 sent'       => ['https://a.example', 'https://a.example:443'],
            'leading zero port'     => ['https://a.example:0443', 'https://a.example'],
        ];
    }

    public function testNonDefaultPortIsKept()
    {
        Cors::allow(['https://a.example:8443'], self::PATHS);
        $this->assertNull(Cors::origin('https://a.example'), 'port 8443 is a different origin');
        $this->assertNotNull(Cors::origin('https://a.example:8443'), 'same port matches');
    }

    public function testNonStringOriginIsRejectedQuietly()
    {
        Cors::allow([self::APP], self::PATHS);
        $this->assertNull(Cors::origin(['x']), 'an array Origin is not an origin (and must not warn)');
    }

    public function testVaryIsTheFirstLine()
    {
        Cors::allow([self::APP], self::PATHS);
        $this->assertSame('Vary: Origin', Cors::headerLines('https://evil.example', false)[0], 'Vary first, so send() adds it without replacing');
        $this->assertSame('Vary: Origin, Access-Control-Request-Method', Cors::headerLines(self::APP, true)[0], 'preflights vary on the requested method too');
    }

    public function testHeadersSendsForAllowedOriginOnce()
    {
        Cors::allow([self::APP], self::PATHS, true);
        $_SERVER['HTTP_ORIGIN'] = self::APP;
        $this->assertTrue(Cors::headers('/rest'), 'allowed origin reports true');
        $this->assertTrue(Cors::headers('/rest'), 'second call in the same request reports the same');
        $this->assertSame([
            ['Vary: Origin', false],
            ['Access-Control-Allow-Origin: ' . self::APP, true],
            ['Access-Control-Allow-Credentials: true', true],
        ], $this->sent, 'sent once; Vary added without replacing, the rest replacing');
    }

    public function testHeadersForOtherOriginSendsOnlyVary()
    {
        Cors::allow([self::APP], self::PATHS, true);
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
        $this->assertFalse(Cors::headers('/rest'), 'other origin reports false');
        $this->assertSame([['Vary: Origin', false]], $this->sent, 'only Vary for a stranger');
    }

    public function testHeadersSendNothingWhenOff()
    {
        $_SERVER['HTTP_ORIGIN'] = self::APP;
        $this->assertFalse(Cors::headers('/rest'), 'off reports false');
        $this->assertSame([], $this->sent, 'nothing sent while off');
    }

    public function testAllowResetsPerRequestState()
    {
        Cors::allow([self::APP], self::PATHS);
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
        $this->assertFalse(Cors::headers('/rest'), 'stranger first');
        Cors::allow(['https://evil.example'], self::PATHS);
        $this->assertTrue(Cors::headers('/rest'), 'after allow() the check runs again');
    }

    /**
     * @dataProvider scopes
     */
    public function testScope($uri, $inScope)
    {
        Cors::allow([self::APP], ['/rest', '/api/v1/'], true);
        $this->assertSame($inScope, Cors::inScope($uri), "scope of " . json_encode($uri));
    }

    public function scopes()
    {
        return [
            'exact'            => ['/rest', true],
            'below'            => ['/rest/x', true],
            'query not stripped' => ['/rest?x=/account', false],   // callers pass the routed path (no query)
            'trailing slash cfg' => ['/api/v1/things', true],
            'prefix lookalike' => ['/restore', false],
            'other page'       => ['/account/settings', false],
            'root'             => ['/', false],
            'not a string'     => [['/rest'], false],
            'double slashes'   => ['//rest/x', true],
            'inner empty seg'  => ['/rest//x', true],
            'trailing slash'   => ['/rest/', true],
        ];
    }

    public function testOutOfScopeGetsNoHeadersAtAll()
    {
        Cors::allow([self::APP], self::PATHS, true);
        $_SERVER['HTTP_ORIGIN'] = self::APP;
        $this->assertFalse(Cors::headers('/account/settings'), 'allowed origin on an out-of-scope path is not allowed');
        $this->assertSame([], $this->sent, 'not even Vary outside the scope');
    }

    public function testOriginsWithoutPathsThrow()
    {
        $this->expectException(\InvalidArgumentException::class);
        Cors::allow([self::APP]);
    }

    /**
     * @dataProvider invalidPaths
     */
    public function testInvalidPathThrows($path)
    {
        $this->expectException(\InvalidArgumentException::class);
        Cors::allow([self::APP], [$path]);
    }

    public function invalidPaths()
    {
        return [
            'relative'     => ['rest'],
            'url'          => ['https://x/rest'],
            'not a string' => [5],
            'root'         => ['/'],
            'only slashes' => ['//'],
            'dot dot'      => ['/rest/..'],
            'dot'          => ['/./x'],
            'query'        => ['/rest?debug'],
            'empty segment' => ['/rest//x'],
            'dot segment start' => ['/.well-known'],
        ];
    }

    public function testHeadersAlreadySentWarnsOncePerRequest()
    {
        Cors::allow([self::APP], self::PATHS, true);
        Cors::setEmitterForTests(function ($line, $replace) {
            $this->sent[] = [$line, $replace];
        }, function () {
            return true;   // output already started
        });
        $_SERVER['HTTP_ORIGIN'] = self::APP;
        $warnings = 0;
        set_error_handler(function ($no) use (&$warnings) {
            $warnings += ($no === E_USER_WARNING);
            return true;
        });
        $first  = Cors::headers('/rest');
        $second = Cors::headers('/rest');
        restore_error_handler();
        $this->assertFalse($first, 'nothing could be sent');
        $this->assertFalse($second, 'cached for the request');
        $this->assertSame(1, $warnings, 'warned exactly once');
        $this->assertSame([], $this->sent, 'no header attempted');
    }

    public function testLongZeroPaddedPortIsTheSamePort()
    {
        Cors::allow(['https://a.example:000443'], self::PATHS);
        $this->assertNotNull(Cors::origin('https://a.example'), ':000443 is :443, the default');
    }

    public function testOnlyConfiguredMethodsGetHeaders()
    {
        Cors::allow([self::APP], self::PATHS, true);
        $_SERVER['HTTP_ORIGIN']    = self::APP;
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $this->assertFalse(Cors::headers('/rest'), 'DELETE is not in the default methods');
        $this->assertSame([['Vary: Origin', false]], $this->sent, 'Vary but no Allow-Origin for a method that is not allowed');
    }

    public function testQueryOnPathIsIgnored()
    {
        Cors::allow([self::APP], self::PATHS, true);
        $this->assertFalse(Cors::inScope('/account?next=/rest'), 'a /rest in the query is not the path');
    }

    /**
     * @dataProvider routedPaths
     */
    public function testRoutedPathMatchesRouterRules($url, $routed)
    {
        $this->assertSame($routed, Cors::routedPath($url), "routed path of $url");
    }

    public function routedPaths()
    {
        return [
            'plain'          => ['/rest/x', '/rest/x'],
            'trailing'       => ['/rest/x/', '/rest/x'],
            'leading double' => ['//rest/x', '/rest/x'],
            'empty segment'  => ['/rest//public', '/rest'],   // urlRoute() stops at the empty segment
            'root'           => ['/', '/'],
        ];
    }

    public function testEmptySegmentDoesNotReachDeeperScope()
    {
        Cors::allow([self::APP], ['/rest/public'], true);
        $this->assertFalse(Cors::inScope('/rest//public'), 'routes as /rest, which is not in scope');
        $this->assertTrue(Cors::inScope('/rest/public/x'), 'the real path is');
    }

    public function testMethodFromTheRouterWins()
    {
        Cors::allow([self::APP], self::PATHS, true);
        $_SERVER['HTTP_ORIGIN']    = self::APP;
        $_SERVER['REQUEST_METHOD'] = 'HEAD';   // the Router routes it as GET and passes that
        $this->assertTrue(Cors::headers('/rest', 'GET'), 'routed as GET: allowed');
    }

    public function testDefaultsReadTheRequest()
    {
        Cors::allow([self::APP], self::PATHS, true);
        $_SERVER['HTTP_ORIGIN'] = self::APP;
        $_SERVER['REQUEST_URI'] = '/rest/x?y=1';
        $this->assertTrue(Cors::headers(), 'no-argument form uses REQUEST_URI and REQUEST_METHOD');
    }

    public function testPreflightChecksTheRequestedMethod()
    {
        Cors::allow([self::APP], self::PATHS, true);
        $_SERVER['HTTP_ORIGIN'] = self::APP;
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'DELETE';
        $this->assertFalse(Cors::preflightHeaders('/rest'), 'DELETE is not an allowed method');
        $this->assertSame([['Vary: Origin, Access-Control-Request-Method', false]], $this->sent, 'only Vary for a disallowed requested method');
        $this->sent = [];
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';
        $this->assertTrue(Cors::preflightHeaders('/rest'), 'POST is allowed');
        $this->assertContains(['Access-Control-Allow-Methods: GET, POST', true], $this->sent, 'approval sent');
    }

    public function testNoWarningWhenNoCorsHeadersWereDue()
    {
        Cors::allow([self::APP], self::PATHS, true);
        Cors::setEmitterForTests(function ($line, $replace) {
            $this->sent[] = [$line, $replace];
        }, function () {
            return true;
        });
        $_SERVER['HTTP_ORIGIN']    = self::APP;
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $warnings = 0;
        set_error_handler(function ($no) use (&$warnings) {
            $warnings += ($no === E_USER_WARNING);
            return true;
        });
        Cors::headers('/rest');
        restore_error_handler();
        $this->assertSame(0, $warnings, 'a disallowed method would only have got Vary: nothing failed that matters');
    }
}
