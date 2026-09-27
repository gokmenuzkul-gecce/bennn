<?php
namespace VanguardLTE\Services;
use Illuminate\Http\Request;
/** Public compatibility facade; private legacy math is excluded from customer releases. */
class CedarGameService {
    public const GAMES = ['CedarDice','CedarWheel','CedarPlinko','CedarMines','CedarCrash','RoyalSteps','CedarLimbo','CedarTower','CedarKeno','CedarCoinFlip','CedarGoal','CedarTreasure','CedarHiLo','CedarBlackjack'];
    public function handle(Request $request, string $game): array {
        if (in_array($game,CedarArcadeService::GAMES,true) && $request->has('command_id')) return (new CedarArcadeService())->handle($request,$game);
        if (class_exists(CedarLegacyGameService::class)) return (new CedarLegacyGameService())->handle($request,$game);
        return ['status'=>'error','message'=>'Open the licensed hosted Cedar game to continue.'];
    }
    public function settleCrashFor(int $userId): void {
        if (class_exists(CedarLegacyGameService::class)) (new CedarLegacyGameService())->settleCrashFor($userId);
    }
}
