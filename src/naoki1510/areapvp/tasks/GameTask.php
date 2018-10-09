<?php

namespace naoki1510\areapvp\tasks;

use naoki1510\areapvp\AreaPvP;
use naoki1510\areapvp\team\TeamManager;
use pocketmine\block\Block;
use pocketmine\entity\EffectInstance;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\scheduler\Task;


class GameTask extends Task{

    /** @var AreaPvP */
    private $areaPvP;

    /** @var TeamManager */
    private $teamManager;

    /** @var Block */
    private $areaBlock;

    /** @var Int */
    public $minPlayer;
    public $count;

    

    public function __construct(AreaPvP $areapvp, Block $areaBlock, Int $minPlayer, TeamManager $teamManager) {
        $this->areaPvP  = $areapvp;
        $this->areaBlock = $areaBlock;
        $this->teamManager = $teamManager;
        $this->minPlayer = $minPlayer;
        $this->count = 0;
    }

    public function onRun(int $currentTick) {
        $gameLevel = $this->areaPvP->getGameLevel();
        if($this->areaPvP->isRunning()){
            $teamsOnBlock = [];
            $playerCount = 0;

            foreach ($gameLevel->getPlayers() as $player) {
                if (count($gameLevel->getPlayers()) < $this->minPlayer) {
                    $player->sendTip(
                        AreaPvP::translate(
                            "game.lessplayers",
                            ["count" => $this->minPlayer - count($gameLevel->getPlayers())]
                        )
                    );
                    continue;
                }

                $blockUnderPlayer = ($gameLevel->getBlock($player->subtract(0, 0.5))->getId() == 0) ? $gameLevel->getBlock($player->subtract(0, 1.5)) : $gameLevel->getBlock($player->subtract(0, 0.5));

                if ($blockUnderPlayer->getId() === $this->areaBlock->getId()) {
                    
                    if ($this->teamManager->isJoin($player)) {
                        $playerTeam = $this->teamManager->getTeamOf($player);
                        $teamsOnBlock[$playerTeam->getName()] = $playerTeam;
                        $playerCount++;
                    }
                }
            }
            if (count($teamsOnBlock) === 1) {
                // Configで設定できた方がいいかも？
                array_shift($teamsOnBlock)->addPoint(1 * $playerCount);

            }

            if($this->count - 1 >= $this->areaPvP->getGameDuration()){
                $this->areaPvP->finish();
            }
        }

        if ($currentTick % 20 < $this->areaPvP->getConfig()->get('CheckInterval', 0.1) * 20 && count($gameLevel->getPlayers()) >= $this->minPlayer) {
            $this->count++;
        }

        if ($this->count >= $this->areaPvP->getGameDuration() + $this->areaPvP->getInterval()) {
            $this->areaPvP->start();
        }
    }

    public function getCount() : Int{
        return ($this->count) ?? 0;
    }

    public function setCount(Int $count = 0)
    {
        $this->count = $count;
    }
}