<?php

namespace naoki1510\areapvp\tasks;

use naoki1510\areapvp\AreaPvP;
use naoki1510\areapvp\team\Team;
use naoki1510\areapvp\team\TeamManager;
use pocketmine\block\Block;
use pocketmine\entity\EffectInstance;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\scheduler\Task;
use onebone\economyapi\EconomyAPI;


class GameTask extends Task{

    /** @var AreaPvP */
    private $areaPvP;

    /** @var TeamManager */
    private $teamManager;

    /** @var Block */
    private $areaBlock;

    /** @var Position */
    private $areapos;

    /** @var Team */
    public $pointTeam;

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
            $playersOnBlock = [];
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

                $blockUnderPlayerId = $gameLevel->getBlock($player->subtract(0, 0.5))->getId() ? : $gameLevel->getBlock($player->subtract(0, 1.5))->getId();

                if ($blockUnderPlayerId === $this->areaBlock->getId()) {
                    
                    if ($this->teamManager->isJoin($player)) {
                        $playerTeam = $this->teamManager->getTeamOf($player);
                        $teamsOnBlock[$playerTeam->getName()] = $playerTeam;
                        array_push($playersOnBlock, $player);
                        $playerCount++;
                        $this->areaBlock = $gameLevel->getBlock($player->subtract(0, 0.5))->getId() ? $gameLevel->getBlock($player->subtract(0, 0.5)) : $gameLevel->getBlock($player->subtract(0, 1.5));
                    }
                }
            }
            if (count($teamsOnBlock) === 1 && !empty($this->areaBlock)) {
                // Configで設定できた方がいいかも？
                /** @var Team $team */
                $team = array_shift($teamsOnBlock);
                //var_dump($team->getAllPlayers(), $this->teamManager->getAllPlayers());
                $ratio = 1 + 1 / count($this->teamManager->getAllTeams()) - count($team->getAllPlayers()) / count($this->teamManager->getAllPlayers());
                $ratio2 = 1 + 1 / count($this->teamManager->getAllTeams()) - ($this->teamManager->getAllPoints() != 0 ? $team->getPoint() / $this->teamManager->getAllPoints() : 0);
                $point = 1 * count($playersOnBlock) * $ratio * $ratio2;
                $team->addPoint($point);
                foreach ($playersOnBlock as $player) {
                    EconomyAPI::getInstance()->addMoney($player, ceil($point));
                }
                $this->changeBlock($gameLevel, $team->getColor('block'));
                $this->pointTeam = $team;
            }elseif($this->areaBlock->isValid()){
                $this->changeBlock($gameLevel, 0);
                $this->pointTeam = null;
            }

            if($this->count >= $this->areaPvP->getGameDuration()){
                $this->areaPvP->finish();
            }
        }

        if ($currentTick % 20 < $this->areaPvP->getConfig()->get('CheckInterval', 0.1) * 20 && count($gameLevel->getPlayers()) >= $this->minPlayer) {
            $this->count++;
        }

        if ($this->count > $this->areaPvP->getGameDuration() + $this->areaPvP->getInterval()) {
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

    private function changeBlock(Level $level, int $meta)
    {
        $pos1 = $this->areaPvP->getConfig()->getNested($level->getName() . '.pos1');
        $pos2 = $this->areaPvP->getConfig()->getNested($level->getName() . '.pos2');
        if(!empty($pos1) && !empty($pos2)){
            $p1data = \explode(',', $pos1);
            $p2data = \explode(',', $pos2);
            for ($x = $p1data[0]; ($p1data[0] < $p2data[0]) ? $x <= $p2data[0] : $x >= $p2data[0]; ($p1data[0] < $p2data[0]) ? $x++ : $x--) {
                for ($z = $p1data[2]; ($p1data[2] < $p2data[2]) ? $z <= $p2data[2] : $z >= $p2data[2]; ($p1data[2] < $p2data[2]) ? $z++ : $z--) { 
                    $block = $level->getBlockAt($x, $p1data[1], $z);
                    if($block->getId() === Block::WOOL){
                        $level->setBlock($block->asPosition(), Block::get(Block::WOOL, $meta));
                    }
                }
            }
        }
    }

    /**
     * @return Team|null
     */
    public function getLastPointTeam()
    {
        return $this->pointTeam ?? null;
    }
}