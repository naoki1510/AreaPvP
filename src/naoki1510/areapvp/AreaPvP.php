<?php
namespace naoki1510\areapvp;

use naoki1510\areapvp\EventListener;
use naoki1510\areapvp\commands\pvpCommand;
use naoki1510\areapvp\commands\setareaCommand;
use naoki1510\areapvp\commands\setspCommand;
use naoki1510\areapvp\events\GameStartEvent;
use naoki1510\areapvp\tasks\GameTask;
use naoki1510\areapvp\tasks\SendMessageTask;
use naoki1510\areapvp\team\Team;
use naoki1510\areapvp\team\TeamManager;
use naoki1510\kitplugin\KitPlugin;
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
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
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
        //$ev->call();
        $this->GameTask->setCount(0);
        //$this->running = true;

        $levelnames = $this->getConfig()->get("worlds", ['pvp']);
        $level = Server::getInstance()->getLevelByName($levelnames[$i = rand(0, count($levelnames) - 1)]);
        if(!$level instanceof Level){
            $this->getLogger()->warning('ワールドが見つかりません。(' . $levelnames[$i] . ')');
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
        // チームが一つだけかどうかのフラグ
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
                    // お金あげる
                    $this->economy->addMoney($player, $winteam->getPoint(), false, "AreaPvP");
                    // 経験値
                    $kitplugin = $this->getServer()->getPluginManager()->getPlugin('KitPlugin');
                    if($kitplugin instanceof KitPlugin){
                        $kitplugin->addExp($player, null, $winteam->getPoint());
                    }
                    //var_dump($winteam->getPoint());
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

    /**
     * 使わない
     */
    public function getInterval() : Int {
        return $this->getConfig()->getNested('game.interval', 15);
    }

    /**
     * 使わない
     */
    public function isRunning() : bool {
        return $this->running ?? false;
    }

    public function getGameTask() : GameTask {
        return $this->GameTask;
    }

    public function getTeamManager() : TeamManager{
        return $this->TeamManager;
    }

    /**
     * Configで設定されたSafeBlockかどうか調べる。
     * 
     * @param Block $block check block.
     * @param Player|null $player You should pass $player to this function if you want to check color.
     */
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
                $teamcolor = $this->TeamManager->isJoin($player) ? $this->TeamManager->getTeamOf($player)->getColor('block') : null;
                //var_dump($safeblock, $teamcolor, $block->getVariant());
                if ($block->getId() === $safeblock->getId()
                    && $block->getVariant() === $teamcolor)
                    return true;
            } catch (\InvalidArgumentException $e) {
                $this->getLogger()->warning('SafeBlock is invalid');
                $this->getLogger()->warning($e->getMessage());
            }
        }
        return false;
    }

    public function isGameLevel(Level $level)
    {
        return $level === $this->gameLevel;
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
}
