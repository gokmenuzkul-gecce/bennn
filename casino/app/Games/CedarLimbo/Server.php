<?php
namespace VanguardLTE\Games\CedarLimbo;
class Server { public function get($request){ return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request,'CedarLimbo'), JSON_THROW_ON_ERROR); } }
