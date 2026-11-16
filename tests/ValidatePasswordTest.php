<?php
use EmPHyre\Validate;
use PHPUnit\Framework\TestCase;

/** Validate::password() rules touched by #5. */
final class ValidatePasswordTest extends TestCase
{
    public function testGoodPasswordPasses()
    {
        $this->assertFalse(Validate::password('Abcdefg1!', 'Abcdefg1!')->isError(), 'a valid password passes');
    }

    public function testNumericLookalikesDoNotMatch()
    {
        $this->assertTrue(Validate::password('1e3aaaa!x', '1000aaa!x')->isError(), 'different strings');
        $this->assertTrue(Validate::password('1e3', '1000', 1, false, false, false)->isError(),
            '"1e3" and "1000" are different passwords');
    }

    public function testNulByteIsRejected()
    {
        $r = Validate::password("Abcdefg1!\0x", "Abcdefg1!\0x");
        $this->assertTrue($r->isError(), 'NUL bytes are refused (bcrypt would cut the password there)');
    }
}
