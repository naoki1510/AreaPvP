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
use pocketmine\entity\utils\Bossbar;
use pocketmine\network\mcpe\protocol\SetScoreboardIdentityPacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use Miste\scoreboardspe\API \{
    Scoreboard, ScoreboardDisplaySlot, ScoreboardSort, ScoreboardAction
};
use pocketmine\Server;



class SendMessageTask extends Task
{
    /** @var AreaPvP */
    private $AreaPvP;
    
    /** @var TeamManager */
    private $TeamManager;

    /** @var Bossbar */
    public $bossbar;

    /** @var int */
    public $lastcount;

    public function __construct(AreaPvP $areapvp, TeamManager $teamManager)
    {
        $this->AreaPvP = $areapvp;
        $this->TeamManager = $teamManager;
        $this->bossbar = new Bossbar("Game will start soon.");
    }

    public function onRun(int $currentTick)
    {
        $gameLevel = $this->AreaPvP->getGameLevel();
        $message = '';
        foreach ($this->TeamManager->getAllTeams() as $team) {
            $message .= '§' . $team->getColor('text') . $team->getName() . ':' . $team->getPoint() . ' §r';
        }
        //\rsort($points);

        $duration = $this->AreaPvP->getGameDuration();
        $count = $this->AreaPvP->getGameTask()->getCount();
        $countdown = $duration - $count;
        if ($this->AreaPvP->getGameTask()->getLastPointTeam() instanceof Team) {
            $team = $this->AreaPvP->getGameTask()->getLastPointTeam();
            $this->bossbar->setTitle("\n\n" . '§e' . date('i:s', $countdown));
        } else {
            $this->bossbar->setTitle("\n\n" . '§e' . date('i:s', $countdown));
        }

        
        foreach ($this->TeamManager->getAllPlayers() as $player) {
            $player->sendPopup($message);
            if ($countdown < 0) continue;
            if(!$player->getBossbar(0) instanceof Bossbar){
                $player->addBossbar($this->bossbar, 0);
            }
            $pteam = $this->TeamManager->getTeamOf($player);

            $this->bossbar->setHealthPercent($countdown / $duration * 1000);
            //$player->addBossbar($this->bossbar);

            if ($countdown < 6 && $this->TeamManager->isJoin($player) && $count !== $this->lastcount) {
                $player->addTitle('§6' . $countdown, '', 2, 16, 2);
            }
        }
        $this->lastcount = $count;
    }
    
}