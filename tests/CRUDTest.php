<?php
use EmPHyre\CRUD;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fakes/RecordingDb.php';

#[\AllowDynamicProperties]
final class CrudThing extends CRUD
{
    protected static $db;
    protected static $tableName  = 'things';
    protected static $primaryKey = 'id';

    public static function add(array $keyValue)
    {
        return static::addByArray($keyValue);
    }

    public function save()
    {
        return $this->commit();
    }
}

final class CRUDTest extends TestCase
{
    /** @var RecordingDb */
    private $db;

    protected function setUp(): void
    {
        $this->db = new RecordingDb();
        (new CrudThing(0))->setDb($this->db);
    }

    protected function tearDown(): void
    {
        RecordingDb::detach(CrudThing::class);
    }

    // Regression: #8. Column names went into the SQL as given: a key with a backtick (e.g. from
    // addByArray($_POST)) could inject SQL.
    public function testColumnNamesCannotBreakOutOfTheirQuotes()
    {
        CrudThing::add(['name' => 'x', 'a`=1, `admin' => 'y']);
        $this->assertSame('INSERT INTO `things` SET `name` = ?, `a``=1, ``admin` = ?', $this->db->lastSql());

        CrudThing::getUniqueId('uuid` OR 1=1 -- ', 'z');
        $this->assertSame('SELECT `id` FROM `things` WHERE `uuid`` OR 1=1 -- `=?', $this->db->lastSql());
    }

    // Regression: #8. commit() compared each column name with the primary key's value, not its
    // name: a column named like that value (or any name vs a 0 id, in PHP 7) was never saved.
    public function testCommitSavesAColumnNamedLikeThePrimaryKeyValue()
    {
        $thing        = new CrudThing('name');
        $thing->_data = ['id' => 'name', 'name' => 'old'];
        $thing->name  = 'new';

        $this->assertTrue($thing->save(), 'the changed column is written');
        $this->assertSame(['UPDATE `things` SET `name` = ? WHERE `id`=?', 'new', 'name'], $this->db->queries[0]);
    }
}
