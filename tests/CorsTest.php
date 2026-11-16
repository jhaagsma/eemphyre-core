<?php
use EmPHyre\Cors;
use PHPUnit\Framework\TestCase;

final class CorsTest extends TestCase
{
    private const APP = 'https://alphaui.earthempires.com';

    protected function setUp(): void
    {
        Cors::allow([]);   // back to the default: off
    }

    public function testOffByDefault()
    {
        $this->assertFalse(Cors::enabled());
        $this->assertSame([], Cors::headerLines(self::APP, true));
    }

    public function testAllowedOriginOnPreflightGetsApproval()
    {
        Cors::allow([self::APP], true);
        $lines = Cors::headerLines(self::APP, true);
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, $lines);
        $this->assertContains('Access-Control-Allow-Credentials: true', $lines);
        $this->assertContains('Access-Control-Allow-Methods: GET, POST', $lines);
        $this->assertContains('Access-Control-Allow-Headers: Content-Type', $lines);
        $this->assertContains('Vary: Origin', $lines);
    }

    public function testRealResponseHasNoPreflightHeaders()
    {
        Cors::allow([self::APP], true);
        $lines = Cors::headerLines(self::APP, false);
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, $lines);
        $this->assertNotContains('Access-Control-Allow-Methods: GET, POST', $lines);
    }

    public function testCredentialsOnlyWhenAsked()
    {
        Cors::allow([self::APP]);
        $this->assertNotContains('Access-Control-Allow-Credentials: true', Cors::headerLines(self::APP, true));
    }

    /**
     * @dataProvider strangers
     */
    public function testOtherOriginsAreNeverEchoed($origin)
    {
        Cors::allow([self::APP], true);
        $this->assertSame(['Vary: Origin'], Cors::headerLines($origin, true));
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
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, Cors::headerLines('HTTPS://AlphaUI.EarthEmpires.com', false));
    }

    public function testInvalidConfiguredOriginIsIgnored()
    {
        $notices = [];
        set_error_handler(function ($no, $msg) use (&$notices) {
            $notices[] = $msg;
            return true;
        });
        Cors::allow(['https://example.com/some/path', '*', self::APP]);
        restore_error_handler();
        $this->assertCount(2, $notices);
        $this->assertSame(['Vary: Origin'], Cors::headerLines('https://example.com', false));
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, Cors::headerLines(self::APP, false));
    }
}
