<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServeStaticAssets
{
    /**
     * Long-lived public cache for immutable, content-hashed build assets and
     * fingerprinted product artwork. Never applied to API/JSON responses.
     */
    private const CACHE_CONTROL = 'public, max-age=604800';

    private const ASSET_PREFIXES = [
        'storage/',
        'images/',
        'build/',
        'vendor/',
        'favicon.ico',
        'robots.txt',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldCache($request)) {
            $response->headers->set('Cache-Control', self::CACHE_CONTROL);
        }

        return $response;
    }

    private function shouldCache(Request $request): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        $path = trim($request->path(), '/');

        foreach (self::ASSET_PREFIXES as $prefix) {
            if ($prefix === 'favicon.ico' || $prefix === 'robots.txt') {
                if ($path === $prefix) return true;
                continue;
            }
            if (str_starts_with($path, rtrim($prefix, '/'))) {
                return true;
            }
        }

        return false;
    }
}
