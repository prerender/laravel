# prerender-laravel

Laravel middleware for [Prerender.io](https://prerender.io). Intercepts requests from bots and crawlers and serves prerendered HTML, so your JavaScript-rendered app is fully indexable by search engines and social media scrapers.

Compatible with **Laravel 11+** and **PHP 8.2+**.

## Installation

```bash
composer require prerender/laravel-prerender
```

Publish the config file:

```bash
php artisan vendor:publish --tag=prerender-config
```

## Setup

Add your token to `.env`:

```env
PRERENDER_TOKEN=your-token
```

The middleware registers itself automatically via the service provider.

## Configuration

| Key | Env var | Default | Description |
|-----|---------|---------|-------------|
| `enable` | `PRERENDER_ENABLE` | `true` | Disable entirely (e.g. local dev) |
| `prerender_url` | `PRERENDER_SERVICE_URL` | `https://service.prerender.io` | Service URL (override for self-hosted) |
| `prerender_token` | `PRERENDER_TOKEN` | `null` | Your Prerender.io token |
| `prerender_soft_http_codes` | `PRERENDER_SOFT_HTTP_STATUS_CODES` | `true` | Pass 3xx/404 codes through as-is |
| `full_url` | `PRERENDER_FULL_URL` | `false` | Send full URL including query string |
| `timeout` | `PRERENDER_TIMEOUT` | `0` | Guzzle timeout in seconds (0 = none) |

### Whitelist / Blacklist

Only prerender URLs matching the whitelist (empty = all URLs pass):

```php
'whitelist' => ['/blog/*', '/product/*'],
```

Never prerender URLs matching the blacklist (static assets are blacklisted by default):

```php
'blacklist' => ['*.js', '*.css', '/admin/*'],
```

Patterns support `*` wildcards.

## How it works

Requests are prerendered when **all** of the following are true:

- The HTTP method is `GET`
- The `User-Agent` matches a known bot/crawler (Googlebot, Bingbot, Twitterbot, GPTBot, ClaudeBot, etc.)  
  — OR the URL contains `_escaped_fragment_`  
  — OR the `X-BUFFERBOT` header is present
- The URI is not blacklisted (static assets are excluded by default)
- The URI matches the whitelist (if configured)

If the Prerender service is unreachable, the middleware falls back gracefully.

## License

MIT
