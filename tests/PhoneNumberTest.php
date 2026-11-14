<?php

namespace Tekkenking\Swissecho\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tekkenking\Swissecho\Support\PhoneNumber;

class PhoneNumberTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            'national with trunk 0'              => ['08012345678', '234', '2348012345678'],
            'already international, no plus'     => ['2348012345678', '234', '2348012345678'],
            'international with plus'            => ['+2348012345678', '234', '2348012345678'],
            'spaces inside'                      => ['+234 801 234 5678', '234', '2348012345678'],
            'brackets and a dash'                => ['(0801) 234-5678', '234', '2348012345678'],
            'calling code written with plus'     => ['08012345678', '+234', '2348012345678'],
            'ghana'                              => ['0501234567', '233', '233501234567'],
            'australia'                          => ['0412345678', '61', '61412345678'],
            'united states, no trunk prefix'     => ['(415) 555-2671', '1', '14155552671'],

            //Italy keeps its leading 0 in international form. Stripping it,
            //as the old string rules did, produces a number that cannot ring.
            'italy keeps its leading zero'       => ['0612345678', '39', '390612345678'],

            //A + means the caller already supplied the country code.
            'foreign number under another place' => ['+447911123456', '234', '447911123456'],

            //Not recognised by the library, so the simple rules apply and
            //the provider decides. Nothing here rejects a number.
            'unrecognised number still formats'  => ['12345', '234', '23412345'],
            'unknown calling code still formats' => ['08012345678', '999', '9998012345678'],

            'empty stays empty'                  => ['', '234', ''],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_normalises(string $number, string $callingCode, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalise($number, $callingCode));
    }

    public function test_a_bare_foreign_number_is_still_prefixed(): void
    {
        //Documented limit: without a + this cannot be told apart from a
        //national number, and guessing risks sending to a stranger abroad.
        $this->assertSame('234447911123456', PhoneNumber::normalise('447911123456', '234'));
    }
}
