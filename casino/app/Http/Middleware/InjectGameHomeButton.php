<?php

namespace VanguardLTE\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectGameHomeButton
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        if (!$response->isSuccessful()) {
            return $response;
        }

        if (stripos($response->headers->get('Content-Type', ''), 'text/html') === false) {
            return $response;
        }

        $content = $response->getContent();
        // Inject before any game scripts, including direct HTTP clients and legacy XHR engines.
        $game = (string)$request->route('game');
        $sessionScript = '<script src="/js/game-session.js?v=6" data-csrf="'
            . htmlspecialchars($request->session()->token(), ENT_QUOTES, 'UTF-8')
            . '" data-runtime-game="' . htmlspecialchars($game, ENT_QUOTES, 'UTF-8')
            . '"></script>';
        if (strpos($content, '/js/game-session.js') === false) {
            $content = preg_replace('/<head\b[^>]*>/i', '$0' . $sessionScript, $content, 1);
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'same-origin');

        $snippet = <<<HTML
<style>
#game-home-btn{position:fixed;top:12px;right:12px;z-index:9999;display:grid;place-items:center;box-sizing:border-box;width:40px;height:40px;padding:0;margin:0;background:rgba(15,17,23,.72);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:50%;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,.25);backdrop-filter:blur(8px);}
#game-home-btn:hover{background:rgba(40,43,51,.95);border-color:rgba(255,255,255,.65);}
#game-home-btn:focus-visible{outline:2px solid #fff;outline-offset:3px;}
#game-home-btn svg{display:block;width:18px;height:18px;pointer-events:none;}
</style>
<button id="game-home-btn" type="button" aria-label="Close game and return home" title="Close game" onclick="window.location.href='/'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
HTML;

        if (strpos($content, 'game-home-btn') === false) {
            $content = str_ireplace('</body>', $snippet . '</body>', $content);
        }

        $response->setContent($content);
        return $response;
    }
}
