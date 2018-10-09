<?php

namespace naoki1510\areapvp\tasks;

use naoki1510\areapvp\AreaPvP;
use naoki1510\areapvp\team\TeamManager;
use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\item\Item;
use pocketmine\scheduler\Task;
use naoki1510\areapvp\team\Team;


class SendMessageTask extends Task
{
    /** @var AreaPvP */
    private $AreaPvP;
    
    /** @var TeamManager */
    private $TeamManager;

    public function __construct(AreaPvP $areapvp, TeamManager $teamManager)
    {
        $this->AreaPvP = $areapvp;
        $this->TeamManager = $teamManager;
    }

    public function onRun(int $currentTick)
    {
        $gameLevel = $this->AreaPvP->getGameLevel();

        foreach ($this->TeamManager->getAllTeams() as $teama) {
            
            // Make a message with points
            $message = '';
            foreach ($this->TeamManager->getAllTeams() as $team) {
                if($team === $teama){
                    $message .= '§l§' . $team->getColor()['text'] . $team->getName() . ' Team§f:§' . $team->getColor()['text'] . $team->getPoint() . '§r§f,';
                }else{
                    $message .= '§' . $team->getColor()['text'] . $team->getName() . ' Team§l§f:§' . $team->getColor()['text'] . $team->getPoint() . '§r§f,';
                }
            }

            $message = trim($message, ",");
            $duration = $this->AreaPvP->getGameDuration();
            $count = $this->AreaPvP->getGameTask()->getCount();
            $countdown = $duration - $count;

            foreach ($teama->getAllPlayers() as $player) {
                $player->sendPopup($message);
                if ($countdown < 0) continue;
                $player->setXpLevel($countdown);
                $player->setXpProgress($countdown / ($duration));

                if ($countdown < 6 && $this->TeamManager->isJoin($player) && $currentTick % 20 < 4) {
                    $player->addTitle('§6' . $countdown, '', 2, 16, 2);
                }
            }
        }
    }
}