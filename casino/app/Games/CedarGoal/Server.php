<?php
namespace VanguardLTE\Games\CedarGoal;
class Server { public function get($request){ return json_encode((new \VanguardLTE\Services\CedarGameService())->handle($request,'CedarGoal'), JSON_THROW_ON_ERROR); } }
