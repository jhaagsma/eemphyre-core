<?php
use EmPHyre\TypeValidator;
use PHPUnit\Framework\TestCase;

final class TypeValidatorTest extends TestCase
{
    private static function v($value, $type)
    {
        return TypeValidator::validate(['k' => $value], 'k', $type);
    }

    public function testScalarsAreCoercedAsBefore()
    {
        $this->assertSame(42, self::v('42', 'i'));
        $this->assertSame('abc', self::v('abc', 's'));
        $this->assertSame(1.5, self::v('1.5', 'f'));
        $this->assertSame(10000, self::v('10k', 'u'));
        $this->assertSame(0, self::v('-5', 'u'));
        $this->assertSame('', self::v('', 's'), 'empty input: the (null) default as a string');
        $this->assertSame(7, self::v('', ['i', 7]), 'empty input: the given default');
        $this->assertSame(true, self::v(true, 'b'), 'a JSON boolean stays one');
    }

    // Regression: #8. settype() made an array "Array" (string, plus a warning) or 1 (int, bool,
    // u_int), so ?id[]=x routed as id 1.
    public function testAnArrayWhereAScalarIsExpectedGetsTheDefault()
    {
        $this->assertSame('', self::v(['x'], 's'));
        $this->assertSame(0, self::v(['x'], 'i'));
        $this->assertSame(0, self::v(['x'], 'u'));
        $this->assertSame(false, self::v(['x'], 'b'), 'an array is not a true bool');
        $this->assertSame('dflt', self::v(['x'], ['s', 'dflt']));
    }

    // Regression: #8. Extractable JSON (json_decode() without assoc) gives objects for nested
    // values; settype($object, 'string') was a fatal error.
    public function testAnObjectWhereAScalarIsExpectedGetsTheDefault()
    {
        $object = json_decode('{"a": 1}');

        $this->assertSame('', self::v($object, 's'));
        $this->assertSame(3, self::v($object, ['u', 3]));
    }

    public function testArraysAreStillValidatedPerElement()
    {
        $this->assertSame([1 => 5, 2 => 0], self::v(['1' => '5', '2' => ['nested']], ['a', [], 'u', 'u']));
    }
}
