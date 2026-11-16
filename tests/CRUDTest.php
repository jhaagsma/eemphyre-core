<?php
use EmPHyre\CRUD;
use PHPUnit\Framework\TestCase;

/**
 * Stand-in for MysqlDb: records the queries CRUD builds, prepared the way MysqlDb::prepare()
 * does for ints and strings, and returns no rows. Never a live database (CLAUDE.md).
 */
final class CrudRecordingDb
{
    /** @var array[] each [sql, args...] as passed to pquery() / pqueryArray() */
    public $queries = [];

    public function pquery($sql, ...$args)
    {
        return $this->pqueryArray(array_merge([$sql], $args));
    }

    public function pqueryArray($args)
    {
        $this->queries[] = $args;
        return new CrudEmptyResult();
    }

    /** The last query with its ? filled in: ints bare, strings quoted, arrays comma-joined */
    public function lastSql()
    {
        $args  = end($this->queries);
        $parts = explode('?', array_shift($args));
        $sql   = array_shift($parts);
        foreach ($parts as $i => $part) {
            $sql .= self::literal($args[$i]).$part;
        }
        return $sql;
    }

    private static function literal($v)
    {
        if (is_array($v)) {
            return implode(',', array_map([self::class, 'literal'], $v));
        }
        return is_int($v) ? (string)$v : "'".addslashes((string)$v)."'";
    }
}

final class CrudEmptyResult
{
    public function fetchFieldSet()
    {
        return [];
    }
}

final class CrudTestThing extends CRUD
{
    protected static $tableName  = 'things';
    protected static $primaryKey = 'thing_id';
}

final class CRUDTest extends TestCase
{
    /** @var CrudRecordingDb */
    private $db;

    protected function setUp(): void
    {
        // CRUD::$db is one static shared by every subclass: set it fresh for each test
        $this->db = new CrudRecordingDb();
        (new CrudTestThing())->setDb($this->db);
    }

    // Regression: #12
    public function testPrimaryListAppliesLimitAndOffset()
    {
        CrudTestThing::primaryList(10, 20);
        $this->assertSame(
            'SELECT `thing_id` FROM `things` ORDER BY `thing_id` ASC LIMIT 10 OFFSET 20',
            $this->db->lastSql()
        );
    }

    public function testPrimaryListWithoutLimitIsUnbounded()
    {
        CrudTestThing::primaryList();
        $this->assertSame('SELECT `thing_id` FROM `things` ORDER BY `thing_id` ASC', $this->db->lastSql());
    }

    // Regression: #12
    public function testFilterColumnAppliesLimitAndOffset()
    {
        CrudTestThing::filterColumn('owner', 'bob', 5, 15, false);
        $this->assertSame(
            "SELECT `thing_id` FROM `things` WHERE `owner`='bob' ORDER BY `thing_id` DESC LIMIT 5 OFFSET 15",
            $this->db->lastSql()
        );
    }

    public function testFilterColumnDefaultLimitZeroIsUnbounded()
    {
        CrudTestThing::filterColumn('owner', 'bob');
        $this->assertSame(
            "SELECT `thing_id` FROM `things` WHERE `owner`='bob' ORDER BY `thing_id` ASC",
            $this->db->lastSql()
        );
    }

    // Regression: #12
    public function testFilterPKArrayAppliesLimitAndOffset()
    {
        CrudTestThing::filterPKArray([3, 4, 5], 'disabled', 'false', 2, 1);
        $this->assertSame(
            "SELECT `thing_id` FROM `things` WHERE `thing_id` IN(3,4,5) AND `disabled`='false'"
            ." ORDER BY `thing_id` ASC LIMIT 2 OFFSET 1",
            $this->db->lastSql()
        );
    }

    public function testFilterPKArrayWithNoKeysRunsNoQuery()
    {
        $this->assertSame([], CrudTestThing::filterPKArray([], 'disabled', 'false', 2));
        $this->assertSame([], $this->db->queries, 'IN () is a syntax error: no query without keys');
    }

    // Regression: #12 (limit, offset and direction were not passed on to filterColumn())
    public function testPrimaryListNotDisabledPassesLimitOffsetAndDirection()
    {
        CrudTestThing::primaryListNotDisabled(7, 14, false);
        $this->assertSame(
            "SELECT `thing_id` FROM `things` WHERE `disabled`='false' ORDER BY `thing_id` DESC LIMIT 7 OFFSET 14",
            $this->db->lastSql()
        );
    }

    public function testLimitAndOffsetFromStringsAreCastToInt()
    {
        // e.g. straight from a query string: must reach pquery() as ints, never as SQL text
        CrudTestThing::primaryList('10; DROP TABLE things', '3x');
        $args = end($this->db->queries);
        $this->assertSame([10, 3], array_slice($args, 1));
    }

    public function testNegativeOrZeroLimitMeansNoLimitAndNegativeOffsetIsZero()
    {
        CrudTestThing::primaryList(-5, 10);
        $this->assertStringNotContainsString('LIMIT', $this->db->lastSql(), 'a negative limit adds no LIMIT');

        CrudTestThing::primaryList(0, 10);
        $this->assertStringNotContainsString('LIMIT', $this->db->lastSql(), 'limit 0 adds no LIMIT');

        CrudTestThing::primaryList(5, -3);
        $this->assertStringEndsWith(' LIMIT 5 OFFSET 0', $this->db->lastSql());
    }
}
