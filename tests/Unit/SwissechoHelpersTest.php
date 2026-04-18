<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SwissechoHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../src/SwissechoHelpers.php';
    }

    public function test_add_country_code_to_phone_number(): void
    {
        $this->assertEquals('2348012345678', addCountryCodeToPhoneNumber('08012345678', '234'));
        $this->assertEquals('2348012345678', addCountryCodeToPhoneNumber('2348012345678', '234'));
        $this->assertEquals('2348012345678', addCountryCodeToPhoneNumber('+2348012345678', '+234'));
    }

    public function test_remove_country_code_from_phone_number(): void
    {
        $this->assertEquals('8012345678', removeCountryCodeFromPhoneNumber('2348012345678', '234'));
        $this->assertEquals('8012345678', removeCountryCodeFromPhoneNumber('+2348012345678', '234'));
    }

    public function test_convert_phone_number_to_array_from_string(): void
    {
        $result = convertPhoneNumberToArray('08012345678, 08098765432');
        $this->assertEquals(['08012345678', '08098765432'], $result);
    }

    public function test_convert_phone_number_to_array_passthrough(): void
    {
        $input = ['08012345678'];
        $this->assertEquals($input, convertPhoneNumberToArray($input));
    }
}
