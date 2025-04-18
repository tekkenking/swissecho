<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho;

use Illuminate\Support\Facades\Facade;

class SwissechoFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'swissecho'; }
}
