<?php
use EmPHyre\Router;
use PHPUnit\Framework\TestCase;

/**
 * Router path extension (pathExtension + extractableJson): a request to /api with api_function=cash
 * is routed as /api/cash, whether api_function comes as its own field or inside the JSON payload.
 */
final class RouterPathExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        $_POST = [];
        $_GET  = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET  = [];
        unset($_SERVER['REQUEST_METHOD']);
        putenv('REQUEST_URI');
        putenv('REQUEST_METHOD');
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
        $router->post('/api/cash', 'x.php', 'api_cash', ['turns' => 'u']);
        return $router->route();
    }

    // Baseline (passes on the old code too): EE's bots send api_function as its own field.
    public function testFunctionAsItsOwnField()
    {
        $route = $this->route(['api_function' => 'cash', 'api_payload' => '{"turns":5}']);
        $this->assertSame('api_cash', $route->function, 'routes to /api/cash');
        $this->assertSame(5, $route->data['turns'], 'with the payload as input');
    }

    public function testFunctionInsideThePayload()
    {
        // Regression: #8. Used to recurse again on /api/cash (the payload was extracted a second time, putting
        // api_function back) and end on /api/cash/cash: a 404.
        $route = $this->route(['api_payload' => '{"api_function":"cash","turns":5}']);
        $this->assertSame('api_cash', $route->function, 'routes to /api/cash, not /api/cash/cash');
        $this->assertSame(5, $route->data['turns'], 'with the payload as input');
    }
}
