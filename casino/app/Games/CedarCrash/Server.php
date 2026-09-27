<?php
namespace VanguardLTE\Games\CedarCrash;

#[\AllowDynamicProperties]
class Server
{
    public function get($request, $game)
    {
        return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request, 'CedarCrash'), JSON_THROW_ON_ERROR);
    }
}
