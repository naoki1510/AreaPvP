<?php

namespace naoki1510\areapvp\tasks;

use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\item\Item;
use pocketmine\scheduler\Task;
use naoki1510\areapvp\AreaPvP;
use naoki1510\areapvp\team\TeamManager;


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

        // Make a message with points
        $message = '';
        foreach ($this->TeamManager->getAllTeams() as $team) {
            $message .= '§l§' . $team->getColor()['text'] . $team->getName() . ' Team§f:§' . $team->getColor()['text'] . $team->getPoint() . '§f,';
        }

        $message = trim($message, ",");
        $duration = $this->AreaPvP->getGameDuration();
        $count = $this->AreaPvP->getGameTask()->getCount();
        $countdown = $duration - $count;
        
        foreach ($gameLevel->getPlayers() as $player) {
            $player->sendPopup($message);
            if($countdown < 0) continue;
            $player->setXpLevel($countdown);
            $player->setXpProgress($countdown / ($duration));

            if ($countdown < 6 && $this->TeamManager->isJoin($player)) {
                $player->addTitle('§6' . $countdown, '', 2, 16, 2);
            }
        }
    }
}