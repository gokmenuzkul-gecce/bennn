/* ---------------------------------------------------------------------------
 * Promex motion runtime
 *
 * Adds scroll reveals, staggered entrances and micro-interactions to the
 * existing markup without changing any content. Everything is opt-in through
 * CSS classes and driven by a single IntersectionObserver, so the cost stays
 * flat no matter how many elements are on the page.
 *
 * Nothing here is required for the site to work: if the script fails to load
 * the page renders exactly as before.
 * ------------------------------------------------------------------------- */
(function () {
    'use strict';

    var root = document.documentElement;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Reveal animations are pure enhancement, so bail out entirely when the
    // visitor asked for reduced motion. The CSS also neutralises the classes.
    if (reduceMotion) {
        root.classList.add('motion-off');
        return;
    }

    var REVEAL_CLASSES = ['motion-reveal', 'motion-reveal--left', 'motion-reveal--right', 'motion-reveal--scale'];
    var CARD_SELECTOR = '.glass-card, .game-card, .provider-tile, .deposit-method-tile, .stock-card, .crypto-card, .payment-card';
    // Elements that rely on their own transform (sticky positioning, marquee
    // tracks, absolutely positioned overlays) must never be animated.
    var SKIP_SELECTOR = '.no-motion, .casino-bg, .bg-shots, .marquee-track, .modal, .motion-skip';

    var observer = null;

    function hasReveal(el) {
        for (var i = 0; i < REVEAL_CLASSES.length; i++) {
            if (el.classList.contains(REVEAL_CLASSES[i])) return true;
        }
        return false;
    }

    function observe(el) {
        // No IntersectionObserver (or a very old engine): show everything at once
        // rather than leaving content stranded at opacity 0.
        if (!('IntersectionObserver' in window)) {
            el.classList.add('is-inview');
            settle(el);
            return;
        }
        if (!observer) {
            observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-inview');
                        observer.unobserve(entry.target);
                        settle(entry.target);
                    }
                });
            }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
        }
        observer.observe(el);
    }

    // Once an element has settled, strip the reveal classes. The reveal
    // transition carries a stagger delay, and leaving it in place would make
    // every later hover on that element wait out the same delay.
    function settle(el) {
        var delay = parseFloat(el.style.getPropertyValue('--motion-delay')) || 0;
        window.setTimeout(function () {
            el.classList.remove('motion-reveal', 'motion-reveal--left', 'motion-reveal--right', 'motion-reveal--scale', 'is-inview');
            el.style.removeProperty('--motion-delay');
        }, delay + 700);
    }

    function revealElement(el, variant) {
        if (!el || el.dataset.motionReady === '1') return;
        if (el.closest(SKIP_SELECTOR)) return;
        // Sticky/fixed elements are positioned by the browser and must not be
        // nudged by a transform.
        var pos = window.getComputedStyle(el).position;
        if (pos === 'sticky' || pos === 'fixed') return;

        el.dataset.motionReady = '1';
        el.classList.add('motion-reveal');
        if (variant) el.classList.add(variant);
        observe(el);
    }

    // Stagger the direct children of grids and list containers so cards arrive
    // in sequence rather than all at once.
    function staggerContainer(container) {
        if (!container || container.dataset.motionStagger === '1') return;
        if (container.closest(SKIP_SELECTOR)) return;
        container.dataset.motionStagger = '1';

        var kids = container.children;
        for (var i = 0; i < kids.length && i < 24; i++) {
            var kid = kids[i];
            if (kid.nodeType !== 1) continue;
            kid.style.setProperty('--motion-delay', (Math.min(i, 11) * 55) + 'ms');
            kid.classList.add('motion-reveal');
            observe(kid);
        }
    }

    // Grids that receive server- or search-rendered cards on the fly. Only the
    // first rows are staggered: a lobby grid can hold hundreds of covers and
    // staggering all of them would leave the tail invisible for too long.
    function findStaggerContainers(scope) {
        var grids = (scope || document).querySelectorAll('#games-grid, [data-motion-grid]');
        for (var i = 0; i < grids.length; i++) {
            staggerContainer(grids[i]);
        }
    }

    // Tag the page structure once, after the DOM is ready.
    function initStructure() {
        var main = document.querySelector('main');
        if (main) {

            // Pages built from <section> blocks (the lobby) reveal each block.
            var sections = main.querySelectorAll('section');
            for (var i = 0; i < sections.length; i++) {
                var section = sections[i];
                if (section.closest(SKIP_SELECTOR)) continue;
                if (section.classList.contains('no-motion')) continue;
                revealElement(section, null);
            }

            // Pages that do not use <section> still get a staggered entrance
            // from the direct children of the content wrapper.
            var wrapper = main.querySelector('div.max-w-7xl');
            if (wrapper) {
                var blocks = wrapper.children;
                for (var w = 0; w < blocks.length; w++) {
                    if (blocks[w].nodeType !== 1) continue;
                    blocks[w].style.setProperty('--motion-delay', (Math.min(w, 6) * 70) + 'ms');
                    revealElement(blocks[w], null);
                }
            }
        }

        // Headings and standalone blocks rise as the visitor scrolls past them.
        // Headings are skipped when they sit inside a section, which already
        // carries its own reveal, so the two animations do not stack.
        var blocks = document.querySelectorAll('main .glass-card, main .section-title-bar');
        for (var j = 0; j < blocks.length; j++) {
            revealElement(blocks[j], null);
        }

        findStaggerContainers(document);
    }

    // Cards that do not already define their own hover lift get a subtle one.
    function initCardLift() {
        var cards = document.querySelectorAll(CARD_SELECTOR);
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            if (card.dataset.motionLift === '1') continue;
            if (card.closest(SKIP_SELECTOR)) continue;
            // game-card, glass-card and provider-tile already animate on hover.
            if (card.classList.contains('game-card') || card.classList.contains('provider-tile') ||
                card.classList.contains('glass-card') || card.classList.contains('deposit-method-tile')) {
                continue;
            }
            card.dataset.motionLift = '1';
            card.classList.add('motion-card');
        }
    }

    // Tag nav links and dropdown entries so they slide instead of snapping.
    function initNavMotion() {
        var items = document.querySelectorAll('aside nav a, #account-menu > *, .account-menu-item');
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            if (item.classList.contains('motion-nav-item') || item.classList.contains('motion-dropdown-item')) continue;
            if (item.closest('#account-menu')) {
                item.classList.add('motion-dropdown-item');
            } else {
                item.classList.add('motion-nav-item');
            }
        }
    }

    // The account dropdown is a plain hidden/shown element; give it the slide
    // down treatment by re-adding the animation class whenever it opens.
    function initDropdownMotion() {
        var menu = document.getElementById('account-menu');
        if (!menu) return;
        var observer = new MutationObserver(function () {
            if (!menu.classList.contains('hidden')) {
                menu.classList.remove('motion-dropdown');
                // Force a reflow so the animation restarts on every open.
                void menu.offsetWidth;
                menu.classList.add('motion-dropdown');
                initNavMotion();
            }
        });
        observer.observe(menu, { attributes: true, attributeFilter: ['class'] });
    }

    // Anything rendered later (search results, AJAX panels) is picked up here.
    function refresh(scope) {
        initNavMotion();
        findStaggerContainers(scope);
        initCardLift();
        var scopeEl = scope && scope.nodeType === 1 ? scope : document;
        var fresh = scopeEl.querySelectorAll('section, .glass-card');
        for (var i = 0; i < fresh.length; i++) revealElement(fresh[i], null);
    }

    function init() {
        initStructure();
        initNavMotion();
        initCardLift();
        initDropdownMotion();

        // Re-scan when new nodes appear (game grid search, modal content, etc.).
        if (window.MutationObserver) {
            var pending = null;
            var mo = new MutationObserver(function (mutations) {
                var relevant = mutations.some(function (m) { return m.addedNodes && m.addedNodes.length; });
                if (!relevant) return;
                if (pending) return;
                pending = requestAnimationFrame(function () {
                    pending = null;
                    refresh(document);
                });
            });
            mo.observe(document.body, { childList: true, subtree: true });
        }

        document.documentElement.classList.add('motion-ready');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.PromexMotion = { refresh: refresh };
})();
