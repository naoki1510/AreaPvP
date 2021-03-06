<?php

namespace naoki1510\areapvp\team;

use naoki1510\areapvp\AreaPvP;
use naoki1510\areapvp\tasks\PlayerTeleportTask;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\SetEntityDataPacket;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\utils\Config;

/** @todo remove dependence on areapvp to make a plugin 'TeamAPI' */
class TeamManager{

	/** @var TeamManager */
	private static $instance;

	/**
	 * @return TeamManager
	 */
	public static function getInstance() : TeamManager
	{
		return self::$instance;
	}

	/** @var AreaPvP */
	private $AreaPvP;

	/** @var GameManager */
	private $gameManager;
	
	/** @var Team[] */
	private $teams;

	/** @var Player[] */
	private $players;

	/** @var Config */
	public $teamConfig;

	public function __construct(AreaPvP $AreaPvP)
	{
		self::$instance = $this;

		$this->AreaPvP = $AreaPvP;
		$AreaPvP->getServer()->getPluginManager()->registerEvents(new EventListener($this), $AreaPvP);

		$AreaPvP->saveResource('teams.yml');
		$this->teamConfig = new Config($AreaPvP->getDataFolder() . 'teams.yml', Config::YAML);
		foreach ($this->teamConfig->getAll() as $name => $data) {

			$spawndata = $this->teamConfig->getNested($name . '.respawns.' . $AreaPvP->getGameLevel()->getName() . '.' . $name, 'not set');
			if(substr_count($spawndata, ',') == 3){
				list($x, $y, $z, $level) = explode(',', $spawndata);
				$respawn = new Position((Int)$x, (Int)$y, (Int)$z, Server::getInstance()->getLevelByName($level) ?? Server::getInstance()->getDefaultLevel());
			}else{
				$respawn = Server::getInstance()->getDefaultLevel()->getSpawnLocation();
			}
			
			$this->teams[$name] = new Team($this, $name, $data['color'] ?? ['text' => 0, 'block' => 0], $respawn);
		}

		$this->players = [];
	}

	/**
	 * Make player belong to team
	 *
	 * @param Player $player
	 * @return bool
	 */
	public function joinTeam(Player $player) : bool{
		if($this->isJoin($player)) {
			return false;
		}
	    $minTeams = [];
	    $minPlayers = Server::getInstance()->getMaxPlayers();
	    foreach ($this->teams as $team) {
	        if ($minPlayers > $team->getPlayerCount()) {
	            $minTeams = [$team];
	            $minPlayers = $team->getPlayerCount();
	        }elseif ($minPlayers == $team->getPlayerCount()) {
	        	array_push($minTeams, $team);
	        }
		}
	    $addTeam = $minTeams[rand(0, count($minTeams) - 1)];
	    $this->players[$player->getName()] = $player;
	    
		foreach ($this->players as $source) {
			if (!$addTeam->exists($source)) {
				$player->sendData($source);
		    }
		}
		$spawn = $addTeam->getSpawn();
		for ($x = -1; $x <= 1; $x++) {
			for ($z = -1; $z <= 1; $z++) {
				$spawn->getLevel()->loadChunk(($spawn->getFloorX() >> 4) + $x, ($spawn->getFloorZ() >> 4) + $z);
			}
		}
		//$player->teleport($addTeam->getSpawn());
		$this->AreaPvP->getScheduler()->scheduleDelayedTask(new PlayerTeleportTask($player, $spawn), 20);
		
	    return $addTeam->add($player);
	}

	public function leaveTeam(Player $player, bool $teleport = false) : void{
		$player->removeBossbar(0);
		if(!$this->isJoin($player)) return;

		$this->getTeamOf($player)->remove($player);
		unset($this->players[$player->getName()]);
		if($teleport) $player->teleport(Server::getInstance()->getDefaultLevel()->getSpawnLocation());

		return;
	}

	public function isJoin(Player $player) : bool{
		foreach ($this->teams as $team) {
			if($team->exists($player)) return true;
		}
		return false;
	}

	/**
	 * @return null|Team
	 */
	public function getTeamOf(Player $player) {
		foreach ($this->teams as $team) {
			if ($team->exists($player)) return $team;
		}
		return null;
	}

	/** 
	 * @return null|Team
	 */
	public function getTeam(String $teamName) {
		if ($this->existsTeam($teamName)) {
			return $this->teams[$teamName];
		}
		return null;
	}

	public function existsTeam(string $teamName) : bool {
		return isset($this->teams[$teamName]);
	}

	public function setSpawn(Position $pos, Team $team){
		$posstr = \implode(',', [$pos->x, $pos->y, $pos->z, $pos->level->getName()]);
		$this->teamConfig->setNested($team->getName() . '.respawns.' . $pos->getLevel()->getName() . '.' . $team->getName(), $posstr);
		$this->teamConfig->save();
	}

	/** 
	 * @return Player[]
	 */
	public function getAllPlayers(){
		$players = [];
		foreach ($this->teams as $team) {
			$players = array_merge($players, $team->getAllPlayers());
		}

		return $players;
	}

	/**
	 * @return Team[]
	 */
	public function getAllTeams(){
		return $this->teams;
	}

	public function getAllPoints() : int{
		$points = 0;
		foreach ($this->teams as $team) {
			$points += $team->getPoint();
		}

		return $points;
	}

	public function leaveAll()
	{
		foreach ($this->players as $playername => $player) {
			$this->leaveTeam($player, true);
		}
	}

	public function setArea(Position $pos, Int $num){
		$this->AreaPvP->getConfig()->setNested($pos->getLevel()->getName() . '.pos' . $num, implode(',', [$pos->x, $pos->y, $pos->z]));
		$this->AreaPvP->saveConfig();
	}

	// use this when gamelevel was changed
	public function reloadRespawn(){
		foreach ($this->teams as $name => $team) {
			$spawndata = $this->teamConfig->getNested($name . '.respawns.' . $this->AreaPvP->getGameLevel()->getName() . '.' . $name, 'not set');
			if (substr_count($spawndata, ',') == 3) {
				list($x, $y, $z, $level) = explode(',', $spawndata);
				$respawn = new Position((Int)$x, (Int)$y, (Int)$z, Server::getInstance()->getLevelByName($level) ?? Server::getInstance()->getDefaultLevel());
			} else {
				$respawn = Server::getInstance()->getDefaultLevel()->getSpawnLocation();
			}

			$team->setSpawn($respawn);
		}
	}
}