<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Symfony\Component\HttpFoundation\Cookie;

class ValidateRequestToken extends PreventRequestForgery
{
    public const COOKIE_NAME = 'REQ-TOKEN';

    protected function getTokenFromRequest($request)
    {
        return $request->input('_token')
            ?: $request->header('X-CSRF-TOKEN')
            ?: $request->header('X-REQ-TOKEN');
    }

    protected function newCookie($request, $config)
    {
        return new Cookie(
            self::COOKIE_NAME,
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            $config['path'],
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'] ?? null,
            $config['partitioned'] ?? false
        );
    }
}
