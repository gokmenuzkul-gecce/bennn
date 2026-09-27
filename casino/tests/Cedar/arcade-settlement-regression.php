<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/fixture-bootstrap.php';
require dirname(__DIR__,3).'/localscripts/licensing_hub/cedar_arcade.php';
use Illuminate\Support\Facades\DB;
use VanguardLTE\Services\CedarArcadeService;
use VanguardLTE\Services\PromexCedarService;
use VanguardLTE\User;
(require __DIR__.'/../../database/migrations/2026_09_26_120000_create_cedar_arcade_commands.php')->up();
$checks=0;function acheck(bool $ok,string $label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
$client=new class extends PromexCedarService {
    public array $engines=[],$cache=[];public bool $failAfter=false;public bool $reject=false;
    public function arcade(string $game,string $scope,string $command,array $payload):array {
        if(isset($this->cache[$command]))return $this->cache[$command];
        $engine=new CedarArcadeEngine($game,$this->engines[$scope]??[]);
        if($this->reject){$this->reject=false;$r=['status'=>'error','message'=>'Signed rejection'];}
        else{$r=$engine->handle($payload);$this->engines[$scope]=$engine->export();}
        $this->cache[$command]=$r;
        if($this->failAfter){$this->failAfter=false;throw new RuntimeException('Response lost after Hub commit.');}return $r;
    }
};
$service=new CedarArcadeService($client);
DB::table('users')->where('id',1)->update(['balance'=>1000]);
$make=fn($action,$data=[])=>['action'=>$action,'command_id'=>bin2hex(random_bytes(16))]+$data;
$init=$service->command(1,'CedarPlinko',$make('init'));
$bet=$make('drop',['wager'=>'10.00','rows'=>16,'risk'=>'medium','server_seed_hash'=>$init['server_seed_hash'],'client_seed'=>$init['client_seed']]);
$client->failAfter=true;
try{$service->command(1,'CedarPlinko',$bet);acheck(false,'expected failure');}catch(RuntimeException){acheck((float)User::find(1)->balance===990.,'reserve only once');}
try{$service->command(1,'CedarPlinko',$make('drop',array_diff_key($bet,['command_id'=>true,'action'=>true])));acheck(false,'second bet accepted');}catch(RuntimeException){acheck((float)User::find(1)->balance===990.,'second bet blocked');}
$restore=$service->command(1,'CedarPlinko',$make('init'));$result=$restore['last_result'];$expected=990.+(float)$result['win_amount'];
acheck((float)User::find(1)->balance===$expected,'init recovers lost settlement');
$service->command(1,'CedarPlinko',$bet);$service->command(1,'CedarPlinko',$make('init'));
acheck((float)User::find(1)->balance===$expected && DB::table('stat_game')->count()===1,'replays never credit twice');
$client->reject=true;$rejected=$service->command(1,'CedarPlinko',$make('drop',['wager'=>'10.00','rows'=>16,'risk'=>'medium','server_seed_hash'=>$restore['server_seed_hash'],'client_seed'=>$restore['client_seed']]));
acheck($rejected['status']==='error' && (float)User::find(1)->balance===$expected,'signed rejection refunds once');
$init=$service->command(1,'CedarBlackjack',$make('init'));
$r=$service->command(1,'CedarBlackjack',$make('bet',['wager'=>'10.00','server_seed_hash'=>$init['server_seed_hash'],'client_seed'=>$init['client_seed']]));
acheck((float)User::find(1)->balance===$expected-10,'interactive reservation');
$stand=$make('stand',['bet_id'=>$r['bet_id']]);$client->failAfter=true;
try{$service->command(1,'CedarBlackjack',$stand);}catch(RuntimeException){}
$restored=$service->command(1,'CedarBlackjack',$make('init'));$result=$restored['last_result'];
acheck((float)User::find(1)->balance===$expected-10+(float)$result['win_amount'],'interactive recovery');
$before=(float)User::find(1)->balance;$service->command(1,'CedarBlackjack',$stand);acheck((float)User::find(1)->balance===$before,'stand replay');
echo "$checks arcade wallet checks passed.\n";
