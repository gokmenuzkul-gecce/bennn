<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $game->title ?? 'Promex Remote Game' }}</title>
    <style>
        html,body,#promex-remote-frame{width:100%;height:100%;margin:0;border:0;background:#090d16;overflow:hidden}
        #promex-remote-status{position:fixed;left:12px;bottom:12px;z-index:2;padding:7px 10px;border-radius:8px;background:rgba(9,13,22,.82);color:#fff;font:12px sans-serif}
    </style>
</head>
<body>
<iframe id="promex-remote-frame" src="{{ $launchUrl }}" allow="autoplay; fullscreen; screen-wake-lock" allowfullscreen referrerpolicy="no-referrer"></iframe>
<div id="promex-remote-status">Opening secure game…</div>
<script src="/js/game-session.js?v=6"
        data-csrf="{{ csrf_token() }}"
        data-runtime-game="{{ $game->name }}"></script>
<script>
(() => {
    'use strict';
    const frame = document.getElementById('promex-remote-frame');
    const status = document.getElementById('promex-remote-status');
    const remoteOrigin = @json($remoteOrigin);
    const game = @json($game->name);
    const launchToken = @json($launch['launch_token']);
    const launchExpires = Number(@json($launch['expires_at']));
    let established = false;
    const arcade = @json(in_array($game->name, \VanguardLTE\Services\CedarArcadeService::GAMES, true));
    const allowedActions = new Set(arcade ? ['init','spin','roll','drop','bet','play','status','cashout','reveal','choose','hit','stand'] : ['init', 'spin']);
    window.addEventListener('message', async event => {
        const message = event.data;
        if (event.origin !== remoteOrigin || event.source !== frame.contentWindow
            || !message || message.type !== 'promex:game-request'
            || message.game !== game || typeof message.request_id !== 'string'
            || !/^[a-f0-9]{32}$/.test(message.request_id)
            || !allowedActions.has(message.action)
            || !message.payload || Object.getPrototypeOf(message.payload) !== Object.prototype
            || message.launch_token !== launchToken) return;
        if (!established) {
            if (Math.floor(Date.now() / 1000) > launchExpires) return;
            established = true;
        }
        try {
            await window.PromexGameSession.ready;
            const response = await fetch('/game/' + encodeURIComponent(game) + '/server', {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify(Object.assign({}, message.payload, {action: message.action}))
            });
            const data = await response.json();
            frame.contentWindow.postMessage({
                type: 'promex:game-response', request_id: message.request_id,
                ok: response.ok, status: response.status, data
            }, remoteOrigin);
            status.style.display = 'none';
        } catch (_) {
            frame.contentWindow.postMessage({
                type: 'promex:game-response', request_id: message.request_id,
                ok: false, status: 503, data: {status: 'error', message: 'Secure game bridge unavailable.'}
            }, remoteOrigin);
        }
    });
})();
</script>
</body>
</html>
