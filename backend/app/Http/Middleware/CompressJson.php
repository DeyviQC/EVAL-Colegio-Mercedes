<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompressJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }
        $content = $response->getContent();
        if (! is_string($content)) {
            return $response;
        }
        if (str_contains($response->headers->get('Content-Type', ''), 'application/json') && ! $response->headers->has('Content-Encoding') && strlen($content) > 1024 && str_contains($request->header('Accept-Encoding', ''), 'gzip') && ! ini_get('zlib.output_compression')) {
            $content = gzencode($content, 6);
            $response->headers->set('Content-Encoding', 'gzip');
            $response->headers->set('Vary', 'Accept-Encoding');
            $response->setContent($content);
        }
        $response->headers->set('Content-Length', (string) strlen($content));

        return $response;
    }
}
