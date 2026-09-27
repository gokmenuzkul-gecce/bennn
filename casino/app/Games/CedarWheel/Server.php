<?php
namespace VanguardLTE\Games\CedarWheel;

#[\AllowDynamicProperties]
class Server
{
    public function get($request, $game)
    {
        return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request, 'CedarWheel'), JSON_THROW_ON_ERROR);
    }
}
