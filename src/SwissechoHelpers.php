<?php


if(! function_exists('swissecho')) {
    function swissecho() {
        return app('swissecho');
    }
}

if(! function_exists('addCountryCodeToPhoneNumber')) {
    function addCountryCodeToPhoneNumber($phone, $countryCode)
    {
        return \Tekkenking\Swissecho\Support\PhoneNumber::normalise((string) $phone, (string) $countryCode);
    }
}

if(! function_exists('removeCountryCodeFromPhoneNumber')) {
    function removeCountryCodeFromPhoneNumber($phone, $countryCode)
    {
        //If phone number starts with country code without +, remove it
        if (str_starts_with($phone, $countryCode)) {
            return substr($phone, strlen($countryCode));
        }

        //If the phone number starts with +country code, remove the + and return it
        if (str_starts_with($phone, '+' . $countryCode)) {
            return substr($phone, strlen($countryCode) + 1);
        }

        return $phone;
    }
}

if(! function_exists('convertPhoneNumberToArray')) {
    function convertPhoneNumberToArray($phone)
    {
        if (is_array($phone)) {
            return $phone;
        }

        //Split by comma and trim spaces
        return array_map('trim', explode(',', $phone));
    }
}
