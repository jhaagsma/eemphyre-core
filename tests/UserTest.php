<?php
use EmPHyre\Container;
use EmPHyre\Result;
use EmPHyre\User;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fakes/RecordingDb.php';

#[\AllowDynamicProperties]
final class TestUser extends User
{
}

final class UserTest extends TestCase
{
    /** @var RecordingDb */
    private $db;
    /** @var \ReflectionProperty */
    private $instances;

    protected function setUp(): void
    {
        $this->db        = new RecordingDb();
        $this->instances = new \ReflectionProperty(Container::class, 'instances');
        $this->instances->setAccessible(true);
        $this->instances->setValue(null, ['app' => $this->db]);
        Container::$params = ['db' => ['default' => ['db_name' => 'app']]];
    }

    protected function tearDown(): void
    {
        $this->instances->setValue(null, []);
        Container::$params = [];
        RecordingDb::detach(User::class);
    }

    private function user()
    {
        $user            = new TestUser(1);
        $user->user_name = 'old@example.com';
        $user->_data     = ['user_id' => 1, 'user_name' => 'old@example.com'];
        $user->setDb($this->db);
        return $user;
    }

    // Regression: #8 (all three). `$error = X == true` stored a bool, and a Result is always
    // truthy: edit() returned true before validating or saving anything.
    public function testEditRefusesAnInvalidEmail()
    {
        $result = $this->user()->edit('not an email', '', '');

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame('EMAIL_NOT_VALID', (string)$result);
    }

    public function testEditRefusesANameThatIsTaken()
    {
        $this->db->answers['SELECT user_id FROM users WHERE user_name'] = [['user_id' => 7]];

        $result = $this->user()->edit('taken@example.com', '', '');

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame('EXISTS', (string)$result);
    }

    public function testEditSavesANewName()
    {
        $result = $this->user()->edit('new@example.com', '', '');

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame('EDITED_USER', (string)$result);
        $updates = array_values(array_filter($this->db->queries, function ($q) {
            return strpos($q[0], 'UPDATE') === 0;
        }));
        $this->assertSame([['UPDATE `users` SET `user_name` = ? WHERE `user_id`=?', 'new@example.com', 1]], $updates);
    }
}
