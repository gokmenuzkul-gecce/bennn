<?php
namespace VanguardLTE\Games\CedarKeno;
class Server { public function get($request){ return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request,'CedarKeno'), JSON_THROW_ON_ERROR); } }
