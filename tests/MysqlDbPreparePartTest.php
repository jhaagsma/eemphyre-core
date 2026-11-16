<?php
use EmPHyre\MysqlDb;
use PHPUnit\Framework\TestCase;

/** preparePart() quoting (#4). No connection: a stub stands in for mysqli's escaping. */
final class MysqlDbPreparePartTest extends TestCase
{
    /** @var ReflectionMethod */
    private $prepare;
    /** @var MysqlDb */
    private $db;

    protected function setUp(): void
    {
        $this->db = (new ReflectionClass(MysqlDb::class))->newInstanceWithoutConstructor();
        $con = new ReflectionProperty(MysqlDb::class, 'con');
        $con->setAccessible(true);
        $con->setValue($this->db, new class {
            public function real_escape_string($s)
            {
                return addslashes($s);
            }

            public function close()
            {
                return true;   // MysqlDb::__destruct() closes the connection
            }
        });
        $this->prepare = new ReflectionMethod(MysqlDb::class, 'preparePart');
        $this->prepare->setAccessible(true);
    }

    private function part($v)
    {
        return $this->prepare->invoke($this->db, $v);
    }

    public function testNumericStringsAreQuoted()
    {
        $this->assertSame("'0'", $this->part('0'), '"0" must not compare numerically');
        $this->assertSame("'123'", $this->part('123'));
        $this->assertSame("'1e3'", $this->part('1e3'));
        $this->assertSame("' 5'", $this->part(' 5'));
    }

    public function testOtherTypesUnchanged()
    {
        $this->assertSame(5, $this->part(5));
        $this->assertSame(1.5, $this->part(1.5));
        $this->assertSame(1, $this->part(true));
        $this->assertSame('NULL', $this->part(null));
        $this->assertSame("1,'2','a\\'b'", $this->part([1, '2', "a'b"]));
    }
}
