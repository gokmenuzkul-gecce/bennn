<?php

namespace VanguardLTE\Games\EgyptTreasuries;

use VanguardLTE\Games\CustomSlotsBaseServer;

#[\AllowDynamicProperties]
class Server extends CustomSlotsBaseServer
{
    public function get($request, $game)
    {
        $symbols = [
            ['id' => 0, 'name' => 'a', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 1, 'name' => 'bug', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,2,5,10]],
            ['id' => 2, 'name' => 'cat', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 3, 'name' => 'cat2', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,3,8,12]],
            ['id' => 4, 'name' => 'cross', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 5, 'name' => 'crown', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 6, 'name' => 'eye', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,5,9,14]],
            ['id' => 7, 'name' => 'j', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,2,5,10]],
            ['id' => 8, 'name' => 'k', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,2,5,10]],
            ['id' => 9, 'name' => 'pyramid', 'wild' => true, 'scatter' => false, 'payouts' => [0,1,10,50,250]],
            ['id' => 10, 'name' => 'q', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 11, 'name' => 'wolf', 'wild' => false, 'scatter' => true, 'payouts' => [0,1,5,15,30]],
        ];

        $reels = [
            [0,1,2,3,4,5,6,7,8,9,10,11],
            [1,2,3,4,5,6,7,8,9,10,11,0],
            [2,3,4,5,6,7,8,9,10,11,0,1],
            [3,4,5,6,7,8,9,10,11,0,1,2],
            [4,5,6,7,8,9,10,11,0,1,2,3],
        ];

        return $this->handleRequest($request, $game, 'egypt-treasuries', 'Egypt Treasuries', $symbols, $reels);
    }
}
