<?php

namespace naoki1510\areapvp\team;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class EventListener implements Listener
{

    /** @var TeamManager */
    private $TeamManager;

    public function __construct(TeamManager $teamManager)
    {
        $this->TeamManager = $teamManager;
    }

    public function onPlayerAttack(EntityDamageByEntityEvent $event)
    {
        $damaged = $event->getEntity();
        $attacker = $event->getDamager();
        
        if ($damaged instanceof Player && $attacker instanceof Player && $this->TeamManager->isJoin($damaged) && $this->TeamManager->isJoin($attacker)) {
            
            if ($this->TeamManager->getTeamOf($damaged) === $this->TeamManager->getTeamOf($attacker)) {
                $event->setCancelled();
            }
        }
    }

    public function onPacketSend(DataPacketSendEvent $e)
    {
        if ($e->getPacket()->getName() === 'SetEntityDataPacket' || $e->getPacket()->getName() === 'AddPlayerPacket') {
            $targetplayer = $e->getPlayer();
            if (isset($e->getPacket()->metadata[4][1]) && isset($e->getPacket()->entityRuntimeId)) {
                $sourceplayer = Server::getinstance()->findEntity($e->getPacket()->entityRuntimeId);

                if (!empty($sourceplayer)
                    &&
                    $this->TeamManager->isJoin($sourceplayer)
                    &&
                    !$this->TeamManager->getTeamOf($sourceplayer)->exists($targetplayer)) {

                    if (isset($e->getPacket()->metadata[4][1])) {
                        $e->getPacket()->metadata[4][1] = '';
                    }

                    if (isset($e->getPacket()->username)) {
                        $e->getPacket()->username = '';
                    }
                }

            }
        }
    }
}
