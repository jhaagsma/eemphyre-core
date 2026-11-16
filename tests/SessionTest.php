<?php
use EmPHyre\Container;
use EmPHyre\Session;
use PHPUnit\Framework\TestCase;

/**
 * Session cookie parsing, lookup and logout (#4, #10). Uses FakeDb, never MySQL.
 * expireSession() calls setcookie(), which warns once PHPUnit has printed output, so those
 * tests run in a separate process.
 */
final class SessionTest extends TestCase
{
    private const KEY   = '0123456789abcdef0123456789abcdef';
    private const OTHER = 'fedcba9876543210fedcba9876543210';

    /** @var FakeDb */
    private $db;

    protected function setUp(): void
    {
        $this->db = new FakeDb();
        $this->db->sessions = [
            ['login_id' => 7, 'user_id' => 42, 'cookieval' => self::KEY,   'expiretime' => time() + 3600],
            ['login_id' => 8, 'user_id' => 42, 'cookieval' => self::OTHER, 'expiretime' => time() + 3600],
        ];
        Container::$params = ['db' => ['default' => ['db_name' => 'test']]];
        $instances = new ReflectionProperty(Container::class, 'instances');
        $instances->setAccessible(true);
        $instances->setValue(null, ['test' => $this->db]);
        $dbProp = new ReflectionProperty(Session::class, 'db');
        $dbProp->setAccessible(true);
        $dbProp->setValue(null, $this->db);
        $GLOBALS['__apcu'] = [];
        unset($_COOKIE[COOKIE_NAME]);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[COOKIE_NAME]);
        Session::parseCookie();   // resets the parsed cookie statics
    }

    private function cookie($value)
    {
        $_COOKIE[COOKIE_NAME] = $value;
        Session::parseCookie();
    }

    public function malformedCookies()
    {
        return [
            'key "0" (numeric compare)'   => ['42:0'],
            'short key'                   => ['42:abc'],
            'uppercase hex'               => ['42:' . strtoupper(self::KEY)],
            'extra part'                  => ['42:' . self::KEY . ':x'],
            'trailing newline'            => ["42:" . self::KEY . "\n"],
            'user 0'                      => ['0:' . self::KEY],
            'user not a number'           => ['x:' . self::KEY],
            'no colon'                    => [self::KEY],
            'empty'                       => [''],
        ];
    }

    /** @dataProvider malformedCookies */
    public function testMalformedCookieIsNoSessionAndRunsNoQuery($cookie)
    {
        $this->cookie($cookie);
        $this->assertFalse(Session::activeSession(true), 'a malformed cookie must not find a session');
        $this->assertSame([], $this->db->queries, 'a malformed cookie must not reach SQL');
    }

    public function testValidCookieFindsItsSession()
    {
        $this->cookie('42:' . self::KEY);
        $this->assertTrue(Session::activeSession(true), 'the right key must find its live session');
        $this->assertSame([42, self::KEY], array_slice($this->db->queries[0], 1), 'user id sent as int, key as string');
    }

    public function testAllDigitKeyIsLookedUpAsAString()
    {
        // md5 hex can be all digits; with preparePart() quoting strings that is just a string compare
        $this->cookie('42:' . str_repeat('1', 32));
        $this->assertFalse(Session::activeSession(true), 'an all-digit key matching no row must fail');
        $this->assertSame(str_repeat('1', 32), $this->db->queries[0][2], 'the key is passed as a string');
    }

    public function testWrongKeyFindsNothing()
    {
        $this->cookie('42:' . str_repeat('a', 32));
        $this->assertFalse(Session::activeSession(true), 'a key that matches no row must fail');
    }

    public function testCachedRowWithOtherKeyIsNotTrusted()
    {
        \EmPHyre\Cache::store(APC_ACTIVE_SESSION_PREFIX . '42', ['cookieval' => self::OTHER, 'login_id' => 8,
            'expiretime' => time() + 3600, 'lastreal' => time()]);
        $this->cookie('42:' . str_repeat('b', 32));
        $this->assertFalse(Session::activeSession(), 'a cached row for another key must not log this cookie in');
        $this->assertCount(1, $this->db->queries, 'the cache miss falls through to SQL');
    }

    public function testCachedRowWithSameKeyNeedsNoQuery()
    {
        \EmPHyre\Cache::store(APC_ACTIVE_SESSION_PREFIX . '42', ['cookieval' => self::KEY, 'login_id' => 7,
            'expiretime' => time() + 3600, 'lastreal' => time()]);
        $this->cookie('42:' . self::KEY);
        $this->assertTrue(Session::activeSession(), 'the cached row for this key is used');
        $this->assertSame([], $this->db->queries, 'cache hit: no SQL');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testForgedCookieDeletesNothing()
    {
        $this->setUp();
        $this->cookie('42:' . str_repeat('c', 32));
        Session::expireSession();
        Session::expireSession(true);
        $this->assertSame([], $this->db->deletes(), 'a cookie whose key matches no session must delete nothing');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSingleLogoutDeletesOnlyThisSession()
    {
        $this->setUp();
        $this->cookie('42:' . self::KEY);
        Session::expireSession(true);
        $this->assertSame([['DELETE FROM active_sessions WHERE login_id = ?', 7]], $this->db->deletes(),
            'single logout deletes just the matching login');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFullLogoutDeletesAllOfTheUsersSessions()
    {
        $this->setUp();
        $this->cookie('42:' . self::KEY);
        Session::expireSession();
        $this->assertSame([['DELETE FROM active_sessions WHERE user_id = ?', 42]], $this->db->deletes(),
            'log-out-everywhere still works for the real session holder');
    }
}
