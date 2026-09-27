<?php
namespace VanguardLTE\Games\RoyalSteps;
class Server
{
    public function get($request, $game)
    {
        return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request, 'RoyalSteps'));
    }
}
