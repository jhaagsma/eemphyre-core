<?php
use EmPHyre\M2M;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fakes/RecordingDb.php';

final class M2MTest extends TestCase
{
    /** @var RecordingDb */
    private $db;

    protected function setUp(): void
    {
        $this->db = new RecordingDb();
    }

    protected function tearDown(): void
    {
        RecordingDb::detach(M2M::class);
    }

    // Regression: #8. Table and keys were static: the second M2M sent the first one's queries to
    // the second one's table and columns.
    public function testEachInstanceKeepsItsOwnTableAndKeys()
    {
        $groups = new M2M('user_permission_groups', 'user_id', 'group_id');
        $other  = new M2M('other_links', 'a_id', 'b_id');
        $groups->setDb($this->db);

        $groups->countM2M(5);
        $this->assertSame(
            'SELECT COUNT(1) FROM `user_permission_groups` WHERE `user_id`=?',
            $this->db->lastSql()
        );

        $other->countM2M(5);
        $this->assertSame('SELECT COUNT(1) FROM `other_links` WHERE `a_id`=?', $this->db->lastSql());
    }
}
