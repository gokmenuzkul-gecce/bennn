<?php

namespace VanguardLTE\Casino\Providers;

class PGSoftProvider extends AbstractCasinoProvider
{
    public const KEY = 'pgsoft';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'PG Soft';
    }
}
