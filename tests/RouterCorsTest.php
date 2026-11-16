<?php
use EmPHyre\Cors;
use EmPHyre\Router;
use PHPUnit\Framework\TestCase;

/**
 * Router::route() and Cors together: the path and method the Router routes are the ones CORS uses.
 */
final class RouterCorsTest extends TestCase
{
    private const APP = 'https://alphaui.earthempires.com';

    /** @var array[] */
    private $sent = [];

    protected function setUp(): void
    {
        Cors::allow([self::APP], ['/rest'], true);
        $this->sent = [];
        Cors::setEmitterForTests(function ($line, $replace) {
            $this->sent[] = $line;
        }, function () {
            return false;
        });
        $_SERVER['HTTP_ORIGIN'] = self::APP;
    }

    protected function tearDown(): void
    {
        Cors::allow([]);
        Cors::setEmitterForTests(null);
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['REQUEST_METHOD']);
        putenv('REQUEST_URI');
        putenv('REQUEST_METHOD');
    }

    private function route($method, $uri)
    {
        putenv("REQUEST_METHOD=$method");
        putenv("REQUEST_URI=$uri");
        $_SERVER['REQUEST_METHOD'] = $method;
        $router = new Router();
        $router->get('/rest', 'x.php', 'rest_get');
        $router->get('/account', 'x.php', 'account');
        return $router->route();
    }

    public function testQueryOnTheRestEndpointKeepsCors()
    {
        $route = $this->route('GET', '/rest?api_function=x');
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, $this->sent, 'GET /rest?query is in scope');
        $this->assertSame('rest_get', $route->function, 'and routes to the /rest handler');
    }

    public function testOutOfScopePageGetsNothing()
    {
        $route = $this->route('GET', '/account?next=/rest');
        $this->assertSame([], $this->sent, 'no CORS headers for /account, whatever the query says');
        $this->assertSame('account', $route->function, 'routes to /account');
    }

    public function testExplicitUrlArgument()
    {
        putenv('REQUEST_URI=/account');   // the request URI says /account ...
        putenv('REQUEST_METHOD=GET');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $router = new Router();
        $router->get('/rest', 'x.php', 'rest_get');
        $route = $router->route('/rest');   // ... but the app routes /rest explicitly
        $this->assertSame('rest_get', $route->function, 'routes the explicit URL');
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, $this->sent, 'and CORS follows the routed URL');
    }

    public function testFirstDecisionPerRequestWins()
    {
        putenv('REQUEST_URI=/account');
        putenv('REQUEST_METHOD=GET');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $router = new Router();
        $router->get('/rest', 'x.php', 'rest_get');
        $router->get('/account', 'x.php', 'account');
        $router->route();         // out of scope: no CORS
        $router->route('/rest');  // like the internal recursion with a user-chosen extension
        $this->assertSame([], $this->sent, 'the first decision in the request stands');
    }

    public function testCorsBeforeRoutingDoesNotLockTheRequest()
    {
        putenv('REQUEST_URI=/index.php');
        $this->assertFalse(\EmPHyre\Cors::headers(), 'an early no-argument call: out of scope');
        putenv('REQUEST_METHOD=GET');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $router = new Router();
        $router->get('/rest', 'x.php', 'rest_get');
        $router->route('/rest');
        $this->assertContains('Access-Control-Allow-Origin: ' . self::APP, $this->sent, 'routing /rest still gets CORS');
    }
}
