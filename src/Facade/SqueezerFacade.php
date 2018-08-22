<?php

namespace Ardentic\Squeezer;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Illuminate\Cookie\CookieJar
 */
class SqueezerFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'squeezer';
    }
}
