<?php

namespace VanguardLTE\Games\VikingTime;

use VanguardLTE\Games\CustomSlotsBaseServer;

#[\AllowDynamicProperties]
class Server extends CustomSlotsBaseServer
{
    public function get($request, $game)
    {
        $symbols = [
            ['id' => 0, 'name' => 'a', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 1, 'name' => 'diamond', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,5,10,15]],
            ['id' => 2, 'name' => 'j', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 3, 'name' => 'k', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 4, 'name' => 'q', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 5, 'name' => 'shield', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,3,6,12]],
            ['id' => 6, 'name' => 'shield2', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,3,6,12]],
            ['id' => 7, 'name' => 'shield3', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,3,6,12]],
            ['id' => 8, 'name' => 'shield4', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,3,6,12]],
            ['id' => 9, 'name' => 'wild', 'wild' => true, 'scatter' => false, 'payouts' => [0,0,10,30,150]],
        ];

        $reels = [
            [0,1,2,3,4,5,6,7,8,9],
            [1,2,3,4,5,6,7,8,9,0],
            [2,3,4,5,6,7,8,9,0,1],
            [3,4,5,6,7,8,9,0,1,2],
            [4,5,6,7,8,9,0,1,2,3],
        ];

        return $this->handleRequest($request, $game, 'viking-time', 'Viking Time', $symbols, $reels);
    }
}
