<?php
namespace VanguardLTE\Games\CedarHiLo;
class Server { public function get($request){ return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request,'CedarHiLo'), JSON_THROW_ON_ERROR); } }
