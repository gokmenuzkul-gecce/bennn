<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $game->title ?? 'Game Arena' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body, html { width: 100%; height: 100%; overflow: hidden; background: #0b0e17; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        #game-stage { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        #game-frame { width: 100%; height: 100%; border: none; display: block; }
        .floating-controls {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 999999;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-control {
            background: rgba(18, 22, 34, 0.85);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            padding: 8px 14px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        }
        .btn-control:hover {
            background: rgba(0, 230, 153, 0.9);
            color: #000000;
            border-color: rgba(0, 230, 153, 1);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="floating-controls">
        <a href="/" class="btn-control">
            <span>‹</span> Lobby
        </a>
        <button type="button" class="btn-control" onclick="toggleFullscreen()">
            ⛶ Fullscreen
        </button>
    </div>

    @php($aspect = $launch['aspect'] ?? 'auto')
    <div id="game-stage">
        <iframe id="game-frame"
                name="game-frame"
                src="{{ !empty($launch['form']) ? 'about:blank' : $launch['url'] }}"
                allow="autoplay; fullscreen; screen-wake-lock"
                allowfullscreen>
        </iframe>
    </div>

    <script>
        // Fixed-orientation vendor builds (PG Soft's phone session) are fitted
        // to their aspect so the game fills the viewport instead of half of it.
        (function () {
            var aspect = @json($aspect);
            var frame = document.getElementById('game-frame');
            function fit() {
                if (!aspect || aspect === 'auto') {
                    frame.style.width = '100%';
                    frame.style.height = '100%';
                    return;
                }
                var parts = String(aspect).split(':');
                var w = parseFloat(parts[0]), h = parseFloat(parts[1]);
                if (parts.length !== 2 || !w || !h) return;
                var scale = Math.min(window.innerWidth / w, window.innerHeight / h);
                frame.style.width = Math.floor(w * scale) + 'px';
                frame.style.height = Math.floor(h * scale) + 'px';
            }
            fit();
            window.addEventListener('resize', fit);
        })();
    </script>

    @if(!empty($launch['form']))
    <form id="vendor-form" method="POST" action="{{ $launch['form']['action'] }}" target="game-frame" style="display:none">
        @foreach($launch['form']['fields'] as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
    </form>
    <script>document.getElementById('vendor-form').submit();</script>
    @endif

    <script>
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {});
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }
    </script>
</body>
</html>