<?php
namespace VanguardLTE\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use VanguardLTE\User;
use VanguardLTE\StatGame;

/** The operator owns money; only signed Hub commands can advance a round. */
final class CedarArcadeService {
    public const GAMES = ['CedarCrash','CedarPlinko','CedarMines','CedarTower','CedarKeno','CedarWheel','CedarDice','CedarLimbo','CedarBlackjack','CedarHiLo'];
    private const BETS = ['bet','play','roll','spin','drop'];
    public function __construct(private ?PromexCedarService $client = null) {}
    public function handle(Request $request, string $game): array {
        try {
            if (!Auth::check()) throw new RuntimeException('Sign in to play.');
            return $this->command((int) Auth::id(), $game, $request->only(['action','command_id','request_id','wager','server_seed_hash','client_seed','bet_id','target','condition','risk','rows','segments','mines','auto_cashout','picks','choice','tile','level']));
        } catch (RuntimeException $e) { return ['status'=>'error','message'=>$e->getMessage()]; }
    }
    public function command(int $userId, string $game, array $input): array {
        $id=$input['command_id']??''; $action=$input['action']??'init';
        if (!in_array($game,self::GAMES,true) || !is_string($id) || !preg_match('/^[a-f0-9]{32}$/D',$id)
            || !is_string($action)) throw new RuntimeException('Invalid arcade command.');
        // A reload recovers an uncertain command first, without creating a new wager.
        if ($action==='init') {
            $pending=DB::table('cedar_arcade_commands')->where('user_id',$userId)->where('game',$game)->whereNull('result')->first();
            if ($pending && $pending->command_id!==$id) $this->command($userId,$game,json_decode($pending->input,true,32,JSON_THROW_ON_ERROR));
        }
        $hash=hash('sha256',json_encode($input,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        $prepared=DB::transaction(function() use($userId,$game,$input,$id,$action,$hash) {
            $user=User::whereKey($userId)->lockForUpdate()->firstOrFail();
            if ($user->is_blocked || $user->status!=='Active') throw new RuntimeException('This account cannot play.');
            $query=DB::table('cedar_arcade_commands')->where('user_id',$userId)->where('game',$game);
            $old=(clone $query)->where('command_id',$id)->first();
            if ($old) {
                if (!hash_equals($old->input_hash,$hash)) throw new RuntimeException('Command ID conflicts with its original input.');
                if ($old->result!==null) return ['result'=>json_decode($old->result,true,128,JSON_THROW_ON_ERROR)];
            } elseif ((clone $query)->whereNull('result')->exists()) throw new RuntimeException('Recover the previous command before continuing.');
            $row=DB::table('cedar_states')->where('user_id',$userId)->where('game',$game)->first();
            $state=$row?json_decode($row->state,true,128,JSON_THROW_ON_ERROR):[];
            if (!isset($state['arcade_scope'])) {
                if (!empty($state['active'])) throw new RuntimeException('Finish the existing legacy round before migrating this game.');
                $state=['arcade_scope'=>bin2hex(random_bytes(16)),'active'=>null];
            }
            if (!$old) {
                $reserved=0;
                if (in_array($action,self::BETS,true)) {
                    if (!empty($state['active'])) throw new RuntimeException('Finish the current round first.');
                    $wager=$input['wager']??null;
                    if (!is_scalar($wager) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',(string)$wager)
                        || (float)$wager<($state['min_bet']??INF) || (float)$wager>($state['max_bet']??-INF)) throw new RuntimeException('Invalid wager. Initialize the game first.');
                    if (($input['server_seed_hash']??'')!==($state['commitment']??null)) throw new RuntimeException('Commitment changed. Recover the game.');
                    $reserved=(float)$wager;
                    if ((float)$user->balance<$reserved) throw new RuntimeException('Insufficient Cedar Coins.');
                    $user->decrement('balance',$reserved);
                } elseif ($action!=='init' && ($input['bet_id']??'')!==($state['active']??null)) throw new RuntimeException('This round is no longer active. Recover the game.');
                DB::table('cedar_arcade_commands')->insert(['user_id'=>$userId,'game'=>$game,'command_id'=>$id,'input_hash'=>$hash,
                    'input'=>json_encode($input,JSON_THROW_ON_ERROR),'reserved'=>$reserved,'created_at'=>now(),'updated_at'=>now()]);
                DB::table('cedar_states')->updateOrInsert(['user_id'=>$userId,'game'=>$game],['state'=>json_encode($state,JSON_THROW_ON_ERROR)]);
            }
            return ['scope'=>$state['arcade_scope']];
        },5);
        if (isset($prepared['result'])) return $prepared['result'];
        $payload=$input; unset($payload['command_id']);
        if (in_array($action,self::BETS,true)) $payload['request_id']=$id;
        $remote=($this->client??new PromexCedarService())->arcade($game,$prepared['scope'],substr(hash('sha256',$prepared['scope'].'|'.$id),0,32),$payload);
        return DB::transaction(function() use($userId,$game,$id,$action,$remote,$prepared) {
            $user=User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $command=DB::table('cedar_arcade_commands')->where('user_id',$userId)->where('game',$game)->where('command_id',$id)->lockForUpdate()->firstOrFail();
            if ($command->result!==null) return json_decode($command->result,true,128,JSON_THROW_ON_ERROR);
            $row=DB::table('cedar_states')->where('user_id',$userId)->where('game',$game)->firstOrFail();
            $state=json_decode($row->state,true,128,JSON_THROW_ON_ERROR);
            if ($state['arcade_scope']!==$prepared['scope']) throw new RuntimeException('Arcade scope changed.');
            if (($remote['status']??'')==='error') {
                if ((float)$command->reserved>0) $user->increment('balance',(float)$command->reserved);
            } else {
                if ((float)$command->reserved>0) {
                    if (!preg_match('/^[a-f0-9]{32}$/D',(string)($remote['bet_id']??'')) || (float)($remote['wager']??-1)!==(float)$command->reserved) throw new RuntimeException('Signed wager does not match reservation.');
                    $state['active']=$remote['bet_id'];
                    DB::table('cedar_rounds')->insert(['id'=>$remote['bet_id'],'user_id'=>$userId,'game'=>$game,'request_id'=>$id,'status'=>'active',
                        'wager'=>$command->reserved,'win'=>0,'data'=>json_encode(['remote'=>true]),'created_at'=>now(),'updated_at'=>now()]);
                }
                $settled=$action==='init'?($remote['last_result']??null):$remote;
                if (is_array($settled) && isset($settled['proof'],$settled['win_amount'],$settled['bet_id'])) {
                    $round=DB::table('cedar_rounds')->where('id',$settled['bet_id'])->where('user_id',$userId)->where('game',$game)->lockForUpdate()->first();
                    if (!$round) throw new RuntimeException('Signed round has no wallet reservation.');
                    if ($round->status==='active') {
                        $win=(string)$settled['win_amount'];
                        if (!preg_match('/^\d{1,9}\.\d{2}$/D',$win) || (float)$win>1000000 || (float)$settled['wager']!==(float)$round->wager) throw new RuntimeException('Invalid signed settlement.');
                        $user->increment('balance',(float)$win);
                        AffiliateService::recordWagerCommission($user,(float)$round->wager,strtolower($game),true);
                        VipService::recordWagerXpAndRakeback($user,(float)$round->wager,5.0);
                        $user->refresh();
                        (new StatGame(['user_id'=>$userId,'balance'=>$user->balance,'bet'=>$round->wager,'win'=>$win,'game'=>$game,'in_game'=>1,'shop_id'=>$user->shop_id?:1,'date_time'=>now()]))->saveQuietly();
                        DB::table('cedar_rounds')->where('id',$round->id)->update(['status'=>'settled','win'=>$win,'data'=>json_encode(['remote'=>true,'result'=>$settled],JSON_THROW_ON_ERROR),'updated_at'=>now()]);
                        $state['active']=null;
                    }
                }
                $state['commitment']=$remote['next_server_seed_hash']??($action==='init'?($remote['server_seed_hash']??null):($state['commitment']??null));
                if ($action==='init') { $state['min_bet']=$remote['min_bet']; $state['max_bet']=$remote['max_bet']; }
            }
            $user->refresh(); $result=$remote+['balance'=>number_format((float)$user->balance,2,'.','')];
            DB::table('cedar_states')->where('id',$row->id)->update(['state'=>json_encode($state,JSON_THROW_ON_ERROR)]);
            DB::table('cedar_arcade_commands')->where('id',$command->id)->update(['result'=>json_encode($result,JSON_THROW_ON_ERROR),'updated_at'=>now()]);
            return $result;
        },5);
    }
}
