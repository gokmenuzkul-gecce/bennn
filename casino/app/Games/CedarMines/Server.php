<?php
namespace VanguardLTE\Games\CedarMines;

#[\AllowDynamicProperties]
class Server
{
    public function get($request, $game)
    {
        return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request, 'CedarMines'), JSON_THROW_ON_ERROR);
    }
}
