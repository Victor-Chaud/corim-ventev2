<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const INLINE_HANDLERS = [
        'sha256-KaAGXblqwqyIx71DZdRgvnHFBwV6fHfxM1G5x3a5/qs=',
        'sha256-OthQGoWU8nQcOg5QHzbCpu11EsiIdnwygFQfIhG+P20=',
        'sha256-biFQTroSCI3Z5BmsMGyEE2jFZdwjjG1Oe7JLytgH6jM=',
        'sha256-R5ef/3nNijX0Wk4oGj7mxtn6G2eLGCqj/rg+Q6r5mYc=',
        'sha256-rRMdkshZyJlCmDX27XnL7g3zXaxv7ei6Sg+yt4R3svU=',
        'sha256-kbHtQyYDQKz4SWMQ8OHVol3EC0t3tHEJFPCSwNG9NxQ=',
        'sha256-loNEcvOiwPZFEqTNJYE+/sdSv0X/6zDcCFyxAG+oGiw=',
    ];

    private const CKEDITOR_PAGES = ['flash_info/add', 'flash_info/edit/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if (! $response->headers->has('Content-Security-Policy') && str_starts_with((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')) {
            $response->headers->set('Content-Security-Policy', $this->policy($request, $nonce));
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private function policy(Request $request, string $nonce): string
    {
        $inline = $request->is(...self::CKEDITOR_PAGES)
            ? "'unsafe-inline'"
            : "'nonce-{$nonce}' 'unsafe-hashes' '".implode("' '", self::INLINE_HANDLERS)."'";

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' {$inline} 'unsafe-eval' https://cdn.ckeditor.com",
            "style-src 'self' 'unsafe-inline' https://cdn.ckeditor.com",
            "img-src 'self' data: https://cdn.ckeditor.com",
            "font-src 'self' https://cdn.ckeditor.com",
            "connect-src 'self' https://cke4.ckeditor.com",
            "frame-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
