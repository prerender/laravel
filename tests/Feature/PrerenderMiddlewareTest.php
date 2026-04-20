<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Request;
use Prerender\Laravel\PrerenderMiddleware;

const BOT_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36';
const PRERENDERED_HTML = '<html><body>prerendered</body></html>';

function makeMiddleware(array $guzzleResponses = []): PrerenderMiddleware
{
    config([
        'prerender.prerender_url'          => 'https://service.prerender.io',
        'prerender.prerender_token'         => null,
        'prerender.prerender_soft_http_codes' => true,
        'prerender.full_url'               => false,
        'prerender.timeout'                => 0,
        'prerender.whitelist'              => [],
        'prerender.blacklist'              => ['*.js', '*.css', '*.png'],
        'prerender.crawler_user_agents'    => ['googlebot', 'bingbot', 'twitterbot'],
    ]);

    $client = new Client(['handler' => HandlerStack::create(new MockHandler($guzzleResponses))]);
    return new PrerenderMiddleware($client);
}

it('passes browser requests through', function () {
    $middleware = makeMiddleware();
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => BROWSER_UA]);

    $response = $middleware->handle($request, fn () => response('normal response'));

    expect($response->getContent())->toBe('normal response');
});

it('returns prerendered response for bot UA', function () {
    $middleware = makeMiddleware([new Response(200, [], PRERENDERED_HTML)]);
    $request = Request::create('/about', 'GET', [], [], [], ['HTTP_USER_AGENT' => BOT_UA]);

    $response = $middleware->handle($request, fn () => response('normal response'));

    expect($response->getContent())->toBe(PRERENDERED_HTML);
    expect($response->getStatusCode())->toBe(200);
});

it('passes static assets through even with bot UA', function () {
    $middleware = makeMiddleware();
    $request = Request::create('/app.js', 'GET', [], [], [], ['HTTP_USER_AGENT' => BOT_UA]);

    $response = $middleware->handle($request, fn () => response('normal response'));

    expect($response->getContent())->toBe('normal response');
});

it('prerenders when _escaped_fragment_ is present', function () {
    $middleware = makeMiddleware([new Response(200, [], PRERENDERED_HTML)]);
    $request = Request::create('/?_escaped_fragment_=', 'GET', [], [], [], ['HTTP_USER_AGENT' => BROWSER_UA]);

    $response = $middleware->handle($request, fn () => response('normal response'));

    expect($response->getContent())->toBe(PRERENDERED_HTML);
});

it('prerenders when X-BUFFERBOT header is present', function () {
    $middleware = makeMiddleware([new Response(200, [], PRERENDERED_HTML)]);
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => BROWSER_UA,
        'X-BUFFERBOT'     => 'true',
    ]);

    $response = $middleware->handle($request, fn () => response('normal response'));

    expect($response->getContent())->toBe(PRERENDERED_HTML);
});

it('passes POST requests through', function () {
    $middleware = makeMiddleware();
    $request = Request::create('/', 'POST', [], [], [], ['HTTP_USER_AGENT' => BOT_UA]);

    $response = $middleware->handle($request, fn () => response('normal response'));

    expect($response->getContent())->toBe('normal response');
});

it('falls back gracefully on connection error', function () {
    $error = new ConnectException('connection refused', new GuzzleRequest('GET', '/'));
    $middleware = makeMiddleware([$error]);
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => BOT_UA]);

    $response = $middleware->handle($request, fn () => response('normal response'));

    expect($response->getContent())->toBe('normal response');
});
