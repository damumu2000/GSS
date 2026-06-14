<?php

return [
    'headers' => [
        'hsts' => [
            'enabled' => filter_var(env('SECURITY_HEADERS_HSTS_APP', false), FILTER_VALIDATE_BOOL),
            'max_age' => max(0, (int) env('SECURITY_HEADERS_HSTS_MAX_AGE', 31536000)),
            'include_subdomains' => filter_var(env('SECURITY_HEADERS_HSTS_INCLUDE_SUBDOMAINS', true), FILTER_VALIDATE_BOOL),
            'preload' => filter_var(env('SECURITY_HEADERS_HSTS_PRELOAD', false), FILTER_VALIDATE_BOOL),
        ],
    ],
];
