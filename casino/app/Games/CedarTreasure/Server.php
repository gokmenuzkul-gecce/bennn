<?php
namespace VanguardLTE\Games\CedarTreasure;
class Server { public function get($request){ return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request,'CedarTreasure'), JSON_THROW_ON_ERROR); } }
