<?php

namespace VanguardLTE\Casino\Providers;

use VanguardLTE\Casino\Providers\Contracts\CasinoProvider;

/**
 * Resolves casino game providers by key.
 *
 * The wallet controller and admin panel depend on this registry, never on a
 * concrete vendor, so adding a brand is a one-line registration.
 */
class CasinoProviderRegistry
{
    /** @var array<string, callable(): CasinoProvider> */
    private array $factories;

    public function __construct()
    {
        $this->factories = [
            PragmaticProvider::KEY => static fn (): CasinoProvider => new PragmaticProvider(),
            PGSoftProvider::KEY => static fn (): CasinoProvider => new PGSoftProvider(),
            AmaticProvider::KEY => static fn (): CasinoProvider => new AmaticProvider(),
            AmusnetProvider::KEY => static fn (): CasinoProvider => new AmusnetProvider(),
            OroPlayProvider::KEY => static fn (): CasinoProvider => new OroPlayProvider(),
            GregmornProvider::KEY => static fn (): CasinoProvider => new GregmornProvider(),
            SmplCoreProvider::KEY => static fn (): CasinoProvider => new SmplCoreProvider(),
            WaijaProvider::KEY => static fn (): CasinoProvider => new WaijaProvider(),
            SoftAggregatorProvider::KEY => static fn (): CasinoProvider => new SoftAggregatorProvider(),
            Aggregator01Provider::KEY => static fn (): CasinoProvider => new Aggregator01Provider(),
        ];
    }

    /** @param callable(): CasinoProvider $factory */
    public function register(string $key, callable $factory): void
    {
        $this->factories[$key] = $factory;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->factories);
    }

    public function has(string $key): bool
    {
        return isset($this->factories[$key]);
    }

    public function make(string $key): CasinoProvider
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException("Unknown casino provider [{$key}].");
        }

        return ($this->factories[$key])();
    }

    /** Resolve the provider that owns an incoming callback slug, if any. */
    public function findBySlug(string $slug): ?CasinoProvider
    {
        $configured = (string) config('casino_providers.callback_slug', '');

        foreach ($this->factories as $key => $factory) {
            // Every brand shares one aggregator callback URL, so any registered
            // provider answers for the configured slug.
            if ($slug === $configured || $slug === $key) {
                return $factory();
            }
        }

        return null;
    }

    /** Callback URL the vendor posts to. All brands share the aggregator slug. */
    public function callbackUrl(?string $slug = null): string
    {
        $base = rtrim((string) config('casino_providers.callback_base', ''), '/');
        $slug = $slug ?: (string) config('casino_providers.callback_slug', 'gregmorn');

        return $base . '/webhooks/aggregator/' . $slug . '/wallet';
    }

    /** Preferred embed aspect ratio for a provider ('auto' or 'w:h'). */
    public function embedAspect(string $key): string
    {
        return $this->has($key) ? $this->make($key)->embedAspect() : 'auto';
    }

    /** True when the provider is enabled for play. */
    public function isEnabled(string $key): bool
    {
        if (!function_exists('settings')) {
            return true;
        }

        return (string) settings('casino_provider_enabled_' . $key, '1') === '1';
    }

    /**
     * Adapter metadata for the admin panel.
     *
     * @return array<int, array{key: string, label: string, configured: bool, enabled: bool, endpoint: string, message: string}>
     */
    public function catalog(): array
    {
        $catalog = [];
        foreach ($this->factories as $key => $factory) {
            $provider = $factory();
            $status = $provider->configStatus();
            $config = $provider->config();
            $catalog[] = [
                'key' => $key,
                'label' => $provider->label(),
                'configured' => $status['configured'],
                'enabled' => $this->isEnabled($key),
                'endpoint' => (string) ($config['endpoint'] ?? ''),
                'callback_url' => method_exists($provider, 'callbackUrl') ? $provider->callbackUrl() : $this->callbackUrl(),
                'message' => $status['message'],
            ];
        }

        return $catalog;
    }
}
