<?php

namespace naoki1510\areapvp;

use Crypto\Rand;
use naoki1510\areapvp\AreaPvP;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\event\Listener;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\Item;
use pocketmine\tile\Sign;

class EventListener implements Listener
{
    /** @var AreaPvP */
    public $AreaPvP;

    public function __construct(AreaPvP $plugin) {
        $this->AreaPvP = $plugin;
    }

    /* Event */

    // Damage on safeblock
    public function onEntityDamage(EntityDamageEvent $event) : void
    {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            $world = $entity->getLevel();
            if (!$this->AreaPvP->isGameLevel($world)) return;
            $block = $world->getBlock($entity->subtract(0, 0.5))->getId() ? $world->getBlock($entity->subtract(0, 0.5)) : $world->getBlock($entity->subtract(0, 1.5));
            if ($this->AreaPvP->isSafeBlock($block, $entity)) {
                $event->setCancelled();
                if($event instanceof EntityDamageByEntityEvent){
                    $attacker = $event->getDamager();
                    if($attacker instanceof Player){
                        $attacker->sendMessage('You can\'t attack players in SafeArea');
                    }
                }
            }
        }
    }

    public function onMove(PlayerMoveEvent $e)
    {
        $player = $e->getPlayer();
        $level = $player->getLevel();
        if (!$this->AreaPvP->isGameLevel($level)) return;
        $block = $level->getBlock($player->subtract(0, 0.5))->getId() ? $level->getBlock($player->subtract(0, 0.5)) : $level->getBlock($player->subtract(0, 1.5));
        if ($this->AreaPvP->isSafeBlock($block, $player)) {
            $player->setHealth($player->getMaxHealth());
            $player->setFood($player->getMaxFood());
        }
    }

    public function onPlayerDeath(PlayerDeathEvent $event) : void
    {
        $victim = $event->getPlayer();
        if ($victim->getLastDamageCause() instanceof EntityDamageByEntityEvent) {
            if ($victim->getLastDamageCause()->getDamager() instanceof Player) {
                if ($victim->getLevel() === $this->AreaPvP->getGameLevel()) {
                    $killer = $victim->getLastDamageCause()->getDamager();
                    if ($this->AreaPvP->getTeamManager()->getTeamOf($killer) !== null) 
                    $this->AreaPvP->getTeamManager()->getTeamOf($killer)->addPoint($this->getConfig()->getNested('game.killpoint'), 100);

                    $drops = [
                        Item::fromString('cooked_beef')->setCount(3),
                        Item::get(Item::WOOD, 0, 8),
                        Item::get(Item::PLANKS, 0, 8),
                        Item::get(Item::COBBLESTONE, 0, 8)
                    ];

                    foreach ($event->getDrops() as $key => $item) {
                        if(!rand(0,3)) array_push($drops, $item);
                    }

                    $event->setDrops($drops);
                    EconomyAPI::getInstance()->addMoney($killer, $this->getConfig()->getNested('game.killpoint'), 100);
                }
            }
        }

        if ($victim->getLevel() == $this->AreaPvP->getGameLevel()) {
            $event->setDrops([]);
        }
    }

    public function onSignChange(SignChangeEvent $e)
    {
        $this->AreaPvP->reloadSign($e);
    }


    public function onPlayerTap(PlayerInteractEvent $e)
    {
        $player = $e->getPlayer();
        if ($player->isSneaking()) return;

        $block = $e->getBlock();
        switch ($block->getId()) {
            // 看板
            case Block::WALL_SIGN:
            case Block::SIGN_POST:
                $sign = $block->getLevel()->getTile($block->asPosition());

                if ($sign instanceof Sign && preg_match('/^(§[0-9a-fklmnor])*\[(§[0-9a-fklmnor])*pvp(§[0-9a-fklmnor])*\]$/iu', trim($sign->getLine(0))) == 1) {
                    $this->AreaPvP->reloadSign($sign);
                    if (!$this->AreaPvP->getTeamManager()->isJoin($player))
                        $this->AreaPvP->getTeamManager()->joinTeam($player);
                }
                break;

            default:
                return;
                break;
        }
        $e->setCancelled();
    }

    public function onQuit(PlayerQuitEvent $event)
    {
        if ($this->AreaPvP->getTeamManager()->isJoin($event->getPlayer())) {
            $team = $this->AreaPvP->getTeamManager()->getTeamOf($event->getPlayer());
            $this->leaver[$event->getPlayer()->getName()] = $team;
            $team->remove($event->getPlayer());
        }
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        if ($player->getLevel() === $this->AreaPvP->getGameLevel() && empty($this->leaver[$player->getName()])) {
            //$this->getLogger()->info('Moving and join Team');
            $this->AreaPvP->getTeamManager()->joinTeam($player);
            //$player->teleport($this->AreaPvP->getServer()->getDefaultLevel()->getSafeSpawn());
        } elseif ($player->getLevel() === $this->AreaPvP->getGameLevel() && !empty($this->leaver[$player->getName()])) {
            //$this->getLogger()->info('Moving and rejoin Team');
            ($this->leaver[$player->getName()])->add($player);
        }
    }

    public function onMoveWorld(EntityLevelChangeEvent $e)
    {
        $target = $e->getTarget();
        $origin = $e->getOrigin();
        //var_dump($target, $origin);
        if($target === $this->AreaPvP->getGameLevel() && $e->getEntity() instanceof Player){
            if(!$this->AreaPvP->getTeamManager()->isJoin($e->getEntity()));
            //$this->AreaPvP->getTeamManager()->joinTeam($e->getEntity());
        }
    }
}
