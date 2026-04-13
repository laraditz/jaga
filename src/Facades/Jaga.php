<?php

namespace Laraditz\Jaga\Facades;

use Illuminate\Support\Facades\Facade;

class Jaga extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'jaga';
    }
}
