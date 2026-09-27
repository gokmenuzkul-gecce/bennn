<?php
namespace VanguardLTE\Games\CedarTower;
class Server { public function get($request){ return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request,'CedarTower'), JSON_THROW_ON_ERROR); } }
