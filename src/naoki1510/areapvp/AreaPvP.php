<?php
namespace naoki1510\areapvp;

use naoki1510\areapvp\commands\pvpCommand;
use naoki1510\areapvp\commands\setspCommand;
use naoki1510\areapvp\tasks\GameTask;
use naoki1510\areapvp\tasks\SendMessageTask;
use naoki1510\areapvp\team\TeamManager;
use onebone\economyapi\EconomyAPI;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\block\Stair;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDropItemEvent;
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

        $this->TeamManager = new TeamManager($this);
        $this->economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");

        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getCommandMap()->register('areapvp', new pvpCommand($this->TeamManager));
        $this->getServer()->getCommandMap()->register('areapvp', new setspCommand($this, $this->TeamManager));

        $this->GameTask = new GameTask(
            $this,
            Item::fromString($this->getConfig()->getNested('block.area', Block::WOOL))->getBlock(),
            $this->getConfig()->getNested('game.minPlayers', 2),
            $this->TeamManager
        );
        $this->getScheduler()->scheduleRepeatingTask($this->GameTask, $this->getConfig()->get('CheckInterval', 0.1) * 20);

        $this->SendMessageTask = new SendMessageTask($this, $this->TeamManager);
        $this->getScheduler()->scheduleRepeatingTask($this->SendMessageTask, 1 * 20);

        $this->start();
    }

    public function start(){
        $this->TeamManager->reJoin();

        $this->GameTask->setCount(0);
        $this->running = true;

        $levelnames = $this->getConfig()->get("worlds", ['pvp']);
        $level = Server::getInstance()->getLevelByName($levelnames[array_rand($levelnames)]);
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
                $player->getInventory()->setContents($items);
                
            }
        }else{
            foreach ($this->TeamManager->getAllPlayers() as $player) {
                
                $player->addTitle('§9Draw', '§6Let\'s win next time', 2, 36, 2);
                

                $items = [];
                for ($i = 0; $i < 9; $i++) {
                    $items[$i] = $this->getFireWorks();
                }
                
                $player->getInventory()->setContents($items);

            }
        }

        foreach ($this->TeamManager->getAllTeams() as $team) {
            $team->removeAllPlayers(false);
        }
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

        if ($entity instanceof Player
            && in_array($world->getName(), $this->getConfig()->get("world", ['pvp']))
            && $this->TeamManager->exists($entity)
            && $block->getId() === Item::fromString($this->getConfig()->getNested('block.safe', 'stained_glass'))->getId()
            && $block->getDamage() === $this->TeamManager->getTeamOf($entity)->getColor()['block']) {
            $event->setCancelled();
        }
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
}
