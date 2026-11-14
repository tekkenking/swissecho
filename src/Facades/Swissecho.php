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
     * Resolve a fresh instance on every call.
     *
     * Facades cache the object they resolve, which would quietly turn the
     * container's bind back into a singleton for anyone using the facade.
     *
     * @var bool
     */
    protected static $cached = false;

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
