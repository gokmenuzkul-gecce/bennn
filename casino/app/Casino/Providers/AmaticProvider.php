<?php

namespace VanguardLTE\Casino\Providers;

class AmaticProvider extends AbstractCasinoProvider
{
    public const KEY = 'amatic';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Amatic';
    }
}
