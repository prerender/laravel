<?php

namespace Prerender\Laravel;

use GuzzleHttp\Client;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

class LaravelPrerenderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/prerender.php' => config_path('prerender.php'),
        ], 'prerender-config');

        if (! config('prerender.enable', true)) {
            return;
        }

        $this->app->make(Kernel::class)->pushMiddleware(PrerenderMiddleware::class);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/prerender.php', 'prerender');

        $this->app->when(PrerenderMiddleware::class)
            ->needs(Client::class)
            ->give(function () {
                $options = ['timeout' => config('prerender.timeout', 0)];
                if (! config('prerender.prerender_soft_http_codes', true)) {
                    $options['allow_redirects'] = false;
                }
                return new Client($options);
            });
    }
}
