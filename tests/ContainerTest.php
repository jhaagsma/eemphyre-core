<?php
use EmPHyre\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    /** @var \ReflectionProperty */
    private $instances;

    protected function setUp(): void
    {
        $this->instances = new \ReflectionProperty(Container::class, 'instances');
        $this->instances->setAccessible(true);
        $this->instances->setValue(null, []);
        Container::$params = ['db' => ['default' => ['db_name' => 'app']]];
    }

    protected function tearDown(): void
    {
        $this->instances->setValue(null, []);
        Container::$params = [];
    }

    // Regression: #7. getDb() ran createIfNotExists() on every call: a SHOW DATABASES each time,
    // and CREATE DATABASE for a mistyped name.
    public function testGetDbDoesNotCreateDatabases()
    {
        $db = new class {
            public $calls = 0;

            public function createIfNotExists()
            {
                $this->calls++;
            }
        };
        $this->instances->setValue(null, ['app' => $db]);

        $this->assertSame($db, Container::getDb());
        $this->assertSame($db, Container::getDb('app'));
        $this->assertSame(0, $db->calls, 'getDb() leaves database creation to setup code');
    }
}
