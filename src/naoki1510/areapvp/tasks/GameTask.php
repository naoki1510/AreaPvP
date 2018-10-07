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
        if($this->areaPvP->isRunning()){
            $gameLevel = $this->areaPvP->getGameLevel();
            $onlyteam = null;
            $teamsOnBlock = 0;

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
                        if ($onlyteam !== $playerTeam) {
                            $teamsOnBlock++;
                        }
                        if ($teamsOnBlock === 1) {
                            $onlyTeam = $playerTeam;
                        }
                    }
                }
            }
            if ($teamsOnBlock === 1) {
                // Configで設定できた方がいいかも？
                $onlyTeam->addPoint(1);

            }

            if($this->count - 1 >= $this->areaPvP->getGameDuration()){
                $this->areaPvP->finish();
            }
        }

        if (0 <= $currentTick % 20 && $currentTick % 20 < $this->areaPvP->getConfig()->get('CheckInterval', 0.1) * 20) {
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