<?php

namespace Prerender\Laravel;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Response;

class PrerenderMiddleware
{
    private bool $returnSoftHttpCodes;
    private bool $useFullURL;
    private string $prerenderUrl;
    private ?string $prerenderToken;
    private array $crawlerUserAgents;
    private array $whitelist;
    private array $blacklist;

    public function __construct(private readonly Client $client)
    {
        $config = config('prerender');
        $this->prerenderUrl = $config['prerender_url'];
        $this->prerenderToken = $config['prerender_token'] ?: null;
        $this->returnSoftHttpCodes = (bool) $config['prerender_soft_http_codes'];
        $this->useFullURL = (bool) $config['full_url'];
        $this->crawlerUserAgents = $config['crawler_user_agents'];
        $this->whitelist = $config['whitelist'];
        $this->blacklist = $config['blacklist'];
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->shouldShowPrerenderedPage($request)) {
            return $next($request);
        }

        $prerenderResponse = $this->getPrerenderedPageResponse($request);

        if (! $prerenderResponse) {
            return $next($request);
        }

        $statusCode = $prerenderResponse->getStatusCode();

        if (! $this->returnSoftHttpCodes && $statusCode >= 300 && $statusCode < 400) {
            $location = array_change_key_case($prerenderResponse->getHeaders(), CASE_LOWER)['location'][0] ?? '/';
            return redirect($location, $statusCode);
        }

        return (new HttpFoundationFactory)->createResponse($prerenderResponse);
    }

    private function shouldShowPrerenderedPage(Request $request): bool
    {
        if (! $request->isMethod('GET')) return false;

        $userAgent = strtolower($request->server->get('HTTP_USER_AGENT', ''));
        if (empty($userAgent)) return false;

        if (! $this->isEligibleForPrerender($request, $userAgent)) return false;

        if ($this->whitelist && ! $this->isListed($request->getRequestUri(), $this->whitelist)) {
            return false;
        }

        $uris = array_values(array_filter([$request->getRequestUri(), $request->headers->get('Referer')]));
        if ($this->blacklist && $this->isListed($uris, $this->blacklist)) return false;

        return true;
    }

    private function isEligibleForPrerender(Request $request, string $userAgent): bool
    {
        if ($request->query->has('_escaped_fragment_')) return true;
        if ($request->server->get('X-BUFFERBOT')) return true;
        return collect($this->crawlerUserAgents)
            ->contains(fn ($agent) => Str::contains($userAgent, strtolower($agent)));
    }

    private function getPrerenderedPageResponse(Request $request): ?ResponseInterface
    {
        $headers = ['User-Agent' => $request->server->get('HTTP_USER_AGENT')];
        if ($this->prerenderToken) {
            $headers['X-Prerender-Token'] = $this->prerenderToken;
        }

        try {
            return $this->client->get($this->buildApiUrl($request), compact('headers'));
        } catch (RequestException $e) {
            if (! $this->returnSoftHttpCodes && $e->getResponse()?->getStatusCode() === 404) {
                abort(404);
            }
            return null;
        } catch (ConnectException) {
            return null;
        }
    }

    private function buildApiUrl(Request $request): string
    {
        return rtrim($this->prerenderUrl, '/') . '/' . $this->generatePrerenderUrl($request);
    }

    private function generatePrerenderUrl(Request $request): string
    {
        if ($this->useFullURL) {
            return $request->fullUrl();
        }
        return $request->getScheme() . '://' . $request->getHost() . $request->getRequestUri();
    }

    private function isListed(string|array $needles, array $list): bool
    {
        return collect($list)->contains(
            fn ($pattern) => collect((array) $needles)->contains(fn ($needle) => Str::is($pattern, $needle))
        );
    }
}
