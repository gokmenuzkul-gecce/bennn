<?php

namespace VanguardLTE\Casino\Providers;

class PragmaticProvider extends AbstractCasinoProvider
{
    public const KEY = 'pragmatic';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Pragmatic Play';
    }
}
