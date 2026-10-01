<?php

namespace Tests\Unit;

use InvalidArgumentException;
use Tests\TestCase;
use VanguardLTE\Sports\Providers\Contracts\SportsOddsProvider;
use VanguardLTE\Sports\Providers\PromexLicensedProvider;
use VanguardLTE\Sports\Providers\SportsProviderRegistry;
use VanguardLTE\Sports\Providers\TheOddsApiProvider;

class SportsProviderRegistryTest extends TestCase
{
    public function test_registry_exposes_the_shipped_adapters(): void
    {
        $registry = new SportsProviderRegistry();

        $this->assertSame(['promex', 'custom'], $registry->keys());
        $this->assertTrue($registry->has('promex'));
        $this->assertTrue($registry->has('custom'));
        $this->assertFalse($registry->has('unknown'));
    }

    public function test_make_returns_the_matching_adapter(): void
    {
        $registry = new SportsProviderRegistry();

        $this->assertInstanceOf(PromexLicensedProvider::class, $registry->make('promex'));
        $this->assertInstanceOf(TheOddsApiProvider::class, $registry->make('custom'));
        $this->assertInstanceOf(SportsOddsProvider::class, $registry->make('promex'));
    }

    public function test_make_rejects_unknown_provider(): void
    {
        $registry = new SportsProviderRegistry();

        $this->expectException(InvalidArgumentException::class);
        $registry->make('does-not-exist');
    }

    public function test_register_can_add_or_override_an_adapter(): void
    {
        $registry = new SportsProviderRegistry();
        $stub = new class implements SportsOddsProvider {
            public function key(): string { return 'stub'; }
            public function label(): string { return 'Stub'; }
            public function requiresLicense(): bool { return false; }
            public function isConfigured(): bool { return true; }
            public function configStatus(): array { return ['configured' => true, 'message' => 'ok']; }
            public function testConnectivity(): array { return ['success' => true, 'message' => 'ok']; }
            public function fetchFixtures(?array $sportKeys = null): array { return []; }
            public function fetchSports(): array { return []; }
        };

        $registry->register('stub', static fn (): SportsOddsProvider => $stub);

        $this->assertTrue($registry->has('stub'));
        $this->assertSame($stub, $registry->make('stub'));
    }

    public function test_catalog_describes_every_provider_without_settings_helpers(): void
    {
        $registry = new SportsProviderRegistry();
        $catalog = $registry->catalog();

        $this->assertCount(2, $catalog);

        $byKey = array_column($catalog, null, 'key');
        $this->assertArrayHasKey('promex', $byKey);
        $this->assertArrayHasKey('custom', $byKey);
        $this->assertTrue($byKey['promex']['requires_license']);
        $this->assertFalse($byKey['custom']['requires_license']);
        $this->assertTrue($byKey['promex']['selected']);
        $this->assertFalse($byKey['custom']['selected']);
    }

    public function test_adapter_metadata_matches_the_contract(): void
    {
        $this->assertSame('promex', (new PromexLicensedProvider())->key());
        $this->assertTrue((new PromexLicensedProvider())->requiresLicense());
        $this->assertSame('custom', (new TheOddsApiProvider())->key());
        $this->assertFalse((new TheOddsApiProvider())->requiresLicense());
    }
}
