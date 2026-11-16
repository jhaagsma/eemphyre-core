<?php
use EmPHyre\Router;
use PHPUnit\Framework\TestCase;

/**
 * Router path extension: the extension value comes from client JSON, so it can be any JSON type.
 * Only a string or an integer names a function; anything else is a 404, like an unknown name.
 */
final class RouterExtensionTypeTest extends TestCase
{
    protected function setUp(): void
    {
        $_POST = [];
        $_GET  = [];
        unset($GLOBALS['emphyre_test_404']);
        putenv('DOCUMENT_ROOT=' . __DIR__ . '/fixtures/docroot');
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET  = [];
        unset($_SERVER['REQUEST_METHOD'], $GLOBALS['emphyre_test_404']);
        putenv('REQUEST_URI');
        putenv('REQUEST_METHOD');
        putenv('DOCUMENT_ROOT');
    }

    private function route(array $post)
    {
        putenv('REQUEST_METHOD=POST');
        putenv('REQUEST_URI=/api');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $post;

        $router = new Router();
        $router->pathExtension('api_function');
        $router->extractableJson('api_payload');
        $router->post('/api', 'x.php', 'api_base');
        $router->post('/api/cash', 'x.php', 'api_cash');
        return $router->route();
    }

    /**
     * Regression: #8. An object used to reach the URL concatenation: "Object of class stdClass could
     * not be converted to string", a fatal error any client could cause.
     *
     * @runInSeparateProcess
     */
    public function testObjectInThePayloadIsNotFound()
    {
        $route = $this->route(['api_payload' => '{"api_function":{"a":1}}']);
        $this->assertNull($route, 'no route for an object');
        $this->assertTrue(isset($GLOBALS['emphyre_test_404']), 'the 404 page is shown');
    }

    /**
     * Regression: #8. A form-field array (api_function[]=cash) became "Array", with a warning.
     *
     * @runInSeparateProcess
     */
    public function testArrayFieldIsNotFound()
    {
        $route = $this->route(['api_function' => ['cash'], 'api_payload' => '{"turns":1}']);
        $this->assertNull($route, 'no route for an array, even one holding a valid name');
        $this->assertTrue(isset($GLOBALS['emphyre_test_404']), 'the 404 page is shown');
    }

    // Fails if the null guard goes: no api_function is no extension (the base route), not a 404.
    public function testAbsentFunctionRoutesToTheBase()
    {
        $route = $this->route(['api_payload' => '{"turns":1}']);
        $this->assertSame('api_base', $route->function);
    }

    // Baseline: a string, as EE's bots send it (api_function as its own field).
    // (An integer can only come inside the payload, which needs #33 to route at all; test it after.)
    public function testStringStillRoutes()
    {
        $route = $this->route(['api_function' => 'cash', 'api_payload' => '{"turns":1}']);
        $this->assertSame('api_cash', $route->function);
    }
}
