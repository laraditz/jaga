<?php

namespace Laraditz\Jaga\Testing;

use Illuminate\Support\Facades\Artisan;

trait RefreshJagaCache
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('jaga:clear');
    }
}
