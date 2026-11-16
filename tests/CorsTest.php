<?php
use EmPHyre\Cors;
use PHPUnit\Framework\TestCase;

final class CorsTest extends TestCase
{
    private const APP = 'https://alphaui.earthempires.com';

    /** @var array[] headers captured by the emitter: [line, replace] */
    private $sent = [];

    protected function setUp(): void
    {
        Cors::allow([]);   // back to the default: off
        $this->sent = [];
        Cors::setEmitter(function ($line, $replace) {
            $this->sent[] = [$line, $replace];
        });
        unset($_SERVER['HTTP_ORIGIN']);
    }

    protected function tearDown(): void
    {
        Cors::setEmitter(null);
        unset($_SERVER['HTTP_ORIGIN']);
    }

    public function testOffByDefault()
    {
        $this->assertFalse(Cors::enabled(), 'CORS must be off until allow() is called');
        $this->assertSame([], Cors::headerLines(self::APP, true), 'no CORS headers at all while off');
    }

    public function testAllowedOriginOnPreflightGetsApproval()
    {
        Cors::allow([self::APP], true);
        $lines = Cors::headerLines(self::APP, true);
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, $lines, 'expected header missing');
        $this->assertContains('Access-Control-Allow-Credentials: true', $lines, 'expected header missing');
        $this->assertContains('Access-Control-Allow-Methods: GET, POST', $lines, 'expected header missing');
        $this->assertContains('Access-Control-Allow-Headers: Content-Type', $lines, 'expected header missing');
        $this->assertContains('Vary: Origin', $lines, 'expected header missing');
    }

    public function testRealResponseHasNoPreflightHeaders()
    {
        Cors::allow([self::APP], true);
        $lines = Cors::headerLines(self::APP, false);
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, $lines, 'expected header missing');
        $this->assertNotContains('Access-Control-Allow-Methods: GET, POST', $lines, 'header must not be sent here');
    }

    public function testCredentialsOnlyWhenAsked()
    {
        Cors::allow([self::APP]);
        $this->assertNotContains('Access-Control-Allow-Credentials: true', Cors::headerLines(self::APP, true), 'header must not be sent here');
    }

    /**
     * @dataProvider strangers
     */
    public function testOtherOriginsAreNeverEchoed($origin)
    {
        Cors::allow([self::APP], true);
        $this->assertSame(['Vary: Origin'], Cors::headerLines($origin, true), "origin $origin must never be echoed");
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
        Cors::allow([self::APP . '/'], true);
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, Cors::headerLines('HTTPS://AlphaUI.EarthEmpires.com', false), 'expected header missing');
    }

    /**
     * @dataProvider invalidOrigins
     */
    public function testInvalidConfiguredOriginThrows($origin)
    {
        $this->expectException(\InvalidArgumentException::class);
        Cors::allow([self::APP, $origin]);
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
        Cors::allow([self::APP], true, ['PUT'], ['X-Foo']);
        Cors::allow([self::APP], true);
        $lines = Cors::headerLines(self::APP, true);
        $this->assertContains('Access-Control-Allow-Methods: GET, POST', $lines, 'methods must go back to the default');
        $this->assertContains('Access-Control-Allow-Headers: Content-Type', $lines, 'headers must go back to the default');
    }

    public function testCustomMethodsAndHeaders()
    {
        Cors::allow([self::APP], false, ['get', 'put'], ['Content-Type', 'X-Requested-With']);
        $lines = Cors::headerLines(self::APP, true);
        $this->assertContains('Access-Control-Allow-Methods: GET, PUT', $lines, 'methods are upper-cased');
        $this->assertContains('Access-Control-Allow-Headers: Content-Type, X-Requested-With', $lines, 'custom headers listed');
    }

    /**
     * @dataProvider defaultPorts
     */
    public function testDefaultPortIsNormalised($configured, $sent)
    {
        Cors::allow([$configured], true);
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
        Cors::allow(['https://a.example:8443']);
        $this->assertNull(Cors::origin('https://a.example'), 'port 8443 is a different origin');
        $this->assertNotNull(Cors::origin('https://a.example:8443'), 'same port matches');
    }

    public function testNonStringOriginIsRejectedQuietly()
    {
        Cors::allow([self::APP]);
        $this->assertNull(Cors::origin(['x']), 'an array Origin is not an origin (and must not warn)');
    }

    public function testVaryIsTheFirstLine()
    {
        Cors::allow([self::APP]);
        $this->assertSame('Vary: Origin', Cors::headerLines('https://evil.example', false)[0], 'Vary first, so send() adds it without replacing');
        $this->assertSame('Vary: Origin', Cors::headerLines(self::APP, true)[0], 'also for allowed origins');
    }

    public function testHeadersSendsForAllowedOriginOnce()
    {
        Cors::allow([self::APP], true);
        $_SERVER['HTTP_ORIGIN'] = self::APP;
        $this->assertTrue(Cors::headers(), 'allowed origin reports true');
        $this->assertTrue(Cors::headers(), 'second call in the same request reports the same');
        $this->assertSame([
            ['Vary: Origin', false],
            ['Access-Control-Allow-Origin: ' . self::APP, true],
            ['Access-Control-Allow-Credentials: true', true],
        ], $this->sent, 'sent once; Vary added without replacing, the rest replacing');
    }

    public function testHeadersForOtherOriginSendsOnlyVary()
    {
        Cors::allow([self::APP], true);
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
        $this->assertFalse(Cors::headers(), 'other origin reports false');
        $this->assertSame([['Vary: Origin', false]], $this->sent, 'only Vary for a stranger');
    }

    public function testHeadersSendNothingWhenOff()
    {
        $_SERVER['HTTP_ORIGIN'] = self::APP;
        $this->assertFalse(Cors::headers(), 'off reports false');
        $this->assertSame([], $this->sent, 'nothing sent while off');
    }

    public function testAllowResetsPerRequestState()
    {
        Cors::allow([self::APP]);
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
        $this->assertFalse(Cors::headers(), 'stranger first');
        Cors::allow(['https://evil.example']);
        $this->assertTrue(Cors::headers(), 'after allow() the check runs again');
    }
}
