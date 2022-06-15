<?php

namespace Aurora\AAuth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Aurora\AAuth\AAuth
 */
class AAuth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'aauth';
    }
}
