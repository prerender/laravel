<?php

return [
    'enable' => env('PRERENDER_ENABLE', true),
    'prerender_url' => env('PRERENDER_SERVICE_URL', 'https://service.prerender.io'),
    'prerender_token' => env('PRERENDER_TOKEN'),
    'prerender_soft_http_codes' => env('PRERENDER_SOFT_HTTP_STATUS_CODES', true),
    'full_url' => env('PRERENDER_FULL_URL', false),
    'timeout' => env('PRERENDER_TIMEOUT', 0),

    'whitelist' => [],

    'blacklist' => [
        '*.js', '*.css', '*.xml', '*.less', '*.png', '*.jpg', '*.jpeg',
        '*.gif', '*.pdf', '*.doc', '*.txt', '*.ico', '*.rss', '*.zip',
        '*.mp3', '*.rar', '*.exe', '*.wmv', '*.avi', '*.ppt', '*.mpg',
        '*.mpeg', '*.tif', '*.wav', '*.mov', '*.psd', '*.ai', '*.xls',
        '*.mp4', '*.m4a', '*.swf', '*.dat', '*.dmg', '*.iso', '*.flv',
        '*.m4v', '*.torrent', '*.ttf', '*.woff', '*.woff2', '*.svg',
    ],

    'crawler_user_agents' => [
        'googlebot', 'yahoo', 'bingbot', 'baiduspider', 'yandex',
        'facebookexternalhit', 'twitterbot', 'rogerbot', 'linkedinbot',
        'embedly', 'quora link preview', 'showyoubot', 'outbrain',
        'pinterest', 'slackbot', 'w3c_validator', 'redditbot', 'applebot',
        'discordbot', 'perplexity', 'oai-searchbot', 'chatgpt-user',
        'gptbot', 'claudebot', 'amazonbot',
    ],
];
