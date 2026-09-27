<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rome Slot 🏛️ - Social Casino</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=Cinzel:wght@700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { background: #0c0804; font-family: 'Cinzel', serif; color: white; margin: 0; overflow: hidden; }
        .iframe-container { width: 100vw; height: calc(100vh - 64px); border: none; }
    </style>
</head>
<body class="h-screen flex flex-col justify-between">

    <!-- Top Navigation Bar -->
    <div class="h-16 flex items-center justify-between px-6 bg-stone-950/90 border-b border-amber-500/30 backdrop-blur-md z-50">
        <div class="flex items-center gap-4">
            <a href="/" class="bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-xl text-xs font-bold font-mono text-white transition-all">&larr; BACK TO LOBBY</a>
            <h1 class="text-lg font-extrabold text-amber-400 tracking-wider uppercase flex items-center gap-2 font-serif">
                <span>🏛️</span> ROME SLOT CASINO
            </h1>
        </div>
        <div class="flex items-center gap-3 font-mono">
            <span class="text-xs text-gray-400 uppercase">Cedar Coins Balance:</span>
            <span id="player-balance" class="text-base font-bold text-amber-400 bg-black/80 px-4 py-1 rounded-xl border border-amber-400/40 shadow-[0_0_15px_rgba(245,158,11,0.3)]">
                {{ Auth::check() ? number_format(Auth::user()->balance, 2, '.', '') : '50,000.00' }} CEDARS
            </span>
        </div>
    </div>

    <!-- HTML5 Phaser Rome Slot Arena -->
    <iframe id="game-frame" src="/games/RomeSlot/index.html" class="iframe-container"></iframe>

    <script>
        let currentBalance = {{ Auth::check() ? Auth::user()->balance : 50000 }};

        document.addEventListener('DOMContentLoaded', function() {
            const iframe = document.getElementById('game-frame');
            iframe.onload = function() {
                try {
                    if (iframe.contentWindow.slotConfig_3x5) {
                        iframe.contentWindow.slotConfig_3x5.defaultCoins = currentBalance;
                    }
                } catch(e) {}
            };
        });

        // Sync spin bets/wins from Phaser slot
        window.addEventListener('message', function(e) {
            if (e.data && e.data.type === 'SLOT_SPIN') {
                const bet = parseFloat(e.data.bet) || 0;
                const win = parseFloat(e.data.win) || 0;

                fetch('/game/RomeSlot/server', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ action: 'spin', bet: bet, win: win, sessionId: 'rome_' + Date.now() })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        currentBalance = parseFloat(data.new_balance.replace(/,/g, ''));
                        document.getElementById('player-balance').innerText = currentBalance.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' CEDARS';
                    }
                });
            }
        });
    </script>
</body>
</html>
