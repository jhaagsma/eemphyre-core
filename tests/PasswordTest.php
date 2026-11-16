<?php
use EmPHyre\Password;
use PHPUnit\Framework\TestCase;

/** Password hashing and verification, old sha512 and password_hash() formats (#5). */
final class PasswordTest extends TestCase
{
    private const SALT = '1234$5f1a2b3c4d5e6f.12345678';   // "<rounds>$<uniqid('', true)>", 28 chars

    public function testNewHashVerifiesAndNeedsNoRehash()
    {
        $hash = Password::hash('correct horse');
        $this->assertTrue(Password::isModernHash($hash), 'hash() gives a password_hash() hash');
        $this->assertSame([true, false], Password::verify('correct horse', $hash, ''));
        $this->assertSame([false, false], Password::verify('wrong', $hash, ''));
    }

    public function testOldHashVerifiesAndAsksForRehash()
    {
        $old = Password::cryptSHA512('hunter2', self::SALT);
        $this->assertSame([true, true], Password::verify('hunter2', $old, self::SALT), 'old hashes still log in, then get upgraded');
        $this->assertSame([false, false], Password::verify('hunter3', $old, self::SALT));
    }

    public function testOutdatedModernHashAsksForRehash()
    {
        $cheap = password_hash('pw', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->assertSame([true, true], Password::verify('pw', $cheap, ''), 'a hash with old settings is upgraded on login');
    }

    public function unusableStored()
    {
        return [
            'default "unset" row'  => ['unset', 'unset'],
            'empty'                => ['', ''],
            'uppercase old hash'   => [strtoupper(Password::cryptSHA512('pw', self::SALT)), self::SALT],
            'old hash, no rounds'  => [Password::cryptSHA512('pw', self::SALT), 'nosalt'],
        ];
    }

    /** @dataProvider unusableStored */
    public function testUnusableStoredValuesNeverMatch($stored, $salt)
    {
        $this->assertSame([false, false], Password::verify('pw', $stored, $salt), 'no password matches this stored value');
        $this->assertSame([false, false], Password::verify('unset', $stored, $salt), 'not even the stored text itself');
    }
}
