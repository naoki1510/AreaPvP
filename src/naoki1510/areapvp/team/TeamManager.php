<?php

namespace naoki1510\areapvp\team;

use naoki1510\areapvp\AreaPvP;
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
				$this->sendNameTag($player, $source, '');
		        
		    }
		}

		$player->teleport($addTeam->getSpawn());
		$player->setAllowMovementCheats(true);
		
	    return $addTeam->add($player);
	}

	public function leaveTeam(Player $player) : void{
		if(!$this->isJoin($player)) return;

		$this->getTeamOf($player)->remove($player);
		unset($this->players[$player->getName()]);
		$player->teleport(Server::getInstance()->getDefaultLevel()->getSpawnLocation());

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
		foreach ($this->players as $playername => $team) {
			array_push($players, Server::getInstance()->getPlayer($playername));
		}

		return $players;
	}

	/**
	 * @return Team[]
	 */
	public function getAllTeams(){
		return $this->teams;
	}

	public function reJoin()
	{
		foreach ($this->players as $player) {
			$this->joinTeam($player);
		}
		
	}

    // This function is based on Entity::sendData()
    public function sendNameTag($targetplayer, Player $sourceplayer, String $nametag) : void{
		if(!is_array($targetplayer)){
			$targetplayer = [$targetplayer];
		}

		$pk = new SetEntityDataPacket();
		$pk->entityRuntimeId = $sourceplayer->getId();
		$pk->metadata[Entity::DATA_NAMETAG] = [Entity::DATA_TYPE_STRING, $nametag];
		
		$remove = new RemoveEntityPacket();
		$remove->entityUniqueId = $sourceplayer->getId();
		$add = new AddPlayerPacket();
		$add->uuid = $sourceplayer->getUniqueId();
		$add->username = $nametag;
		$add->entityRuntimeId = $sourceplayer->getId();
		$add->position = $sourceplayer->asVector3();
		$add->motion = $sourceplayer->getMotion();
		$add->yaw = $sourceplayer->yaw;
		$add->pitch = $sourceplayer->pitch;
		$add->item = $sourceplayer->getInventory()->getItemInHand();
		$add->metadata = $sourceplayer->getDataPropertyManager()->getAll();
		$add->metadata[Entity::DATA_NAMETAG] = [Entity::DATA_TYPE_STRING, $nametag];
		

		foreach($targetplayer as $p){
			if($p === $sourceplayer){
				continue;
			}
			$p->sendDataPacket(clone $pk);
			$p->sendDataPacket(clone $remove);
			$p->sendDataPacket(clone $add);
			
		}
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