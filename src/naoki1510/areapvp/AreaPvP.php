<?php
namespace naoki1510\areapvp;

use naoki1510\areapvp\commands\pvpCommand;
use naoki1510\areapvp\commands\setareaCommand;
use naoki1510\areapvp\commands\setspCommand;
use naoki1510\areapvp\events\GameStartEvent;
use naoki1510\areapvp\tasks\GameTask;
use naoki1510\areapvp\tasks\SendMessageTask;
use naoki1510\areapvp\team\TeamManager;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\block\Stair;
use pocketmine\entity\utils\Bossbar;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\cheat\PlayerIllegalMoveEvent;
use pocketmine\item\Fireworks;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;



class AreaPvP extends PluginBase implements Listener
{
    /** @var Config */
    private static $messages;

    public static function translate(string $key, array $replaces = array()) : string
    {
        if ($rawMessage = self::$messages->getNested($key)) {
            if (is_array($replaces)) {
                foreach ($replaces as $replace => $value) {
                    $rawMessage = str_replace("{" . $replace . "}", $value, $rawMessage);
                }
            }
            return $rawMessage;
        }
        return $key;
    }

    /** @var Config */
    private $inventories;

    /** @var TeamManager */
    private $TeamManager;

    /** @var EconomyAPI */
    private $economy;

    /** @var bool */
    public $running;

    /** @var Level */
    private $gameLevel;

    /** @var GameTask */
    private $GameTask;
    
    /** @var SendMessageTask */
    private $SendMessageTask;

    public function onEnable()
    {
        self::$messages = new Config(
            $this->getFile() . "resources/languages/" . $this->getConfig()->get("language", "en") . ".yml"
        );

        // register API
        $this->TeamManager = new TeamManager($this);
        $this->economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");

        // About config
        $this->saveDefaultConfig();
        // register Listener
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        // register commands
        $this->getServer()->getCommandMap()->register('areapvp', new pvpCommand($this, $this->TeamManager));
        $this->getServer()->getCommandMap()->register('areapvp', new setspCommand($this, $this->TeamManager));
        $this->getServer()->getCommandMap()->register('areapvp', new setareaCommand($this, $this->TeamManager));

        // register tasks
        $this->GameTask = new GameTask(
            $this,
            Item::fromString($this->getConfig()->getNested('block.area', Block::WOOL))->getBlock(),
            $this->getConfig()->getNested('game.minPlayers', 2),
            $this->TeamManager
        );
        $this->getScheduler()->scheduleRepeatingTask($this->GameTask, $this->getConfig()->get('CheckInterval', 0.1) * 20);

        $this->SendMessageTask = new SendMessageTask($this, $this->TeamManager);
        $this->getScheduler()->scheduleRepeatingTask($this->SendMessageTask, 4);

        // game start!
        $this->start();
    }

    public function start(){
        $ev = $this->getServer()->getPluginManager()->callEvent(new GameStartEvent);
        // rejoin players to team
        if($this->gameLevel instanceof Level){
            foreach ($this->gameLevel->getPlayers() as $player) {
                $this->TeamManager->joinTeam($player);
            }
        }else{
            foreach ($this->getConfig()->get("worlds", ['pvp']) as $levelname) {
                if(($level = $this->getServer()->getLevelByName($levelname)) instanceof Level){
                    foreach ($level->getPlayers() as $player) {
                        $this->TeamManager->joinTeam($player);
                    }
                }
            }
        }

        $this->GameTask->setCount(0);
        $this->running = true;

        $levelnames = $this->getConfig()->get("worlds", ['pvp']);
        $level = Server::getInstance()->getLevelByName($levelnames[rand(0, count($levelnames) - 1)]);
        $this->gameLevel = $level;

        $this->TeamManager->reloadRespawn();

        foreach ($this->TeamManager->getAllTeams() as $team) {
            $team->respawnAllPlayers();
            $team->setPoint(0);
        }

    }

    /**
     * @todo configurable message
     */
    public function finish()
    {
        $this->running = false;
        $points = [];
        $oneteam = true;
        foreach ($this->TeamManager->getAllTeams() as $team) {
            if (empty($points[$team->getPoint()])) {
                $points[$team->getPoint()] = $team;
            } else {
                $oneteam = false;
            }
        }

        krsort($points);
        $winteam = array_shift($points);

        if ($oneteam) {
            foreach ($this->TeamManager->getAllPlayers() as $player) {
                if ($winteam->exists($player)) {
                    $player->addTitle('§cYou win!!', '§6Congratulations!', 2, 36, 2);
                    $this->economy->addMoney($player, $winteam->getPoint() * $this->getConfig()->get('game.prizeratio', 1), false, "HotBlock");
                } else {
                    $player->addTitle('§9You Lose...', '§6Let\'s win next time', 2, 36, 2);
                }

                $items = [];
                for ($i = 0; $i < 9; $i++) { 
                    $items[$i] = $this->getFireWorks();
                }
                //$player->getInventory()->setContents($items);
                
            }
        }else{
            foreach ($this->TeamManager->getAllPlayers() as $player) {
                
                $player->addTitle('§9Draw', '§6Let\'s win next time', 2, 36, 2);
                

                $items = [];
                for ($i = 0; $i < 9; $i++) {
                    $items[$i] = $this->getFireWorks();
                }
                
                //$player->getInventory()->setContents($items);

            }
        }

        $this->TeamManager->leaveAll();
    }

    public function getGameLevel() : Level {
        return $this->gameLevel ?? $this->getServer()->getDefaultLevel();
    }

    public function getGameDuration() : Int {
        return $this->getConfig()->getNested('game.duration', 180);
    }

    public function getInterval() : Int {
        return $this->getConfig()->getNested('game.interval', 15);
    }

    public function getGameTask() : GameTask {
        return $this->GameTask;
    }

    public function isRunning() : bool {
        return $this->running ?? false;
    }

    public function getFireWorks()
    {
        $firework = new Fireworks();
        $firework->setFlightDuration(1);
        $firework->addExplosion(rand(0, 4), ["\x01", "\x04", "\x09", "\x0a", "\x0b", "\x0c", "\x0e", "\x0f"][rand(0, 7)]);
        return $firework;
    }

    public function onDrop(PlayerDropItemEvent $e){
        if(in_array($e->getPlayer()->getLevel()->getName(), $this->getConfig()->get('worlds'))){
            $e->setCancelled();
        }
    }

    public function onEntityDamage(EntityDamageEvent $event) : void
    {
        $entity = $event->getEntity();
        $world = $entity->getLevel();
        $block = ($world->getBlock($entity->subtract(0, 0.5))->getId() == 0) ? $world->getBlock($entity->subtract(0, 1.5)) : $world->getBlock($entity->subtract(0, 0.5));

        if($entity instanceof Player && $this->TeamManager->isJoin($entity)){
            try{
                if ($block->getId() === Item::fromString($this->getConfig()->getNested('block.safe', 'stained_glass'))->getId()
                    && $block->getDamage() === $this->TeamManager->getTeamOf($entity)->getColor()['block']) {
                    $event->setCancelled();
                }
            }catch(\InvalidArgumentException $e){
                $this->getLogger()->warning('SafeBlock is invalid');
                $this->getLogger()->warning($e->getMessage());
            }
        }
    }

    public function onMove(PlayerMoveEvent $e)
    {
        $player = $e->getPlayer();
        $level = $player->getLevel();
        $block = ($level->getBlock($player->subtract(0, 0.5))->getId() == 0) ? $level->getBlock($player->subtract(0, 1.5)) : $level->getBlock($player->subtract(0, 0.5));
        if ($this->TeamManager->isJoin($player)) {
            try {
                if ($block->getId() === Item::fromString($this->getConfig()->getNested('block.safe', 'stained_glass'))->getId()
                    && $block->getDamage() === $this->TeamManager->getTeamOf($player)->getColor()['block']) {
                    $player->setHealth($player->getMaxHealth());
                    $player->setFood($player->getMaxFood());
                }
            } catch (\InvalidArgumentException $e) {
                $this->getLogger()->warning('SafeBlock is invalid');
                $this->getLogger()->warning($e->getMessage());
            }
        }
            
            
            
            /*$blockUnderPlayer->getId() == Block::STAINED_GLASS && in_array($level->getName(), $this->getConfig()->get('worlds', []))) {
            $kit = $this->playerdata->get($player->getName());

            $this->setItems($player, $kit);
            $player->setHealth($player->getMaxHealth());
            $player->setFood($player->getMaxFood());
        }*/
		//var_dump($this->getConfig()->get('worlds', ['pvp']));
    }

    public function onIllegalMove(PlayerIllegalMoveEvent $e){
        $player = $e->getPlayer();
        $world = $player->getLevel();
        $block = ($world->getBlock($player->subtract(0, 0.5))->getId() == 0) ? $world->getBlock($player->subtract(0, 1.5)) : $world->getBlock($player->subtract(0, 0.5));
        if($block instanceof Stair){
            $e->setCancelled();
            //$player->sendMessage("cheat?");
        }
    }

    public function onPlayerDeath(PlayerDeathEvent $event) : void
    {
        $victim = $event->getPlayer();
        if ($victim->getLastDamageCause() instanceof EntityDamageByEntityEvent) {
            if ($victim->getLastDamageCause()->getDamager() instanceof Player) {
                if ($victim->getLevel() == $this->gameLevel) {
                    $killer = $victim->getLastDamageCause()->getDamager();
                    if($this->TeamManager->getTeamOf($killer) !== null) $this->TeamManager->getTeamOf($killer)->addPoint($this->getConfig()->getNested('game.killpoint'), 100);
                    
                    $drops = [];
                    foreach ($event->getDrops() as $item) {
                        if(rand(0, 99) < 5){
                            //$drops += [$item];
                        }
                    }

                    $event->setDrops([Item::fromString('cooked_beef')->setCount(3)] + $drops);
                    //$this->getLogger()->info("アイテムのドロップをキャンセル！");
                    EconomyAPI::getInstance()->addMoney($killer, $this->getConfig()->getNested('game.killpoint'), 100);
                }
            }
        }

        if ($victim->getLevel() == $this->gameLevel) {
            if ($this->TeamManager->getTeamOf($victim) !== null) $this->TeamManager->getTeamOf($victim)->addPoint($this->getConfig()->getNested('game.deathpoint'), -10);

            $event->setDrops([Item::fromString('cooked_beef')->setCount(3)]);
            //$this->getLogger()->info("アイテムのドロップをキャンセル！");
        }
    }


    /*
    public function onTeleportWorld(EntityLevelChangeEvent $e)
    {
		//$this->getLogger()->info('MovingWorld detected.');

		//Playerなどイベント関連情報を取得
        $player = $e->getEntity();
        if (!$player instanceof Player) {
            return;
        }
        $target = $e->getTarget();
        $origin = $e->getOrigin();
        if ($target === $this->gameLevel) {
            //$this->getLogger()->info('MovingWorld (1) detected.');
            
            $oitems = $player->getInventory()->getContents();

            $items = [];
            foreach ($this->inventories->getNested($player->getName() . '.' . $target->getName(), []) as $item) {
                if ($item instanceof Item) {
                    array_push($items, $item);
                } elseif (is_string($item)) {
                    if (($item = unserialize($item)) instanceof Item) {
                        array_push($items, $item);
                    }
                }
            }
            $player->getInventory()->setContents($items);

            $this->inventories->setNested($player->getName() . '.origin', $oitems);
        } elseif ($origin === $this->gameLevel) {
            //$this->getLogger()->info('MovingWorld (2) detected.');

            $player->setNameTagVisible(true);
            $items = $player->getInventory()->getContents();
            $this->inventories->setNested($player->getName() . '.' . $origin->getName(), $items);
            $items = [];
            foreach ($this->inventories->getNested($player->getName() . '.origin', []) as $item) {
                if ($item instanceof Item) {
                    array_push($items, $item);
                } elseif (is_string($item)) {
                    if (unserialize($item) instanceof Item) {
                        array_push($items, unserialize($item));
                    }
                }
            }
            $player->getInventory()->setContents($items);
        }
        $this->inventories->save();
    }*/

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        if($player->getLevel() === $this->gameLevel){
            $player->teleport($this->getServer()->getDefaultLevel()->getSpawnLocation());
            $player->setSpawn($this->getServer()->getDefaultLevel()->getSpawnLocation());
        }
    }
}
