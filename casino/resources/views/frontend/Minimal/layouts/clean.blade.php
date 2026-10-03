<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover"/>
    <!-- Progressive Web App (PWA) Meta & Manifest -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0b0e14">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ settings('app_name', 'Promex Gaming Suite') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @php
        // Real provider cover art for the ambient background. Read-only, no seeded data.
        // Mobile gets 4 tiles instead of 6: each cover is ~250 KB, and phones are the
        // slowest clients, so the extra depth is not worth the download.
        $isMobileViewport = (new \Detection\MobileDetect())->isMobile();
        $bgShots = \VanguardLTE\Game::query()
            ->where('view', 1)
            ->whereNotNull('icon_url')->where('icon_url', '!=', '')
            ->whereIn('provider_key', ['pragmatic', 'pgsoft', 'amatic', 'amusnet'])
            ->inRandomOrder()->limit($isMobileViewport ? 4 : 6)->get(['name', 'icon_url'])
            ->map(fn($g) => game_cover($g))->all();
    @endphp

    <title>@yield('page-title', settings('app_name', 'Promex Gaming Suite'))</title>

    <!-- Tailwind CSS (prebuilt, see tailwind.config.js — replaces the old runtime CDN) -->
    @php $twCss = base_path('../minimal/css/tailwind.css'); @endphp
    <link rel="stylesheet" href="/minimal/css/tailwind.css?v={{ file_exists($twCss) ? filemtime($twCss) : '1' }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    
    <style>
        :root {
            --sat: env(safe-area-inset-top);
            --sar: env(safe-area-inset-right);
            --sab: env(safe-area-inset-bottom);
            --sal: env(safe-area-inset-left);
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            display: inline-block;
            vertical-align: middle;
            line-height: 1;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #07090f;
            color: #f8fafc;
            overflow-x: hidden;
            min-height: 100vh;
            min-height: 100dvh;
        }
        /* ============ AMBIENT CASINO BACKGROUND ============ */
        .casino-bg {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
            background:
                radial-gradient(ellipse 80% 55% at 15% -5%, rgba(16, 185, 129, 0.16) 0%, transparent 60%),
                radial-gradient(ellipse 70% 50% at 90% 5%, rgba(6, 182, 212, 0.13) 0%, transparent 60%),
                radial-gradient(ellipse 90% 60% at 50% 105%, rgba(168, 85, 247, 0.10) 0%, transparent 65%),
                linear-gradient(180deg, #07090f 0%, #0a0d15 45%, #07090f 100%);
        }
        /* Layer 1 — real game cover art, lightly blurred so text stays legible.
           Kept deliberately cheap: few tiles, modest blur, paint containment. */
        .bg-shots {
            position: absolute;
            inset: -22% -6% -10% -6%;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-auto-rows: 30vh;
            gap: 30px;
            transform: translate3d(0, var(--sp, 0px), 0);
            filter: blur(13px) saturate(1.1) brightness(1.16);
            opacity: 0.2;
            transition: opacity 0.8s ease;
            will-change: transform;
            contain: strict;
        }
        .casino-bg.is-scrolled .bg-shots { opacity: 0.3; }
        .bg-shot {
            position: relative;
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 30px 90px -30px rgba(0,0,0,0.85);
            animation: shotFloat var(--dur, 26s) ease-in-out infinite;
            animation-delay: var(--del, 0s);
        }
        .bg-shot img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .bg-shot::after {
            content: "";
            position: absolute; inset: 0;
            background: linear-gradient(160deg, rgba(7,9,15,0.05), rgba(7,9,15,0.55));
        }
        .bg-shot:nth-child(4n+1) { --dur: 24s; --del: -3s; }
        .bg-shot:nth-child(4n+2) { --dur: 30s; --del: -11s; }
        .bg-shot:nth-child(4n+3) { --dur: 27s; --del: -7s; }
        .bg-shot:nth-child(4n+4) { --dur: 33s; --del: -16s; }
        @keyframes shotFloat {
            0%,100% { transform: translate3d(0, 0, 0) scale(1.04); }
            50%     { transform: translate3d(0, -26px, 0) scale(1.1); }
        }
        /* Layer 2 — depth scrim keeps the UI readable over the artwork */
        .bg-scrim {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 78% 62% at 50% 28%, rgba(7,9,15,0.2), rgba(7,9,15,0.72) 78%),
                linear-gradient(180deg, rgba(7,9,15,0.55), rgba(7,9,15,0.34) 42%, rgba(7,9,15,0.74));
        }
        /* Layer 3 — soft moving colour wash */
        .bg-wash {
            position: absolute;
            inset: -10%;
            background:
                radial-gradient(circle at 20% 25%, rgba(16,185,129,0.22), transparent 45%),
                radial-gradient(circle at 82% 30%, rgba(6,182,212,0.2), transparent 45%),
                radial-gradient(circle at 50% 90%, rgba(245,158,11,0.16), transparent 48%),
                radial-gradient(circle at 68% 65%, rgba(168,85,247,0.18), transparent 45%);
            animation: washDrift 34s ease-in-out infinite alternate;
            will-change: transform;
        }
        @keyframes washDrift {
            0%   { transform: translate3d(-2%, -1%, 0) scale(1.02); }
            100% { transform: translate3d(3%, 2%, 0) scale(1.08); }
        }
        /* Layer 4 — reward bursts: chips/coins/sparkles popping like live wins */
        .bg-bursts { position: absolute; inset: 0; pointer-events: none; }
        .burst { position: absolute; opacity: 0; will-change: transform, opacity; }
        .burst-chip {
            width: var(--sz, 46px); height: var(--sz, 46px);
            border-radius: 50%;
            background: radial-gradient(circle at 34% 30%, #ffffff, var(--bc, #f59e0b) 42%, #7c2d12 100%);
            box-shadow: 0 0 22px -2px var(--bc, #f59e0b), inset 0 0 0 5px rgba(255,255,255,0.55), inset 0 0 0 8px var(--bc, #f59e0b);
            animation: burstChip var(--dur, 6.5s) cubic-bezier(0.2,0.7,0.3,1) infinite;
            animation-delay: var(--del, 0s);
        }
        .burst-coin {
            width: var(--sz, 30px); height: var(--sz, 30px);
            border-radius: 50%;
            background: radial-gradient(circle at 34% 30%, #fef9c3, #facc15 55%, #b45309 100%);
            box-shadow: 0 0 18px -2px rgba(250,204,21,0.9), inset 0 0 0 3px rgba(255,255,255,0.6);
            animation: burstCoin var(--dur, 7.5s) cubic-bezier(0.2,0.7,0.3,1) infinite;
            animation-delay: var(--del, 0s);
        }
        .burst-spark {
            width: 6px; height: 6px; border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 0 12px 2px var(--bc, #34d399);
            animation: burstSpark var(--dur, 5s) ease-out infinite;
            animation-delay: var(--del, 0s);
        }
        @keyframes burstChip {
            0%   { opacity: 0; transform: translate3d(0, 26px, 0) rotate(0deg) scale(0.5); }
            12%  { opacity: 0.95; }
            70%  { opacity: 0.85; }
            100% { opacity: 0; transform: translate3d(var(--dx, 40px), -150px, 0) rotate(220deg) scale(1.05); }
        }
        @keyframes burstCoin {
            0%   { opacity: 0; transform: translate3d(0, 14px, 0) scale(0.4); }
            14%  { opacity: 0.9; }
            68%  { opacity: 0.7; }
            100% { opacity: 0; transform: translate3d(var(--dx, -30px), -190px, 0) scale(1); }
        }
        @keyframes burstSpark {
            0%   { opacity: 0; transform: translate3d(0, 0, 0) scale(0.2); }
            25%  { opacity: 1; transform: translate3d(var(--dx, 20px), -40px, 0) scale(1.3); }
            100% { opacity: 0; transform: translate3d(calc(var(--dx, 20px) * 1.6), -120px, 0) scale(0.4); }
        }
        /* Win ticker strip */
        .bg-ticker {
            position: absolute; left: 0; right: 0; bottom: 8px;
            display: flex; justify-content: center; gap: 26px;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase;
            color: rgba(148, 163, 184, 0.0);
        }
        .bg-ticker span { animation: tickerFade var(--dur, 9s) ease-in-out infinite; animation-delay: var(--del, 0s); }
        @keyframes tickerFade {
            0%, 70%, 100% { opacity: 0; transform: translateY(6px); }
            20%, 45%      { opacity: 0.55; transform: translateY(0); }
        }
        .casino-bg.is-paused .bg-shot,
        .casino-bg.is-paused .bg-wash,
        .casino-bg.is-paused .burst,
        .casino-bg.is-paused .bg-ticker span { animation-play-state: paused !important; }
        @media (prefers-reduced-motion: reduce) {
            .bg-shot, .bg-wash, .burst, .bg-ticker span { animation: none !important; }
            .bg-shots { transform: none !important; }
        }
        @media (max-width: 767px) {
            .bg-shots { grid-template-columns: repeat(2, 1fr); gap: 22px; opacity: 0.22; filter: blur(18px) brightness(1.12); }
            .bg-ticker { display: none; }
            .burst-chip, .burst-coin { --sz: 30px; }
        }

        /* ============ MODERN HERO SLIDER ============ */
        .hero-slider {
            position: relative;
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.10);
            box-shadow: 0 30px 80px -30px rgba(0,0,0,0.85), inset 0 1px 0 rgba(255,255,255,0.06);
            isolation: isolate;
        }
        .hero-track {
            position: relative;
            height: 300px;
        }
        @media (min-width: 640px) { .hero-track { height: 360px; } }
        @media (min-width: 1024px) { .hero-track { height: 400px; } }
        .hero-slide {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            padding: 26px 28px;
            opacity: 0;
            transform: translateX(6%) scale(1.02);
            transition: opacity 0.7s cubic-bezier(0.22,1,0.36,1), transform 0.7s cubic-bezier(0.22,1,0.36,1);
            pointer-events: none;
        }
        @media (min-width: 768px) { .hero-slide { padding: 40px 48px; } }
        .hero-slide.is-active {
            opacity: 1;
            transform: translateX(0) scale(1);
            pointer-events: auto;
            z-index: 2;
        }
        .hero-slide::after {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 34px 34px;
            opacity: 0.5;
            mask-image: radial-gradient(ellipse 60% 90% at 80% 50%, #000, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse 60% 90% at 80% 50%, #000, transparent 75%);
            z-index: -1;
        }
        .hero-dots { position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%);
                     display: flex; gap: 8px; z-index: 5; }
        .hero-dot { width: 9px; height: 9px; border-radius: 9999px; border: none; cursor: pointer;
                    background: rgba(255,255,255,0.28); transition: all 0.35s; padding: 0; }
        .hero-dot.is-active { width: 30px; background: linear-gradient(90deg,#10b981,#34d399); }
        .hero-nav {
            position: absolute; top: 50%; transform: translateY(-50%);
            width: 42px; height: 42px; border-radius: 9999px; z-index: 5;
            display: none; align-items: center; justify-content: center; cursor: pointer;
            background: rgba(9,12,18,0.6); border: 1px solid rgba(255,255,255,0.16);
            color: #fff; backdrop-filter: blur(10px); transition: all 0.25s;
        }
        @media (min-width: 768px) { .hero-nav { display: flex; } }
        .hero-nav:hover { background: rgba(16,185,129,0.9); border-color: transparent; }
        .hero-nav.prev { left: 16px; }
        .hero-nav.next { right: 16px; }

        /* ============ PROVIDER TILES ============ */
        .provider-tile {
            position: relative;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 178px;
            height: 78px;
            padding: 0 16px;
            border-radius: 18px;
            background: transparent;
            border: 1px solid transparent;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: rgba(255,255,255,0.86);
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), background 0.3s, box-shadow 0.3s;
            overflow: hidden;
            text-decoration: none;
            cursor: pointer;
        }
        .provider-tile::before {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: radial-gradient(circle at 50% 135%, var(--pc, #10b981), transparent 72%);
            opacity: 0;
            transition: opacity 0.35s;
        }
        .provider-tile:hover {
            transform: translateY(-6px) scale(1.05);
            background: rgba(255,255,255,0.05);
            box-shadow: 0 18px 44px -18px var(--pc, #10b981), inset 0 0 0 1px rgba(255,255,255,0.10);
            color: #fff;
        }
        .provider-tile:hover::before { opacity: 0.28; }
        .provider-tile span { position: relative; z-index: 1; }
        .provider-logo {
            position: relative;
            z-index: 1;
            height: 46px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            opacity: 0.82;
            filter: drop-shadow(0 6px 16px rgba(0,0,0,0.5)) saturate(1.05);
            transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.35s, filter 0.35s;
        }
        .provider-tile:hover .provider-logo {
            transform: scale(1.08);
            opacity: 1;
            filter: drop-shadow(0 10px 22px rgba(0,0,0,0.6)) brightness(1.06);
        }

        .marquee-mask {
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
            mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
        }
        .marquee-track {
            display: flex;
            gap: 14px;
            width: max-content;
            animation: marquee 42s linear infinite;
        }
        .marquee-mask:hover .marquee-track { animation-play-state: paused; }
        @keyframes marquee {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        /* ============ DEPOSIT METHOD TILES ============ */
        .deposit-method-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 12px 8px;
            border-radius: 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--muted, rgba(255,255,255,0.7));
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .deposit-method-tile:hover {
            background: rgba(255,255,255,0.07);
            color: #fff;
        }
        .deposit-method-tile.is-active {
            background: rgba(16,185,129,0.12);
            border-color: rgba(16,185,129,0.4);
            color: #34d399;
            box-shadow: 0 10px 30px -14px rgba(16,185,129,0.6);
        }
        .deposit-method-tile .material-symbols-outlined { font-size: 26px; }
        .deposit-method-tile-label { font-size: 11px; font-weight: 700; text-align: center; line-height: 1.2; }

        .deposit-info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
        }
        .deposit-info-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgba(255,255,255,0.45);
            flex-shrink: 0;
            min-width: 92px;
        }
        .deposit-info-value {
            flex: 1;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
            word-break: break-all;
        }
        .deposit-copy-btn {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 9px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.6);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .deposit-copy-btn:hover { background: rgba(16,185,129,0.15); color: #34d399; }
        .deposit-copy-btn .material-symbols-outlined { font-size: 16px; }

        /* ============ LIVE WINS TICKER ============ */
        .wins-bar {
            position: relative;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 16px;
            border-radius: 18px;
            background: linear-gradient(90deg, rgba(13,17,25,0.9), rgba(13,17,25,0.72));
            border: 1px solid rgba(255,255,255,0.07);
            overflow: hidden;
        }
        .wins-bar::after {
            content: "";
            position: absolute; inset: 0; pointer-events: none;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent);
            transform: translateX(-100%);
            animation: winsSheen 6s ease-in-out infinite;
        }
        @keyframes winsSheen { 0%,100% { transform: translateX(-100%); } 55% { transform: translateX(100%); } }
        .wins-label {
            display: flex; align-items: center; gap: 7px; flex-shrink: 0;
            font-size: 11px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase;
            color: #fbbf24;
        }
        .wins-live {
            width: 7px; height: 7px; border-radius: 50%; background: #22c55e;
            box-shadow: 0 0 0 0 rgba(34,197,94,0.7);
            animation: winsPulse 1.8s ease-out infinite;
        }
        @keyframes winsPulse {
            0%   { box-shadow: 0 0 0 0 rgba(34,197,94,0.7); }
            70%  { box-shadow: 0 0 0 9px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }
        .win-item {
            display: inline-flex; align-items: center; gap: 9px;
            padding: 5px 12px 5px 5px;
            border-radius: 14px;
            background: rgba(255,255,255,0.035);
            border: 1px solid rgba(255,255,255,0.06);
            white-space: nowrap;
        }
        .win-avatar {
            width: 30px; height: 30px; border-radius: 10px; flex-shrink: 0;
            display: grid; place-items: center;
            font-size: 13px; font-weight: 800; color: #fff;
            background: linear-gradient(135deg, var(--c1, #10b981), var(--c2, #06b6d4));
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3);
        }
        .win-meta { display: flex; flex-direction: column; line-height: 1.15; }
        .win-name { font-size: 11.5px; font-weight: 700; color: #e6ebf3; }
        .win-game {
            display: flex; align-items: center; gap: 5px;
            font-size: 10px; color: #7f8ea3; font-weight: 500;
        }
        .win-game i { width: 5px; height: 5px; border-radius: 50%; background: var(--c1, #10b981); opacity: 0.9; }
        .win-amount {
            font-size: 12.5px; font-weight: 800; letter-spacing: -0.01em;
            color: #34d399; text-shadow: 0 0 14px rgba(52,211,153,0.35);
        }
        .win-amount.is-multi { color: #fbbf24; text-shadow: 0 0 14px rgba(251,191,36,0.35); }

        /* ============ MODERN BUTTONS ============ */
        .btn-glow {
            position: relative;
            overflow: hidden;
            font-weight: 800;
            letter-spacing: 0.02em;
            border-radius: 14px;
            transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s, filter 0.25s;
        }
        .btn-glow::after {
            content: "";
            position: absolute;
            top: 0; left: -120%;
            width: 60%; height: 100%;
            background: linear-gradient(100deg, transparent, rgba(255,255,255,0.45), transparent);
            transform: skewX(-20deg);
            transition: left 0.6s ease;
        }
        .btn-glow:hover::after { left: 140%; }
        .btn-glow:hover { transform: translateY(-2px); filter: brightness(1.08); }
        .btn-glow:active { transform: translateY(0) scale(0.98); }

        /* ============ GAME CARD MODERN ============ */
        .game-card {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            background: #10141f;
            border: 1px solid rgba(255,255,255,0.07);
            transition: transform 0.35s cubic-bezier(0.34,1.4,0.64,1), box-shadow 0.35s, border-color 0.35s;
        }
        .game-card:hover {
            transform: translateY(-8px);
            border-color: rgba(16,185,129,0.55);
            box-shadow: 0 24px 48px -18px rgba(16,185,129,0.55);
        }
        .game-card .card-shine {
            position: absolute;
            top: 0; left: -120%;
            width: 55%; height: 100%;
            background: linear-gradient(100deg, transparent, rgba(255,255,255,0.28), transparent);
            transform: skewX(-20deg);
            transition: left 0.7s ease;
            z-index: 20;
            pointer-events: none;
        }
        .game-card:hover .card-shine { left: 150%; }

        .text-gradient {
            background: linear-gradient(92deg, #34d399, #22d3ee 55%, #f59e0b);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .section-title-bar {
            width: 4px; height: 26px; border-radius: 9999px;
            background: linear-gradient(180deg,#10b981,#06b6d4);
        }
        .pulse-dot { position: relative; }
        .pulse-dot::after {
            content: ""; position: absolute; inset: 0; border-radius: 9999px;
            background: inherit; animation: pulseRing 1.8s ease-out infinite;
        }
        @keyframes pulseRing {
            0% { transform: scale(1); opacity: 0.7; }
            100% { transform: scale(2.6); opacity: 0; }
        }
        .font-mono-jet {
            font-family: 'JetBrains Mono', monospace;
        }
        /* Glass & Card Surfaces */
        .glass-card {
            background: rgba(18, 22, 32, 0.75);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.07);
            box-shadow: 0 4px 24px -1px rgba(0, 0, 0, 0.35);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            border-color: rgba(255, 255, 255, 0.15);
        }
        .glass-panel {
            background: rgba(15, 19, 28, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        /* Subtle Luxury Glows */
        .glow-emerald {
            box-shadow: 0 0 24px rgba(16, 185, 129, 0.25);
        }
        .glow-gold {
            box-shadow: 0 0 24px rgba(245, 158, 11, 0.25);
        }
        .glow-cyan {
            box-shadow: 0 0 24px rgba(6, 182, 212, 0.25);
        }
        /* Custom Ultra-Clean Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #0b0e14;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(16, 185, 129, 0.5);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        /* Modal Backdrop & Dialog */
        .modal {
            display: none !important;
            position: fixed;
            z-index: 99999;
            inset: 0;
            background: rgba(5, 7, 11, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal.active, .modal.show {
            display: flex !important;
        }
        .modal-content {
            background: #121622;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 28px;
            max-width: 480px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.7);
            color: #f8fafc;
        }
        @media (max-width: 640px) {
            .modal-content {
                padding: 20px;
                border-radius: 20px;
            }
        }
        .close-modal {
            position: absolute;
            top: 18px;
            right: 20px;
            font-size: 22px;
            color: #94a3b8;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            transition: all 0.2s;
        }
        .close-modal:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.15);
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 11px;
            margin-bottom: 6px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            background: #0b0e14;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #ffffff;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }
        .btn-primary {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.35);
            transition: all 0.2s;
        }
        .btn-primary:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        /* Mobile Safe-Area Utilities */
        .pb-safe {
            padding-bottom: calc(env(safe-area-inset-bottom, 16px) + 64px);
        }
    </style>

    @yield('styles')
</head>
<body class="text-on-surface bg-background flex flex-col lg:flex-row min-h-screen custom-scrollbar w-full overflow-x-hidden antialiased selection:bg-primary/20 selection:text-primary">

    <!-- Ambient Casino Background -->
    <div class="casino-bg" aria-hidden="true">
        @if(!empty($bgShots))
        <div class="bg-shots">
            @foreach($bgShots as $shot)
                <div class="bg-shot">
                    <img src="{{ $shot }}" alt="" loading="lazy" decoding="async"
                         onerror="this.closest('.bg-shot').style.display='none'">
                </div>
            @endforeach
        </div>
        @endif
        <div class="bg-wash"></div>
        <div class="bg-scrim"></div>
        <div class="bg-bursts">
            @php
                $bursts = [
                    ['chip', 12, 22, '#f59e0b', 46, -34, '7.5s', '0s'],
                    ['coin', 24, 68, '#facc15', 26,  38, '6.4s', '1.2s'],
                    ['chip', 33, 14, '#10b981', 38,  26, '8.2s', '2.6s'],
                    ['coin', 46, 82, '#facc15', 22, -22, '7.0s', '0.6s'],
                    ['chip', 57, 30, '#06b6d4', 42,  40, '7.8s', '3.4s'],
                    ['coin', 64, 58, '#fbbf24', 28, -36, '6.8s', '1.9s'],
                    ['chip', 74, 20, '#a855f7', 34, -28, '8.6s', '2.2s'],
                    ['coin', 83, 74, '#facc15', 24,  30, '7.2s', '4.1s'],
                    ['chip', 90, 44, '#f43f5e', 36,  34, '7.6s', '3.0s'],
                ];
                $sparks = [
                    [18, 40, '#34d399',  18, '4.6s', '0.4s'],
                    [39, 55, '#22d3ee', -16, '5.2s', '1.6s'],
                    [62, 35, '#fbbf24',  22, '4.9s', '2.8s'],
                    [80, 60, '#a78bfa', -20, '5.6s', '1.1s'],
                ];
            @endphp
            @foreach($bursts as $b)
                <span class="burst burst-{{ $b[0] }}"
                      style="left:{{ $b[1] }}%;top:{{ $b[2] }}%;--sz:{{ $b[4] }}px;--bc:{{ $b[3] }};--dx:{{ $b[5] }}px;--dur:{{ $b[6] }};--del:{{ $b[7] }};"></span>
            @endforeach
            @foreach($sparks as $sp)
                <span class="burst burst-spark"
                      style="left:{{ $sp[0] }}%;top:{{ $sp[1] }}%;--bc:{{ $sp[2] }};--dx:{{ $sp[3] }}px;--dur:{{ $sp[4] }};--del:{{ $sp[5] }};"></span>
            @endforeach
        </div>
        <div class="bg-ticker">
            <span style="--dur:9s;--del:0s;">2.000+ Oyun</span>
            <span style="--dur:11s;--del:2.2s;">Anında TRY Odeme</span>
            <span style="--dur:10s;--del:4.4s;">Lisansli Saglayicilar</span>
            <span style="--dur:12s;--del:6.6s;">7/24 Canli Destek</span>
        </div>
    </div>

    <!-- Navigation (Sidebar + Mobile Header + Mobile Sheet + Bottom Dock) -->
    @include('frontend.Minimal.partials.navbar')

    <!-- Content + Footer column (keeps the footer under the content, not beside it) -->
    <div class="flex-1 w-full min-w-0 flex flex-col">
    <!-- Main Content Area -->
    <main class="flex-1 w-full min-w-0 min-h-screen relative flex flex-col pb-28 lg:pb-14">
        <!-- Desktop top-right account area (transparent dropdown) -->
        <div class="hidden lg:flex items-center justify-end gap-2.5 px-6 md:px-10 lg:px-12 pt-5 relative z-50">
            @guest
                <button type="button" class="open-modal text-xs font-bold uppercase tracking-wider text-white bg-white/[0.06] hover:bg-white/[0.12] border border-white/[0.10] px-4 py-2.5 rounded-xl transition-all" data-target="modal-login">
                    Giriş Yap
                </button>
                <button type="button" class="open-modal text-xs font-bold uppercase tracking-wider text-white bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 px-4 py-2.5 rounded-xl shadow-md shadow-emerald-500/25 transition-all" data-target="modal-register">
                    Ücretsiz Kayıt Ol
                </button>
            @else
                <div class="flex items-center gap-2 bg-white/[0.05] border border-white/[0.09] px-3 py-2 rounded-xl backdrop-blur-md">
                    <span class="material-symbols-outlined text-primary text-base">account_balance_wallet</span>
                    <span class="font-mono-jet text-xs font-bold text-primary" id="topbar-balance">{{ number_format(Auth::user()->balance, 2) }}</span>
                    <span class="text-[10px] font-bold text-primary-light">{{ strtoupper(settings('default_currency') ?: 'TRY') }}</span>
                </div>

                <div class="relative" id="account-menu-wrap">
                    <button type="button" id="account-menu-toggle" aria-haspopup="true" aria-expanded="false"
                            class="flex items-center gap-2 text-xs font-bold text-white bg-white/[0.05] hover:bg-white/[0.12] border border-white/[0.10] px-3 py-2 rounded-xl transition-all backdrop-blur-md">
                        <span class="w-6 h-6 rounded-lg bg-gradient-to-tr from-emerald-400 to-teal-600 flex items-center justify-center text-[11px] font-extrabold">{{ strtoupper(substr(Auth::user()->username ?? 'U', 0, 1)) }}</span>
                        <span class="max-w-[130px] truncate">{{ Auth::user()->username ?? Auth::user()->email }}</span>
                        <span class="material-symbols-outlined text-base text-on-surface-muted">expand_more</span>
                    </button>

                    <div id="account-menu" class="hidden absolute right-0 mt-2 w-60 rounded-2xl overflow-hidden border border-white/[0.10] bg-white/[0.06] backdrop-blur-2xl shadow-2xl shadow-black/50">
                        <div class="px-4 py-3 border-b border-white/[0.08]">
                            <p class="text-[10px] uppercase tracking-wider text-on-surface-subtle font-bold">Hesap</p>
                            <p class="text-sm font-bold text-white truncate">{{ Auth::user()->username ?? Auth::user()->email }}</p>
                            <div class="mt-1.5 flex items-baseline gap-1.5">
                                <span class="font-mono-jet text-lg font-bold text-primary" id="account-menu-balance">{{ number_format(Auth::user()->balance, 2) }}</span>
                                <span class="text-[10px] font-bold text-primary-light">{{ strtoupper(settings('default_currency') ?: 'TRY') }}</span>
                            </div>
                        </div>
                        <nav class="py-1.5 text-sm">
                            <button type="button" class="account-menu-item open-modal w-full text-left px-4 py-2.5 flex items-center gap-3 text-on-surface-muted hover:text-white hover:bg-white/[0.07] transition-colors" data-target="modal-profile">
                                <span class="material-symbols-outlined text-lg">person</span> Profilim
                            </button>
                            <button type="button" class="account-menu-item open-modal w-full text-left px-4 py-2.5 flex items-center gap-3 text-on-surface-muted hover:text-white hover:bg-white/[0.07] transition-colors" data-target="modal-deposit">
                                <span class="material-symbols-outlined text-lg">add_card</span> Bakiye Yükle
                            </button>
                            @if(settings('enable_cashout', '1') == '1')
                            <button type="button" class="account-menu-item open-modal w-full text-left px-4 py-2.5 flex items-center gap-3 text-on-surface-muted hover:text-white hover:bg-white/[0.07] transition-colors" data-target="modal-cashout">
                                <span class="material-symbols-outlined text-lg">payments</span> Para Çekme
                            </button>
                            @endif
                            <a href="{{ route('frontend.vip.index') }}" class="account-menu-item w-full text-left px-4 py-2.5 flex items-center gap-3 text-on-surface-muted hover:text-white hover:bg-white/[0.07] transition-colors no-underline">
                                <span class="material-symbols-outlined text-lg">workspace_premium</span> VIP Kulübü
                            </a>
                            <a href="{{ route('frontend.affiliates.index') }}" class="account-menu-item w-full text-left px-4 py-2.5 flex items-center gap-3 text-on-surface-muted hover:text-white hover:bg-white/[0.07] transition-colors no-underline">
                                <span class="material-symbols-outlined text-lg">groups</span> Ortaklık
                            </a>
                            <div class="my-1 border-t border-white/[0.08]"></div>
                            <a href="{{ route('frontend.auth.logout') }}" class="account-menu-item w-full text-left px-4 py-2.5 flex items-center gap-3 text-rose-400 hover:text-rose-300 hover:bg-white/[0.07] transition-colors no-underline">
                                <span class="material-symbols-outlined text-lg">logout</span> Çıkış Yap
                            </a>
                        </nav>
                    </div>
                </div>
            @endguest
        </div>

        <div class="px-3.5 sm:px-6 md:px-10 lg:px-12 py-5 sm:py-8 md:py-10 space-y-6 sm:space-y-8 md:space-y-10 max-w-7xl w-full min-w-0 mx-auto">
            @yield('content')
        </div>
    </main>

    @include('frontend.Minimal.partials.footer')
    </div>

    <!-- Global Modals (Auth, WhatsApp OTP, Profile, Wallet Refill) -->
    @include('frontend.Minimal.partials.modals')

    <script src="/frontend/Default/js/jquery-3.4.1.min.js"></script>
    <script src="/minimal/js/app.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').then(function(reg) {
                    console.log('PWA ServiceWorker registered cleanly!');
                }).catch(function(err) {
                    console.log('PWA ServiceWorker error: ', err);
                });
            });
        }

        // Automatic Local Timezone Formatter for <time class="local-time" datetime="...">
        document.addEventListener('DOMContentLoaded', function() {
            const timeElems = document.querySelectorAll('time.local-time');
            timeElems.forEach(elem => {
                const isoStr = elem.getAttribute('datetime');
                if (!isoStr) return;
                try {
                    const d = new Date(isoStr);
                    const fmt = elem.dataset.format || 'time';
                    if (fmt === 'time') {
                        elem.innerText = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    } else if (fmt === 'full') {
                        elem.innerText = d.toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    }
                } catch (err) {
                    console.error('Timezone format error:', err);
                }
            });
        });

        // Transparent top-right account dropdown
        document.addEventListener('DOMContentLoaded', function() {
            var toggle = document.getElementById('account-menu-toggle');
            var menu = document.getElementById('account-menu');
            var wrap = document.getElementById('account-menu-wrap');
            if (!toggle || !menu || !wrap) return;

            function closeMenu() {
                menu.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            }

            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden', isOpen);
                toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });
            document.addEventListener('click', function(e) {
                if (!wrap.contains(e.target)) closeMenu();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeMenu();
            });
        });
    </script>
    @yield('scripts')
    <script>
        // Ambient background parallax + idle pause. The artwork drifts as the page
        // scrolls, and all background animations stop while the tab is hidden so the
        // page never burns CPU it isn't using.
        (function () {
            var bg = document.querySelector('.casino-bg');
            var shots = document.querySelector('.bg-shots');
            if (!bg) return;
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            document.addEventListener('visibilitychange', function () {
                bg.classList.toggle('is-paused', document.hidden);
            });
            if (reduce || !shots) return;
            var ticking = false;
            var lastY = -1;
            var MAX = 260;
            function apply() {
                ticking = false;
                var y = window.scrollY || window.pageYOffset || 0;
                if (y === lastY) return;
                lastY = y;
                // Scale the drift down and cap it: a cheap, GPU-composited translate
                // that never turns into a big repaint on long pages.
                var offset = Math.min(y * 0.25, MAX);
                shots.style.setProperty('--sp', offset.toFixed(1) + 'px');
                bg.classList.toggle('is-scrolled', y > 40);
            }
            window.addEventListener('scroll', function () {
                if (!ticking) { ticking = true; requestAnimationFrame(apply); }
            }, { passive: true });
            apply();
        })();
    </script>
</body>
</html>
