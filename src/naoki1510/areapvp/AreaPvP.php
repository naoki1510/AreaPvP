<?php
namespace naoki1510\areapvp;

use naoki1510\areapvp\commands\pvpCommand;
use naoki1510\areapvp\commands\setareaCommand;
use naoki1510\areapvp\commands\setspCommand;
use naoki1510\areapvp\events\GameStartEvent;
use naoki1510\areapvp\tasks\GameTask;
use naoki1510\areapvp\tasks\SendMessageTask;
use naoki1510\areapvp\team\Team;
use naoki1510\areapvp\team\TeamManager;
use onebone\economyapi\EconomyAPI;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\block\Stair;
use pocketmine\entity\utils\Bossbar;
use pocketmine\event\Listener;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\cheat\PlayerIllegalMoveEvent;
use pocketmine\item\Fireworks;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;



class AreaPvP extends PluginBase implements Listener
{
    public const BOSSBAR_ID = 0;

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

    /** @var Team[] */
    public $leaver;

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
        $this->getScheduler()->scheduleRepeatingTask($this->SendMessageTask, 10);
        // game start!
        $this->start();
    }

    public function start(){
        //$ev = new GameStartEvent;
        //($ev)->call();

        $this->GameTask->setCount(0);
        $this->running = true;

        $levelnames = $this->getConfig()->get("worlds", ['pvp']);
        $level = Server::getInstance()->getLevelByName($levelnames[rand(0, count($levelnames) - 1)]);
        if(!$level instanceof Level){
            $this->getLogger()->warning('ワールドが見つかりません。');
            $level = Server::getInstance()->getDefaultLevel();
        }
        $this->gameLevel = $level;
        // リスポーン地点を更新
        $this->TeamManager->reloadRespawn();
        // ポイントをリセット
        foreach ($this->TeamManager->getAllTeams() as $team) {
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
                    $this->economy->addMoney($player, $winteam->getPoint(), false, "AreaPvP");
                } else {
                    $player->addTitle('§9You Lose...', '§6Let\'s win next time', 2, 36, 2);
                }
            }
        }else{
            foreach ($this->TeamManager->getAllPlayers() as $player) {
                $player->addTitle('§9Draw', '§6Let\'s win next time', 2, 36, 2);
            }
        }
        //$this->TeamManager->leaveAll();
    }

    public function onDisable(){
        foreach ($this->TeamManager->getAllPlayers() as $player) {
            $this->TeamManager->leaveTeam($player);
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

    public function isSafeBlock(Block $block, ?Player $player = null) : bool{
        if(empty($player)){
            try {
                $safeblock = Item::fromString($this->getConfig()->getNested('block.safe', 'stained_glass'));
                if ($block->getId() === $safeblock->getId())
                    return true;
            } catch (\InvalidArgumentException $e) {
                $this->getLogger()->warning('SafeBlock is invalid');
                $this->getLogger()->warning($e->getMessage());
            }
        }else{
            try {
                $safeblock = Item::fromString($this->getConfig()->getNested('block.safe', 'stained_glass'));
                $teamcolor = $this->TeamManager->getTeamOf($player)->getColor('block');
                if ($block->getId() === $safeblock->getId()
                    && $block->getDamage() === $teamcolor)
                    return true;
            } catch (\InvalidArgumentException $e) {
                $this->getLogger()->warning('SafeBlock is invalid');
                $this->getLogger()->warning($e->getMessage());
            }
        }
        return false;
    }

    // Event
    public function onDrop(PlayerDropItemEvent $e){
        if(in_array($e->getPlayer()->getLevel()->getName(), $this->getConfig()->get('worlds'))){
            $e->setCancelled();
        }
    }

    // Damage on safeblock
    public function onEntityDamage(EntityDamageEvent $event) : void
    {
        $entity = $event->getEntity();
        $world = $entity->getLevel();
        $block = $world->getBlock($entity->subtract(0, 0.5))->getId() ? $world->getBlock($entity->subtract(0, 1.5)) : $world->getBlock($entity->subtract(0, 0.5));

        if($entity instanceof Player && $this->TeamManager->isJoin($entity)){
            //if($this->isSafeBlock($block, $entity)){
            if($this->isSafeBlock($block)){
                $event->setCancelled();
            }
        }
    }

    public function onMove(PlayerMoveEvent $e)
    {
        $player = $e->getPlayer();
        $level = $player->getLevel();
        $block = ($level->getBlock($player->subtract(0, 0.5))->getId() == 0) ? $level->getBlock($player->subtract(0, 1.5)) : $level->getBlock($player->subtract(0, 0.5));
        if ($this->TeamManager->isJoin($player)) {
            if ($this->isSafeBlock($block, $player)) {
                $player->setHealth($player->getMaxHealth());
                $player->setFood($player->getMaxFood());
            }
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
                    
                    $drops = [
                        Item::fromString('cooked_beef')->setCount(3),
                        Item::get(Item::WOOD, 0, 8),
                        Item::get(Item::PLANKS, 0, 16),
                        Item::get(Item::COBBLESTONE, 0, 8)
                    ];
                    
                    $event->setDrops($drops);
                    EconomyAPI::getInstance()->addMoney($killer, $this->getConfig()->getNested('game.killpoint'), 100);
                }
            }
        }

        if ($victim->getLevel() == $this->gameLevel) {
            if ($this->TeamManager->getTeamOf($victim) !== null) $this->TeamManager->getTeamOf($victim)->addPoint($this->getConfig()->getNested('game.deathpoint'), -10);

            $event->setDrops([]);
        }
    }

    /** @param SignChangeEvent|Sign $sign */
    public function reloadSign($sign)
    {
        try {
            if (preg_match('/^(§[0-9a-fklmnor])*\[?(§[0-9a-fklmnor])*pvp(§[0-9a-fklmnor])*\]?$/iu', trim($sign->getLine(0))) == 1) {

                $sign->setLine(0, '§a[§lPvP§r§a]');
                $sign->setLine(1, '§l§eタップでPvPに参加!!');
                $sign->setLine(2, '§cルールを読んでから');
                $sign->setLine(3, '§c参加してください');
                
            }
        } catch (\BadMethodCallException $e) {
            $this->getLogger()->warning($e->getMessage());
        }
    }

    public function onSignChange(SignChangeEvent $e)
    {
        $this->reloadSign($e);
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
                    $this->reloadSign($sign);
                    if(!$this->TeamManager->isJoin($player))
                    $this->TeamManager->joinTeam($player);
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
        if ($this->TeamManager->isJoin($event->getPlayer())) {
            $this->TeamManager->leaveTeam($event->getPlayer());
            $this->leaver[$event->getPlayer()->getName()] = $this->TeamManager->getTeamOf($event->getPlayer());
        }
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        if($player->getLevel() === $this->gameLevel && empty($this->leaver[$player->getName()])){
            $this->TeamManager->joinTeam($player);
        }elseif(!empty($this->leaver[$player->getName()])){
            ($this->leaver[$player->getName()])->add($player);
        }
    }

    public function onMoveWorld(EntityLevelChangeEvent $e){
        $player = $e->getEntity();
        if($player instanceof Player){
            if(!$this->TeamManager->isJoin($player)){
                $player->removeBossbar(0);
            }
        }
    }
}
