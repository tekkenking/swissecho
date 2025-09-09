<?php


if(! function_exists('swissecho')) {
    function swissecho() {
        return app('swissecho');
    }
}

    if(! function_exists('addCountryCodeToPhoneNumber')) {
    function addCountryCodeToPhoneNumber($phone, $countryCode)
    {

        //If phone number starts with country code without +, return it
        if (str_starts_with($phone, $countryCode)) {
            return $phone;
        }

        //If phone number starts with +country code, remove the + and return it
        if (str_starts_with($phone, '+' . $countryCode)) {
            return substr($phone, 1);
        }

        //If phone number starts with 0, remove it
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        return $countryCode . $phone;
    }
}
