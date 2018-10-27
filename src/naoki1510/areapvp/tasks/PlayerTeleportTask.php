<?php

namespace naoki1510\areapvp\tasks;

use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\scheduler\Task;


class PlayerTeleportTask extends Task
{
    /** @var Player */
    public $player;

    /** @var Position */
    public $pos;

    public function __construct(Player $player, Position $pos)
    {
        $this->player = $player;
        $this->pos = $pos;
    }

    public function onRun(int $currentTick)
    {
        if($this->player->isOnline()){
            $this->player->pitch = 270;
            $this->player->teleport($this->pos);
        }
    }
}