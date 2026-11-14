<?php

namespace Tekkenking\Swissecho\Tests;

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Load helpers
        require_once __DIR__ . '/../src/SwissechoHelpers.php';
    }

    public function test_add_country_code_to_plain_number(): void
    {
        $this->assertSame('2348012345678', addCountryCodeToPhoneNumber('08012345678', '234'));
    }

    public function test_add_country_code_skips_when_already_present(): void
    {
        $this->assertSame('2348012345678', addCountryCodeToPhoneNumber('2348012345678', '234'));
    }

    public function test_add_country_code_strips_leading_plus(): void
    {
        $this->assertSame('2348012345678', addCountryCodeToPhoneNumber('+2348012345678', '234'));
    }

    public function test_an_international_number_for_another_country_is_left_alone(): void
    {
        //A + means the caller already gave the country code. Prepending the
        //place's code produced '234447911123456', a billed send to a number
        //that cannot exist, reported as a success.
        $this->assertSame('447911123456', addCountryCodeToPhoneNumber('+447911123456', '234'));
        $this->assertSame('14155551234', addCountryCodeToPhoneNumber('+14155551234', '234'));
    }

    public function test_add_country_code_with_plus_prefix_in_code(): void
    {
        $this->assertSame('2348012345678', addCountryCodeToPhoneNumber('08012345678', '+234'));
    }

    public function test_remove_country_code(): void
    {
        $this->assertSame('8012345678', removeCountryCodeFromPhoneNumber('2348012345678', '234'));
    }

    public function test_remove_country_code_with_plus(): void
    {
        $this->assertSame('8012345678', removeCountryCodeFromPhoneNumber('+2348012345678', '234'));
    }

    public function test_remove_country_code_no_code_present(): void
    {
        $this->assertSame('08012345678', removeCountryCodeFromPhoneNumber('08012345678', '234'));
    }

    public function test_convert_phone_number_to_array_from_string(): void
    {
        $this->assertSame(['08012345678'], convertPhoneNumberToArray('08012345678'));
    }

    public function test_convert_phone_number_to_array_from_comma_separated(): void
    {
        $this->assertSame(['08012345678', '08087654321'], convertPhoneNumberToArray('08012345678, 08087654321'));
    }

    public function test_convert_phone_number_to_array_passthrough_array(): void
    {
        $input = ['08012345678', '08087654321'];
        $this->assertSame($input, convertPhoneNumberToArray($input));
    }
}
