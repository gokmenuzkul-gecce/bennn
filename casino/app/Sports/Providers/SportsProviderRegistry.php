<?php

namespace VanguardLTE\Sports\Providers;

use VanguardLTE\Sports\Providers\Contracts\SportsOddsProvider;

/**
 * Resolves the configured sportsbook odds provider.
 *
 * Adding a new source only means registering an adapter here; the sync service
 * and controllers depend on the contract, never on a concrete provider.
 */
class SportsProviderRegistry
{
    /**
     * @var array<string, callable(): SportsOddsProvider>
     */
    private array $factories;

    public function __construct()
    {
        $this->factories = [
            PromexLicensedProvider::KEY => static fn (): SportsOddsProvider => new PromexLicensedProvider(),
            TheOddsApiProvider::KEY => static fn (): SportsOddsProvider => new TheOddsApiProvider(),
        ];
    }

    /**
     * Register or override an adapter (used by extensions and tests).
     *
     * @param  callable(): SportsOddsProvider  $factory
     */
    public function register(string $key, callable $factory): void
    {
        $this->factories[$key] = $factory;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->factories);
    }

    public function has(string $key): bool
    {
        return isset($this->factories[$key]);
    }

    public function make(string $key): SportsOddsProvider
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException("Unknown sportsbook provider [{$key}].");
        }

        return ($this->factories[$key])();
    }

    /** The provider key selected by the operator, defaulting to PROMEX. */
    public function selectedKey(): string
    {
        $selected = function_exists('settings')
            ? (string) settings('sportsbook_api_provider', PromexLicensedProvider::KEY)
            : PromexLicensedProvider::KEY;

        return $this->has($selected) ? $selected : PromexLicensedProvider::KEY;
    }

    public function selected(): SportsOddsProvider
    {
        return $this->make($this->selectedKey());
    }

    /**
     * Adapter metadata for the admin panel.
     *
     * @return array<int, array{key: string, label: string, requires_license: bool, configured: bool, message: string, selected: bool}>
     */
    public function catalog(): array
    {
        $selectedKey = $this->selectedKey();
        $catalog = [];

        foreach ($this->factories as $key => $factory) {
            $provider = $factory();
            $status = $provider->configStatus();
            $catalog[] = [
                'key' => $key,
                'label' => $provider->label(),
                'requires_license' => $provider->requiresLicense(),
                'configured' => $status['configured'],
                'message' => $status['message'],
                'selected' => $key === $selectedKey,
            ];
        }

        return $catalog;
    }
}
