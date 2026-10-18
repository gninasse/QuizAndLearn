<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marque blanche + hygiène : aucune réponse n'annonce la pile technique
 * (X-Powered-By: PHP/x.y…). À compléter côté serveur web (expose_php=Off,
 * server_tokens off) — voir DEPLOIEMENT.md.
 */
class HideTechnologyHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        $response = $next($request);
        $response->headers->remove('X-Powered-By');
        foreach (['X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'strict-origin-when-cross-origin'] as $header => $value) {
            if (! $response->headers->has($header)) {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }
}
