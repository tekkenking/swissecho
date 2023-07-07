<?php


if(! function_exists('swissecho')) {
    function swissecho() {
        return app('swissecho');
    }
}

if (!function_exists('add_country_code')) {
    function add_country_code($phone, $countrycode) {
        $phone = trim($phone);

        //Is leading zero?
        if (Str::startsWith($phone, '0')) $phone = Str::replaceFirst('0', '', $phone);

        //Does the number start with +
        if (Str::startsWith($phone, '+')) return $phone;

        return '+' . $countrycode . $phone;
    }
}


if (!function_exists('remove_country_code')) {
    function remove_country_code($phone, $countrycode)
    {
        $phone = trim($phone);

        if (Str::startsWith($phone, '00')) return '0'. Str::replaceFirst('00', '', Str::replaceFirst($countrycode, '', $phone));

        //Is leading zero?
        if (Str::startsWith($phone, '0')) return $phone;

        //Does the number start with +
        if (Str::startsWith($phone, '+')) return '0'. Str::replaceFirst('+', '', Str::replaceFirst($countrycode, '', $phone));

        return '0'.$phone;

    }
}
