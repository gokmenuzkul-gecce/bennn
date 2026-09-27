<?php
namespace VanguardLTE\Games\CedarBlackjack;
class Server { public function get($request){ return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request,'CedarBlackjack'), JSON_THROW_ON_ERROR); } }
