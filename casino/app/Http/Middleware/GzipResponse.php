<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * gzip the HTML/JSON/CSS/JS responses when no web server does it for us.
 *
 * Production runs behind nginx, which gzips (see deploy/nginx-casino.conf.example)
 * and sets Content-Encoding after PHP has answered — compressing here too would
 * double-encode. The lobby alone is ~13 MB of markup, so the PHP built-in server
 * (`php -S`, PHP_SAPI === 'cli-server'), which has no gzip of its own, is the one
 * case that needs to do it in-process.
 */
class GzipResponse
{
    private const MIN_BYTES = 1024;

    private const COMPRESSIBLE = [
        'text/html',
        'text/plain',
        'text/css',
        'text/xml',
        'application/javascript',
        'application/json',
        'application/xml',
        'application/rss+xml',
        'image/svg+xml',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (PHP_SAPI !== 'cli-server' || !$this->shouldCompress($request, $response)) {
            return $response;
        }

        $compressed = gzencode($response->getContent(), 5);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->headers->remove('Content-Length');

        return $response;
    }

    private function shouldCompress(Request $request, Response $response): bool
    {
        if (!$response->isSuccessful() || $response->headers->has('Content-Encoding')) {
            return false;
        }

        if ($response instanceof BinaryFileResponse || $response->headers->has('Transfer-Encoding')) {
            return false;
        }

        $type = strtolower((string) $response->headers->get('Content-Type'));
        $compressible = false;
        foreach (self::COMPRESSIBLE as $candidate) {
            if (str_contains($type, $candidate)) {
                $compressible = true;
                break;
            }
        }
        if (!$compressible) {
            return false;
        }

        $content = (string) $response->getContent();
        if (strlen($content) < self::MIN_BYTES) {
            return false;
        }

        return str_contains(strtolower((string) $request->header('Accept-Encoding')), 'gzip');
    }
}
