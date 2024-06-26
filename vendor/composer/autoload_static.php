<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit43206e34014bba9041516992ac3df38a
{
    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'Tekkenking\\Swissecho\\' => 21,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Tekkenking\\Swissecho\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit43206e34014bba9041516992ac3df38a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit43206e34014bba9041516992ac3df38a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit43206e34014bba9041516992ac3df38a::$classMap;

        }, null, ClassLoader::class);
    }
}
