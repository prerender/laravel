<?php

namespace Prerender\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Prerender\Laravel\LaravelPrerenderServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaravelPrerenderServiceProvider::class];
    }
}
