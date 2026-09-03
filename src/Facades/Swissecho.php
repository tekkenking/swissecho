<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Tekkenking\Swissecho\Swissecho
 */
class Swissecho extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'swissecho';
    }
}
