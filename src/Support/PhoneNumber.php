<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Turns a recipient into the digits-only international form gateways expect.
 *
 * libphonenumber does the work when it recognises the number, because the
 * rules differ by country in ways string handling gets wrong: Italy keeps
 * its leading 0, the United States has no trunk prefix at all, and numbers
 * arrive with spaces, dashes and brackets in them.
 *
 * It is never a gate. Its metadata can lag behind newly issued ranges, so a
 * number it does not recognise is still formatted the simple way and sent,
 * and the provider decides. Nothing here rejects a number.
 */
final class PhoneNumber
{
    /**
     * @param string $number      As the caller wrote it.
     * @param string $callingCode The place's dialling code, with or without +.
     * @return string Digits only, country code first.
     */
    public static function normalise(string $number, string $callingCode): string
    {
        $number = trim($number);
        $callingCode = ltrim(trim($callingCode), '+');

        if ($number === '') {
            return '';
        }

        return self::viaLibrary($number, $callingCode)
            ?? self::bySimpleRules($number, $callingCode);
    }

    /**
     * Null when the library cannot parse the number or does not consider it
     * a valid one, which hands over to the simple rules.
     */
    private static function viaLibrary(string $number, string $callingCode): ?string
    {
        $util = PhoneNumberUtil::getInstance();

        //'ZZ' for a dialling code the library does not know, in which case
        //only a number written with + can be parsed.
        $region = ctype_digit($callingCode)
            ? $util->getRegionCodeForCountryCode((int) $callingCode)
            : 'ZZ';

        try {
            $parsed = $util->parse($number, $region);
        } catch (NumberParseException) {
            return null;
        }

        if (!$util->isValidNumber($parsed)) {
            return null;
        }

        return ltrim($util->format($parsed, PhoneNumberFormat::E164), '+');
    }

    /**
     * The original string rules, kept as the fallback.
     *
     * A leading + means the caller already supplied a country code, so the
     * number is returned as given: prefixing it anyway turned
     * '+447911123456' with place 'nga' into '234447911123456'.
     */
    private static function bySimpleRules(string $number, string $callingCode): string
    {
        $hasPlus = str_starts_with($number, '+');
        $digits = preg_replace('/[^0-9]/', '', $number) ?? '';

        if ($hasPlus) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if ($callingCode !== '' && str_starts_with($digits, $callingCode)) {
            return $digits;
        }

        return $callingCode . $digits;
    }
}
