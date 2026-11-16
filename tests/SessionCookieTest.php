<?php
use EmPHyre\Session;
use PHPUnit\Framework\TestCase;

/** The session cookie's Set-Cookie line (#6). */
final class SessionCookieTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    public function testLoginCookieIsHostOnlyHttpOnlyLax()
    {
        $line = Session::cookieHeader('42:abc', 0, false);
        $this->assertSame('Set-Cookie: ' . COOKIE_NAME . '=42%3Aabc; Path=/; HttpOnly; SameSite=Lax', $line,
            'no Domain (host only), no Expires (session cookie), not Secure when asked not to be');
    }

    public function testSecureFollowsHttpsAndProxyHeader()
    {
        $this->assertStringNotContainsString('; Secure', Session::cookieHeader('v', 0), 'plain HTTP: not Secure');
        $_SERVER['HTTPS'] = 'on';
        $this->assertStringContainsString('; Secure', Session::cookieHeader('v', 0), 'HTTPS: Secure');
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertStringContainsString('; Secure', Session::cookieHeader('v', 0), 'HTTPS at the proxy: Secure');
        $_SERVER['HTTPS'] = 'off';
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        $this->assertStringNotContainsString('; Secure', Session::cookieHeader('v', 0), 'HTTPS=off (IIS style): not Secure');
    }

    public function testClearingCookieExpiresWithSamePathAndDomain()
    {
        $line = Session::cookieHeader('', 1, true, '.example.com');
        $this->assertStringContainsString('=; Expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0; Path=/; Domain=.example.com', $line,
            'an expired cookie with the path and domain it was set with');
    }

    public function testDomainCannotInjectAttributes()
    {
        $line = Session::cookieHeader('v', 0, false, ".example.com; Secure\r\nX: y");
        $this->assertStringContainsString('; Domain=.example.comSecureXy;', $line, 'only host characters survive');
        $this->assertStringNotContainsString("\n", $line);
    }
}
