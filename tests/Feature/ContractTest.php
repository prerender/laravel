<?php

// Contract tests against the shared mock server.
// Spec: https://github.com/prerender/integration-contract
// CI fetches mock-server.mjs into the repo root; locally:
//   curl -fsSL -o mock-server.mjs https://raw.githubusercontent.com/prerender/integration-contract/main/mock-server.mjs

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Prerender\Laravel\PrerenderMiddleware;

const CONTRACT_BOT_UA = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
const CONTRACT_BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36';
const TEST_TOKEN = 'test-token-abc123';
const UUID_V4_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

function findFreePort(): int
{
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 0);
    socket_getsockname($sock, $_addr, $port);
    socket_close($sock);
    return $port;
}

function waitForHealth(string $url, int $attempts = 50): void
{
    for ($i = 0; $i < $attempts; $i++) {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        $body = @file_get_contents("$url/__health", false, $ctx);
        if ($body !== false) return;
        usleep(100000);
    }
    throw new RuntimeException("mock server at $url did not become ready");
}

function mockRecorded(string $url): array
{
    return json_decode(file_get_contents("$url/__requests"), true);
}

function mockReset(string $url): void
{
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true]]);
    file_get_contents("$url/__reset", false, $ctx);
}

beforeAll(function () {
    $mockPath = getenv('MOCK_SERVER_PATH') ?: dirname(__DIR__, 2) . '/mock-server.mjs';
    if (!file_exists($mockPath)) {
        throw new RuntimeException(
            "mock-server.mjs not found at $mockPath; fetch it via curl from prerender/integration-contract"
        );
    }
    $port = findFreePort();
    $cmd = sprintf('PORT=%d node %s > /dev/null 2>&1 & echo $!', $port, escapeshellarg($mockPath));
    $pid = (int) trim(shell_exec($cmd));
    $url = "http://127.0.0.1:$port";
    waitForHealth($url);
    $GLOBALS['__mock_pid'] = $pid;
    $GLOBALS['__mock_url'] = $url;
});

afterAll(function () {
    if (!empty($GLOBALS['__mock_pid'])) {
        posix_kill($GLOBALS['__mock_pid'], SIGTERM);
    }
});

beforeEach(function () {
    mockReset($GLOBALS['__mock_url']);
    config([
        'prerender.prerender_url'           => $GLOBALS['__mock_url'],
        'prerender.prerender_token'         => TEST_TOKEN,
        'prerender.prerender_soft_http_codes' => true,
        'prerender.full_url'                => false,
        'prerender.timeout'                 => 0,
        'prerender.whitelist'               => [],
        'prerender.blacklist'               => ['*.js', '*.css', '*.png'],
        'prerender.crawler_user_agents'     => ['googlebot', 'bingbot'],
    ]);
});

function buildMiddleware(): PrerenderMiddleware
{
    return new PrerenderMiddleware(new Client());
}

function botRequest(string $path = '/'): Request
{
    return Request::create($path, 'GET', [], [], [], ['HTTP_USER_AGENT' => CONTRACT_BOT_UA]);
}

it('bot request emits exactly one outgoing request with required headers', function () {
    buildMiddleware()->handle(botRequest('/blog/post-1'), fn () => response('original'));

    $recorded = mockRecorded($GLOBALS['__mock_url']);
    expect($recorded)->toHaveCount(1);
    $r = $recorded[0];
    expect($r['method'])->toBe('GET');
    expect($r['url'])->toEndWith('/blog/post-1');
    expect($r['headers']['user-agent'])->toBe(CONTRACT_BOT_UA);
    expect($r['headers']['x-prerender-token'])->toBe(TEST_TOKEN);
    expect($r['headers']['x-prerender-int-type'])->toBe('Laravel');
    expect($r['headers']['x-prerender-int-version'])->toMatch('/^\d+\.\d+\.\d+/');
    expect($r['headers']['x-prerender-request-id'])->toMatch(UUID_V4_REGEX);
});

it('browser request emits no outgoing request', function () {
    $req = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => CONTRACT_BROWSER_UA]);
    buildMiddleware()->handle($req, fn () => response('original'));

    expect(mockRecorded($GLOBALS['__mock_url']))->toBeEmpty();
});

it('static asset with bot UA emits no outgoing request', function () {
    buildMiddleware()->handle(botRequest('/styles.css'), fn () => response('original'));

    expect(mockRecorded($GLOBALS['__mock_url']))->toBeEmpty();
});

it('token is omitted when unconfigured', function () {
    config(['prerender.prerender_token' => null]);
    buildMiddleware()->handle(botRequest('/'), fn () => response('original'));

    $recorded = mockRecorded($GLOBALS['__mock_url']);
    expect($recorded)->toHaveCount(1);
    expect($recorded[0]['headers'])->not->toHaveKey('x-prerender-token');
});

it('escaped_fragment query triggers prerender for browser UA', function () {
    $req = Request::create('/?_escaped_fragment_=', 'GET', [], [], [], ['HTTP_USER_AGENT' => CONTRACT_BROWSER_UA]);
    buildMiddleware()->handle($req, fn () => response('original'));

    $recorded = mockRecorded($GLOBALS['__mock_url']);
    expect($recorded)->toHaveCount(1);
    expect($recorded[0]['url'])->toContain('_escaped_fragment_');
});

it('request id is unique per outgoing request', function () {
    buildMiddleware()->handle(botRequest('/'), fn () => response('original'));
    buildMiddleware()->handle(botRequest('/'), fn () => response('original'));

    $recorded = mockRecorded($GLOBALS['__mock_url']);
    expect($recorded)->toHaveCount(2);
    expect($recorded[0]['headers']['x-prerender-request-id'])
        ->not->toBe($recorded[1]['headers']['x-prerender-request-id']);
});
