<?php
namespace VanguardLTE\Games\CedarPlinko;

#[\AllowDynamicProperties]
class Server
{
    public function get($request, $game)
    {
        return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request, 'CedarPlinko'), JSON_THROW_ON_ERROR);
    }
}
