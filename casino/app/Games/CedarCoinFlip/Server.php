<?php
namespace VanguardLTE\Games\CedarCoinFlip;
class Server { public function get($request){ return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request,'CedarCoinFlip'), JSON_THROW_ON_ERROR); } }
