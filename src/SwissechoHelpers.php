<?php


if(! function_exists('swissecho')) {
    function swissecho() {
        return app('swissecho');
    }
}

if(! function_exists('addCountryCodeToPhoneNumber')) {
    function addCountryCodeToPhoneNumber($phone, $countryCode)
    {

        //If the country code starts with +, remove the +
        if (str_starts_with($countryCode, '+')) {
            $countryCode = substr($countryCode, 1);
        }

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        //If phone number starts with country code without +, return it
        if (str_starts_with($phone, $countryCode)) {
            return $phone;
        }

        return $countryCode . $phone;
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
