<?php

namespace VanguardLTE\Games\MidnightCity;

use VanguardLTE\Games\CustomSlotsBaseServer;

#[\AllowDynamicProperties]
class Server extends CustomSlotsBaseServer
{
    public function get($request, $game)
    {
        $symbols = [
            ['id' => 0, 'name' => 'a', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 1, 'name' => 'j', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,2,5,10]],
            ['id' => 2, 'name' => 'k', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,2,5,10]],
            ['id' => 3, 'name' => 'q', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,3,8,12]],
            ['id' => 4, 'name' => 'clubs', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,2,5,10]],
            ['id' => 5, 'name' => 'diamonds', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 6, 'name' => 'hearts', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,5,9,14]],
            ['id' => 7, 'name' => 'spades', 'wild' => false, 'scatter' => false, 'payouts' => [0,0,2,5,10]],
            ['id' => 8, 'name' => 'seven', 'wild' => false, 'scatter' => false, 'payouts' => [0,1,2,5,10]],
            ['id' => 9, 'name' => 'wild', 'wild' => true, 'scatter' => false, 'payouts' => [0,0,15,50,200]],
            ['id' => 10, 'name' => 'scatter', 'wild' => false, 'scatter' => true, 'payouts' => [0,0,10,15,20]],
        ];

        $reels = [
            [0,1,2,3,4,5,6,7,8,9,10],
            [1,2,3,4,5,6,7,8,9,10,0],
            [2,3,4,5,6,7,8,9,10,0,1],
            [3,4,5,6,7,8,9,10,0,1,2],
            [4,5,6,7,8,9,10,0,1,2,3],
        ];

        return $this->handleRequest($request, $game, 'midnight-city', 'Midnight City', $symbols, $reels);
    }
}
