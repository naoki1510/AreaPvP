<?php

namespace naoki1510\areapvp\team;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use naoki1510\areapvp\AreaPvP;

class Team {
	/** @var TeamManager */
	private $teamManager;
	
	/** @var String */
	public $name;
	public $textColor;
	
	/** @var int */
	public $points;
	public $blockColor;
	
	/** @var Player[] */
	private $players;
	
	/** @var Position */
	public $spawn;
	
	public function __construct(TeamManager $teamManager, String $name, Array $color, Vector3 $pos = null) {
		$this->teamManager = $teamManager;
		$this->name = $name;
		$this->textColor = $color['text'] ?? 'f';
		$this->blockColor = $color['block'] ?? '0';
		$this->players = [];
		$this->points = 0;
		$this->spawn = $pos ? ($pos instanceof Position ? $pos : Position::fromObject($pos, Server::getInstance()->getDefaultLevel())) : null;
	}
	
	public function add(Player $player) : bool {
		if (!$this->exists($player)) {
			$this->players[$player->getName()] = $player;
			$player->setNameTag('§' . $this->textColor . $player->getName());
			$player->sendMessage(AreaPvP::translate('team.join',['color' => $this->textColor, 'name' => $this->getName()]));
			$player->setAllowMovementCheats(true);
			$player->setSpawn($this->spawn ?? Server::getInstance()->getDefaultLevel()->getSpawnLocation());
			return true;
		}
		return false;
	}
	
	public function remove(Player $player) : bool {
		if ($this->exists($player)) {
			unset($this->players[$player->getName()]);
			$player->setNameTag($player->getName());
			$player->sendMessage(AreaPvP::translate('team.leave', ['color' => $this->textColor, 'name' => $this->getName()]));
			$player->setAllowMovementCheats(false);
			$player->setSpawn(Server::getInstance()->getDefaultLevel()->getSpawnLocation());
			return true;
		}
		return false;
	}
	
	public function exists(Player $player) : bool {
		return isset($this->players[$player->getName()]);
	}

	public function addPoint(Int $point = 1){
		$this->points += $point;
	}

	public function getPoint() : Int {
		return $this->points;
	}

	public function setPoint(int $point){
		$this->points = $point;
	}
	
	public function setSpawn(Vector3 $pos) {
	    $this->spawn = $pos instanceof Position ? $pos : Position::fromObject($pos, Server::getInstance()->getDefaultLevel());
	}
	
	public function getSpawn() : Position{
	    return $this->spawn;
	}

	public function respawnAllPlayers(){
		foreach ($this->getAllPlayers() as $player) {
			$player->teleport($this->spawn);
		}
	}

	public function removeAllPlayers(bool $notifyToPlayer = false)
	{
		if($notifyToPlayer){
			foreach ($this->players as $player) {
				$this->remove($player);
			}
		}else{
			foreach ($this->players as $player) {
				$player->setNameTag($player->getName());
				$player->setAllowMovementCheats(false);
				$player->setSpawn(Server::getInstance()->getDefaultLevel()->getSpawnLocation());
			}
			$this->players = [];
		}
	}
	
	/**
	 * @return Player[]
	 */
	public function getAllPlayers(){
		return $this->players;
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function setName($name) {
		return $this->name = $name;
	}
	
	public function getColor(string $type = null) {

		switch ($type) {
			case 'text':
				return $this->textColor;
				break;

			case 'block':
				return $this->blockColor;
				break;
			
			default:
				return ['text' => $this->textColor, 'block' => $this->blockColor];
				break;
		}
		return ['text' => $this->textColor, 'block' => $this->blockColor];
	}
	
	public function getPlayerCount() {
		return count($this->players);
	}
}