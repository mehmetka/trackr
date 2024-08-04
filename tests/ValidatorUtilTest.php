<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\util\ValidatorUtil;

class ValidatorUtilTest extends TestCase
{
    /**
     * @dataProvider providerForValidateIntegerByConstraints
     */
    public function testValidateIntegerByConstraints($value, $min, $max, $expected)
    {
        $result = ValidatorUtil::validateIntegerByConstraints($value, $min, $max);
        $this->assertSame($expected, $result);
    }

    public static function providerForValidateIntegerByConstraints()
    {
        return [
            'value within range' => [10, 1, 20, true],
            'value equals min' => [1, 1, 20, true],
            'value equals max' => [20, 1, 20, true],
            'value below min' => [0, 1, 20, false],
            'value above max' => [21, 1, 20, false],
            'value not an integer' => ['1', 1, 20, true],
            'value as float' => [10.5, 1, 20, false],
            'value as negative integer' => [-5, -10, -1, true],
            'value below negative range' => [-11, -10, -1, false],
            'value above negative range' => [0, -10, -1, false],
        ];
    }
}