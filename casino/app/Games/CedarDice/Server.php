<?php
namespace VanguardLTE\Games\CedarDice;

#[\AllowDynamicProperties]
class Server
{
    public function get($request, $game)
    {
        return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request, 'CedarDice'), JSON_THROW_ON_ERROR);
    }
}
